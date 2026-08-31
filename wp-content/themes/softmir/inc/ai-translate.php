<?php
/**
 * SoftMir — AI Auto-Translation System
 * Translates posts, taxonomies and attributes via Google Gemini API
 * Works dynamically with ALL languages configured in Polylang.
 */

// ======================== CONFIG ========================

// Primary model for translations
if (!defined('SOFTMIR_GEMINI_MODEL')) {
    define('SOFTMIR_GEMINI_MODEL', 'gemini-3.1-flash-lite');
}
define('SOFTMIR_GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/' . SOFTMIR_GEMINI_MODEL . ':generateContent');

// Translatable ACF fields for each post type
function softmir_get_translatable_fields($post_type)
{
    $fields = [
        'software' => [
            'post' => ['post_title', 'post_content', 'post_excerpt'],
            'acf' => [
                'short_description',
                'pricing',
                'key_features',
                'top_reasons',
                'disadvantages',
                'best_for',
                'bad_for',
                'business_areas',
                'price_summary',
                'scenarios_md',
            ],
            'skip_copy' => ['company_logo', 'website_url', 'video_url', 'screenshot_1', 'screenshot_2', 'screenshot_3', 'screenshot_4', 'is_featured', 'is_pinned', 'target_markets'],
            'meta' => ['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'],
        ],
        'integrator' => [
            'post' => ['post_title', 'post_content', 'post_excerpt'],
            'acf' => ['integrator_short_desc'],
            'skip_copy' => ['integrator_logo', 'integrator_website'],
        ],
        'sw_attribute' => [
            'post' => ['post_title'],
            'acf' => [],
            'meta' => ['_attr_options'], // comma-separated, translate each option
        ],
    ];

    return $fields[$post_type] ?? [
        'post' => ['post_title', 'post_content', 'post_excerpt'],
        'acf' => [],
    ];
}

/**
 * Compute md5 hash of all translatable text fields for a post.
 * Used to detect whether text content actually changed since last translation.
 *
 * @param int $post_id Post ID
 * @return string md5 hash
 */
function softmir_compute_content_hash($post_id)
{
    $post = get_post($post_id);
    if (!$post) return '';

    $field_config = softmir_get_translatable_fields($post->post_type);
    $parts = [];

    // Post fields (title, content, excerpt)
    foreach ($field_config['post'] as $field) {
        $parts[] = $post->$field ?? '';
    }

    // ACF fields
    if (!empty($field_config['acf'])) {
        foreach ($field_config['acf'] as $field) {
            $parts[] = (string) get_field($field, $post_id);
        }
    }

    // Meta fields (rank_math_title, etc.)
    if (!empty($field_config['meta'])) {
        foreach ($field_config['meta'] as $meta_key) {
            $parts[] = (string) get_post_meta($post_id, $meta_key, true);
        }
    }

    // Dynamic attributes (text/select types only — those that get translated)
    if ($post->post_type === 'software' && function_exists('softmir_get_attributes')) {
        $all_attrs = softmir_get_attributes();
        foreach ($all_attrs as $attr) {
            $attr_meta = softmir_get_attr_meta($attr->ID);
            if (in_array($attr_meta['type'], ['text', 'select', 'checkbox'])) {
                $val = get_post_meta($post_id, '_sw_attr_' . $attr->ID, true);
                $parts[] = is_array($val) ? implode('|||', $val) : (string) $val;
            }
        }

        // Selected key functions
        $kf = get_post_meta($post_id, '_selected_key_functions', true);
        if (is_array($kf)) {
            $parts[] = implode('|||', $kf);
        }
    }

    return md5(implode('|FIELD|', $parts));
}

// ======================== GEMINI API ========================

/**
 * Call Gemini API to translate an array of texts.
 *
 * @param array  $texts       Associative array ['field_key' => 'text to translate']
 * @param string $target_lang Target language code (e.g., 'uk', 'en')
 * @param string $source_lang Source language code (e.g., 'ru')
 * @return array|WP_Error     Associative array ['field_key' => 'translated text'] or WP_Error
 */
function softmir_gemini_translate($texts, $target_lang, $source_lang = 'ru')
{
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : (defined('GOOGLE_GEMINI_KEY') ? GOOGLE_GEMINI_KEY : '');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('Gemini API key is not configured. Set it in Settings → SoftMir AI.', 'softmir'));
    }

    // Filter out empty texts
    $texts_to_translate = array_filter($texts, function ($text) {
        return !empty(trim(strip_tags($text)));
    });

    if (empty($texts_to_translate)) {
        return [];
    }

    // Language names for better AI understanding
    $lang_names = [
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'en' => 'English',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'pt' => 'Portuguese',
        'it' => 'Italian',
    ];

    $source_name = $lang_names[$source_lang] ?? $source_lang;
    $target_name = $lang_names[$target_lang] ?? $target_lang;

    // Remove PHP timeout — translations can take a while with retries
    set_time_limit(0);

    // Split into chunks — paid API key allows larger batches
    $chunks = array_chunk($texts_to_translate, 10, true);
    $all_translated = [];

    foreach ($chunks as $chunk) {
        $result = softmir_gemini_translate_chunk($chunk, $source_name, $target_name, $api_key);
        if (is_wp_error($result)) {
            return $result;
        }
        $all_translated = array_merge($all_translated, $result);
        // Small delay between chunks to avoid rate limiting
        if (count($chunks) > 1) {
            sleep(1);
        }
    }

    // Merge back empty fields
    return array_merge($texts, $all_translated);
}

