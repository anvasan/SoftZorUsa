<?php
require 'd:/laragon/www/SoftZor/wp-load.php';
$posts = get_posts(['post_type' => 'software', 'post__in' => [1117, 1116]]);
foreach ($posts as $p) {
    echo "ID: " . $p->ID . "\n";
    var_dump(get_post_meta($p->ID, 'scenarios', true));
    echo "\n";
}
