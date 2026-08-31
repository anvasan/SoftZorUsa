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
        // Since json_decode fails due to stripped slashes for double quotes in descriptions,
        // we'll use regex to fix the "icon" field where it has non-ascii characters.

        $new_json = preg_replace_callback('/"icon":"([^"]+)"/u', function ($matches) {
            $icon_val = $matches[1];
            // If the icon is not a standard lowercase ascii word with underscores...
            if (preg_match('/[^a-z_]/', $icon_val)) {
                return '"icon":"check_circle"';
            }
            return $matches[0];
        }, $scenarios_json);

        if ($new_json !== $scenarios_json) {
            // Update the post meta directly to avoid ACF slashing rules messing up again
            update_post_meta($post->ID, 'scenarios', wp_slash($new_json));
            echo "Fixed icons for Post ID: {$post->ID}\n";
            $count++;
        }
    }
}

echo "Done. Fixed $count posts.\n";