/**
 * Translate a single chunk of texts (internal helper).
 */
function softmir_gemini_translate_chunk($texts, $source_name, $target_name, $api_key)
{
    $json_payload = json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $prompt = "You are a professional translator for a software catalog website. "
        . "Translate the following JSON values from {$source_name} to {$target_name}. "
        . "Keep the JSON keys unchanged. Preserve all HTML tags, links, and formatting. "
        . "Do NOT translate product names, brand names, or technical terms that should remain in their original form. "
        . "Return ONLY the JSON object with translated values, no explanations.\n\n"
        . $json_payload;

    $body = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json',
        ],
    ];

    $models = [
        SOFTMIR_GEMINI_MODEL => 4, // 4 attempts for primary
        'gemini-3.1-flash-lite' => 2 // 2 attempts for fallback
    ];

    $response = null;
    $status_code = 0;
    $response_body = '';

    foreach ($models as $model_name => $max_retries) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model_name . ':generateContent?key=' . $api_key;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $response = wp_remote_post(
                $endpoint,
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode($body),
                    'timeout' => 60,
                    'data_format' => 'body',
                ]
            );

            if (is_wp_error($response)) {
                if ($attempt < $max_retries) {
                    sleep(2);
                    continue;
                }
                $status_code = 0;
                $response_body = $response->get_error_message();
                break; // Give up on this model, go to fallback
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($status_code === 200) {
                if (function_exists('softmir_api_log'))
                    softmir_api_log('gemini', 200);
                break 2; // Success: break both loops!
            }

            // 429 Rate limited, 503 Service Unavailable — wait and retry
            if (in_array($status_code, [429, 500, 503]) && $attempt < $max_retries) {
                sleep(6); // 6s wait to recover capacity
                continue;
            }

            // Other error - break attempt loop
            break;
        }
    }

    if ($status_code !== 200) {
        if (function_exists('softmir_api_log'))
            softmir_api_log('gemini', $status_code ?: 500);
        return new WP_Error(
            'api_error',
            sprintf('Gemini API error %d: %s', $status_code, $response_body)
        );
    }

    $data = json_decode($response_body, true);

    // Extract text from Gemini response
    $translated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($translated_text)) {
        return new WP_Error('empty_response', 'Gemini returned an empty response');
    }

    // Parse JSON response — clean markdown code fences if present
    $translated_text = trim($translated_text);
    $translated_text = preg_replace('/^```json\s*/i', '', $translated_text);
    $translated_text = preg_replace('/\s*```$/', '', $translated_text);

    $translated = json_decode($translated_text, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error(
            'json_parse_error',
            'Failed to parse Gemini translation response: ' . json_last_error_msg(),
            ['raw_response' => $translated_text]
        );
    }

    // Merge back empty fields
    return array_merge($texts, $translated);
}

// ======================== POST TRANSLATION ========================

/**
 * Translate a post (software/integrator/sw_attribute) to a target language.
 *
 * @param int    $post_id     Source post ID
 * @param string $target_lang Target language slug (e.g., 'uk', 'en')
 * @return array|WP_Error     Result info or error
 */
