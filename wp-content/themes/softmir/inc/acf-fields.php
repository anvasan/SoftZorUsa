<?php
/**
 * SoftMir — ACF Field Groups
 * Extracted from functions.php for maintainability
 */

if (!defined('ABSPATH'))
    exit;

function softmir_acf_fields()
{
    if (!function_exists('acf_add_local_field_group'))
        return;

    // Software Top Fields (Logo, Primary Category)
    acf_add_local_field_group([
        'key' => 'group_software_top',
        'title' => 'Basic Information (Logo and Category)',
        'fields' => [
            ['key' => 'field_sw_logo', 'label' => 'Company logo', 'name' => 'company_logo', 'type' => 'image', 'return_format' => 'url', 'preview_size' => 'medium', 'instructions' => 'Recommended size 300x150'],
            ['key' => 'field_sw_primary_cat', 'label' => 'Main category', 'name' => 'primary_category', 'type' => 'taxonomy', 'taxonomy' => 'software_category', 'field_type' => 'select', 'allow_null' => 1, 'add_term' => 0, 'save_terms' => 0, 'load_terms' => 0, 'return_format' => 'id', 'multiple' => 0, 'instructions' => 'Select a main category for breadcrumbs and key feature pull-ups.'],
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
            ['key' => 'field_sw_short_desc', 'label' => 'Brief description', 'name' => 'short_description', 'type' => 'textarea', 'rows' => 3],
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
        'title' => 'Integrator details',
        'fields' => [
            ['key' => 'field_int_logo', 'label' => 'Logo', 'name' => 'integrator_logo', 'type' => 'image', 'return_format' => 'url'],
            ['key' => 'field_int_website', 'label' => 'Website', 'name' => 'integrator_website', 'type' => 'url'],
            ['key' => 'field_int_short_desc', 'label' => 'Brief description', 'name' => 'integrator_short_desc', 'type' => 'textarea', 'rows' => 3],
        ],
        'location' => [
            [['param' => 'post_type', 'operator' => '==', 'value' => 'integrator']],
        ],
    ]);
}
add_action('acf/init', 'softmir_acf_fields');
