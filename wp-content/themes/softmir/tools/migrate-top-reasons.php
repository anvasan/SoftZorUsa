<?php
/**
 * Миграция данных из старого сломанного поля 'advantages' в новое 'top_reasons'.
 * И очистка HTML на лету.
 */
require_once dirname(__FILE__, 5) . '/wp-load.php';
global $wpdb;

echo "<h2>🚀 Миграция 'Плюсов' в новое поле top_reasons...</h2>";

function softmir_clean_html_lines($raw)
{
    if (empty($raw) || is_array($raw))
        return $raw;
    $text = str_replace(
        ['</tr>', '</td>', '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>', '</th>', '</strong>', '</em>', '</a>', '</div>', '</span>'],
        "\n",
        $raw
    );
    $text = wp_strip_all_tags($text);
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    return implode("\n", $lines);
}

$posts = get_posts(['post_type' => 'software', 'posts_per_page' => -1, 'post_status' => 'any']);
$migrated = 0;

foreach ($posts as $p) {
    // 1. Берем старое значение
    $old_val = get_post_meta($p->ID, 'advantages', true);

    // 2. Если пусто — берём новое (может уже заполнили руками)
    if (empty($old_val)) {
        continue;
    }

    // 3. Очищаем HTML
    $clean_val = softmir_clean_html_lines($old_val);

    // 4. Записываем в НОВОЕ поле
    update_post_meta($p->ID, 'top_reasons', $clean_val);
    update_post_meta($p->ID, '_top_reasons', 'field_sw_top_reasons'); // Связь с ACF

    // 5. Удаляем старое поле полностью, чтобы больше не мозолило глаза
    delete_post_meta($p->ID, 'advantages');
    delete_post_meta($p->ID, '_advantages');

    echo "<p>✅ <strong>{$p->post_title}</strong> — перенесено в top_reasons и очищено.</p>";
    $migrated++;
}

wp_cache_flush();

echo "<hr><p style='color:green; font-size:1.2em;'><strong>✅ Готово! Мигрировано {$migrated} постов.</strong></p>";
echo "<p>Старое поле (advantages) удалено из базы. Теперь всё работает через <code>top_reasons</code>.</p>";