function softmir_translate_post($post_id, $target_lang)
{
    // Set global flag to prevent cascade hooks during wp_insert_post / wp_update_post
    global $softmir_is_translating;
    $softmir_is_translating = true;

    if (!function_exists('pll_get_post') || !function_exists('pll_save_post_translations')) {
        $softmir_is_translating = false;
        return new WP_Error('no_polylang', 'Polylang is not active');
    }

    $post = get_post($post_id);
    if (!$post) {
        $softmir_is_translating = false;
        return new WP_Error('no_post', 'Post not found');
    }

    $source_lang = pll_get_post_language($post_id);
    if (!$source_lang) {
        $source_lang = pll_default_language();
        pll_set_post_language($post_id, $source_lang);
    }

    // Note: No fail-fast ping here. Translations use gemini-3.1-flash-lite (stable model)
    // and softmir_gemini_translate() has its own retry logic with model fallback.
    // The ping was designed to protect Firecrawl credits, which translations don't use.
    $field_config = softmir_get_translatable_fields($post->post_type);

    // Collect texts to translate
    $texts = [];

    // Post fields
    foreach ($field_config['post'] as $field) {
        $value = $post->$field ?? '';
        if (!empty($value)) {
            $texts[$field] = $value;
        }
    }

    // ACF fields
    if (!empty($field_config['acf'])) {
        foreach ($field_config['acf'] as $field) {
            $value = get_field($field, $post_id);
            if (!empty($value)) {
                $texts['acf_' . $field] = $value;
            }
        }
    }

    // Meta fields (e.g., _attr_options for sw_attribute)
    if (!empty($field_config['meta'])) {
        foreach ($field_config['meta'] as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);
            if (!empty($value)) {
                $texts['meta_' . $meta_key] = $value;
            }
        }
    }

    // Dynamic attributes for software posts
    if ($post->post_type === 'software') {
        $all_attrs = softmir_get_attributes();
        foreach ($all_attrs as $attr) {
            $attr_meta = softmir_get_attr_meta($attr->ID);
            // Only translate text-type attributes, not URLs or numbers
            if (in_array($attr_meta['type'], ['text', 'select', 'checkbox'])) {
                $attr_value = get_post_meta($post_id, '_sw_attr_' . $attr->ID, true);
                if (!empty($attr_value)) {
                    if (is_array($attr_value)) {
                        $texts['dynattr_' . $attr->ID] = implode('|||', $attr_value);
                    } else {
                        $texts['dynattr_' . $attr->ID] = $attr_value;
                    }
                }
            }
        }

        // Include selected key functions for translation
        $selected_kf = get_post_meta($post_id, '_selected_key_functions', true);
        if (!empty($selected_kf) && is_array($selected_kf)) {
            $texts['selected_key_functions'] = implode('|||', $selected_kf);
        }
    }

    if (empty($texts)) {
        $softmir_is_translating = false;
        return ['status' => 'skip', 'message' => 'No translatable content found'];
    }

    // Call Gemini API
    $translated = softmir_gemini_translate($texts, $target_lang, $source_lang);
    if (is_wp_error($translated)) {
        $softmir_is_translating = false;
        return $translated;
    }

    // Get or create translation post
    $translation_id = pll_get_post($post_id, $target_lang);
    $is_new_translation = false;

    if (!$translation_id) {
        // Create new translation post
        $new_post = [
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_author' => $post->post_author,
            'post_name' => $post->post_name,
            'post_title' => $translated['post_title'] ?? $post->post_title,
            'post_content' => $translated['post_content'] ?? $post->post_content,
            'post_excerpt' => $translated['post_excerpt'] ?? $post->post_excerpt,
        ];

        $translation_id = wp_insert_post($new_post);
        if (is_wp_error($translation_id)) {
            $softmir_is_translating = false;
            return $translation_id;
        }

        // Set language for the new post
        pll_set_post_language($translation_id, $target_lang);
        $is_new_translation = true;
    } else {
        // Update existing translation post
        $update = ['ID' => $translation_id];
        if (isset($translated['post_title'])) {
            $update['post_title'] = $translated['post_title'];
        }
        if (isset($translated['post_content'])) {
            $update['post_content'] = $translated['post_content'];
        }
        if (isset($translated['post_excerpt'])) {
            $update['post_excerpt'] = $translated['post_excerpt'];
        }
        wp_update_post($update);
    }

    // Save translated ACF fields
    if (!empty($field_config['acf'])) {
        foreach ($field_config['acf'] as $field) {
            $key = 'acf_' . $field;
            if (isset($translated[$key])) {
                update_field($field, $translated[$key], $translation_id);
            }
        }
    }

    // Save translated meta fields
    if (!empty($field_config['meta'])) {
        foreach ($field_config['meta'] as $meta_key) {
            $key = 'meta_' . $meta_key;
            if (isset($translated[$key])) {
                update_post_meta($translation_id, $meta_key, $translated[$key]);
            }
        }
    }

    // ====== Copy ALL non-translatable fields via raw post_meta ======
    // ACF image fields store attachment IDs in meta. get_field() returns URL,
    // but update_field() for image type needs the attachment ID.
    // Solution: use get_post_meta/update_post_meta for reliable cloning.
    $skip_copy = $field_config['skip_copy'] ?? [];
    foreach ($skip_copy as $field) {
        // Read raw meta value (works for images=attachment ID, urls=string, booleans=1/0)
        $raw_value = get_post_meta($post_id, $field, true);
        // Also check ACF's internal key mapping (field_sw_logo => company_logo)
        if ($raw_value === '' || $raw_value === false) {
            // Try get_field as fallback (some ACF fields may use different storage)
            $raw_value = get_field($field, $post_id, false); // false = raw/unformatted
        }
        if ($raw_value !== null && $raw_value !== '' && $raw_value !== false) {
            update_post_meta($translation_id, $field, $raw_value);
            // Also copy the ACF field reference key (e.g., _company_logo => field_sw_logo)
            $acf_key = get_post_meta($post_id, '_' . $field, true);
            if ($acf_key) {
                update_post_meta($translation_id, '_' . $field, $acf_key);
            }
        }
    }

    // ====== Copy ALL dynamic attribute values ======
    if ($post->post_type === 'software') {
        $all_attrs = softmir_get_attributes();
        foreach ($all_attrs as $attr) {
            $attr_meta_key = '_sw_attr_' . $attr->ID;
            $attr_meta = softmir_get_attr_meta($attr->ID);
            $attr_value = get_post_meta($post_id, $attr_meta_key, true);

            // Always copy the attribute, even if empty (to preserve unchecked checkboxes, empty fields)
            $dyn_key = 'dynattr_' . $attr->ID;

            if (in_array($attr_meta['type'], ['text', 'select']) && isset($translated[$dyn_key])) {
                // Use AI-translated value for text and select types
                $trans_value = $translated[$dyn_key];
                // Restore array if the original was an array
                if (is_array($attr_value)) {
                    $trans_value = explode('|||', $trans_value);
                }
                update_post_meta($translation_id, $attr_meta_key, $trans_value);
            } else {
                // Copy value as-is for: checkbox, number, url, or non-translated text
                // This handles all attribute types including:
                // - checkbox with options (array of selected values)
                // - checkbox without options (simple '1' / '0')
                // - url fields (string)
                // - number fields (string/int)
                // - empty arrays for unchecked checkboxes
                if ($attr_value !== '' && $attr_value !== false) {
                    update_post_meta($translation_id, $attr_meta_key, $attr_value);
                } elseif ($attr_meta['type'] === 'checkbox') {
                    // Explicitly copy empty checkbox state (empty array or '0')
                    update_post_meta($translation_id, $attr_meta_key, $attr_value === false ? '0' : $attr_value);
                }
            }
        }
    }

    // ====== Save translated selected key functions ======
    if ($post->post_type === 'software' && isset($translated['selected_key_functions'])) {
        $trans_kf = explode('|||', $translated['selected_key_functions']);
        $trans_kf = array_map('trim', $trans_kf);
        $trans_kf = array_filter($trans_kf);
        update_post_meta($translation_id, '_selected_key_functions', $trans_kf);
    }

    // ====== Copy sw_attribute meta settings (type, icon, positions, options) ======
    if ($post->post_type === 'sw_attribute') {
        $attr_meta_keys = ['_attr_type', '_attr_icon', '_attr_filterable', '_attr_card_position', '_attr_page_position', '_attr_multiple'];
        foreach ($attr_meta_keys as $mk) {
            $val = get_post_meta($post_id, $mk, true);
            if ($val !== '' && $val !== false) {
                update_post_meta($translation_id, $mk, $val);
            }
        }
        // Also copy category positions (_attr_cat_XX)
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $mk => $mv) {
            if (strpos($mk, '_attr_cat_') === 0) {
                update_post_meta($translation_id, $mk, $mv[0]);
            }
        }
    }

    // ====== Copy taxonomy terms (categories) ======
    if ($post->post_type === 'software') {
        softmir_copy_taxonomy_translations($post_id, $translation_id, 'software_category', $target_lang);
    } elseif ($post->post_type === 'post') {
        softmir_copy_taxonomy_translations($post_id, $translation_id, 'category', $target_lang);
        softmir_copy_taxonomy_translations($post_id, $translation_id, 'post_tag', $target_lang);
    }

    // ====== Copy thumbnail (featured image) ======
    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        set_post_thumbnail($translation_id, $thumb_id);
    }

    // ====== Copy ALL remaining post meta from source ======
    // This catches any meta that might have been missed above
    $source_meta = get_post_meta($post_id);
    // Keys to skip (already handled above, or internal WordPress keys)
    $skip_meta_keys = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_softmir_translated_at',
        '_softmir_source_post_id',
        '_softmir_source_modified',
        '_thumbnail_id', // Already handled via set_post_thumbnail
    ];
    // Build list of already-handled keys
    $handled_keys = array_merge(
        $field_config['acf'] ?? [],
        $field_config['meta'] ?? [],
        $skip_copy,
        $skip_meta_keys
    );
    // Add ACF reference keys
    foreach (($field_config['acf'] ?? []) as $f) {
        $handled_keys[] = '_' . $f;
    }
    foreach ($skip_copy as $f) {
        $handled_keys[] = '_' . $f;
    }
    // Add dynamic attribute keys
    if ($post->post_type === 'software') {
        if (!isset($all_attrs)) {
            $all_attrs = softmir_get_attributes();
        }
        foreach ($all_attrs as $attr) {
            $handled_keys[] = '_sw_attr_' . $attr->ID;
        }
    }
    // Add selected key functions key (already handled above)
    if ($post->post_type === 'software') {
        $handled_keys[] = '_selected_key_functions';
    }
    // Add sw_attribute meta keys
    if ($post->post_type === 'sw_attribute') {
        $handled_keys = array_merge($handled_keys, ['_attr_type', '_attr_icon', '_attr_filterable', '_attr_card_position', '_attr_page_position', '_attr_multiple']);
    }

    // Now copy any remaining meta keys that weren't handled above
    foreach ($source_meta as $meta_key => $meta_values) {
        // Skip already-handled keys
        if (in_array($meta_key, $handled_keys)) {
            continue;
        }
        // Skip internal WordPress/Polylang keys
        if (strpos($meta_key, '_edit_') === 0 || strpos($meta_key, '_wp_') === 0) {
            continue;
        }
        // Skip Polylang internal fields
        if (strpos($meta_key, '_pll_') === 0) {
            continue;
        }
        // Skip _attr_cat_ keys (already handled for sw_attribute)
        if ($post->post_type === 'sw_attribute' && strpos($meta_key, '_attr_cat_') === 0) {
            continue;
        }
        // Copy each remaining meta value
        $value = get_post_meta($post_id, $meta_key, true);
        update_post_meta($translation_id, $meta_key, $value);
    }

    // Save translation timestamp
    update_post_meta($translation_id, '_softmir_translated_at', current_time('mysql'));
    update_post_meta($translation_id, '_softmir_source_post_id', $post_id);
    update_post_meta($translation_id, '_softmir_source_modified', $post->post_modified);

    // Save content hash so we can skip translation if text hasn't changed next time
    $content_hash = softmir_compute_content_hash($post_id);
    update_post_meta($post_id, '_softmir_content_hash', $content_hash);

    // ====== Link translations LAST (after all meta updates) ======
    // Must be at the very end because wp_update_post/update_post_meta
    // trigger Polylang save_post hooks that can reset translation links.
    // Always ensure Polylang link exists (fixes "page not assigned" after re-translation)
    $translations = pll_get_post_translations($post_id);
    $translations[$target_lang] = $translation_id;
    $translations[$source_lang] = $post_id;
    pll_save_post_translations($translations);

    $softmir_is_translating = false;

    return [
        'status' => 'success',
        'translation_id' => $translation_id,
        'fields_translated' => count($translated),
        'target_lang' => $target_lang,
    ];
}

