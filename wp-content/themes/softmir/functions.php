<?php
/**
 * SoftMir Theme Functions
 */

// ========== Content Width ==========
if (!isset($content_width)) {
    $content_width = 1280;
}

// ========== Theme Setup ==========
function softmir_setup()
{
    load_theme_textdomain('softmir', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    add_theme_support('custom-logo', ['height' => 60, 'width' => 250, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('align-wide');

    register_nav_menus([
        'primary' => __('Top Menu (Header)', 'softmir'),
        'footer' => __('Footer Menu', 'softmir'),
    ]);
}
add_action('after_setup_theme', 'softmir_setup');

// ========== Enqueue Styles & Scripts ==========
function softmir_enqueue()
{
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
    $theme_version = filemtime(get_stylesheet_directory() . '/style.css');
    wp_enqueue_style('softmir-style', get_stylesheet_uri(), ['google-fonts'], $theme_version);

    if (is_front_page()) {
        wp_enqueue_script('popular-tabs', get_template_directory_uri() . '/js/popular-tabs.js', [], '1.0.0', true);
    }
    if (is_post_type_archive('software')) {
        wp_enqueue_script('view-switcher', get_template_directory_uri() . '/js/view-switcher.js', [], '1.0.1', true);
        wp_enqueue_script('catalog-filter', get_template_directory_uri() . '/js/catalog-filter.js', [], '1.0.0', true);
        wp_localize_script('catalog-filter', 'softmirCatalog', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('softmir_catalog_filter'),
            'archiveUrl' => get_post_type_archive_link('software'),
        ]);
    }
    if (is_singular('software')) {
        wp_enqueue_script('softmir-single', get_template_directory_uri() . '/js/single-software.js', [], '1.0.0', true);
        wp_localize_script('softmir-single', 'softmirSingleL10n', [
            'showMore' => softmir_quiz_t('sw_show_more', 'Show more...'),
            'showLess' => softmir_quiz_t('sw_show_less', 'Show less...'),
            'hide' => softmir_quiz_t('sw_hide', 'Hide'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('softmir_send_sw_info'),
        ]);
    }

    // Auth JS (global)
    wp_enqueue_script('softmir-auth', get_template_directory_uri() . '/js/auth.js', [], '1.0.0', true);

    // Compare JS (global)
    wp_enqueue_script('softmir-compare', get_template_directory_uri() . '/js/compare.js', ['jquery'], '1.0.0', true);
    wp_localize_script('softmir-compare', 'softmirCompare', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('softmir_compare'),
    ]);

    // Click Tracker JS (global)
    wp_enqueue_script('softmir-click-tracker', get_template_directory_uri() . '/js/click-tracker.js', [], '1.0.0', true);
    wp_localize_script('softmir-click-tracker', 'softmirClickTracker', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('softmir_click_track'),
    ]);

    // Attrs Toggle JS (global — for "Show more attributes" on cards)
    wp_enqueue_script('softmir-attrs-toggle', get_template_directory_uri() . '/js/attrs-toggle.js', [], '1.0.0', true);

    // Group Buying Nonce (global — for REST API security)
    wp_localize_script('softmir-attrs-toggle', 'softmirGroupBuy', [
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
}
add_action('wp_enqueue_scripts', 'softmir_enqueue');

// ========== Add defer to non-critical scripts ==========
function softmir_defer_scripts($tag, $handle, $src)
{
    // Don't defer jQuery or inline scripts
    $no_defer = ['jquery', 'jquery-core', 'jquery-migrate', 'wp-embed'];
    if (in_array($handle, $no_defer)) {
        return $tag;
    }
    // Only defer theme scripts
    $defer_handles = [
        'popular-tabs',
        'view-switcher',
        'catalog-filter',
        'softmir-single',
        'softmir-auth',
        'softmir-compare',
        'softmir-click-tracker',
        'softmir-attrs-toggle'
    ];
    if (in_array($handle, $defer_handles) && strpos($tag, 'defer') === false) {
        $tag = str_replace(' src=', ' defer src=', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'softmir_defer_scripts', 10, 3);

// ========== Polylang: Fallback to default language for software ==========
function softmir_pll_fallback_query($query)
{
    if (is_admin() || !function_exists('pll_current_language'))
        return;
    if (!$query->is_main_query())
        return;

    // Only for software/integrator archives and taxonomy pages
    if (
        $query->is_post_type_archive('software') ||
        $query->is_post_type_archive('integrator') ||
        $query->is_tax('software_category')
    ) {
        $cur_lang = pll_current_language();
        $def_lang = pll_default_language();

        // If not default language, check if translations exist
        if ($cur_lang && $cur_lang !== $def_lang) {
            $post_type = $query->get('post_type') ?: 'software';
            if (is_array($post_type))
                $post_type = reset($post_type);

            $transient_name = 'softmir_pll_fallback_' . $post_type . '_' . $cur_lang;
            $has_posts = get_transient($transient_name);

            if (false === $has_posts) {
                $test = new WP_Query([
                    'post_type' => $post_type,
                    'posts_per_page' => 1,
                    'lang' => $cur_lang,
                    'fields' => 'ids',
                    'no_found_rows' => true,
                ]);
                $has_posts = $test->post_count > 0 ? 'yes' : 'no';
                set_transient($transient_name, $has_posts, HOUR_IN_SECONDS);
            }

            // No translated posts → fallback to all languages
            if ($has_posts === 'no') {
                $query->set('lang', '');
            }
        }
    }
}
add_action('pre_get_posts', 'softmir_pll_fallback_query');

/**
 * Get terms with Polylang fallback: if no terms in current language, return all.
 */
function softmir_pll_get_terms($args = [])
{
    // First try current language (Polylang filters automatically)
    $terms = get_terms($args);
    if (!empty($terms) && !is_wp_error($terms)) {
        return $terms;
    }
    // Fallback: get terms from all languages
    $args['lang'] = '';
    return get_terms($args);
}

// ========== Register CPT: Software ==========
function softmir_cpt_software()
{
    register_post_type('software', [
        'labels' => [
            'name' => __('Каталог ПО', 'softmir'),
            'singular_name' => __('Программа', 'softmir'),
            'add_new' => __('Добавить ПО', 'softmir'),
            'add_new_item' => __('Добавить новое ПО', 'softmir'),
            'edit_item' => __('Редактировать ПО', 'softmir'),
            'all_items' => __('Все программы', 'softmir'),
            'search_items' => __('Искать ПО', 'softmir'),
            'not_found' => __('Не найдено', 'softmir'),
            'menu_name' => __('Каталог ПО', 'softmir'),
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'software', 'with_front' => false],
        'menu_icon' => 'dashicons-grid-view',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'softmir_cpt_software');

// ========== Register CPT: Integrator ==========
function softmir_cpt_integrator()
{
    register_post_type('integrator', [
        'labels' => [
            'name' => __('Интеграторы', 'softmir'),
            'singular_name' => __('Интегратор', 'softmir'),
            'add_new' => __('Добавить', 'softmir'),
            'add_new_item' => __('Добавить интегратора', 'softmir'),
            'edit_item' => __('Редактировать', 'softmir'),
            'all_items' => __('Все интеграторы', 'softmir'),
            'menu_name' => __('Интеграторы', 'softmir'),
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'integrator', 'with_front' => false],
        'menu_icon' => 'dashicons-groups',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'softmir_cpt_integrator');

// ========== Register Taxonomy: Software Category ==========
function softmir_taxonomy_software_category()
{
    register_taxonomy('software_category', 'software', [
        'labels' => [
            'name' => __('Категории ПО', 'softmir'),
            'singular_name' => __('Категория ПО', 'softmir'),
            'search_items' => __('Искать категории', 'softmir'),
            'all_items' => __('Все категории', 'softmir'),
            'parent_item' => __('Родительская категория', 'softmir'),
            'edit_item' => __('Редактировать', 'softmir'),
            'add_new_item' => __('Добавить категорию', 'softmir'),
            'menu_name' => __('Категории', 'softmir'),
        ],
        'hierarchical' => true,
        'public' => true,
        'rewrite' => ['slug' => 'software-category'],
        'show_in_rest' => true,
        'show_admin_column' => true,
    ]);
}
add_action('init', 'softmir_taxonomy_software_category');

// ========== Move Category Meta Box to Main Column ==========
function softmir_move_category_metabox()
{
    remove_meta_box('software_categorydiv', 'software', 'side');
    add_meta_box('software_categorydiv', __('Категории ПО', 'softmir'), 'post_categories_meta_box', 'software', 'normal', 'high', ['taxonomy' => 'software_category']);
}
add_action('add_meta_boxes', 'softmir_move_category_metabox');

// ========== ACF Field Groups ==========
function softmir_acf_fields()
{
    if (!function_exists('acf_add_local_field_group'))
        return;

    // Software Top Fields (Logo, Primary Category)
    acf_add_local_field_group([
        'key' => 'group_software_top',
        'title' => 'Основная информация (Логотип и Категория)',
        'fields' => [
            // Logo
            ['key' => 'field_sw_logo', 'label' => 'Логотип компании', 'name' => 'company_logo', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium', 'instructions' => 'Рекомендуемый размер 300x150'],
            // Primary Category
            ['key' => 'field_sw_primary_cat', 'label' => 'Главная категория', 'name' => 'primary_category', 'type' => 'taxonomy', 'taxonomy' => 'software_category', 'field_type' => 'select', 'allow_null' => 1, 'add_term' => 0, 'save_terms' => 0, 'load_terms' => 0, 'return_format' => 'id', 'multiple' => 0, 'instructions' => 'Выберите главную категорию (для хлебных крошек и вывода основных фич).'],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'software']],
        ],
        'position' => 'normal',
        'style' => 'default',
        'menu_order' => 0,
    ]);

    // Software Main Fields
    acf_add_local_field_group([
        'key' => 'group_software',
        'title' => 'Product details',
        'fields' => [
            // Short Description
            ['key' => 'field_sw_short_desc', 'label' => 'Short Description', 'name' => 'short_description', 'type' => 'textarea', 'rows' => 3],
            // Screenshots (Individual fields for Free ACF) — compact 4-in-a-row layout
            ['key' => 'field_sw_screen_1', 'label' => 'Screenshot 1', 'name' => 'screenshot_1', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_2', 'label' => 'Screenshot 2', 'name' => 'screenshot_2', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_3', 'label' => 'Screenshot 3', 'name' => 'screenshot_3', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_4', 'label' => 'Screenshot 4', 'name' => 'screenshot_4', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            // [REMOVED] Pricing, Key Features, Advantages, Business Areas WYSIWYG fields
            // Data preserved in post_meta. Backup: functions.php.bak-wysiwyg
            // Pricing Summary
            ['key' => 'field_sw_price_summary', 'label' => 'Price (отображение в карточке)', 'name' => 'price_summary', 'type' => 'text', 'instructions' => 'Example: From $19/mo'],
            // [REMOVED] Target Markets — not needed for UA-focused platform
            // Flags
            ['key' => 'field_sw_featured', 'label' => 'Recommended (TOP)', 'name' => 'is_featured', 'type' => 'true_false', 'ui' => 1],
            ['key' => 'field_sw_pinned', 'label' => 'Pin to home', 'name' => 'is_pinned', 'type' => 'true_false', 'ui' => 1],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'software']],
        ],
        'position' => 'normal',
        'style' => 'default',
        'menu_order' => 10,
    ]);

    // Software Links & Media Fields
    acf_add_local_field_group([
        'key' => 'group_software_links',
        'title' => 'Links & Media',
        'fields' => [
            // Website
            ['key' => 'field_sw_website', 'label' => 'Website', 'name' => 'website_url', 'type' => 'url'],
            // Video
            ['key' => 'field_sw_video', 'label' => 'Video (YouTube)', 'name' => 'video_url', 'type' => 'url'],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'software']],
        ],
        'position' => 'normal',
        'style' => 'default',
        'menu_order' => 2,
    ]);

    // Automatically enforce meta box order for software
    add_filter('get_user_option_meta-box-order_software', function ($order) {
        $required_normal_order = 'acf-group_software_top,software_categorydiv,softmir_affiliate_meta,acf-group_software_links,acf-group_software_scout_details,softmir_software_key_functions,softmir_sw_attributes,acf-group_software,acf-group_software_vendor_buying';

        if (empty($order) || !is_array($order)) {
            $order = [
                'normal' => $required_normal_order,
                'side' => 'submitdiv,softmir_autofill,softmir_ai_translate,postimagediv,slugdiv,postcustom',
                'advanced' => '',
            ];
        } else {
            $order['normal'] = $required_normal_order;
        }
        return $order;
    });

    // Integrator Fields
    acf_add_local_field_group([
        'key' => 'group_integrator',
        'title' => 'Данные интегратора',
        'fields' => [
            ['key' => 'field_int_logo', 'label' => 'Логотип', 'name' => 'integrator_logo', 'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_int_website', 'label' => 'Веб-сайт', 'name' => 'integrator_website', 'type' => 'url'],
            ['key' => 'field_int_short_desc', 'label' => 'Краткое описание', 'name' => 'integrator_short_desc', 'type' => 'textarea', 'rows' => 3],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'integrator']],
        ],
    ]);


}
add_action('acf/init', 'softmir_acf_fields');

// ========== Include Attribute Helpers ==========
// require_once get_template_directory() . '/inc/acf-home.php';
require_once get_template_directory() . '/inc/shortcodes.php';
require_once get_template_directory() . '/inc/shortcodes-home.php';
require_once get_template_directory() . '/inc/acf-home-options.php';
require_once get_template_directory() . '/inc/acf-software.php';
require_once get_template_directory() . '/inc/block-patterns.php';

require_once get_template_directory() . '/inc/attributes.php';
require_once get_template_directory() . '/inc/schema.php';
require_once get_template_directory() . '/inc/ajax-filter.php';
require_once get_template_directory() . '/inc/key-functions.php';
require_once get_template_directory() . '/inc/admin-autofill.php';

// ========== Send Software Info ==========
require_once get_template_directory() . '/inc/send-software-info.php';
require_once get_template_directory() . '/inc/lead-capture.php';

// ========== AI Translation ==========
require_once get_template_directory() . '/inc/ai-translate.php';
require_once get_template_directory() . '/inc/ai-translate-admin.php';
require_once get_template_directory() . '/inc/ai-autopost.php';
require_once get_template_directory() . '/inc/ai-inbox-admin.php';

// ========== Admin Settings (Gemini API Key) ==========
require_once get_template_directory() . '/inc/admin-settings.php';

// ========== Auth System ==========
require_once get_template_directory() . '/inc/auth.php';
require_once get_template_directory() . '/inc/smtp.php';
require_once get_template_directory() . '/inc/google-oauth.php';

// ========== Quiz System ==========
require_once get_template_directory() . '/inc/polylang-strings.php';
require_once get_template_directory() . '/inc/quiz-functions.php';
require_once get_template_directory() . '/inc/db-tables.php';
require_once get_template_directory() . '/inc/quiz-frontend.php';
require_once get_template_directory() . '/inc/quiz-rest-api.php';
require_once get_template_directory() . '/inc/api-group-buy.php';

// ========== Template Helpers (extracted from single-software.php) ==========
require_once get_template_directory() . '/inc/template-helpers.php';

// ========== Admin: Software Import & Export ==========
require_once get_template_directory() . '/inc/admin-import-export.php';

// ========== Polylang Admin Warnings ==========
require_once get_template_directory() . '/inc/polylang-admin-warnings.php';

// ========== API for AI: Custom REST API for Python Enricher Fallback ==========
require_once get_template_directory() . '/inc/api-enricher.php';
require_once get_template_directory() . '/inc/api-software-fields.php';

// ========== API for AI: Native WP-Cron Background Enricher ==========
require_once get_template_directory() . '/inc/ai-content-pipeline.php';
require_once get_template_directory() . '/inc/ai-background-enricher.php';

// ========== API for AI: Category Features Generator ==========
require_once get_template_directory() . '/inc/api-category-features.php';

// ========== API for AI: Category SEO & Rank Math Settings ==========
require_once get_template_directory() . '/inc/admin-category-ai.php';

// ========== API for AI: Blog Receiver ==========
require_once get_template_directory() . '/inc/api-blog-receiver.php';

// ========== API for AI: Hermes Endpoints ==========
require_once get_template_directory() . '/inc/api-hermes.php';

// ========== AI Core Engine: Ping ==========
function softmir_ping_gemini($return_error = false)
{
    // Check cache first (avoid pinging Google on every request)
    $cached = get_transient('softmir_gemini_ping');
    if ($cached === 'ok' && !$return_error) {
        return true;
    }

    $api_key = function_exists('softmir_get_gemini_key') ? trim(softmir_get_gemini_key()) : '';
    if (empty($api_key))
        return $return_error ? 'API Key is empty' : false;

    // Ping the primary model to check availability
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $body = [
        'contents' => [['parts' => [['text' => 'Ping']]]],
        'generationConfig' => ['maxOutputTokens' => 1]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        set_transient('softmir_gemini_ping', 'fail', 60); // Cache failure for 1 min
        return $return_error ? 'WP_Error: ' . $response->get_error_message() : false;
    }
    $status_code = wp_remote_retrieve_response_code($response);
    $ok = ($status_code === 200 || $status_code === 400);
    set_transient('softmir_gemini_ping', $ok ? 'ok' : 'fail', $ok ? 300 : 60); // 5 min on success, 1 min on fail
    
    if (!$ok && $return_error) {
        return "HTTP $status_code: " . wp_remote_retrieve_body($response);
    }
    
    return $ok;
}

// ========== Custom Admin Dashboard ==========
require_once get_template_directory() . '/inc/admin-analytics.php';

// ========== API Monitor (Gemini/Firecrawl usage tracking) ==========
require_once get_template_directory() . '/inc/api-monitor.php';

// ========== Admin Group Buying ==========
require_once get_template_directory() . '/inc/admin-group-buying.php';

// ========== Vendor Partnership ==========
require_once get_template_directory() . '/inc/api-partner-request.php';
require_once get_template_directory() . '/inc/admin-partner-requests.php';

// ========== Affiliate Cloaking Radar ==========
require_once get_template_directory() . '/inc/affiliate-cloak.php';

// ========== Register CPT: sw_attribute ==========
function softmir_cpt_sw_attribute()
{
    register_post_type('sw_attribute', [
        'labels' => [
            'name' => __('Атрибуты', 'softmir'),
            'singular_name' => __('Атрибут', 'softmir'),
            'add_new' => __('Добавить атрибут', 'softmir'),
            'add_new_item' => __('Добавить новый', 'softmir'),
            'edit_item' => __('Редактировать', 'softmir'),
            'all_items' => __('Атрибуты', 'softmir'),
            'search_items' => __('Искать атрибуты', 'softmir'),
            'not_found' => __('Не найдено', 'softmir'),
            'menu_name' => __('Атрибуты', 'softmir'),
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=software',
        'menu_icon' => 'dashicons-tag',
        'supports' => ['title'],
        'has_archive' => false,
        'rewrite' => false,
    ]);
}
add_action('init', 'softmir_cpt_sw_attribute');

// ========== Register sw_attribute in Polylang ==========
function softmir_pll_post_types($post_types)
{
    $post_types['sw_attribute'] = 'sw_attribute';
    return $post_types;
}
add_filter('pll_get_post_types', 'softmir_pll_post_types');

// ========== Admin Columns: sw_attribute ==========
function softmir_sw_attribute_columns($columns)
{
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['attr_type'] = 'Type';
            $new['attr_card_pos'] = 'Card';
            $new['attr_page_pos'] = 'Page';
            $new['attr_categories'] = 'Category binding';
        }
    }
    return $new;
}
add_filter('manage_sw_attribute_posts_columns', 'softmir_sw_attribute_columns');

function softmir_sw_attribute_column_content($column, $post_id)
{
    $type_labels = [
        'text' => 'Text',
        'number' => 'Number',
        'url' => 'Link',
        'select' => 'List',
        'checkbox' => 'Checkboxes',
    ];
    $card_labels = ['none' => '—', 'middle' => 'Middle', 'footer' => 'Footer'];
    $page_labels = ['none' => '—', 'middle' => 'Main', 'sidebar' => 'Sidebar'];

    switch ($column) {
        case 'attr_type':
            $type = get_post_meta($post_id, '_attr_type', true) ?: 'text';
            echo esc_html($type_labels[$type] ?? $type);
            break;

        case 'attr_card_pos':
            $pos = get_post_meta($post_id, '_attr_card_position', true) ?: 'none';
            echo esc_html($card_labels[$pos] ?? $pos);
            break;

        case 'attr_page_pos':
            $pos = get_post_meta($post_id, '_attr_page_position', true) ?: 'none';
            echo esc_html($page_labels[$pos] ?? $pos);
            break;

        case 'attr_categories':
            $cats = get_post_meta($post_id, '_attr_categories', true);
            if (empty($cats) || !is_array($cats)) {
                echo '<em style="color:#888;">All Categories</em>';
            } else {
                $names = [];
                foreach ($cats as $term_id) {
                    $term = get_term($term_id, 'software_category');
                    if ($term && !is_wp_error($term)) {
                        $names[] = esc_html($term->name);
                    }
                }
                echo !empty($names) ? implode(', ', $names) : '<em style="color:#888;">All Categories</em>';
            }
            break;
    }
}
add_action('manage_sw_attribute_posts_custom_column', 'softmir_sw_attribute_column_content', 10, 2);

// ========== Meta Box: Attribute Settings ==========
function softmir_attr_settings_meta_box()
{
    add_meta_box(
        'softmir_attr_settings',
        'Настройки атрибута',
        'softmir_attr_settings_render',
        'sw_attribute',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'softmir_attr_settings_meta_box');

function softmir_attr_settings_render($post)
{
    wp_nonce_field('softmir_attr_settings', 'softmir_attr_nonce');

    $type = get_post_meta($post->ID, '_attr_type', true) ?: 'text';
    $icon = get_post_meta($post->ID, '_attr_icon', true) ?: '';
    $filterable = get_post_meta($post->ID, '_attr_filterable', true);
    $card_pos = get_post_meta($post->ID, '_attr_card_position', true) ?: 'none';
    $page_pos = get_post_meta($post->ID, '_attr_page_position', true) ?: 'none';
    $options = get_post_meta($post->ID, '_attr_options', true) ?: '';
    $multiple = get_post_meta($post->ID, '_attr_multiple', true);
    $bound_cats = get_post_meta($post->ID, '_attr_categories', true) ?: [];

    $types = [
        'text' => 'Text',
        'number' => 'Number',
        'url' => 'Link (URL)',
        'select' => 'Dropdown list',
        'checkbox' => 'Checkboxes',
    ];
    $card_positions = ['none' => 'Do not show', 'middle' => 'Middle secция', 'footer' => 'Footer'];
    $page_positions = ['none' => 'Do not show', 'middle' => 'Main колонка', 'sidebar' => 'Sidebar'];

    echo '<table class="form-table">';

    // Type
    echo '<tr><th><label>Тип данных</label></th><td><select name="_attr_type" style="min-width:200px">';
    foreach ($types as $k => $v) {
        echo '<option value="' . esc_attr($k) . '"' . selected($type, $k, false) . '>' . esc_html($v) . '</option>';
    }
    echo '</select></td></tr>';

    // Icon
    echo '<tr><th><label>Иконка</label></th><td>';
    echo '<input type="text" name="_attr_icon" value="' . esc_attr($icon) . '" placeholder="Emoji or CSS class, e.g. 🌐" style="min-width:200px">';
    echo '<p class="description">Emoji (🚀 📊 💬) или CSS-класс иконки</p>';
    echo '</td></tr>';

    // Filterable
    echo '<tr><th><label>Участвует в фильтрах</label></th><td>';
    echo '<label><input type="checkbox" name="_attr_filterable" value="1"' . checked($filterable, '1', false) . '> Показывать в фильтрах каталога</label>';
    echo '</td></tr>';

    // Card position
    echo '<tr><th><label>Позиция в карточке</label></th><td><select name="_attr_card_position" style="min-width:200px">';
    foreach ($card_positions as $k => $v) {
        echo '<option value="' . esc_attr($k) . '"' . selected($card_pos, $k, false) . '>' . esc_html($v) . '</option>';
    }
    echo '</select></td></tr>';

    // Page position
    echo '<tr><th><label>Позиция на странице</label></th><td><select name="_attr_page_position" style="min-width:200px">';
    foreach ($page_positions as $k => $v) {
        echo '<option value="' . esc_attr($k) . '"' . selected($page_pos, $k, false) . '>' . esc_html($v) . '</option>';
    }
    echo '</select></td></tr>';

    // Options (for select/checkbox)
    echo '<tr><th><label>Опции</label></th><td>';
    echo '<textarea name="_attr_options" rows="3" style="width:100%;max-width:400px" placeholder="Option 1, Option 2, Option 3">' . esc_textarea($options) . '</textarea>';
    echo '<p class="description">Через запятую. Только для типов \'Dropdown\' и \'Checkboxes\'</p>';
    echo '</td></tr>';

    // Multiple
    echo '<tr><th><label>Множественный выбор</label></th><td>';
    echo '<label><input type="checkbox" name="_attr_multiple" value="1"' . checked($multiple, '1', false) . '> Разрешить множественный выбор</label>';
    echo '</td></tr>';

    // Category binding
    $all_cats = softmir_pll_get_terms(['taxonomy' => 'software_category', 'hide_empty' => false]);
    if ($all_cats && !is_wp_error($all_cats)) {
        echo '<tr><th><label>Привязка к категориям</label></th><td>';
        echo '<fieldset style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:8px;border-radius:4px;">';
        foreach ($all_cats as $cat) {
            $chk = is_array($bound_cats) && in_array($cat->term_id, $bound_cats) ? ' checked' : '';
            echo '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="_attr_categories[]" value="' . esc_attr($cat->term_id) . '"' . $chk . '> ' . esc_html($cat->name) . '</label>';
        }
        echo '</fieldset>';
        echo '<p class="description">Если ничего не выбрано — атрибут применяется ко всем категориям</p>';
        echo '</td></tr>';
    }

    echo '</table>';
}

// ========== Save Attribute Settings ==========
function softmir_attr_settings_save($post_id)
{
    if (!isset($_POST['softmir_attr_nonce']) || !wp_verify_nonce($_POST['softmir_attr_nonce'], 'softmir_attr_settings'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (get_post_type($post_id) !== 'sw_attribute')
        return;

    $fields = ['_attr_type', '_attr_icon', '_attr_card_position', '_attr_page_position', '_attr_options'];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
        }
    }
    // Checkboxes
    update_post_meta($post_id, '_attr_filterable', isset($_POST['_attr_filterable']) ? '1' : '0');
    update_post_meta($post_id, '_attr_multiple', isset($_POST['_attr_multiple']) ? '1' : '0');

    // Categories (array of term IDs)
    $cats = isset($_POST['_attr_categories']) ? array_map('intval', $_POST['_attr_categories']) : [];
    update_post_meta($post_id, '_attr_categories', $cats);
}
add_action('save_post', 'softmir_attr_settings_save');

// ========== Meta Box: Dynamic Attributes on Software Edit ==========
function softmir_software_attrs_meta_box()
{
    add_meta_box(
        'softmir_sw_attributes',
        '📋 Атрибуты ПО',
        'softmir_software_attrs_render',
        'software',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'softmir_software_attrs_meta_box');

function softmir_software_attrs_render($post)
{
    wp_nonce_field('softmir_sw_attrs', 'softmir_sw_attrs_nonce');

    $attrs = softmir_get_attributes();
    if (empty($attrs)) {
        echo '<p style="color:#888;">Атрибуты еще не созданы. <a href="' . admin_url('edit.php?post_type=sw_attribute') . '">Создать атрибуты</a></p>';
        return;
    }

    echo '<table class="form-table">';
    foreach ($attrs as $attr) {
        if (!softmir_attr_applies_to_software($attr->ID, $post->ID))
            continue;

        $meta = softmir_get_attr_meta($attr->ID);
        $value = softmir_get_software_attr_value($post->ID, $attr->ID);
        $field_name = '_sw_attr_' . $attr->ID;
        $icon = $meta['icon'] ? $meta['icon'] . ' ' : '';

        echo '<tr><th><label>' . esc_html($icon . $attr->post_title) . '</label></th><td>';

        switch ($meta['type']) {
            case 'checkbox':
                $options = softmir_parse_options($meta['options']);
                if (!empty($options)) {
                    $current = is_array($value) ? $value : [];
                    foreach ($options as $opt) {
                        $chk = in_array($opt, $current) ? ' checked' : '';
                        echo '<label style="display:inline-block;margin-right:12px;margin-bottom:4px;"><input type="checkbox" name="' . esc_attr($field_name) . '[]" value="' . esc_attr($opt) . '"' . $chk . '> ' . esc_html($opt) . '</label>';
                    }
                } else {
                    echo '<label><input type="checkbox" name="' . esc_attr($field_name) . '" value="1"' . checked($value, '1', false) . '> Yes</label>';
                }
                break;

            case 'select':
                $options = softmir_parse_options($meta['options']);
                if ($meta['multiple']) {
                    $current = is_array($value) ? $value : [];
                    echo '<select name="' . esc_attr($field_name) . '[]" multiple style="min-width:250px;min-height:80px">';
                    foreach ($options as $opt) {
                        $sel = in_array($opt, $current) ? ' selected' : '';
                        echo '<option value="' . esc_attr($opt) . '"' . $sel . '>' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="description">Hold Ctrl for multiple selection</p>';
                } else {
                    echo '<select name="' . esc_attr($field_name) . '" style="min-width:250px">';
                    echo '<option value="">— Select —</option>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '"' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                }
                break;

            case 'url':
                echo '<input type="url" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" style="width:100%;max-width:400px" placeholder="https://">';
                break;

            case 'number':
                echo '<input type="number" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" style="min-width:150px">';
                break;

            case 'text':
            default:
                // Handle array values stored from older saves
                $display_value = is_array($value) ? implode(', ', $value) : $value;
                echo '<input type="text" name="' . esc_attr($field_name) . '" value="' . esc_attr($display_value) . '" style="width:100%">';
                break;
        }

        echo '</td></tr>';
    }
    echo '</table>';
}

// ========== Save Software Attribute Values ==========
function softmir_software_attrs_save($post_id)
{
    if (!isset($_POST['softmir_sw_attrs_nonce']) || !wp_verify_nonce($_POST['softmir_sw_attrs_nonce'], 'softmir_sw_attrs'))
        return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        return;
    if (get_post_type($post_id) !== 'software')
        return;

    $attrs = softmir_get_attributes();
    foreach ($attrs as $attr) {
        $field_name = '_sw_attr_' . $attr->ID;
        $meta = softmir_get_attr_meta($attr->ID);

        if (isset($_POST[$field_name])) {
            $val = $_POST[$field_name];
            if (is_array($val)) {
                $val = array_map('sanitize_text_field', $val);
            } else {
                $val = sanitize_text_field($val);
            }
            update_post_meta($post_id, $field_name, $val);
        } else {
            // Checkbox unchecked or nothing selected
            if ($meta['type'] === 'checkbox') {
                $options = softmir_parse_options($meta['options']);
                if (!empty($options)) {
                    update_post_meta($post_id, $field_name, []);
                } else {
                    update_post_meta($post_id, $field_name, '0');
                }
            }
        }
    }
}
add_action('save_post', 'softmir_software_attrs_save');

// ========== Helper: Stars Rating HTML ==========
function softmir_stars($rating = 0, $max = 5)
{
    $output = '<span class="stars">';
    for ($i = 1; $i <= $max; $i++) {
        $output .= $i <= round($rating) ? '★' : '<span class="empty">★</span>';
    }
    $output .= '</span>';
    return $output;
}

// ========== Helper: Truncate text ==========
function softmir_truncate($text, $length = 120)
{
    if (mb_strlen($text, 'UTF-8') <= $length)
        return $text;
    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '...';
}

// ========== Helper: Language Fallback for Fields ==========
function softmir_get_field_with_lang_fallback($field_name, $post_id = 0)
{
    if (!$post_id)
        $post_id = get_the_ID();

    $val = get_field($field_name, $post_id);
    if (!empty($val))
        return $val;

    $val = get_post_meta($post_id, $field_name, true);
    if (!empty($val))
        return $val;

    if (function_exists('pll_default_language') && function_exists('pll_get_post_translations')) {
        $default_lang = pll_default_language();
        $current_lang = pll_get_post_language($post_id);

        if ($current_lang && $current_lang !== $default_lang) {
            $translations = pll_get_post_translations($post_id);
            if (!empty($translations[$default_lang]) && $translations[$default_lang] != $post_id) {
                $orig_id = $translations[$default_lang];
                $val = get_field($field_name, $orig_id);
                if (empty($val)) {
                    $val = get_post_meta($orig_id, $field_name, true);
                }
            }
        }
    }

    return $val ? $val : '';
}

// ========== Post View Counter ==========
function softmir_track_views()
{
    if (!is_singular(['software', 'post']))
        return;
    if (is_admin())
        return;

    // Don't count bots
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT']))
        return;

    $post_id = get_the_ID();
    $count = (int) get_post_meta($post_id, 'softmir_views', true);
    update_post_meta($post_id, 'softmir_views', $count + 1);
}
add_action('wp_head', 'softmir_track_views');

function softmir_get_views($post_id = null)
{
    if (!$post_id)
        $post_id = get_the_ID();
    return (int) get_post_meta($post_id, 'softmir_views', true);
}

// ========== Click Counter (AJAX) ==========
function softmir_get_clicks($post_id = null)
{
    if (!$post_id)
        $post_id = get_the_ID();
    return (int) get_post_meta($post_id, 'softmir_clicks', true);
}

function softmir_ajax_track_click()
{
    check_ajax_referer('softmir_click_track', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || get_post_type($post_id) !== 'software') {
        wp_send_json_error();
    }

    $count = (int) get_post_meta($post_id, 'softmir_clicks', true);
    update_post_meta($post_id, 'softmir_clicks', $count + 1);

    wp_send_json_success(['clicks' => $count + 1]);
}
add_action('wp_ajax_softmir_track_click', 'softmir_ajax_track_click');
add_action('wp_ajax_nopriv_softmir_track_click', 'softmir_ajax_track_click');

// ========== Site Reviews Localization ==========
function softmir_translate_site_reviews($translated_text, $text, $domain)
{
    if ($domain === 'site-reviews' || empty($domain)) {
        $translations = [
            'excellent' => [
                'uk' => 'Excellent',
                'ru' => 'Excellent',
            ],
            'very good' => [
                'uk' => 'Very good',
                'ru' => 'Very good',
            ],
            'average' => [
                'uk' => 'Average',
                'ru' => 'Average',
            ],
            'poor' => [
                'uk' => 'Bad',
                'ru' => 'Bad',
            ],
            'terrible' => [
                'uk' => 'Terrible',
                'ru' => 'Terrible',
            ],
            'write a review' => [
                'uk' => 'Write a review',
                'ru' => 'Write a review',
            ],
        ];

        $lookup = strtolower(trim($text));
        if (isset($translations[$lookup])) {
            $lang = function_exists('pll_current_language') ? pll_current_language() : 'ru';
            return $translations[$lookup][$lang] ?? $translated_text;
        }
    }
    return $translated_text;
}
add_filter('gettext', 'softmir_translate_site_reviews', 20, 3);

// ========== Flush Rewrite on Activation ==========
function softmir_flush_rewrites()
{
    softmir_cpt_software();
    softmir_cpt_integrator();
    softmir_cpt_sw_attribute();
    softmir_taxonomy_software_category();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'softmir_flush_rewrites');

// ========== RankMath SEO Compatibility ==========
/**
 * Disable RankMath Schema on Software pages since we have custom JSON-LD
 */
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if (is_singular('software') || is_post_type_archive('software') || is_tax('software_category')) {
        return []; // Return empty array to strip RankMath's schema
    }
    return $data;
}, 99, 2);

/**
 * Hide unreviewed Scout cards from search engines (noindex + sitemap exclusion).
 * Cards remain visible to users on the site and accessible via direct links.
 * Once admin saves/reviews the card, _reviewed_at is set and restrictions lift.
 */

// 1. Add noindex,nofollow meta tag for unreviewed scout cards
add_action('wp_head', function () {
    if (!is_singular('software')) return;

    $post_id = get_the_ID();
    $status = get_post_meta($post_id, 'software_status', true);
    if ($status !== 'external_scout') return;

    $reviewed = get_post_meta($post_id, '_reviewed_at', true);
    if ($reviewed) return;

    // Unreviewed scout card — block indexing
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
}, 1); // Priority 1: before RankMath outputs its own robots tag

// 2. Tell RankMath to set noindex for unreviewed scout cards (belt-and-suspenders)
add_filter('rank_math/frontend/robots', function ($robots) {
    if (!is_singular('software')) return $robots;

    $post_id = get_the_ID();
    $status = get_post_meta($post_id, 'software_status', true);
    if ($status !== 'external_scout') return $robots;

    $reviewed = get_post_meta($post_id, '_reviewed_at', true);
    if ($reviewed) return $robots;

    $robots['index'] = 'noindex';
    $robots['follow'] = 'nofollow';
    return $robots;
});

// 3. Exclude unreviewed scout cards from Rank Math XML Sitemap
add_filter('rank_math/sitemap/entry', function ($url, $type, $object) {
    if (!isset($object->ID)) return $url;
    if (get_post_type($object->ID) !== 'software') return $url;

    $status = get_post_meta($object->ID, 'software_status', true);
    if ($status !== 'external_scout') return $url;

    $reviewed = get_post_meta($object->ID, '_reviewed_at', true);
    if ($reviewed) return $url;

    return false; // Exclude from sitemap
}, 10, 3);

/**
 * Optionally, disable the RankMath meta box on specific post types
 * if we want to manage it entirely via our own ACF fields.
 * For now, we leave it active so you can manually edit titles/descriptions.
 */
// add_filter( 'rank_math/metabox/post_types', function( $post_types ) {
//     if( in_array( 'sw_attribute', $post_types ) ) {
//         unset( $post_types['sw_attribute'] );
//     }
//     return $post_types;
// });

// ========== Compare Feature AJAX Handler ==========
add_action('wp_ajax_softmir_get_compare_titles', 'softmir_ajax_get_compare_titles');
add_action('wp_ajax_nopriv_softmir_get_compare_titles', 'softmir_ajax_get_compare_titles');

function softmir_ajax_get_compare_titles()
{
    check_ajax_referer('softmir_compare', 'nonce');

    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    if (empty($ids)) {
        wp_send_json_error();
    }

    $q = new WP_Query([
        'post_type' => 'software',
        'post__in' => $ids,
        'posts_per_page' => 4,
        'orderby' => 'post__in'
    ]);

    ob_start();
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $logo = get_field('company_logo');
            echo '<div class="compare-item-preview" title="' . esc_attr(get_the_title()) . '">';
            if ($logo) {
                echo '<img src="' . esc_url($logo) . '" alt="" class="compare-preview-logo" loading="lazy">';
            }
            echo '<span class="compare-preview-title">' . esc_html(get_the_title()) . '</span>';
            echo '<span class="compare-item-remove" data-id="' . get_the_ID() . '">✕</span>';
            echo '</div>';
        }
        wp_reset_postdata();
    }
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

// ========== Compare Feature Page ID Helper ==========
function softmir_get_compare_page_id()
{
    // Attempt to find a page using the compare template
    $pages = get_pages([
        'meta_key' => '_wp_page_template',
        'meta_value' => 'page-compare.php',
        'number' => 1
    ]);
    if (!empty($pages)) {
        return $pages[0]->ID;
    }
    return 0;
}

// ========== CTA Email Subscription AJAX Handler ==========
add_action('wp_ajax_softmir_cta_subscribe', 'softmir_cta_subscribe_handler');
add_action('wp_ajax_nopriv_softmir_cta_subscribe', 'softmir_cta_subscribe_handler');

function softmir_cta_subscribe_handler()
{
    check_ajax_referer('softmir_cta_subscribe', 'nonce');

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (!is_email($email)) {
        wp_send_json_error(['message' => __('Invalid email address.', 'softmir')]);
    }

    // Store subscriber in wp_options (simple approach)
    $subscribers = get_option('softmir_cta_subscribers', []);
    if (in_array($email, $subscribers)) {
        wp_send_json_error(['message' => __('This email is already subscribed.', 'softmir')]);
    }
    $subscribers[] = $email;
    update_option('softmir_cta_subscribers', $subscribers);

    // Send notification to admin
    $admin_email = get_option('admin_email');
    $subject = sprintf(__('[SoftMir] New request: %s', 'softmir'), $email);
    $body = sprintf(__('User %s submitted a request via the CTA form on the site.', 'softmir'), $email);
    wp_mail($admin_email, $subject, $body);

    wp_send_json_success();
}

// ========== Affiliate Link Cloaking: /go/software-slug/ ==========
function softmir_go_redirect_rewrite()
{
    add_rewrite_rule(
        '^go/([^/]+)/?$',
        'index.php?softmir_go=$matches[1]',
        'top'
    );
}
add_action('init', 'softmir_go_redirect_rewrite');

function softmir_go_query_var($vars)
{
    $vars[] = 'softmir_go';
    return $vars;
}
add_filter('query_vars', 'softmir_go_query_var');

function softmir_go_redirect_template()
{
    $slug = get_query_var('softmir_go');
    if (!$slug)
        return;

    $post = get_page_by_path($slug, OBJECT, 'software');
    if (!$post) {
        wp_safe_redirect(home_url('/'));
        exit;
    }

    $website = get_field('website_url', $post->ID);
    if ($website) {
        // Track the click
        $count = (int) get_post_meta($post->ID, 'softmir_clicks', true);
        update_post_meta($post->ID, 'softmir_clicks', $count + 1);

        wp_redirect($website, 301);
        exit;
    }

    // Fallback to the software page itself
    wp_safe_redirect(get_permalink($post->ID));
    exit;
}
add_action('template_redirect', 'softmir_go_redirect_template');

// ========== Admin: Scout Source Column, Review Tracking & Filter ==========

/**
 * Auto-set _reviewed_at when admin manually saves a scout card.
 * This marks the card as "reviewed" without removing its scout origin.
 */
add_action('save_post_software', 'softmir_mark_scout_reviewed', 50, 3);
function softmir_mark_scout_reviewed($post_id, $post, $update)
{
    // Only on manual admin saves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (defined('DOING_CRON') && DOING_CRON) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Skip if translation process is running
    global $softmir_is_translating;
    if (!empty($softmir_is_translating)) return;
    if (defined('SOFTMIR_TRANSLATING') && SOFTMIR_TRANSLATING) return;

    // Only for scout cards that haven't been reviewed yet
    $status = get_post_meta($post_id, 'software_status', true);
    if ($status !== 'external_scout') return;

    $already_reviewed = get_post_meta($post_id, '_reviewed_at', true);
    if ($already_reviewed) return;

    // Mark as reviewed
    update_post_meta($post_id, '_reviewed_at', current_time('mysql'));
    update_post_meta($post_id, '_reviewed_by', get_current_user_id());
}

/**
 * Add "Andсточник" (Source) column to software list table
 */
function softmir_software_source_column($columns)
{
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['sw_source'] = '📦 Andсточник';
        }
    }
    return $new;
}
add_filter('manage_software_posts_columns', 'softmir_software_source_column');

/**
 * Render source column content with review status
 */
function softmir_software_source_column_content($column, $post_id)
{
    if ($column !== 'sw_source')
        return;

    $status = get_post_meta($post_id, 'software_status', true);
    if ($status === 'external_scout') {
        $reviewed_at = get_post_meta($post_id, '_reviewed_at', true);
        if ($reviewed_at) {
            // Reviewed scout card
            $reviewed_by = get_post_meta($post_id, '_reviewed_by', true);
            $user_name = $reviewed_by ? get_userdata($reviewed_by)->display_name : '';
            $tooltip = sprintf('Checked: %s', wp_date('d.m.Y H:i', strtotime($reviewed_at)));
            if ($user_name) $tooltip .= ' (' . $user_name . ')';
            echo '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#d1fae5;border:1px solid #34d399;border-radius:4px;font-size:12px;font-weight:500;color:#065f46;" title="' . esc_attr($tooltip) . '">🤖 Scout ✅</span>';
        } else {
            // Unreviewed scout card
            echo '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;font-size:12px;font-weight:500;color:#856404;" title="In moderation — not yet verified by administrator">🤖 Scout ⚠️</span>';
        }
    } else {
        echo '<span style="color:#888;">—</span>';
    }
}
add_action('manage_software_posts_custom_column', 'softmir_software_source_column_content', 10, 2);

/**
 * Add "Andсточник" dropdown filter in software list
 */
function softmir_software_source_filter()
{
    global $typenow;
    if ($typenow !== 'software')
        return;

    $current = $_GET['sw_source_filter'] ?? '';
    ?>
    <select name="sw_source_filter">
        <option value="">All sources</option>
        <option value="scout" <?php selected($current, 'scout'); ?>>🤖 Scout — все</option>
        <option value="scout_unreviewed" <?php selected($current, 'scout_unreviewed'); ?>>🤖 Scout ⚠️ на модерации</option>
        <option value="scout_reviewed" <?php selected($current, 'scout_reviewed'); ?>>🤖 Scout ✅ проверенные</option>
        <option value="manual" <?php selected($current, 'manual'); ?>>✍️ Manual addition</option>
    </select>
    <?php
}
add_action('restrict_manage_posts', 'softmir_software_source_filter');

/**
 * Apply source filter to query
 */
function softmir_software_source_filter_query($query)
{
    global $pagenow, $typenow;
    if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== 'software' || !$query->is_main_query())
        return;

    $filter = $_GET['sw_source_filter'] ?? '';
    if ($filter === 'scout') {
        $query->set('meta_key', 'software_status');
        $query->set('meta_value', 'external_scout');
    } elseif ($filter === 'scout_unreviewed') {
        $query->set('meta_query', [
            'relation' => 'AND',
            ['key' => 'software_status', 'value' => 'external_scout'],
            ['key' => '_reviewed_at', 'compare' => 'NOT EXISTS'],
        ]);
    } elseif ($filter === 'scout_reviewed') {
        $query->set('meta_query', [
            'relation' => 'AND',
            ['key' => 'software_status', 'value' => 'external_scout'],
            ['key' => '_reviewed_at', 'compare' => 'EXISTS'],
        ]);
    } elseif ($filter === 'manual') {
        $query->set('meta_query', [
            'relation' => 'OR',
            ['key' => 'software_status', 'compare' => 'NOT EXISTS'],
            ['key' => 'software_status', 'value' => 'external_scout', 'compare' => '!='],
        ]);
    }
}
add_action('pre_get_posts', 'softmir_software_source_filter_query');

