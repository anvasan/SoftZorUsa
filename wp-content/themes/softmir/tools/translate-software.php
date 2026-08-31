<?php
/**
 * Bulk translate all software posts to all languages.
 * URL: http://wp_test_anti.test/wp-content/themes/softmir/tools/translate-software.php
 */

// Bootstrap WordPress
require_once(dirname(__FILE__) . '/../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

set_time_limit(1800);
ini_set('memory_limit', '512M');

echo '<pre style="font-family:monospace;font-size:14px;">';
echo "=== Bulk Translation: software ===\n\n";

$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo "Default language: {$default_lang}\n";
echo "Target languages: " . implode(', ', $target_langs) . "\n\n";

$posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'lang' => '',
]);

$source_posts = [];
foreach ($posts as $p) {
    $lang = pll_get_post_language($p->ID);
    if (!$lang || $lang === $default_lang) {
        $source_posts[] = $p;
    }
}

echo "Total software posts: " . count($posts) . "\n";
echo "Source language posts: " . count($source_posts) . "\n\n";

$done = 0;
$errors = 0;

foreach ($source_posts as $p) {
    echo "🖥️ Software: {$p->post_title} (ID: {$p->ID})\n";

    foreach ($target_langs as $lang) {
        $done++;
        echo "   → Translating to [{$lang}]... ";
        flush();

        $result = softmir_translate_post($p->ID, $lang);

        if (is_wp_error($result)) {
            echo "❌ ERROR: " . $result->get_error_message() . "\n";
            $errors++;

            if (strpos($result->get_error_message(), '429') !== false) {
                echo "   ⏳ Rate limited, waiting 60s...\n";
                flush();
                sleep(60);
                $result = softmir_translate_post($p->ID, $lang);
                if (is_wp_error($result)) {
                    echo "   ❌ Retry failed: " . $result->get_error_message() . "\n";
                }
                else {
                    echo "   ✅ Retry OK → ID: {$result['translation_id']}\n";
                    $errors--;
                }
            }
        }
        elseif (isset($result['status']) && $result['status'] === 'success') {
            echo "✅ OK → ID: {$result['translation_id']}\n";
        }
        else {
            echo "⏭️ Skipped: " . ($result['message'] ?? 'unknown') . "\n";
        }

        flush();
        sleep(3); // Slightly longer delay for larger posts
    }
    echo "\n";
}

echo "=== DONE ===\n";
echo "Translated: {$done} | Errors: {$errors}\n";
echo '</pre>';
