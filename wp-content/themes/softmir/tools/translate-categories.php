<?php
/**
 * Bulk translate all software_category terms to all languages.
 * Uses BATCH mode: sends all terms in one API call per language.
 * URL: http://wp_test_anti.test/wp-content/themes/softmir/tools/translate-categories.php
 */

// Bootstrap WordPress
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(dirname(__FILE__) . '/../../../../wp-load.php');

// Security: only admins
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

set_time_limit(600);
ini_set('memory_limit', '512M');

echo '<pre style="font-family:monospace;font-size:14px;">';
echo "=== Bulk Translation: software_category (BATCH MODE) ===\n\n";

$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo "Default language: {$default_lang}\n";
echo "Target languages: " . implode(', ', $target_langs) . "\n\n";

// Get all categories in default language
$terms = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => false,
    'lang' => '',
]);

$source_terms = [];
foreach ($terms as $term) {
    $lang = pll_get_term_language($term->term_id);
    if (!$lang || $lang === $default_lang) {
        $source_terms[] = $term;
    }
}

echo "Total categories: " . count($terms) . "\n";
echo "Source language categories: " . count($source_terms) . "\n\n";

if (empty($source_terms)) {
    echo "No source terms found.\n";
    echo '</pre>';
    exit;
}

// ─── BATCH TRANSLATE ───
foreach ($target_langs as $lang) {
    echo "━━━ Translating to [{$lang}] ━━━\n";
    flush();

    // Build batch: term_id => name
    $batch = [];
    $desc_batch = [];
    foreach ($source_terms as $t) {
        $batch['t_' . $t->term_id] = $t->name;
        if (!empty($t->description)) {
            $desc_batch['d_' . $t->term_id] = $t->description;
        }
    }

    echo "   📦 Sending " . count($batch) . " names";
    if (!empty($desc_batch)) {
        echo " + " . count($desc_batch) . " descriptions";
    }
    echo " in one API call...\n";
    flush();

    // Merge names and descriptions into one payload
    $payload = array_merge($batch, $desc_batch);

    // Split into chunks of 50 terms to avoid too-large requests
    $chunks = array_chunk($payload, 100, true);
    $all_translated = [];

    foreach ($chunks as $ci => $chunk) {
        if (count($chunks) > 1) {
            echo "   📤 Chunk " . ($ci + 1) . "/" . count($chunks) . " (" . count($chunk) . " items)... ";
            flush();
        }

        $translated = softmir_gemini_translate($chunk, $lang, $default_lang);

        if (is_wp_error($translated)) {
            echo "❌ API ERROR: " . $translated->get_error_message() . "\n";

            if (strpos($translated->get_error_message(), '429') !== false) {
                echo "   ⏳ Rate limited, waiting 60s...\n";
                flush();
                sleep(60);
                $translated = softmir_gemini_translate($chunk, $lang, $default_lang);
                if (is_wp_error($translated)) {
                    echo "   ❌ Retry failed. Skipping this chunk.\n";
                    continue;
                }
            }
            else {
                continue;
            }
        }

        if (count($chunks) > 1) {
            echo "✅\n";
        }
        $all_translated = array_merge($all_translated, $translated);

        if ($ci < count($chunks) - 1) {
            sleep(3);
        }
    }

    echo "   ✅ API returned " . count($all_translated) . " translations\n";
    flush();

    // Now create/update terms in Polylang
    $created = 0;
    $updated = 0;
    $errors = 0;

    foreach ($source_terms as $t) {
        $name_key = 't_' . $t->term_id;
        $desc_key = 'd_' . $t->term_id;

        $translated_name = $all_translated[$name_key] ?? $t->name;
        $translated_desc = $all_translated[$desc_key] ?? $t->description;

        $trans_term_id = pll_get_term($t->term_id, $lang);

        if (!$trans_term_id) {
            // Create new translated term
            $slug = $t->slug . '-' . $lang;
            $parent_id = softmir_get_translated_parent($t->parent, 'software_category', $lang);

            $result = wp_insert_term($translated_name, 'software_category', [
                'slug' => $slug,
                'description' => $translated_desc,
                'parent' => $parent_id,
            ]);

            if (is_wp_error($result)) {
                // If term already exists with this slug, try with a suffix
                if ($result->get_error_code() === 'term_exists') {
                    $existing_id = $result->get_error_data();
                    if ($existing_id) {
                        $trans_term_id = $existing_id;
                        wp_update_term($trans_term_id, 'software_category', [
                            'name' => $translated_name,
                            'description' => $translated_desc,
                        ]);
                        $updated++;
                    }
                }
                else {
                    echo "   ❌ {$t->name}: " . $result->get_error_message() . "\n";
                    $errors++;
                    continue;
                }
            }
            else {
                $trans_term_id = $result['term_id'];
                $created++;
            }

            if ($trans_term_id) {
                // Set language and link
                pll_set_term_language($trans_term_id, $lang);

                $term_translations = pll_get_term_translations($t->term_id);
                $term_translations[$lang] = $trans_term_id;
                $term_translations[$default_lang] = $t->term_id;
                pll_save_term_translations($term_translations);

                update_term_meta($trans_term_id, '_softmir_translated_at', current_time('mysql'));
            }
        }
        else {
            // Update existing translation
            wp_update_term($trans_term_id, 'software_category', [
                'name' => $translated_name,
                'description' => $translated_desc,
            ]);
            update_term_meta($trans_term_id, '_softmir_translated_at', current_time('mysql'));
            $updated++;
        }
    }

    echo "   📊 Created: {$created} | Updated: {$updated} | Errors: {$errors}\n\n";
    flush();

    sleep(3); // pause between languages
}

echo "=== DONE ===\n";
echo '</pre>';
