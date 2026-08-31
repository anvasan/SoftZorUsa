<?php
/**
 * SoftMir - Category Features AI Generator
 * Generates macro-features for software categories using Gemini native knowledge.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========== REST API Endpoint (For Manual Trigger) ==========

add_action('rest_api_init', function () {
    register_rest_route('softmir/v1', '/generate-category-features', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_generate_category_features',
        'permission_callback' => function () {
            return current_user_can('manage_categories');
        }
    ]);
});

function softmir_rest_generate_category_features(WP_REST_Request $request)
{
    $term_id = $request->get_param('term_id');

    if (!$term_id) {
        return new WP_Error('missing_params', 'Term ID is required', ['status' => 400]);
    }

    $result = softmir_gemini_generate_category_features($term_id);

    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response([
        'success' => true,
        'features' => $result,
        'message' => 'Macro functions have been successfully generated!'
    ]);
}

// ========== Core Generation Logic ==========

/**
 * @param int $term_id
 * @return string|WP_Error Comma-separated features string on success
 */
function softmir_gemini_generate_category_features($term_id)
{
    $term = get_term($term_id, 'software_category');
    if (!$term || is_wp_error($term)) {
        return new WP_Error('invalid_term', 'Invalid software category', ['status' => 404]);
    }

    // Check Gemini Key
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : (defined('GOOGLE_GEMINI_KEY') ? GOOGLE_GEMINI_KEY : '');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API Key is missing. Configure it in SoftMir AI settings.', ['status' => 500]);
    }

    // Build Inheritance Context
    $inherited_features_text = '';
    $inherited_funcs = function_exists('softmir_get_inherited_key_functions') ? softmir_get_inherited_key_functions($term_id) : [];

    if (!empty($inherited_funcs)) {
        $inherited_features_text = "\n\nWe ALREADY HAVE the following inherited basic functions from parent categories:\n" . implode(', ', $inherited_funcs);
        $inherited_features_text .= "\n\nТвоя задача — сгенерировать ТОЛЬКО специфические нишевые функции, присущие именно '{$term->name}', и строго AndСКЛЮЧAndТЬ (не дублировать) базовые функции, перечисленные выше.";
    }

    // Build Prompt
    $prompt = "ROLE: You are a Lead Business Architect and B2B Product Analyst.\n\n";
    $prompt .= "CONTEXT: Your task is to develop a standardized taxonomy of functionality for the software market in the category: '" . esc_html($term->name) . "'.\n";
    $prompt .= "You have comprehensive knowledge of world standards and the local B2B software market.\n\n";
    $prompt .= "TASK: Select 8–12 large functional blocks (macro categories) for this industry.\n";
    $prompt .= "These blocks will become functional checkboxes in our software catalog.\n\n";
    $prompt .= "NAMING RULES:\n";
    $prompt .= "1. The names should be succinct and professional (for example: “Customer Base Management”, “Multichannel Telephony (SIP)”, “Lead Scoring”).\n";
    $prompt .= "2. Reflect the specifics of the industry and avoid general phrases like “User-friendly interface.”\n";
    $prompt .= "3. It is strictly forbidden to use nested lists, explanations, numbering, line breaks or markdown in the answer.\n";
    $prompt .= "4. Generation language: English.\n";

    // Append Inheritance constraints
    $prompt .= $inherited_features_text . "\n\n";
    $prompt .= "OUTPUT FORMAT: Return ONLY the names of these aggregated categories, strictly separated by COMMA on one line.";

    // Call Gemini
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'body' => wp_json_encode($body),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_err', 'Failed to connect to AI', ['status' => 500]);
    }

    $status = wp_remote_retrieve_response_code($response);
    $res_body = wp_remote_retrieve_body($response);

    if ($status !== 200) {
        return new WP_Error('gemini_api_err', "AI Error {$status}: {$res_body}", ['status' => 500]);
    }

    $json = json_decode($res_body, true);
    if (empty($json) || empty($json['candidates'][0]['content']['parts'][0]['text'])) {
        return new WP_Error('gemini_empty', 'AI returned empty or invalid format.', ['status' => 500]);
    }

    $raw_text = trim($json['candidates'][0]['content']['parts'][0]['text']);

    // Clean up response
    $cleaned = str_replace(["\r", "\n", "- ", "* "], "", $raw_text);
    $cleaned_array = array_map('trim', explode(',', $cleaned));
    $cleaned_array = array_filter($cleaned_array);
    $final_string = implode(', ', $cleaned_array);

    // Update term meta
    update_term_meta($term_id, '_key_functions', $final_string);

    return $final_string;
}

