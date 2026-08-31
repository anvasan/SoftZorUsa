<?php
/**
 * REST API Endpoint for Automated AI Enrichment
 */

add_action('rest_api_init', function () {
    register_rest_route('softmir/v1', '/enrich-software', [
        [
            'methods' => 'GET',
            'callback' => 'softmir_api_get_unprocessed_software',
            'permission_callback' => 'softmir_api_enricher_permissions_check',
        ],
        [
            'methods' => 'POST',
            'callback' => 'softmir_api_save_enriched_software',
            'permission_callback' => 'softmir_api_enricher_permissions_check',
        ],
    ]);

    register_rest_route('softmir/v1', '/extract-facts', [
        'methods' => 'POST',
        'callback' => 'softmir_api_extract_facts',
        'permission_callback' => 'softmir_api_enricher_permissions_check',
    ]);

    register_rest_route('softmir/v1', '/categories', [
        'methods' => 'GET',
        'callback' => 'softmir_api_get_categories',
        'permission_callback' => 'softmir_api_enricher_permissions_check',
    ]);

    register_rest_route('softmir/v1', '/attributes', [
        'methods' => 'GET',
        'callback' => 'softmir_api_get_attributes',
        'permission_callback' => 'softmir_api_enricher_permissions_check',
    ]);
});

function softmir_api_get_categories($request) {
    $terms = get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => false,
    ]);
    
    if (is_wp_error($terms)) {
        return new WP_Error('db_error', 'Could not retrieve categories', ['status' => 500]);
    }
    
    $data = [];
    foreach ($terms as $t) {
        $key_functions = function_exists('softmir_get_all_key_functions_for_category') ? softmir_get_all_key_functions_for_category($t->term_id) : [];
        $data[] = [
            'id' => $t->term_id,
            'name' => $t->name,
            'slug' => $t->slug,
            'key_functions' => $key_functions
        ];
    }
    return rest_ensure_response($data);
}

function softmir_api_get_attributes($request) {
    $cat_id = $request->get_param('category_id');
    
    if ($cat_id && function_exists('softmir_get_attrs_for_category')) {
        $attrs = softmir_get_attrs_for_category($cat_id);
    } else {
        $attrs = get_posts([
            'post_type' => 'sw_attribute',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
    }
    
    $data = [];
    foreach ($attrs as $a) {
        $meta = function_exists('softmir_get_attr_meta') ? softmir_get_attr_meta($a->ID) : [];
        $data[] = [
            'id' => $a->ID,
            'name' => $a->post_title,
            'type' => $meta['type'] ?? 'text',
            'options' => $meta['options'] ?? '',
            'multiple' => $meta['multiple'] ?? false
        ];
    }
    return rest_ensure_response($data);
}

function softmir_api_enricher_permissions_check($request)
{
    if (defined('WP_CLI') || current_user_can('edit_posts')) {
        return true;
    }

    $token = $request->get_header('x_enricher_token');
    if (empty($token)) {
        $token = $request->get_param('token');
    }
    
    $valid_token = get_option('softmir_enricher_token', '');

    if (empty($valid_token)) {
        // Generate random token if it doesn't exist
        $valid_token = wp_generate_password(24, false);
        update_option('softmir_enricher_token', $valid_token);
    }

    if ($token !== $valid_token) {
        return new WP_Error('rest_forbidden', 'Invalid token or unauthorized.', ['status' => 401]);
    }
    return true;
}

function softmir_api_get_unprocessed_software($request)
{
    $batch_size = $request->get_param('batch') ? intval($request->get_param('batch')) : 50;

    $args = [
        'post_type' => 'software',
        'posts_per_page' => $batch_size,
        'post_status' => 'publish',
        // Get posts that haven't been processed by AI
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => '_ai_enriched',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_ai_enriched',
                    'value' => '1',
                    'compare' => '!='
                ]
            ],
            // Must have a URL to scrape
            [
                'key' => 'website_url',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'website_url',
                'value' => '',
                'compare' => '!='
            ]
        ]
    ];

    if (function_exists('pll_default_language')) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'language',
                'field' => 'slug',
                'terms' => pll_default_language(),
            ],
        ];
    }

    $query = new WP_Query($args);
    $items = [];

    foreach ($query->posts as $post) {
        $primary_cat = get_post_meta($post->ID, 'primary_category', true);
        if (!$primary_cat) {
            $cats = wp_get_post_terms($post->ID, 'software_category', ['fields' => 'ids']);
            if (!empty($cats) && !is_wp_error($cats)) {
                $primary_cat = $cats[0];
            }
        }

        $items[] = [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'website_url' => get_post_meta($post->ID, 'website_url', true),
            'primary_category_id' => $primary_cat
        ];
    }

    return rest_ensure_response([
        'success' => true,
        'count' => count($items),
        'items' => $items
    ]);
}

function softmir_api_save_enriched_software($request)
{
    $item = $request->get_json_params();
    if (empty($item) || empty($item['ID'])) {
        return new WP_Error('invalid_data', 'Missing payload or ID', ['status' => 400]);
    }

    $post_id = intval($item['ID']);
    if (get_post_type($post_id) !== 'software') {
        return new WP_Error('invalid_post', 'Invalid post ID', ['status' => 400]);
    }

    // Include the admin-autofill logic if not already loaded
    if (!function_exists('softmir_autofill_save_fields')) {
        require_once get_template_directory() . '/inc/admin-autofill.php';
    }

    // Call the universal save function (used by both Admin UI and REST API)
    $result = softmir_autofill_save_fields($post_id, $item, true);

    // Auto-Translate to other languages if needed
    if (function_exists('pll_default_language') && function_exists('pll_languages_list') && function_exists('softmir_translate_post')) {
        $default_lang = pll_default_language();
        $target_langs = array_filter(pll_languages_list(), fn($lang) => $lang !== $default_lang);
        foreach ($target_langs as $lang) {
            softmir_translate_post($post_id, $lang);
        }
    }

    return rest_ensure_response([
        'success' => true, 
        'post_id' => $post_id, 
        'message' => 'Processed via unified REST API',
        'saved_data' => $result
    ]);
}

