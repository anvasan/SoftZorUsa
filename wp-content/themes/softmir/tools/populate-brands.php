<?php
/**
 * Скрипт для заполнения поля software_brand у существующих карточек ПО.
 * Запускается один раз через wp eval-file.
 */
if (php_sapi_name() !== 'cli') {
    die("Only CLI execution is allowed");
}

require_once dirname(__FILE__) . '/../../../../wp-load.php';

echo "Start processing software items...\n";

$query = new WP_Query([
    'post_type' => 'software',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids'
]);

$count = 0;
$updated = 0;

foreach ($query->posts as $post_id) {
    $count++;
    $title = get_the_title($post_id);

    // Чистим бренд
    $clean_brand = trim(preg_split('/[:\-]/', $title)[0]);

    if (!empty($clean_brand)) {
        update_post_meta($post_id, 'software_brand', $clean_brand);
        $updated++;
        echo "[$count] Updated $post_id: $title  -> BRAND: $clean_brand\n";
    }
}

echo "\nDone! Total items: $count. Updated: $updated.\n";
