<?php
/**
 * Programmatic ACF Fields Registration
 * Ensures fields always exist even if ACF JSON / DB is out of sync.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    // Software Main Fields Group
    acf_add_local_field_group([
        'key' => 'group_software',
        'title' => 'Software Specifications',
        'fields' => [
            ['key' => 'field_sw_verdict', 'label' => 'Verdict', 'name' => 'verdict', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'Editorial verdict on the software'],
            ['key' => 'field_sw_short_desc', 'label' => 'Short Description', 'name' => 'short_description', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Short summary for cards and snippets (max 200 chars)'],
            ['key' => 'field_sw_full_desc', 'label' => 'Full Description', 'name' => 'full_description', 'type' => 'wysiwyg', 'instructions' => 'Comprehensive review / overview of the software'],
            ['key' => 'field_sw_advantages', 'label' => 'Pros', 'name' => 'advantages', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'Key pros, one per line'],
            ['key' => 'field_sw_disadvantages', 'label' => 'Cons', 'name' => 'disadvantages', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'Key cons, one per line'],
            ['key' => 'field_sw_best_for', 'label' => 'Best For', 'name' => 'best_for', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Target audience, one per line'],
            ['key' => 'field_sw_bad_for', 'label' => 'Bad For', 'name' => 'bad_for', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Who should avoid it, one per line'],
            ['key' => 'field_sw_features', 'label' => 'Key Features', 'name' => 'features', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'List of core features, one per line'],
            ['key' => 'field_sw_integrations', 'label' => 'Integrations', 'name' => 'integrations', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Integrated platforms, one per line'],
            ['key' => 'field_sw_tech_specs', 'label' => 'Technical Specs', 'name' => 'tech_specs', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Deployment, API, compliance details'],
            ['key' => 'field_sw_origin', 'label' => 'Country of Origin', 'name' => 'origin', 'type' => 'text', 'instructions' => 'e.g. US, DE, UK'],
            ['key' => 'field_sw_logo', 'label' => 'Logo', 'name' => 'logo', 'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_sw_screen_1', 'label' => 'Screenshot 1', 'name' => 'screenshot_1', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_2', 'label' => 'Screenshot 2', 'name' => 'screenshot_2', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_3', 'label' => 'Screenshot 3', 'name' => 'screenshot_3', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_screen_4', 'label' => 'Screenshot 4', 'name' => 'screenshot_4', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'thumbnail', 'wrapper' => ['width' => '25']],
            ['key' => 'field_sw_price_summary', 'label' => 'Price (displayed in card)', 'name' => 'price_summary', 'type' => 'text', 'maxlength' => 60, 'instructions' => 'Max. 60 characters. For example: From $19/month'],
            ['key' => 'field_sw_featured', 'label' => 'Recommended (TOP)', 'name' => 'is_featured', 'type' => 'true_false', 'ui' => 1],
            ['key' => 'field_sw_pinned', 'label' => 'Pin to main page', 'name' => 'is_pinned', 'type' => 'true_false', 'ui' => 1],
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
        'title' => 'Links and Media',
        'fields' => [
            ['key' => 'field_sw_website', 'label' => 'Website', 'name' => 'website_url', 'type' => 'url'],
            ['key' => 'field_sw_partner_cabinet', 'label' => 'Партнёрский кабинет', 'name' => 'partner_cabinet', 'type' => 'text', 'instructions' => 'Ссылка на партнёрский кабинет или заметка (например: URL дашборда, «в программе», «письма раз в месяц»).'],
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
});
