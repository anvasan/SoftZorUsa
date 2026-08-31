<?php
/**
 * Native WP-Cron AI Background Enricher
 * Replaces the Python external script with a pure-PHP hourly background process.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Custom Cron Schedule
add_filter('cron_schedules', 'softmir_ai_cron_intervals');
function softmir_ai_cron_intervals($schedules)
{
    // 10 minutes interval to avoid getting blocked
    $schedules['every_ten_minutes'] = [
        'interval' => 600,
        'display' => 'Every 10 Minutes (for bat script)'
    ];
    return $schedules;
}

// Clear the old hooks if they exist and schedule the new 'every_ten_minutes' one
if (!wp_next_scheduled('softmir_hourly_ai_enrichment')) {
    wp_schedule_event(time(), 'every_ten_minutes', 'softmir_hourly_ai_enrichment');
}

add_action('softmir_hourly_ai_enrichment', 'softmir_run_background_enrichment');

/**
 * Main Cron Callback
 */
function softmir_run_background_enrichment()
{
    if (get_option('softmir_cron_enabled', '1') !== '1')
        return;

    set_time_limit(0); // Prevent timeouts

    // 0. Autoreset stuck cards (processing for > 1 hour)
    global $wpdb;
    $stuck_posts = $wpdb->get_col("
        SELECT post_id FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->postmeta} pm_start ON pm.post_id = pm_start.post_id AND pm_start.meta_key = '_ai_processing_start'
        WHERE pm.meta_key = '_ai_enriched' AND pm.meta_value = '2'
        AND (pm_start.meta_value IS NULL OR pm_start.meta_value < " . (time() - 3600) . ")
    ");
    if (!empty($stuck_posts)) {
        foreach ($stuck_posts as $sp_id) {
            update_post_meta($sp_id, '_ai_enriched', '0');
            update_post_meta($sp_id, '_ai_enriched_error', 'Reset automatically: processing timeout (> 1 hour)');
            delete_post_meta($sp_id, '_ai_processing_start');
        }
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("🔄 Stuck cards are automatically reset: " . count($stuck_posts) . " pcs.");
        }
    }

    // 1. Get categories that ARE ready (Stage 1 completed)
    $ready_cats = get_terms([
        'taxonomy' => 'software_category',
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_key_functions',
                'compare' => 'EXISTS',
            ],
            [
                'key' => '_key_functions',
                'value' => '',
                'compare' => '!=',
            ]
        ]
    ]);

    if (empty($ready_cats) || is_wp_error($ready_cats)) {
        return; // No ready categories, so no cards can be processed
    }

    // Process a max of 2 items per hour to stay well within timeout/rate limits
    $batch_size = 2;

    $args = [
        'post_type' => 'software',
        'posts_per_page' => $batch_size,
        'post_status' => 'publish',
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_ai_enriched', 'compare' => 'NOT EXISTS'],
                ['key' => '_ai_enriched', 'value' => '0', 'compare' => '=']
            ],
            ['key' => 'website_url', 'compare' => 'EXISTS'],
            ['key' => 'website_url', 'value' => '', 'compare' => '!=']
        ],
        'tax_query' => [
            [
                'taxonomy' => 'software_category',
                'field' => 'term_id',
                'terms' => $ready_cats,
            ],
        ],
    ];

    if (function_exists('pll_default_language')) {
        $def_slug = is_string(pll_default_language('slug')) ? pll_default_language('slug') : pll_default_language();
        $args['tax_query']['relation'] = 'AND';
        $args['tax_query'][] = [
            'taxonomy' => 'language',
            'field' => 'slug',
            'terms' => $def_slug,
        ];
    }

    $query = new WP_Query($args);
    if (empty($query->posts)) {
        return; // Nothing to do
    }

    foreach ($query->posts as $post) {
        if (function_exists('pll_default_language') && function_exists('pll_get_post_language')) {
            $def_lang = is_string(pll_default_language('slug')) ? pll_default_language('slug') : pll_default_language();
            if ($def_lang && pll_get_post_language($post->ID) !== $def_lang) {
                continue; // Fail-safe: ignore translations if tax_query failed
            }
        }
        softmir_enrich_single_software($post->ID);
    }
}

/**
 * Core Logic: Enrich a Single Software Card
 * @param int $post_id
 */