/**
 * Copy taxonomy terms to translation, using translated terms where available.
 */
function softmir_copy_taxonomy_translations($source_id, $target_id, $taxonomy, $target_lang)
{
    $terms = wp_get_post_terms($source_id, $taxonomy, ['fields' => 'ids']);
    if (is_wp_error($terms) || empty($terms)) {
        return;
    }

    $translated_term_ids = [];
    foreach ($terms as $term_id) {
        if (function_exists('pll_get_term')) {
            $trans_term_id = pll_get_term($term_id, $target_lang);
            $translated_term_ids[] = $trans_term_id ?: $term_id;
        } else {
            $translated_term_ids[] = $term_id;
        }
    }

    wp_set_post_terms($target_id, $translated_term_ids, $taxonomy);
}

// ======================== TAXONOMY TRANSLATION ========================

/**
 * Translate a taxonomy term to target language.
 *
 * @param int    $term_id     Source term ID
 * @param string $taxonomy    Taxonomy name
 * @param string $target_lang Target language slug
 * @return array|WP_Error
 */
function softmir_translate_term($term_id, $taxonomy, $target_lang)
{
    if (!function_exists('pll_get_term')) {
        return new WP_Error('no_polylang', 'Polylang is not active');
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return new WP_Error('no_term', 'Term not found');
    }

    $source_lang = pll_get_term_language($term_id);
    if (!$source_lang) {
        $source_lang = pll_default_language();
        pll_set_term_language($term_id, $source_lang);
    }

    $texts = [];
    if (!empty($term->name)) {
        $texts['name'] = $term->name;
    }
    if (!empty($term->description)) {
        $texts['description'] = $term->description;
    }

    // Include key functions for translation (comma-separated list)
    $source_key_functions = get_term_meta($term_id, '_key_functions', true);
    if (!empty($source_key_functions)) {
        $texts['key_functions'] = $source_key_functions;
    }

    // Include quiz questions for translation (JSON string)
    $source_quiz_questions = get_field('quiz_questions', 'software_category_' . $term_id);
    if (!empty($source_quiz_questions)) {
        $texts['quiz_questions'] = $source_quiz_questions;
    }

    if (empty($texts)) {
        return ['status' => 'skip', 'message' => 'No translatable content'];
    }

    $translated = softmir_gemini_translate($texts, $target_lang, $source_lang);
    if (is_wp_error($translated)) {
        return $translated;
    }

    // Get or create translated term
    $trans_term_id = pll_get_term($term_id, $target_lang);

    if (!$trans_term_id) {
        // Create new term
        $slug = $term->slug . '-' . $target_lang;
        $result = wp_insert_term(
            $translated['name'] ?? $term->name,
            $taxonomy,
            [
                'slug' => $slug,
                'description' => $translated['description'] ?? $term->description,
                'parent' => softmir_get_translated_parent($term->parent, $taxonomy, $target_lang),
            ]
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $trans_term_id = $result['term_id'];

        // Set language and link
        pll_set_term_language($trans_term_id, $target_lang);

        $term_translations = pll_get_term_translations($term_id);
        $term_translations[$target_lang] = $trans_term_id;
        $term_translations[$source_lang] = $term_id;
        pll_save_term_translations($term_translations);
    } else {
        // Update existing term
        wp_update_term($trans_term_id, $taxonomy, [
            'name' => $translated['name'] ?? $term->name,
            'description' => $translated['description'] ?? $term->description,
        ]);
    }

    // Save translated key functions to the target term
    if (!empty($source_key_functions)) {
        $translated_kf = $translated['key_functions'] ?? $source_key_functions;
        // Normalize: trim spaces around commas
        $kf_array = array_map('trim', explode(',', $translated_kf));
        $kf_array = array_filter($kf_array);
        update_term_meta($trans_term_id, '_key_functions', implode(', ', $kf_array));
    }

    // Save translated quiz questions to the target term
    if (!empty($source_quiz_questions)) {
        $translated_qq = $translated['quiz_questions'] ?? $source_quiz_questions;
        update_field('quiz_questions', $translated_qq, 'software_category_' . $trans_term_id);
    }

    // Save translation timestamp
    update_term_meta($trans_term_id, '_softmir_translated_at', current_time('mysql'));

    return [
        'status' => 'success',
        'term_id' => $trans_term_id,
        'target_lang' => $target_lang,
    ];
}

/**
 * Get translated parent term ID, fallback to original.
 */
function softmir_get_translated_parent($parent_id, $taxonomy, $target_lang)
{
    if (!$parent_id) {
        return 0;
    }

    if (function_exists('pll_get_term')) {
        $trans_parent = pll_get_term($parent_id, $target_lang);
        if ($trans_parent) {
            return $trans_parent;
        }
    }

    return $parent_id;
}

// ======================== TRANSLATION STATUS ========================

/**
 * Check if a post's translation needs updating.
 *
 * @param int    $post_id     Source post ID
 * @param string $target_lang Target language slug
 * @return array Status info: ['status' => 'translated|outdated|missing', 'translated_at' => '...']
 */
function softmir_get_translation_status($post_id, $target_lang)
{
    if (!function_exists('pll_get_post')) {
        return ['status' => 'missing'];
    }

    $translation_id = pll_get_post($post_id, $target_lang);

    if (!$translation_id) {
        return ['status' => 'missing', 'translation_id' => null];
    }

    $translated_at = get_post_meta($translation_id, '_softmir_translated_at', true);
    $source_modified = get_post_meta($translation_id, '_softmir_source_modified', true);
    $current_modified = get_post($post_id)->post_modified;

    if (empty($translated_at)) {
        return [
            'status' => 'manual',
            'translation_id' => $translation_id,
            'message' => 'Translated manually (no auto-translation record)',
        ];
    }

    // Compare source modification time with the saved snapshot
    if ($source_modified && $source_modified !== $current_modified) {
        return [
            'status' => 'outdated',
            'translation_id' => $translation_id,
            'translated_at' => $translated_at,
            'message' => 'Source updated after last translation',
        ];
    }

    return [
        'status' => 'translated',
        'translation_id' => $translation_id,
        'translated_at' => $translated_at,
    ];
}

// ======================== AJAX HANDLERS & CRON FALLBACK ========================

add_action('softmir_background_translate_post', 'softmir_translate_post', 10, 2);

add_action('wp_ajax_softmir_translate_post', 'softmir_ajax_translate_post');
function softmir_ajax_translate_post()
{
    check_ajax_referer('softmir_translate', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    $target_lang = sanitize_text_field($_POST['target_lang'] ?? '');

    if (!$post_id || !$target_lang) {
        wp_send_json_error(['message' => 'Missing post_id or target_lang']);
    }

    $result = softmir_translate_post($post_id, $target_lang);

    if (is_wp_error($result)) {
        // Fallback to queue if Google fails
        if (!wp_next_scheduled('softmir_background_translate_post', [$post_id, $target_lang])) {
            wp_schedule_single_event(time() + 60, 'softmir_background_translate_post', [$post_id, $target_lang]);
        }

        wp_send_json_success([
            'status' => 'queued',
            'message' => 'Google API is overloaded. The transfer is added to the queue (crowns) and will be completed on its own within a couple of minutes.',
            'target_lang' => $target_lang
        ]);
        exit;
    }

    wp_send_json_success($result);
}

add_action('wp_ajax_softmir_translate_all_langs', 'softmir_ajax_translate_all_langs');
function softmir_ajax_translate_all_langs()
{
    check_ajax_referer('softmir_translate', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error(['message' => 'Missing post_id']);
    }

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    $results = [];
    foreach ($target_langs as $lang) {
        $results[$lang] = softmir_translate_post($post_id, $lang);
        if (is_wp_error($results[$lang])) {
            $err_msg = $results[$lang]->get_error_message();
            // Schedule fallback
            if (!wp_next_scheduled('softmir_background_translate_post', [$post_id, $lang])) {
                wp_schedule_single_event(time() + 60, 'softmir_background_translate_post', [$post_id, $lang]);
            }

            $results[$lang] = [
                'status' => 'queued',
                'message' => 'Error: ' . $err_msg,
                'translation_id' => null,
            ];
        }
    }

    wp_send_json_success([
        'results' => $results,
        'languages_count' => count($target_langs),
    ]);
}

add_action('wp_ajax_softmir_translate_term_action', 'softmir_ajax_translate_term');
function softmir_ajax_translate_term()
{
    check_ajax_referer('softmir_translate', 'nonce');

    if (!current_user_can('manage_categories')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $term_id = intval($_POST['term_id'] ?? 0);
    $taxonomy = sanitize_text_field($_POST['taxonomy'] ?? 'software_category');
    $target_lang = sanitize_text_field($_POST['target_lang'] ?? '');

    if (!$term_id) {
        wp_send_json_error(['message' => 'Missing term_id']);
    }

    $default_lang = pll_default_language();

    if ($target_lang) {
        // Translate to specific language
        $result = softmir_translate_term($term_id, $taxonomy, $target_lang);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success($result);
    } else {
        // Translate to all languages
        $all_langs = pll_languages_list();
        $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

        $results = [];
        foreach ($target_langs as $lang) {
            $results[$lang] = softmir_translate_term($term_id, $taxonomy, $lang);
            if (is_wp_error($results[$lang])) {
                $results[$lang] = [
                    'status' => 'error',
                    'message' => $results[$lang]->get_error_message(),
                ];
            }
        }
        wp_send_json_success(['results' => $results]);
    }
}

add_action('wp_ajax_softmir_get_translation_status', 'softmir_ajax_get_translation_status');
function softmir_ajax_get_translation_status()
{
    check_ajax_referer('softmir_translate', 'nonce');

    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error(['message' => 'Missing post_id']);
    }

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    $statuses = [];
    foreach ($target_langs as $lang) {
        $statuses[$lang] = softmir_get_translation_status($post_id, $lang);
    }

    wp_send_json_success(['statuses' => $statuses]);
}

// ======================== AUTO-TRANSLATE ON SAVE ========================

/**
 * Auto-translate sw_attribute posts when they are saved/published.
 * Only triggers for default-language posts.
 */
add_action('save_post_sw_attribute', 'softmir_auto_translate_attribute', 999, 3);
function softmir_auto_translate_attribute($post_id, $post, $update)
{
    // Prevent infinite loops
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING) {
        return;
    }

    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // Only for published posts
    if ($post->post_status !== 'publish') {
        return;
    }

    // Check Polylang
    if (!function_exists('pll_get_post_language') || !function_exists('pll_default_language')) {
        return;
    }

    // Only translate default-language posts
    $post_lang = pll_get_post_language($post_id);
    $default_lang = pll_default_language();
    if ($post_lang && $post_lang !== $default_lang) {
        return;
    }

    // Check API key
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        return;
    }

    // Schedule translation (runs in background to avoid blocking the editor)
    if (!wp_next_scheduled('softmir_do_translate_post', [$post_id])) {
        wp_schedule_single_event(time() + 5, 'softmir_do_translate_post', [$post_id]);
    }
}

