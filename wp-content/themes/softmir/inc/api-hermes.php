<?php
/**
 * Hermes Agent API Endpoints
 * Provides structured data to the Hermes AI agent for contextual awareness.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('softmir/v1', '/catalog-index', [
        'methods' => 'GET',
        'callback' => 'softmir_hermes_catalog_index_endpoint',
        'permission_callback' => function (WP_REST_Request $request) {
            // Protect endpoint with AI_CONTENT_SECRET_KEY if defined
            $token = $request->get_header('x-ai-token');
            if (defined('AI_CONTENT_SECRET_KEY') && $token !== AI_CONTENT_SECRET_KEY) {
                return new WP_Error('rest_forbidden', 'Invalid API Token.', ['status' => 401]);
            }
            return true;
        }
    ]);
});

function softmir_hermes_catalog_index_endpoint(WP_REST_Request $request)
{
    $args = [
        'post_type' => 'software',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    $query = new WP_Query($args);
    $results = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // SoftZor URL
            $url = get_permalink();

            // Vendor URL
            $vendor_url = get_post_meta($post_id, 'website_url', true) ?: '';
            $is_affiliate = get_post_meta($post_id, 'is_affiliate', true);
            if ($is_affiliate && $vendor_url) {
                $vendor_url = softmir_get_software_outbound_link($post_id);
            }
            
            // Primary Category
            $primary_cat_id = get_post_meta($post_id, 'primary_category', true);
            $category = '';
            
            if ($primary_cat_id) {
                $term_to_display = get_term($primary_cat_id, 'software_category');
                if ($term_to_display && !is_wp_error($term_to_display)) {
                    $category = $term_to_display->name;
                }
            } 
            
            if (!$category) {
                $terms = get_the_terms($post_id, 'software_category');
                if ($terms && !is_wp_error($terms)) {
                    $category = $terms[0]->name;
                }
            }
            
            if ($category) {
                $results[] = [
                    'category' => $category,
                    'software' => get_the_title(),
                    'softzor_url' => $url,
                    'vendor_url' => $vendor_url
                ];
            }
        }
        wp_reset_postdata();
    }

    // Sort alphabetically by category
    usort($results, function($a, $b) {
        return strcmp($a['category'], $b['category']);
    });

    return rest_ensure_response([
        'success' => true,
        'count' => count($results),
        'data' => $results
    ]);
}
