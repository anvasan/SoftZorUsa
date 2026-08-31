<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

$posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);

$count = 0;

foreach ($posts as $post) {
    $scenarios_json = get_post_meta($post->ID, 'scenarios', true);
    if (!empty($scenarios_json)) {
        $scenarios = json_decode($scenarios_json, true);
        if (is_array($scenarios)) {
            $updated = false;
            foreach ($scenarios as &$scenario) {
                // If the icon is longer than a typical material symbol name (e.g., > 30 chars or contains spaces)
                // we reset it to a default icon.
                if (isset($scenario['icon']) && (mb_strlen($scenario['icon']) > 30 || strpos($scenario['icon'], ' ') !== false || preg_match('/[А-Яа-яЄєІіЇїҐґ]/u', $scenario['icon']))) {
                    // Extract emoji if present in the title and maybe use it? Material symbols don't support emojis.
                    $scenario['icon'] = 'check_circle';
                    $updated = true;
                }
            }
            if ($updated) {
                update_field('scenarios', wp_json_encode($scenarios, JSON_UNESCAPED_UNICODE), $post->ID);
                echo "Fixed icons for Post ID: {$post->ID}\n";
                $count++;
            }
        }
    }
}

echo "Done. Fixed $count posts.\n";