/**
 * Auto-translate blog posts (standard 'post' type) when published.
 * Only triggers for default-language posts.
 */
add_action('save_post_post', 'softmir_auto_translate_blog_post', 999, 3);
function softmir_auto_translate_blog_post($post_id, $post, $update)
{
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_status !== 'publish') {
        return;
    }
    if (!function_exists('pll_get_post_language') || !function_exists('pll_default_language')) {
        return;
    }
    $post_lang = pll_get_post_language($post_id);
    $default_lang = pll_default_language();
    if ($post_lang && $post_lang !== $default_lang) {
        return;
    }
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        return;
    }
    // Check if translatable text actually changed
    $current_hash = softmir_compute_content_hash($post_id);
    $saved_hash = get_post_meta($post_id, '_softmir_content_hash', true);

    if ($saved_hash && $current_hash === $saved_hash) {
        return;
    }

    // Schedule background translation
    if (!wp_next_scheduled('softmir_do_translate_post', [$post_id])) {
        wp_schedule_single_event(time() + 5, 'softmir_do_translate_post', [$post_id]);
    }
}

/**
 * Auto-translate software_category terms when created or edited.
 * Only triggers for default-language terms.
 */
add_action('created_software_category', 'softmir_auto_translate_category', 999, 2);
add_action('edited_software_category', 'softmir_auto_translate_category', 999, 2);
function softmir_auto_translate_category($term_id, $tt_id = 0)
{
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING) {
        return;
    }

    if (!function_exists('pll_get_term_language') || !function_exists('pll_default_language')) {
        return;
    }

    $term_lang = pll_get_term_language($term_id);
    $default_lang = pll_default_language();
    if ($term_lang && $term_lang !== $default_lang) {
        return;
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        return;
    }

    // Schedule translation
    if (!wp_next_scheduled('softmir_do_translate_term', [$term_id, 'software_category'])) {
        wp_schedule_single_event(time() + 5, 'softmir_do_translate_term', [$term_id, 'software_category']);
    }
}

