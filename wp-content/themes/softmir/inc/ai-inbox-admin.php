<?php
/**
 * SoftMir — AI Inbox & Moderation System
 * Handles the display of moderation tools for AI-suggested categories.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Add a column to the Software list
add_filter('manage_software_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        if ($key === 'date') {
            $new_columns['ai_suggestion'] = 'AI proposal';
        }
        $new_columns[$key] = $title;
    }
    return $new_columns;
});

// 2. Display the value in the column
add_action('manage_software_posts_custom_column', function ($column, $post_id) {
    if ($column === 'ai_suggestion') {
        $suggested = get_field('ai_suggested_category', $post_id) ?: get_post_meta($post_id, 'ai_suggested_category', true);
        if ($suggested) {
            echo '<strong style="color:#d63638;">' . esc_html($suggested) . '</strong>';
        } else {
            echo '<span style="color:#a7aaad;">—</span>';
        }
    }
}, 10, 2);

// 3. Add a shortcut link (sub-menu) above the table to display all entries that require moderation
add_action('views_edit-software', function ($views) {
    global $wpdb;
    // Count how many posts have a non-empty ai_suggested_category
    $count = $wpdb->get_var("SELECT COUNT(post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = 'ai_suggested_category' AND pm.meta_value != '' AND p.post_status != 'trash'");

    if ($count > 0) {
        $class = (isset($_GET['ai_moderation']) && $_GET['ai_moderation'] === '1') ? 'current' : '';
        $url = admin_url('edit.php?post_type=software&ai_moderation=1');
        $views['ai_moderation'] = "<a href='{$url}' class='{$class}'>In moderation (AI Suggestion) <span class='count'>({$count})</span></a>";
    }
    return $views;
});

// 4. We implement filtering using our shortcut
add_filter('parse_query', function ($query) {
    global $pagenow;
    if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'software') {
        if (isset($_GET['ai_moderation']) && $_GET['ai_moderation'] === '1') {
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key' => 'ai_suggested_category',
                'value' => '',
                'compare' => '!='
            ];
            $query->set('meta_query', $meta_query);
        }
    }
});
