<?php
/**
 * Sync Integrations: post_meta['integrations'] <-> _sw_attr_{id}
 * Run via: /wp-admin/admin.php?page=softmir-sync-integrations
 * Or via WP-CLI: wp eval-file wp-content/themes/softmir/tools/sync-integrations.php
 */

if (!defined('ABSPATH')) {
    // Allow running via WP-CLI
    if (php_sapi_name() === 'cli') {
        require_once dirname(__DIR__, 4) . '/wp-load.php';
    } else {
        exit('Direct access not allowed');
    }
}

// Only admins
if (!current_user_can('manage_options') && php_sapi_name() !== 'cli') {
    wp_die('Access denied');
}

// Find the sw_attribute post for "Интеграции"
global $wpdb;
$attr_id = $wpdb->get_var(
    "SELECT ID FROM {$wpdb->posts} 
     WHERE post_type = 'sw_attribute' 
     AND post_status = 'publish' 
     AND (post_title LIKE '%нтеграц%' OR post_title LIKE '%ntegration%')
     LIMIT 1"
);

echo "<h2>Sync Integrations Tool</h2>";

if (!$attr_id) {
    echo "<p style='color:red'>❌ Attribute 'Интеграции' (sw_attribute) not found in database!</p>";
    echo "<p>Available sw_attribute posts:</p><ul>";
    $all_attrs = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'sw_attribute' AND post_status = 'publish'");
    foreach ($all_attrs as $a) {
        echo "<li>ID {$a->ID}: {$a->post_title}</li>";
    }
    echo "</ul>";
    exit;
}

echo "<p>✅ Found attribute 'Интеграции': ID = <strong>{$attr_id}</strong>, meta key = <code>_sw_attr_{$attr_id}</code></p>";

// Get all software posts
$software_posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'lang' => '', // Ignore Polylang
]);

echo "<p>Total software posts: <strong>" . count($software_posts) . "</strong></p>";
echo "<hr><h3>Results:</h3><table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Post ID</th><th>Title</th><th>post_meta['integrations']</th><th>_sw_attr_{$attr_id}</th><th>Action</th></tr>";

$synced = 0;
$skipped = 0;
$errors = 0;

foreach ($software_posts as $pid) {
    $title = get_the_title($pid);
    $meta_integrations = get_post_meta($pid, 'integrations', true);
    $attr_integrations = get_post_meta($pid, '_sw_attr_' . $attr_id, true);

    // Normalize: if either is an array, convert to string
    if (is_array($meta_integrations)) {
        $meta_integrations = implode(', ', $meta_integrations);
        update_post_meta($pid, 'integrations', $meta_integrations);
    }
    if (is_array($attr_integrations)) {
        $attr_integrations_display = 'Array (broken)';
    } else {
        $attr_integrations_display = $attr_integrations ?: '(empty)';
    }

    $action = '—';

    // Case 1: post_meta has data, attr doesn't (or has Array)
    if (!empty($meta_integrations) && (empty($attr_integrations) || is_array($attr_integrations))) {
        update_post_meta($pid, '_sw_attr_' . $attr_id, $meta_integrations);
        $action = "✅ Synced meta → attr";
        $synced++;
    }
    // Case 2: attr has data, post_meta doesn't
    elseif (empty($meta_integrations) && !empty($attr_integrations) && !is_array($attr_integrations)) {
        update_post_meta($pid, 'integrations', $attr_integrations);
        $action = "✅ Synced attr → meta";
        $synced++;
    }
    // Case 3: Both have data (and are consistent)
    elseif (!empty($meta_integrations) && !empty($attr_integrations) && !is_array($attr_integrations)) {
        $action = "⏭ Both filled";
        $skipped++;
    }
    // Case 4: Neither has data
    else {
        $action = "⏭ No data";
        $skipped++;
    }

    $meta_display = $meta_integrations ?: '(empty)';

    echo "<tr><td>{$pid}</td><td>{$title}</td><td>" . esc_html(mb_substr($meta_display, 0, 60)) . "</td><td>" . esc_html(mb_substr($attr_integrations_display, 0, 60)) . "</td><td>{$action}</td></tr>";
}

echo "</table>";
echo "<hr><p><strong>Done!</strong> Synced: {$synced} | Skipped: {$skipped}</p>";