// ========== Background Cron Automation ==========

add_action('init', function () {
    if (!wp_next_scheduled('softmir_hourly_category_enrichment')) {
        wp_schedule_event(time(), 'hourly', 'softmir_hourly_category_enrichment');
    }
});

add_action('softmir_hourly_category_enrichment', 'softmir_cron_generate_category_features');

function softmir_cron_generate_category_features()
{
    if (get_option('softmir_cron_enabled', '1') !== '1')
        return;

    // 1. Process Parent Categories First (top-level) to ensure inheritance works correctly
    $meta_query_args = [
        'relation' => 'OR',
        [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_key_functions', 'compare' => 'NOT EXISTS'],
                ['key' => '_key_functions', 'value' => '', 'compare' => '=']
            ],
            ['key' => '_ai_cat_features_attempted', 'compare' => 'NOT EXISTS']
        ],
        [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => 'quiz_questions', 'compare' => 'NOT EXISTS'],
                ['key' => 'quiz_questions', 'value' => '', 'compare' => '=']
            ],
            ['key' => '_ai_cat_quiz_attempted', 'compare' => 'NOT EXISTS']
        ]
    ];

    $parents = get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => true,
        'parent' => 0,
        'meta_query' => $meta_query_args,
        'number' => 2 // Process 2 per hour
    ]);

    $terms_to_process = [];

    if (!empty($parents) && !is_wp_error($parents)) {
        $terms_to_process = $parents;
    } else {
        // 2. If parents are done, process children incrementally
        $children = get_terms([
            'taxonomy' => 'software_category',
            'hide_empty' => true,
            'meta_query' => $meta_query_args,
            'number' => 2
        ]);

        if (!empty($children) && !is_wp_error($children)) {
            $terms_to_process = $children;
        }
    }

    if (empty($terms_to_process)) {
        // Transition to card generation as requested
        if (!wp_next_scheduled('softmir_hourly_ai_enrichment')) {
            wp_schedule_single_event(time(), 'softmir_hourly_ai_enrichment');
        }
        return; // All caught up
    }

    foreach ($terms_to_process as $term) {
        $features_done = get_term_meta($term->term_id, '_key_functions', true);
        $result = $features_done;

        // 1. Generate Features if missing
        if (empty($features_done)) {
            update_term_meta($term->term_id, '_ai_cat_features_attempted', '1');
            $result = softmir_gemini_generate_category_features($term->term_id);

            if (!is_wp_error($result) && function_exists('softmir_log_ai_action')) {
                softmir_log_ai_action("✅ Category filled: '" . $term->name . "' (Functions: " . $result . ")");

                // Trigger auto-translation for the newly populated category
                if (function_exists('softmir_auto_translate_category')) {
                    softmir_auto_translate_category($term->term_id);
                }
            }
        }

        // 2. Generate Quiz Questions if missing
        if (function_exists('softmir_auto_generate_quiz_questions')) {
            $quiz_done = function_exists('get_field') ? get_field('quiz_questions', 'software_category_' . $term->term_id) : '';
            if (empty($quiz_done)) {
                update_term_meta($term->term_id, '_ai_cat_quiz_attempted', '1');
                $quiz_result = softmir_auto_generate_quiz_questions($term->term_id, $term->name);
                if (!empty($quiz_result) && function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("✅ Quiz questions have been generated for the category: '" . $term->name . "'");
                }
            }
        }

        // --- CHAIN: Immediately take 2 cards from this category for processing ---
        // (Only if macro functions exist/successfully generated)
        if (!empty($result) && !is_wp_error($result) && function_exists('softmir_enrich_single_software')) {
            $software_posts = get_posts([
                'post_type' => 'software',
                'posts_per_page' => 2,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'software_category',
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ],
                ],
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        ['key' => '_ai_enriched', 'compare' => 'NOT EXISTS'],
                        ['key' => '_ai_enriched', 'value' => '0', 'compare' => '=']
                    ],
                    ['key' => 'website_url', 'compare' => 'EXISTS'],
                    ['key' => 'website_url', 'value' => '', 'compare' => '!=']
                ]
            ]);

            if (!empty($software_posts)) {
                if (function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("🔗 Chain: Category '{$term->name}' ready. Starting processing " . count($software_posts) . " cards...");
                }
                foreach ($software_posts as $p_id) {
                    softmir_enrich_single_software($p_id);
                }
            }
        }

        // Wait briefly between API calls
        sleep(2);
    }
}