function softmir_enrich_single_software($post_id)
{
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'software') {
        return false;
    }

    $cat_id = get_post_meta($post_id, 'primary_category', true);
    if (!$cat_id) {
        $cats = wp_get_post_terms($post_id, 'software_category', ['fields' => 'ids']);
        if (!empty($cats) && !is_wp_error($cats)) {
            $cat_id = $cats[0];
        }
    }

    // --- Protection from "Race Condition Check" ---
    if ($cat_id) {
        $cat_funcs = get_term_meta($cat_id, '_key_functions', true);
        if (empty($cat_funcs)) {
            update_post_meta($post_id, '_ai_enriched', '0'); // Revert to queued
            update_post_meta($post_id, '_ai_enriched_error', 'Expectation: The category has not yet generated features');
            return false;
        }
    }

    // Mark as processing
    update_post_meta($post_id, '_ai_enriched', '2');
    update_post_meta($post_id, '_ai_processing_start', time());
    $url = get_post_meta($post_id, 'website_url', true);

    // --- Step 0: Fail-Fast Ping ---
    if (function_exists('softmir_ping_gemini') && !softmir_ping_gemini()) {
        update_post_meta($post_id, '_ai_enriched', '0'); // Revert to queued
        update_post_meta($post_id, '_ai_enriched_error', 'Waiting: Google Gemini servers are overloaded (503)');
        if (function_exists('softmir_log_ai_action'))
            softmir_log_ai_action("⏸️ Postponed: Gemini API is overloaded.");
        return false;
    }

    // 1. Data will be sourced via Google Search grounding in Gemini API call

    // 3. Generate AI Data
    $ai_data = softmir_gemini_generate_software_data($post, $url, $cat_id);

    if (is_wp_error($ai_data) || empty($ai_data)) {
        // Mark as failed so it doesn't block the queue
        update_post_meta($post_id, '_ai_enriched', '-1');
        $err_msg = is_wp_error($ai_data) ? $ai_data->get_error_message() : 'Unknown error or empty resulting format';
        update_post_meta($post_id, '_ai_enriched_error', $err_msg);
        return false;
    }

    // 4. Check for hallucination/404 markers in the generated text
    if (!empty($ai_data['full_description'])) {
        $markers = ['Product not found', 'The link goes nowhere', 'error 404', 'Page not found', 'Unfortunately', 'the link is empty'];
        foreach ($markers as $marker) {
            if (mb_stripos($ai_data['full_description'], $marker) !== false) {
                update_post_meta($post_id, '_ai_enriched', '-1');
                update_post_meta($post_id, '_ai_enriched_error', "Галлюцинация AndAnd (404): {$marker}");
                wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                if (function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("🚫 Остановлено (Галлюцинация/404) для '{$post->post_title}' по маркеру: {$marker}");
                }
                return false;
            }
        }
    }

    // 5. Apply data to post
    softmir_apply_ai_data_to_post($post_id, $ai_data);

    // 5. Agent Critic Verification (QA)
    $qa_result = softmir_pipeline_qa_check($ai_data);
    if (!is_wp_error($qa_result) && isset($qa_result['score'])) {
        update_post_meta($post_id, '_qa_score', intval($qa_result['score']));
        update_post_meta($post_id, '_qa_feedback', sanitize_text_field($qa_result['feedback']));

        if ($qa_result['score'] < 7) {
            update_post_meta($post_id, '_ai_enriched', '3'); // 3 = Needs Manual Review
        } else {
            if (function_exists('softmir_log_ai_action')) {
                softmir_log_ai_action("✅ Оценка Критика (Pass): '{$post->post_title}' - {$qa_result['score']}/10.");
            }
        }
    }

    // 6. Auto-Translate to all configured languages
    if (function_exists('pll_languages_list') && function_exists('softmir_translate_post')) {
        $default_lang = pll_default_language();
        $all_langs = pll_languages_list();
        foreach ($all_langs as $lang) {
            if ($lang !== $default_lang) {
                softmir_translate_post($post_id, $lang);
            }
        }
    }


}

/**
 * Gemini Generation Logic
 */
