<?php
/**
 * SoftZor Affiliate Cloaking Radar
 * Masks partner URLs and tracks outbound clicks.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Add rewrite rule for /go/software-slug/
add_action('init', function () {
    add_rewrite_rule('^go/([^/]*)/?', 'index.php?sz_go=$matches[1]', 'top');
});

// 2. Register query var
add_filter('query_vars', function ($vars) {
    $vars[] = 'sz_go';
    return $vars;
});

// 3. Catch the redirect before template loads
add_action('template_redirect', function () {
    $go_slug = get_query_var('sz_go');

    if (!empty($go_slug)) {
        // Find the software by slug
        $args = [
            'name' => $go_slug,
            'post_type' => 'software',
            'post_status' => 'publish',
            'posts_per_page' => 1
        ];

        $posts = get_posts($args);

        if ($posts) {
            $post_id = $posts[0]->ID;
            $website_url = get_field('website_url', $post_id);

            if ($website_url) {
                // Track outbound click for Analytics Dashboard
                $current_clicks = intval(get_post_meta($post_id, '_sz_click_count', true));
                update_post_meta($post_id, '_sz_click_count', $current_clicks + 1);

                // Log click with timestamp for analytics graphs
                if (function_exists('softmir_log_click')) {
                    softmir_log_click($post_id);
                }

                // Perform the redirect
                wp_redirect($website_url, 301);
                exit;
            }
        }

        // If not found or no URL, fallback to home
        wp_redirect(home_url(), 302);
        exit;
    }
});

/**
 * Get outbound link for software card.
 * Tries to get new Pretty Link first. Falls back to old /go/ format if not found.
 */
function softmir_get_software_outbound_link($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $pretty_link = get_post_meta($post_id, 'pretty_link', true);
    if (!empty($pretty_link)) {
        return $pretty_link;
    }
    
    // Fallback to old logic
    return home_url('/go/' . get_post_field('post_name', $post_id) . '/');
}
