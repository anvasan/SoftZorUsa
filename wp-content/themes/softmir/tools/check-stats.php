<?php
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options'))
    wp_die('Access denied');

$posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'lang' => '', // get all languages
]);

$stats = [];
$default_lang = function_exists('pll_default_language') ? pll_default_language() : 'n/a';
$langs = function_exists('pll_languages_list') ? pll_languages_list() : [];

echo "Default Language: $default_lang\n";
echo "Total Software Posts: " . count($posts) . "\n\n";

$by_lang = [];
foreach ($posts as $p) {
    if (function_exists('pll_get_post_language')) {
        $l = pll_get_post_language($p->ID);
        if (!isset($by_lang[$l]))
            $by_lang[$l] = 0;
        $by_lang[$l]++;
    }
}

foreach ($by_lang as $l => $count) {
    echo "Language [$l]: $count posts\n";
}

echo "\n--- Needs Translation ---\n";
// Count items in default lang that don't have translations in other langs
$source_posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'lang' => $default_lang,
]);

foreach ($source_posts as $sp) {
    $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($sp->ID) : [];
    $missing = [];
    foreach ($langs as $l) {
        if ($l === $default_lang)
            continue;
        if (!isset($translations[$l])) {
            $missing[] = $l;
        }
    }
    if (!empty($missing)) {
        echo "Post ID {$sp->ID}: {$sp->post_title} | Missing: " . implode(', ', $missing) . "\n";
    }
}