function softmir_gemini_generate_software_data($post, $url, $cat_id)
{
    if (!function_exists('softmir_get_gemini_key'))
        return false;
    $api_key = softmir_get_gemini_key();
    if (empty($api_key))
        return false;

    // Attributes prompt setup
    $attrs_prompt = "Fill in the dynamic attributes for this program. Below is the list (ID : Name [Type]):\n";
    $has_attrs = false;
    if (function_exists('softmir_get_attributes')) {
        $all_attrs = softmir_get_attributes();
        foreach ($all_attrs as $attr) {
            $meta = softmir_get_attr_meta($attr->ID);
            $is_bound = false;
            if (empty($meta['categories']) || (is_array($meta['categories']) && count($meta['categories']) == 0)) {
                $is_bound = true;
            } else if (in_array((string) $cat_id, $meta['categories']) || in_array((int) $cat_id, $meta['categories'])) {
                $is_bound = true;
            }
            if ($is_bound) {
                $type_desc = $meta['type'];
                if ($meta['multiple'] || $meta['type'] == 'checkbox') {
                    $type_desc .= ' (array of values)';
                }
                if (!empty($meta['options'])) {
                    $opts_str = is_array($meta['options']) ? implode(', ', $meta['options']) : $meta['options'];
                    $type_desc .= ' (Options: ' . $opts_str . ')';
                }
                $attrs_prompt .= "- {$attr->ID} : {$attr->post_title} [{$type_desc}]\n";
                $has_attrs = true;
            }
        }
    }
    if (!$has_attrs)
        $attrs_prompt = "";

    $cat_funcs_prompt = "";
    if ($cat_id && function_exists('softmir_get_all_key_functions_for_category')) {
        $inherited_funcs = softmir_get_all_key_functions_for_category($cat_id);
        if (!empty($inherited_funcs)) {
            $cat_funcs_str = implode(', ', $inherited_funcs);
            $cat_funcs_prompt = "\nРЕЙТAndНГ ФУНКЦAndЙ (макро-функции категории):\n[{$cat_funcs_str}]\nВыбери из списка ТОЛЬКО те, что реально есть у программы.\n";
        }
    }

    $lang_name = "English";

    // Use unified 4-step AI content pipeline
    return softmir_run_content_pipeline($url, $lang_name, [
        'attrs_prompt'     => $attrs_prompt,
        'cat_funcs_prompt' => $cat_funcs_prompt,
        'post_title'       => $post->post_title,
        'post_id'          => $post->ID,
    ]);
}


/**
 * Apply Generated JSON Data to Post
 */
