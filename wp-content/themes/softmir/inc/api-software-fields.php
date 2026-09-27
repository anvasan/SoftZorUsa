<?php
/**
 * REST API Exposure for Software CPT
 * Allows the AI Enricher to read and write meta fields natively at the root level of the REST response.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    $fields = [
        'short_description'      => ['type' => 'string'],
        'full_description'       => ['type' => 'string'],
        'advantages'             => ['type' => 'array'],
        'disadvantages'          => ['type' => 'array'],
        'best_for'               => ['type' => 'array'],
        'bad_for'                => ['type' => 'array'],
        'scenarios'              => ['type' => 'array'],
        'features'               => ['type' => 'array'],
        'tech_specs'             => ['type' => 'string'],
        'category_key_functions' => ['type' => 'array'],
        'custom_features'        => ['type' => 'array'],
        'integrations'           => ['type' => 'array'],
        'price_summary'          => ['type' => 'string'],
        'pricing_list'           => ['type' => 'array'],
        'external_reviews'       => ['type' => 'array'],
        'focus_keyword'          => ['type' => 'string'],
        'rank_math_title'        => ['type' => 'string'],
        'rank_math_description'  => ['type' => 'string'],
        'rank_math_permalink'    => ['type' => 'string'],
        'is_referral'            => ['type' => 'boolean'],
        'origin'                 => ['type' => 'string'],
        'vendor_url'             => ['type' => 'string'],
        'price_updated_date'     => ['type' => 'string'],
        'works_in_ukraine'       => ['type' => 'boolean'],
        'partner_link'           => ['type' => 'string'],
        'pretty_link'            => ['type' => 'string'],
    ];

    foreach ($fields as $field => $schema) {
        register_rest_field('software', $field, [
            'get_callback' => function ($post) use ($field, $schema) {
                $val = get_post_meta($post['id'], $field, true);
                if ($schema['type'] === 'boolean') {
                    return (bool)$val;
                }
                if ($schema['type'] === 'array' && is_string($val)) {
                    $decoded = json_decode($val, true);
                    return is_array($decoded) ? $decoded : [];
                }
                return $val;
            },
            'update_callback' => function ($value, $post) use ($field, $schema) {
                if (!current_user_can('edit_post', $post->ID)) {
                    return new WP_Error('rest_forbidden', 'No rights', ['status' => 403]);
                }

                // If it's an array/object, WordPress/ACF usually handles it as serialized array
                // but if we receive a JSON string for an array field, decode it.
                if ($schema['type'] === 'array' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    $value = is_array($decoded) ? $decoded : [];
                }
                
                // For boolean
                if ($schema['type'] === 'boolean') {
                    $value = (bool)$value ? '1' : '0';
                }

                update_post_meta($post->ID, $field, $value);
                return true;
            },
            'schema' => [
                'type' => $schema['type'],
                'description' => 'Meta field: ' . $field,
                'context' => ['view', 'edit']
            ]
        ]);
    }
});

require_once __DIR__ . '/prompt-templates.php';
