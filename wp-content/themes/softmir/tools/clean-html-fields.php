<?php
/**
 * Очистка HTML в полях advantages/disadvantages/best_for/bad_for
 * НЕ УДАЛЯЕТ данные, а ЗАМЕНЯЕТ HTML на чистый текст
 */
require_once dirname(__FILE__, 5) . '/wp-load.php';
global $wpdb;

$fields = ['advantages', 'disadvantages', 'best_for', 'bad_for'];

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

function strip_html_to_lines($raw)
{
    if (empty($raw) || is_array($raw))
        return $raw;
    if ($raw === strip_tags($raw))
        return $raw; // нет HTML — не трогаем

    // Заменяем закрывающие блочные теги на \n
    $text = str_replace(
        ['</tr>', '</td>', '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>', '</th>', '</strong>', '</em>', '</a>', '</div>', '</span>'],
        "\n",
        $raw
    );
    $text = wp_strip_all_tags($text);
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    return implode("\n", $lines);
}

// === ОЧИСТИТЬ ВСЁ ===
if ($mode === 'fix_all') {
    echo "<h2>🔧 Очистка HTML во ВСЕХ карточках ПО...</h2>";

    $all_metas = $wpdb->get_results(
        "SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_title 
         FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
         WHERE p.post_type = 'software' 
         AND pm.meta_key IN ('advantages', 'disadvantages', 'best_for', 'bad_for')
         ORDER BY pm.post_id, pm.meta_key"
    );

    $cleaned = 0;
    foreach ($all_metas as $row) {
        if (empty($row->meta_value))
            continue;
        if ($row->meta_value === strip_tags($row->meta_value))
            continue; // чисто — пропускаем

        $clean = strip_html_to_lines($row->meta_value);
        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => $clean],
            ['meta_id' => $row->meta_id]
        );
        echo "<p>✅ <strong>{$row->post_title}</strong> (ID:{$row->post_id}) → <code>{$row->meta_key}</code> очищено</p>";
        $cleaned++;
    }

    wp_cache_flush();
    echo "<hr><p style='color:green; font-size:1.2em;'><strong>✅ Очищено {$cleaned} полей</strong></p>";
    echo "<p><a href='?'>← Назад</a></p>";
    exit;
}

// === ОЧИСТИТЬ ОДИН ПОСТ ===
if ($mode === 'fix' && $post_id > 0) {
    $title = get_the_title($post_id);
    echo "<h2>🔧 Очистка поста: {$title} (ID: {$post_id})</h2>";
    $cleaned = 0;
    foreach ($fields as $f) {
        $raw = get_post_meta($post_id, $f, true);
        if (empty($raw) || $raw === strip_tags($raw)) {
            echo "<p>⏭️ <code>{$f}</code> — чисто или пусто</p>";
            continue;
        }
        $clean = strip_html_to_lines($raw);
        update_post_meta($post_id, $f, $clean);
        echo "<p>✅ <code>{$f}</code> — HTML очищен (" . count(explode("\n", $clean)) . " строк)</p>";
        $cleaned++;
    }
    wp_cache_delete($post_id, 'post_meta');
    echo "<hr><p style='color:green;'><strong>✅ Очищено {$cleaned} полей</strong></p>";
    echo "<p><a href='?'>← Назад</a></p>";
    exit;
}

// === СПИСОК ПОСТОВ ===
echo "<h2>📋 Карточки ПО — статус полей</h2>";
echo "<p><a href='?mode=fix_all' style='background:red; color:white; padding:12px 24px; text-decoration:none; border-radius:8px; font-size:1.1em;'>🧹 ОЧИСТИТЬ HTML ВО ВСЕХ КАРТОЧКАХ</a></p>";

$posts = get_posts(['post_type' => 'software', 'posts_per_page' => -1, 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC']);

echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse; font-size:13px;'>";
echo "<tr style='background:#f0f0f0;'><th>ID</th><th>Название</th><th>advantages</th><th>disadvantages</th><th>best_for</th><th>bad_for</th><th>Действия</th></tr>";

foreach ($posts as $p) {
    $row_has_html = false;
    $cells = '';
    foreach ($fields as $f) {
        $val = get_post_meta($p->ID, $f, true);
        if (empty($val)) {
            $cells .= "<td>—</td>";
        } elseif ($val !== strip_tags($val)) {
            $cells .= "<td style='background:#ffe0e0;'>⚠️ HTML (" . mb_strlen($val) . ")</td>";
            $row_has_html = true;
        } else {
            $lines = count(array_filter(explode("\n", $val)));
            $cells .= "<td style='background:#e8f5e9;'>✅ {$lines} строк</td>";
        }
    }
    $bg = $row_has_html ? "background:#fff5f5;" : "";
    echo "<tr style='{$bg}'>";
    echo "<td>{$p->ID}</td><td><strong>{$p->post_title}</strong></td>";
    echo $cells;
    echo "<td><a href='?mode=fix&post_id={$p->ID}' style='color:red;'>Очистить</a></td>";
    echo "</tr>";
}
echo "</table>";
