<?php
/**
 * Translate all software_category terms that have _key_functions filled in.
 * Usage: php translate-kf-categories.php
 */

// Bootstrap WordPress
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
    die("ERROR: wp-load.php not found at: $wp_load\n");
}
require_once $wp_load;

// Check Polylang
if (!function_exists('pll_default_language') || !function_exists('pll_languages_list')) {
    die("ERROR: Polylang is not active!\n");
}

// Check Gemini API
if (!defined('GOOGLE_GEMINI_KEY') || empty(GOOGLE_GEMINI_KEY)) {
    die("ERROR: GOOGLE_GEMINI_KEY is not defined!\n");
}

// Check translation function
if (!function_exists('softmir_translate_term')) {
    die("ERROR: softmir_translate_term() not found. Make sure ai-translate.php is loaded.\n");
}

$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo "=== Translating categories with Key Functions ===\n";
echo "Default language: $default_lang\n";
echo "Target languages: " . implode(', ', $target_langs) . "\n\n";

// Get all software_category terms in the default language
$terms = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => false,
    'lang' => $default_lang,
]);

if (is_wp_error($terms) || empty($terms)) {
    die("No software_category terms found.\n");
}

$translated_count = 0;
$error_count = 0;

foreach ($terms as $term) {
    $key_functions = get_term_meta($term->term_id, '_key_functions', true);

    if (empty($key_functions)) {
        continue;
    }

    echo "📂 [{$term->term_id}] {$term->name}\n";
    echo "   Key Functions: {$key_functions}\n";

    foreach ($target_langs as $lang) {
        echo "   → Translating to [{$lang}]... ";

        $result = softmir_translate_term($term->term_id, 'software_category', $lang);

        if (is_wp_error($result)) {
            echo "❌ ERROR: " . $result->get_error_message() . "\n";
            $error_count++;
        } else {
            $trans_term_id = $result['term_id'] ?? '?';
            // Verify key functions were copied
            $trans_kf = get_term_meta($trans_term_id, '_key_functions', true);
            echo "✅ term_id={$trans_term_id}";
            if (!empty($trans_kf)) {
                echo " | KF: {$trans_kf}";
            } else {
                echo " | ⚠️ KF is empty!";
            }
            echo "\n";
            $translated_count++;
        }

        // Small delay to avoid API rate limits
        sleep(2);
    }
    echo "\n";
}

echo "=== Done ===\n";
echo "Translated: {$translated_count} | Errors: {$error_count}\n";
