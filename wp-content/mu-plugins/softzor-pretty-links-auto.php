<?php
/**
 * Plugin Name: SoftZor Pretty Links Auto-Generator
 * Description: Automatically generates Pretty Links for software cards upon save.
 * Version: 1.0
 * Author: SoftMir Dev
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook into ACF save_post to automatically generate a Pretty Link.
 * Runs after ACF fields are saved (priority 20).
 */
add_action('acf/save_post', 'softzor_auto_generate_pretty_link', 20);
function softzor_auto_generate_pretty_link($post_id) {
    // Ensure it is not a revision and is the 'software' post type
    if (get_post_type($post_id) !== 'software' || wp_is_post_revision($post_id)) {
        return;
    }

    // Get original link.
    // If partner_link is empty, fallback to website_url
    $url = get_field('partner_link', $post_id);
    if (empty($url)) {
        $url = get_field('website_url', $post_id);
    }
    if (empty($url)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'prli_links';
    $post = get_post($post_id);
    
    // Try to get software name from Rank Math Focus Keyword
    $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
    if (!empty($focus_keyword)) {
        // If comma-separated, take the first one
        $focus_words = explode(',', $focus_keyword);
        $default_slug = sanitize_title(trim($focus_words[0]));
    } else {
        // Fallback: use post slug, removing suffixes like -review
        $default_slug = preg_replace('/-(review|obzor|oglyad)$/i', '', $post->post_name);
    }

    // Protection: check if Pretty Links table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return;
    }

    // Find link by ID first (if we already created it)
    $prli_id = get_post_meta($post_id, '_prli_id', true);
    $existing_link = null;

    if ($prli_id) {
        $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $prli_id));
    }

    // If not found by ID, look by standard slug
    if (!$existing_link) {
        $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE slug = %s", $default_slug));
        if ($existing_link) {
            update_post_meta($post_id, '_prli_id', $existing_link->id);
        }
    }

    $link_name = 'Affiliate: ' . $post->post_title;
    $actual_slug = $default_slug;

    if (!$existing_link) {
        // Link doesn't exist -> Create (INSERT)
        $wpdb->insert(
            $table_name,
            array(
                'slug' => $default_slug,
                'url' => $url,
                'name' => $link_name,
                'redirect_type' => '307',
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        $new_id = $wpdb->insert_id;
        update_post_meta($post_id, '_prli_id', $new_id);
    } else {
        $actual_slug = $existing_link->slug; // Use the current slug from DB
        
        // Link exists -> Update URL and Name if changed (UPDATE)
        if ($existing_link->url !== $url || $existing_link->name !== $link_name) {
            $wpdb->update(
                $table_name,
                array(
                    'url' => $url,
                    'name' => $link_name,
                ),
                array('id' => $existing_link->id), // Update strictly by ID
                array('%s', '%s'),
                array('%d')
            );
        }
    }

    // Form the final URL exactly like Pretty Links plugin (without /go/)
    $pretty_url = home_url('/' . $actual_slug . '/');
    
    // Temporarily remove hook to prevent infinite loop
    remove_action('acf/save_post', 'softzor_auto_generate_pretty_link', 20);
    
    // Update the pretty_link ACF field
    update_field('pretty_link', $pretty_url, $post_id);
    
    // Restore hook
    add_action('acf/save_post', 'softzor_auto_generate_pretty_link', 20);
}