/**
 * Auto-translate standard category (blog) terms when created or edited.
 * Only triggers for default-language terms.
 */
add_action('created_category', 'softmir_auto_translate_blog_category', 999, 2);
add_action('edited_category', 'softmir_auto_translate_blog_category', 999, 2);
function softmir_auto_translate_blog_category($term_id, $tt_id = 0)
{
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING) {
        return;
    }

    if (!function_exists('pll_get_term_language') || !function_exists('pll_default_language')) {
        return;
    }

    $term_lang = pll_get_term_language($term_id);
    $default_lang = pll_default_language();
    if ($term_lang && $term_lang !== $default_lang) {
        return;
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        return;
    }

    // Schedule translation
    if (!wp_next_scheduled('softmir_do_translate_term', [$term_id, 'category'])) {
        wp_schedule_single_event(time() + 5, 'softmir_do_translate_term', [$term_id, 'category']);
    }
}

/**
 * Background: translate a post to all languages.
 */
add_action('softmir_do_translate_post', 'softmir_background_translate_post');
function softmir_background_translate_post($post_id)
{
    if (!function_exists('pll_default_language')) {
        return;
    }

    define('SOFTMIR_TRANSLATING', true);

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    foreach ($target_langs as $lang) {
        $result = softmir_translate_post($post_id, $lang);

        // If rate limited, wait and retry once
        if (is_wp_error($result) && strpos($result->get_error_message(), '429') !== false) {
            sleep(30);
            softmir_translate_post($post_id, $lang);
        }

        // Small delay between languages
        sleep(2);
    }
}