/**
 * Add "Scout" view links in the views bar with unreviewed count
 */
function softmir_software_views_scout($views)
{
    global $wpdb;

    // Total scout count
    $total_scout = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'software' AND p.post_status = 'publish'
         AND pm.meta_key = 'software_status' AND pm.meta_value = 'external_scout'"
    );

    // Unreviewed scout count
    $unreviewed = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'software' AND p.post_status = 'publish'
         AND pm.meta_key = 'software_status' AND pm.meta_value = 'external_scout'
         AND p.ID NOT IN (
             SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_reviewed_at'
         )"
    );

    $current_filter = $_GET['sw_source_filter'] ?? '';

    // All scout
    $current_all = $current_filter === 'scout' ? 'class="current"' : '';
    $url_all = admin_url('edit.php?post_type=software&sw_source_filter=scout');
    $views['scout'] = "<a href=\"{$url_all}\" {$current_all}>🤖 Scout <span class=\"count\">({$total_scout})</span></a>";

    // Unreviewed scout (only show if > 0)
    if ($unreviewed > 0) {
        $current_unrev = $current_filter === 'scout_unreviewed' ? 'class="current"' : '';
        $url_unrev = admin_url('edit.php?post_type=software&sw_source_filter=scout_unreviewed');
        $views['scout_unreviewed'] = "<a href=\"{$url_unrev}\" {$current_unrev} style=\"color:#b45309;\">⚠️ In moderation <span class=\"count\">({$unreviewed})</span></a>";
    }

    return $views;
}
add_filter('views_edit-software', 'softmir_software_views_scout');

