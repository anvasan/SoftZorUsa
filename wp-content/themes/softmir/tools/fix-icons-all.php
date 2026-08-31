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
                // If the icon is not a standard lowercase snake_case word, it's probably bad data from migration
                if (isset($scenario['icon']) && preg_match('/[^a-z_]/', $scenario['icon'])) {
                    $scenario['icon'] = 'check_circle';
                    $updated = true;
                }
            }
            // re-assign the reference
            unset($scenario);

            if ($updated) {
                update_field('scenarios', wp_json_encode($scenarios, JSON_UNESCAPED_UNICODE), $post->ID);
                echo "Fixed icons for Post ID: {$post->ID}\n";
                $count++;
            }
        }
    }
}

echo "Done. Fixed $count posts.\n";