/**
 * Background: translate a term to all languages.
 */
add_action('softmir_do_translate_term', 'softmir_background_translate_term', 10, 2);
function softmir_background_translate_term($term_id, $taxonomy)
{
    if (!function_exists('pll_default_language')) {
        return;
    }

    define('SOFTMIR_TRANSLATING', true);

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    foreach ($target_langs as $lang) {
        $result = softmir_translate_term($term_id, $taxonomy, $lang);

        if (is_wp_error($result) && strpos($result->get_error_message(), '429') !== false) {
            sleep(30);
            softmir_translate_term($term_id, $taxonomy, $lang);
        }

        sleep(2);
    }
}
/**
 * Processor for asynchronous translation of cards created by AI Scout
 */
add_action('softmir_async_translate_scout_cards', 'softmir_handle_async_translate_scout_cards');
function softmir_handle_async_translate_scout_cards($post_id)
{
    if (!function_exists('pll_languages_list') || !function_exists('softmir_translate_post') || !function_exists('pll_get_post_language')) {
        return;
    }

    $current_lang = pll_get_post_language($post_id);
    if (!$current_lang) {
        $current_lang = pll_default_language();
        pll_set_post_language($post_id, $current_lang);
    }

    $all_langs = pll_languages_list();
    foreach ($all_langs as $lang) {
        if ($lang !== $current_lang) {
            softmir_translate_post($post_id, $lang);
        }
    }
}