/**
 * Make source column sortable
 */
function softmir_software_source_sortable($columns)
{
    $columns['sw_source'] = 'sw_source';
    return $columns;
}
add_filter('manage_edit-software_sortable_columns', 'softmir_software_source_sortable');

function softmir_software_source_orderby($query)
{
    if (!is_admin() || !$query->is_main_query())
        return;
    if ($query->get('orderby') === 'sw_source') {
        $query->set('meta_key', 'software_status');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'softmir_software_source_orderby');

/**
 * Получить logo компании с поддержкой фоллбэка через Polylang (если Media Module блокирует картинки)
 */
function softmir_get_company_logo($post_id = null)
{
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    // Пытаемся получить стандартным способом
    $logo = get_field('company_logo', $post_id);
    if (!empty($logo)) {
        return $logo;
    }

    // Если logo скрыт из-за отсутствия перевода медиа в Polylang: ищем в оригинале
    if (function_exists('pll_get_post_translations')) {
        $translations = pll_get_post_translations($post_id);
        if (!empty($translations)) {
            foreach ($translations as $lang => $trans_id) {
                if ($trans_id != $post_id) {
                    $raw_logo = get_post_meta($trans_id, 'company_logo', true);
                    if ($raw_logo) {
                        if (is_numeric($raw_logo)) {
                            $url = wp_get_attachment_url($raw_logo);
                            if ($url)
                                return $url;
                        } else {
                            return $raw_logo;
                        }
                    }
                }
            }
        }
    }

    return '';
}

// ========== Admin Tool: Sync Integrations ==========
function softmir_sync_integrations_menu()
{
    add_management_page(
        'Sync Integrations',
        '🔗 Sync Integrations',
        'manage_options',
        'softmir-sync-integrations',
        function () {
            include get_template_directory() . '/tools/sync-integrations.php';
        }
    );
}
add_action('admin_menu', 'softmir_sync_integrations_menu');

// ========== AI Prompt Templates (Single Source of Truth) ==========
require_once get_template_directory() . '/inc/prompt-templates.php';

// ========== SEO Fixes ==========

// 1. Noindex for /login/ page
add_filter('rank_math/frontend/robots', function ($robots) {
    if (is_page('login') || is_page_template('page-login.php')) {
        $robots['index'] = 'noindex';
        $robots['follow'] = 'nofollow';
    }
    return $robots;
}, 99);

// 2. OG Image fallback to company_logo for Software cards
function softmir_software_og_image($attachment_url) {
    if (is_singular('software')) {
        $logo = function_exists('softmir_get_company_logo') ? softmir_get_company_logo(get_the_ID()) : get_field('company_logo');
        if (!empty($logo)) {
            return $logo;
        }
    }
    return $attachment_url;
}
add_filter('rank_math/opengraph/facebook/image', 'softmir_software_og_image');
add_filter('rank_math/opengraph/twitter/image', 'softmir_software_og_image');

// 3. Hreflang x-default and bidirectional links via Polylang
add_filter('pll_rel_hreflang_attributes', function ($hreflangs) {
    // Ensure all available translations are present (bidirectional)
    if (function_exists('pll_the_languages')) {
        $langs = pll_the_languages(['raw' => 1]);
        if (is_array($langs)) {
            foreach ($langs as $lang) {
                if (!empty($lang['url']) && !isset($hreflangs[$lang['slug']])) {
                    $hreflangs[$lang['slug']] = $lang['url'];
                }
            }
        }
    }

    // Add x-default
    if (!empty($hreflangs) && function_exists('pll_default_language')) {
        $default_lang = pll_default_language('slug');
        if (isset($hreflangs[$default_lang])) {
            $hreflangs['x-default'] = $hreflangs[$default_lang];
        } else {
            $first = reset($hreflangs);
            if ($first) {
                $hreflangs['x-default'] = $first;
            }
        }
    }
    
    return $hreflangs;
});

// ========== Universal Platform Branding ==========
require_once get_template_directory() . '/inc/branding.php';