// ========== Admin Columns for AI Enrichment & Affiliate ==========
add_filter('manage_software_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['ai_enriched'] = '🤖 AI Content';
            $new_columns['is_affiliate'] = '🔗 Link type';
        }
    }
    return $new_columns;
});

add_action('manage_software_posts_custom_column', function ($column, $post_id) {
    if ($column === 'ai_enriched') {
        $status = get_post_meta($post_id, '_ai_enriched', true);
        if ($status == '1') {
            echo '<span style="color: green; font-weight: bold;">✅ Done</span>';
        } else {
            echo '<span style="color: #aaa;">In queue</span>';
        }
    } elseif ($column === 'is_affiliate') {
        $is_aff = get_post_meta($post_id, 'is_affiliate', true);
        if ($is_aff == '1') {
            echo '<span style="color: #eab308; font-weight: bold;">💰 Affiliate</span>';
        } else {
            echo '<span style="color: #64748b;">🌐 Normal</span>';
        }
    }
}, 10, 2);

add_filter('manage_edit-software_sortable_columns', function ($columns) {
    $columns['ai_enriched'] = 'ai_enriched';
    $columns['is_affiliate'] = 'is_affiliate';
    return $columns;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'software') {
        return;
    }
    if ($query->get('orderby') === 'ai_enriched') {
        $query->set('meta_key', '_ai_enriched');
        $query->set('orderby', 'meta_value_num');
    }
    if ($query->get('orderby') === 'is_affiliate') {
        $query->set('meta_key', 'is_affiliate');
        $query->set('orderby', 'meta_value_num');
    }
});

// ========== Native Meta Box for Affiliate Link Toggle ==========
add_action('add_meta_boxes', function () {
    add_meta_box(
        'softmir_affiliate_meta',
        '💰 Monetization: Affiliate link',
        function ($post) {
            $val = get_post_meta($post->ID, 'is_affiliate', true);
            echo '<div style="padding: 10px; background: #fffcf0; border: 1px solid #fde047; border-radius: 6px;">';
            echo '<label style="font-size: 15px; font-weight: 600; cursor: pointer; color: #854d0e;">';
            echo '<input type="checkbox" name="is_affiliate" value="1" ' . checked($val, 1, false) . ' style="margin-top: -2px; margin-right: 8px;" />';
            echo 'This card contains an affiliate link';
            echo '</label>';
            echo '<p style="color: #a16207; font-size: 13px; margin-top: 5px; margin-bottom: 0;">The application will be highlighted with the “💰 Affiliate” status in the admin panel. Redirection via /go/ works automatically for all links.</p>';
            echo '</div>';
            wp_nonce_field('softmir_affiliate', 'softmir_affiliate_nonce');
        },
        'software',
        'normal', // Swapped from "side" to "normal" to place it in the main card layout
        'high'
    );
});

add_action('save_post_software', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (!current_user_can('edit_post', $post_id))
        return;

    // If the user manually clicks "Update/Publish" in the WP interface,
    // we remove the "Queue" flag (so that Cron does not overwrite manual labor)
    if (isset($_POST['action']) && $_POST['action'] === 'editpost') {
        update_post_meta($post_id, '_ai_enriched', '1');
    }

    if (!isset($_POST['softmir_affiliate_nonce']) || !wp_verify_nonce($_POST['softmir_affiliate_nonce'], 'softmir_affiliate'))
        return;

    update_post_meta($post_id, 'is_affiliate', isset($_POST['is_affiliate']) ? '1' : '0');
});

function softmir_api_extract_facts($request)
{
    $item = $request->get_json_params();
    if (empty($item) || empty($item['url'])) {
        return new WP_Error('invalid_data', 'Missing URL', ['status' => 400]);
    }

    $url = esc_url_raw($item['url']);
    $lang_name = $item['lang_name'] ?? 'English';
    $force_refresh = !empty($item['force_refresh']) ? true : false;
    $post_title = $item['title'] ?? $url;

    $url_hash = md5($url . '_' . $lang_name);
    $cache_key = '_softzor_ai_facts_' . $url_hash;

    if (!$force_refresh) {
        $cached_facts = get_option($cache_key);
        if (!empty($cached_facts) && is_array($cached_facts)) {
            return rest_ensure_response([
                'success' => true,
                'source' => 'cache',
                'facts' => $cached_facts
            ]);
        }
    }

    // Include pipeline logic
    if (!function_exists('softmir_run_content_pipeline')) {
        require_once get_template_directory() . '/inc/ai-content-pipeline.php';
    }

    $pipeline_result = softmir_run_content_pipeline($url, $lang_name, [
        'post_title' => $post_title,
        'force_refresh' => $force_refresh,
        'step_1_only' => true
    ]);

    if (is_wp_error($pipeline_result)) {
        return new WP_Error('pipeline_error', $pipeline_result->get_error_message(), ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'source' => 'api',
        'facts' => $pipeline_result
    ]);
}