function softmir_apply_ai_data_to_post($post_id, $ai_data)
{
    if (!empty($ai_data['title'])) {
        wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($ai_data['title'])]);
    }
    if (!empty($ai_data['full_description'])) {
        wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($ai_data['full_description'])]);
    }

    // SEO Meta Rank Math (centralized: saves fields + calculates score)
    if (function_exists('softmir_save_rank_math_data')) {
        softmir_save_rank_math_data($post_id, $ai_data);
    }

    $simple_fields = [
        'short_description' => 'short_description',
        'price_summary' => 'price_summary'
    ];
    foreach ($simple_fields as $json_key => $acf_key) {
        if (!empty($ai_data[$json_key])) {
            update_post_meta($post_id, $acf_key, wp_kses_post($ai_data[$json_key]));
        }
    }

    $array_fields = [
        'top_reasons' => 'advantages',
        'disadvantages' => 'disadvantages',
        'best_for' => 'best_for',
        'bad_for' => 'bad_for'
    ];
    foreach ($array_fields as $acf_key => $json_key) {
        if (!empty($ai_data[$json_key]) && is_array($ai_data[$json_key])) {
            $text_val = implode("\n", array_map('wp_kses_post', array_filter($ai_data[$json_key])));
            update_post_meta($post_id, $acf_key, $text_val);
        }
    }

    // Integrations
    if (!empty($ai_data['integrations']) && is_array($ai_data['integrations'])) {
        update_post_meta($post_id, 'integrations', implode(', ', $ai_data['integrations']));
    }

    // Scenarios HTML/MD
    if (!empty($ai_data['scenarios']) && is_array($ai_data['scenarios'])) {
        $sc_md = "";
        foreach ($ai_data['scenarios'] as $sc) {
            $sc_md .= "### " . ($sc['title'] ?? '') . "\n" . ($sc['desc'] ?? '') . "\n\n";
        }
        update_post_meta($post_id, 'scenarios_md', trim($sc_md));
    }

    // Key Features Table
    if (!empty($ai_data['features']) && is_array($ai_data['features'])) {
        $rows = "";
        foreach ($ai_data['features'] as $idx => $f) {
            $rows .= "<tr><td>" . ($idx + 1) . "</td><td>Functionality</td><td>{$f}</td></tr>";
        }
        $html = "<table class='softmir-data-table'><thead><tr><th>№</th><th>Category</th><th>Features</th></tr></thead><tbody>{$rows}</tbody></table>";
        update_post_meta($post_id, 'key_features', $html);
    }

    // Business Areas Table
    if (!empty($ai_data['business_areas_list']) && is_array($ai_data['business_areas_list'])) {
        $rows = "";
        foreach ($ai_data['business_areas_list'] as $ba) {
            $rows .= "<tr><td>{$ba['area']}</td><td>{$ba['benefit']}</td></tr>";
        }
        $html = "<table class='softmir-data-table'><thead><tr><th>Business field</th><th>How it helps</th></tr></thead><tbody>{$rows}</tbody></table>";
        update_post_meta($post_id, 'business_areas', $html);
    }

    // Pricing Table
    if (!empty($ai_data['pricing_list']) && is_array($ai_data['pricing_list'])) {
        $rows = "";
        foreach ($ai_data['pricing_list'] as $pl) {
            $rows .= "<tr><td>{$pl['name']}</td><td>{$pl['price']}</td><td>{$pl['features']}</td></tr>";
        }
        $html = "<table class='softmir-data-table'><thead><tr><th>Plan</th><th>Cost</th><th>Capabilities</th></tr></thead><tbody>{$rows}</tbody></table>";
        update_post_meta($post_id, 'pricing', $html);
    }

    // Dynamic Attributes
    if (!empty($ai_data['attributes']) && is_array($ai_data['attributes'])) {
        foreach ($ai_data['attributes'] as $attr) {
            if (empty($attr['id']))
                continue;
            $val = $attr['value'];
            if (is_array($val)) {
                // Remove empty items and save as array for checkboxes/multiple select
                update_post_meta($post_id, '_sw_attr_' . $attr['id'], array_values(array_filter($val)));
            } else {
                // If it's a comma separated string, try to split it into array for known array fields
                if (strpos($val, ',') !== false) {
                    $val_arr = array_map('trim', explode(',', $val));
                    update_post_meta($post_id, '_sw_attr_' . $attr['id'], array_values(array_filter($val_arr)));
                } else {
                    update_post_meta($post_id, '_sw_attr_' . $attr['id'], sanitize_text_field($val));
                }
            }
        }
    }

    // Category Key Functions (Checkboxes sync)
    if (!empty($ai_data['category_key_functions']) && is_array($ai_data['category_key_functions'])) {
        update_post_meta($post_id, '_selected_key_functions', array_values(array_filter($ai_data['category_key_functions'])));
    }


    // Mark completed
    update_post_meta($post_id, '_ai_enriched', '1');

    if (function_exists('softmir_log_ai_action')) {
        softmir_log_ai_action("✅ Software card completed: '" . get_the_title($post_id) . "' (ID: {$post_id})");
    }

    // Trigger Translation with delays to avoid 503 rate limiting
    if (function_exists('pll_default_language') && function_exists('pll_languages_list') && function_exists('softmir_translate_post')) {
        $default_lang = pll_default_language();
        $target_langs = array_filter(pll_languages_list(), fn($lang) => $lang !== $default_lang);
        $failed_langs = [];

        foreach ($target_langs as $lang) {
            $tr_result = softmir_translate_post($post_id, $lang);
            if (is_wp_error($tr_result)) {
                $failed_langs[] = $lang;
                if (function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("⚠️ Перевод на '{$lang}' не удался (попытка 1): " . $tr_result->get_error_message());
                }
            } else {
                if (function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("🌐 Translated to '{$lang}': '" . get_the_title($post_id) . "'");
                }
            }
            // Pause between languages to let API quota recover
            sleep(10);
        }

        // Retry failed languages after cooldown
        if (!empty($failed_langs)) {
            sleep(15);
            foreach ($failed_langs as $lang) {
                $tr_result = softmir_translate_post($post_id, $lang);
                if (is_wp_error($tr_result)) {
                    // Schedule as individual cron event for later
                    if (!wp_next_scheduled('softmir_background_translate_post', [$post_id, $lang])) {
                        wp_schedule_single_event(time() + 120, 'softmir_background_translate_post', [$post_id, $lang]);
                    }
                    if (function_exists('softmir_log_ai_action')) {
                        softmir_log_ai_action("❌ Перевод на '{$lang}' не удался (попытка 2), поставлен в крон.");
                    }
                } else {
                    if (function_exists('softmir_log_ai_action')) {
                        softmir_log_ai_action("🌐 Translated to '{$lang}' (retry): '" . get_the_title($post_id) . "'");
                    }
                }
                sleep(10);
            }
        }
    }
}
