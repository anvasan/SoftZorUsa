<?php
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options'))
    wp_die('Access denied');

$post_id = 458; // Ringostat
$lang = 'uk';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "Debugging translation for Post ID: $post_id to $lang\n";

if (!function_exists('pll_get_post')) {
    echo "ERROR: Polylang functions not found!\n";
    die();
}

echo "Current Default Lang: " . pll_default_language() . "\n";
echo "Available Langs: " . implode(', ', pll_languages_list()) . "\n";

$result = softmir_translate_post($post_id, $lang);

if (is_wp_error($result)) {
    echo "RESULT: WP_Error\n";
    echo "Message: " . $result->get_error_message() . "\n";
    echo "Code: " . $result->get_error_code() . "\n";
    $data = $result->get_error_data();
    if ($data) {
        echo "Data: " . print_r($data, true) . "\n";
    }
} else {
    echo "RESULT: Success!\n";
    print_r($result);
}
echo "</pre>";
