<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

$post_id = $argv[1] ?? 0;
if (!$post_id)
    die("Usage: php inspect-meta.php <post_id>\n");

$meta = get_post_custom($post_id);
ksort($meta);

echo "=== Meta for Post #$post_id (" . get_the_title($post_id) . ") ===\n";
foreach ($meta as $key => $values) {
    echo "$key: ";
    $val = $values[0];
    if (is_array($val)) {
        print_r($val);
    } elseif (is_serialized($val)) {
        $data = maybe_unserialize($val);
        if (is_array($data)) {
            print_r($data);
        } else {
            echo $data . "\n";
        }
    } else {
        echo "$val\n";
    }
}