/**
 * Automatic start of transfers when manually saving a software card.
 * Uses a hash of text fields - if the text has not changed, the translation does not start,
 * and easy synchronization of non-text fields (logo, categories, checkboxes) is performed.
 */
add_action('save_post_software', 'softmir_auto_translate_software_on_save', 99, 3);
function softmir_auto_translate_software_on_save($post_id, $post, $update)
{
    // Skip non-manual saves (REST API, Cron, translation process)
    global $softmir_is_translating;
    if (!empty($softmir_is_translating))
        return;
    if (defined('REST_REQUEST') && REST_REQUEST)
        return;
    if (defined('DOING_CRON') && DOING_CRON)
        return;
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING)
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;
    if ($post->post_status !== 'publish')
        return;

    // Only if we save in the base language
    if (function_exists('pll_get_post_language') && function_exists('pll_default_language')) {
        $lang = pll_get_post_language($post_id);
        if ($lang && $lang !== pll_default_language()) {
            return;
        }
    }

    // === Hash check: Has the text content changed? ===
    $current_hash = softmir_compute_content_hash($post_id);
    $saved_hash = get_post_meta($post_id, '_softmir_content_hash', true);

    if ($saved_hash && $current_hash === $saved_hash) {
        // The text has not changed - we are just synchronizing non-text fields
        if (!wp_next_scheduled('softmir_sync_nontranslatable', [$post_id])) {
            wp_schedule_single_event(time() + 5, 'softmir_sync_nontranslatable', [$post_id]);
        }
        return;
    }

    // The text has changed - full translation
    $next = wp_next_scheduled('softmir_async_translate_scout_cards', [$post_id]);
    if ($next) {
        wp_unschedule_event($next, 'softmir_async_translate_scout_cards', [$post_id]);
    }
    // We put it in the queue after 10 seconds so that all meta fields have time to be saved
    wp_schedule_single_event(time() + 10, 'softmir_async_translate_scout_cards', [$post_id]);
}

/**
 * Easy synchronization of non-text fields in all language versions.
 * Called when the text content has not changed (the hash matches).
 * Synchronizes: logo, URL, screenshots, categories, thumbnail, dynamic attributes.
 */
add_action('softmir_sync_nontranslatable', 'softmir_do_sync_nontranslatable');
function softmir_do_sync_nontranslatable($post_id)
{
    if (!function_exists('pll_default_language') || !function_exists('pll_get_post')) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) return;

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    $field_config = softmir_get_translatable_fields('software');
    $skip_copy = $field_config['skip_copy'] ?? [];

    foreach ($target_langs as $lang) {
        $translation_id = pll_get_post($post_id, $lang);
        if (!$translation_id) continue;

        // 1. Copy skip_copy fields (logo, urls, screenshots, flags)
        foreach ($skip_copy as $field) {
            $raw_value = get_post_meta($post_id, $field, true);
            if ($raw_value === '' || $raw_value === false) {
                $raw_value = get_field($field, $post_id, false);
            }
            if ($raw_value !== null && $raw_value !== '' && $raw_value !== false) {
                update_post_meta($translation_id, $field, $raw_value);
                $acf_key = get_post_meta($post_id, '_' . $field, true);
                if ($acf_key) {
                    update_post_meta($translation_id, '_' . $field, $acf_key);
                }
            }
        }

        // 2. Copy taxonomy terms (categories)
        softmir_copy_taxonomy_translations($post_id, $translation_id, 'software_category', $lang);

        // 3. Copy thumbnail
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            set_post_thumbnail($translation_id, $thumb_id);
        }

        // 4. Copy non-translatable dynamic attributes (checkbox, number, url)
        if (function_exists('softmir_get_attributes')) {
            $all_attrs = softmir_get_attributes();
            foreach ($all_attrs as $attr) {
                $attr_meta = softmir_get_attr_meta($attr->ID);
                if (!in_array($attr_meta['type'], ['text', 'select'])) {
                    $attr_meta_key = '_sw_attr_' . $attr->ID;
                    $attr_value = get_post_meta($post_id, $attr_meta_key, true);
                    if ($attr_value !== '' && $attr_value !== false) {
                        update_post_meta($translation_id, $attr_meta_key, $attr_value);
                    } elseif ($attr_meta['type'] === 'checkbox') {
                        update_post_meta($translation_id, $attr_meta_key, $attr_value === false ? '0' : $attr_value);
                    }
                }
            }
        }

        // 5. Copy post_status (if changed to draft/publish)
        $trans_post = get_post($translation_id);
        if ($trans_post && $trans_post->post_status !== $post->post_status) {
            wp_update_post(['ID' => $translation_id, 'post_status' => $post->post_status]);
        }

        // 6. Copy is_affiliate and primary_category meta
        foreach (['is_affiliate', 'primary_category', 'discount_available'] as $extra_meta) {
            $val = get_post_meta($post_id, $extra_meta, true);
            if ($val !== '' && $val !== false) {
                update_post_meta($translation_id, $extra_meta, $val);
            }
        }
    }
}
