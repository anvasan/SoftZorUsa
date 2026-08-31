<?php
/**
 * Universal AI Blog Receiver API
 * Enables external AI agents (like the SEOtext factory) to push drafted articles directly into WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('ai-content/v1', '/publish', [
        'methods' => 'POST',
        'callback' => 'ai_blog_receiver_endpoint',
        'permission_callback' => function (WP_REST_Request $request) {
            // Very simple auth check for local scripts. In production, use strong tokens.
            $token = $request->get_header('x-ai-token');
            if (defined('AI_CONTENT_SECRET_KEY') && $token !== AI_CONTENT_SECRET_KEY) {
                // If token is defined but mismatched, reject
                return new WP_Error('rest_forbidden', 'Invalid API Token.', ['status' => 401]);
            }
            return true; // Local/Dev environment or token matched
        }
    ]);
});

function ai_blog_receiver_endpoint(WP_REST_Request $request)
{
    $params = $request->get_json_params();

    if (empty($params['title']) || empty($params['content'])) {
        return new WP_Error('missing_data', 'Title and Content are required fields.', ['status' => 400]);
    }

    $post_data = [
        'post_title' => sanitize_text_field($params['title']),
        'post_content' => wp_kses_post($params['content']),
        'post_status' => 'draft', // Always push to draft for final human review
        'post_type' => 'post',
    ];

    $post_id = wp_insert_post($post_data, true);

    if (is_wp_error($post_id)) {
        return new WP_Error('db_error', 'Failed to create post: ' . $post_id->get_error_message(), ['status' => 500]);
    }

    // Attach SEO tags or excerpt if provided
    if (!empty($params['excerpt'])) {
        wp_update_post(['ID' => $post_id, 'post_excerpt' => sanitize_textarea_field($params['excerpt'])]);
    }

    // Attach Meta fields for RankMath / Yoast SEO if provided
    if (!empty($params['seo_title'])) {
        update_post_meta($post_id, 'rank_math_title', sanitize_text_field($params['seo_title']));
        update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($params['seo_title']));
    }

    if (!empty($params['seo_description'])) {
        update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($params['seo_description']));
        update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($params['seo_description']));
    }

    return rest_ensure_response([
        'success' => true,
        'post_id' => $post_id,
        'message' => 'Article successfully drafted in WordPress.'
    ]);
}
