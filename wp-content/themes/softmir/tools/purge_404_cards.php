<?php
/**
 * Script to purge hallucinated/404 software cards.
 * Changes the post_status from 'publish' to 'draft' if the content contains specific error markers.
 */
define('ABSPATH', 'd:/laragon/www/SoftZor/');
require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

global $wpdb;

$markers = [
    'Продукт не найден',
    'Ссылка ведет в никуда',
    'ошибка 404',
    'Страница не найдена',
    'К сожалению',
    'не удалось найти информацию',
    'по ссылке пустота'
];

$sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'software' AND post_status = 'publish'";
$posts = $wpdb->get_results($sql);

$drafted = 0;

foreach ($posts as $post) {
    $content = $post->post_content;
    $found_marker = false;

    foreach ($markers as $marker) {
        if (stripos($content, $marker) !== false) {
            $found_marker = $marker;
            break;
        }
    }

    // Additional check for short description if needed, but the main hallucination is in post_content
    if ($found_marker) {
        echo "Drafting ID {$post->ID} ({$post->post_title})\n";
        echo "Reason: Found marker '{$found_marker}'\n";

        $post_update = [
            'ID' => $post->ID,
            'post_status' => 'draft'
        ];
        wp_update_post($post_update);
        $drafted++;
        echo "-----\n";
    }
}

echo "Done. Drafted {$drafted} hallucinated cards.\n";
