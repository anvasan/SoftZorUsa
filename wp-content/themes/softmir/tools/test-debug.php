<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<pre style="font-family:monospace;font-size:14px;">';

echo "Step 1: Loading WP...\n";
flush();
require_once(dirname(__FILE__) . '/../../../../wp-load.php');
echo "Step 2: WP loaded\n";
flush();

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}
echo "Step 3: Admin OK\n";
flush();

set_time_limit(600);
ini_set('memory_limit', '512M');

$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo "Default: {$default_lang}\n";
echo "Targets: " . implode(', ', $target_langs) . "\n";
flush();

$terms = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => false,
    'lang' => '',
]);
echo "Step 4: Total terms: " . count($terms) . "\n";
flush();

$source_terms = [];
foreach ($terms as $term) {
    $lang = pll_get_term_language($term->term_id);
    if (!$lang || $lang === $default_lang) {
        $source_terms[] = $term;
    }
}
echo "Step 5: Source terms: " . count($source_terms) . "\n";
flush();

echo "Step 6: Checking softmir_translate_term exists: " . (function_exists('softmir_translate_term') ? 'YES' : 'NO') . "\n";
flush();

// Test with first term only
if (!empty($source_terms)) {
    $first = $source_terms[0];
    echo "Step 7: Testing translation of '{$first->name}' (ID: {$first->term_id})...\n";
    flush();

    foreach ($target_langs as $lang) {
        echo "   -> [{$lang}] ... ";
        flush();
        $result = softmir_translate_term($first->term_id, 'software_category', $lang);
        if (is_wp_error($result)) {
            echo "ERROR: " . $result->get_error_message() . "\n";
        }
        else {
            echo "OK: " . json_encode($result) . "\n";
        }
        flush();
        break; // only first language
    }
}

echo "\n=== TEST DONE ===\n";
echo '</pre>';
