<?php
/**
 * Tool: Mark all existing Scout cards as reviewed.
 * 
 * Usage: Open in browser as admin:
 *   https://your-site.com/wp-content/themes/softmir/mark-all-scout-reviewed.php?confirm=yes
 */

// Load WordPress
require_once dirname(__DIR__, 3) . '/wp-load.php';

// Security: admin only
if (!current_user_can('manage_options')) {
    wp_die('Доступ запрещен. Войдите как адminистратор.');
}

// Safety check
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// Find all unreviewed scout cards
global $wpdb;

$unreviewed = $wpdb->get_results("
    SELECT p.ID, p.post_title
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'software'
      AND p.post_status = 'publish'
      AND pm.meta_key = 'software_status'
      AND pm.meta_value = 'external_scout'
      AND p.ID NOT IN (
          SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_reviewed_at'
      )
    ORDER BY p.post_date DESC
");

$count = count($unreviewed);

if (!$confirm) {
    // Show preview
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Mark Scout Reviewed</title>";
    echo "<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px}";
    echo "table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px;text-align:left}";
    echo "th{background:#f5f5f5}.btn{background:#2271b1;color:#fff;padding:12px 24px;border:none;border-radius:4px;font-size:16px;cursor:pointer;text-decoration:none;display:inline-block}.btn:hover{background:#135e96}</style>";
    echo "</head><body>";
    echo "<h1>🤖 Bulk marking Scout cards as checked</h1>";
    echo "<p>Found <strong>{$count}</strong> непроверенных карточек Scoutа:</p>";

    if ($count === 0) {
        echo "<p style='color:green;font-size:18px'>✅ Все карточки Scoutа уже помечены как проверенные! Делать нечего.</p>";
    } else {
        echo "<table><tr><th>ID</th><th>Name</th></tr>";
        foreach ($unreviewed as $post) {
            echo "<tr><td>{$post->ID}</td><td>" . esc_html($post->post_title) . "</td></tr>";
        }
        echo "</table>";
        echo "<br><a class='btn' href='?confirm=yes' onclick=\"return confirm('Пометить все {$count} карточек как проверенных?')\">✅ Mark all as checked</a>";
    }

    echo "</body></html>";
    exit;
}

// Execute bulk update
$updated = 0;
$now = current_time('mysql');
$user_id = get_current_user_id();

foreach ($unreviewed as $post) {
    update_post_meta($post->ID, '_reviewed_at', $now);
    update_post_meta($post->ID, '_reviewed_by', $user_id);
    $updated++;
}

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Done</title>";
echo "<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px}</style>";
echo "</head><body>";
echo "<h1>✅ Done!</h1>";
echo "<p>Marked as checked: <strong>{$updated}</strong> cards.</p>";
echo "<p>Now these cards:</p>";
echo "<ul>";
echo "<li>Will be shown as <code>🤖 Scout ✅</code> в адminке</li>";
echo "<li>Will be added to XML sitemap</li>";
echo "<li>Will be available for indexing by search engines</li>";
echo "</ul>";
echo "<p><a href='" . admin_url('edit.php?post_type=software') . "'>← Вернуться в Catalog Software</a></p>";
echo "</body></html>";
