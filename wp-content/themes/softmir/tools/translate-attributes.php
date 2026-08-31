<?php
/**
 * Bulk translate all sw_attribute posts to all languages.
 * Uses BATCH mode: sends all titles+options in one API call per language.
 * URL: http://wp_test_anti.test/wp-content/themes/softmir/tools/translate-attributes.php
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
echo "=== Bulk Translation: sw_attribute (BATCH MODE) ===\n\n";

$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo "Default language: {$default_lang}\n";
echo "Target languages: " . implode(', ', $target_langs) . "\n\n";

// Get all attributes
$attrs = get_posts([
    'post_type' => 'sw_attribute',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'lang' => '',
]);

// Filter to only source language
$source_attrs = [];
foreach ($attrs as $attr) {
    $lang = pll_get_post_language($attr->ID);
    if (!$lang || $lang === $default_lang) {
        $source_attrs[] = $attr;
    }
}

echo "Total attributes found: " . count($attrs) . "\n";
echo "Source language attributes: " . count($source_attrs) . "\n\n";

if (empty($source_attrs)) {
    echo "No source attributes found.\n</pre>";
    exit;
}

// Meta keys to copy as-is (not translate)
$copy_meta_keys = ['_attr_type', '_attr_icon', '_attr_filterable', '_attr_card_position', '_attr_page_position', '_attr_multiple'];

// ─── BATCH TRANSLATE PER LANGUAGE ───
foreach ($target_langs as $lang) {
    echo "━━━ Translating to [{$lang}] ━━━\n";
    flush();

    // Build batch payload: titles + options
    $batch = [];
    foreach ($source_attrs as $attr) {
        $batch['title_' . $attr->ID] = $attr->post_title;
        $options = get_post_meta($attr->ID, '_attr_options', true);
        if (!empty($options)) {
            $batch['opts_' . $attr->ID] = $options;
        }
    }

    echo "   📦 Sending " . count($batch) . " items";
    flush();

    // Split into chunks of 100 to avoid too-large requests
    $chunks = array_chunk($batch, 100, true);
    $all_translated = [];

    echo " in " . count($chunks) . " chunk(s)...\n";
    flush();

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

    // Now create/update attribute translations
    $created = 0;
    $updated = 0;
    $errors = 0;

    foreach ($source_attrs as $attr) {
        $title_key = 'title_' . $attr->ID;
        $opts_key = 'opts_' . $attr->ID;

        $translated_title = $all_translated[$title_key] ?? $attr->post_title;
        $translated_opts = $all_translated[$opts_key] ?? get_post_meta($attr->ID, '_attr_options', true);

        $trans_id = pll_get_post($attr->ID, $lang);

        if (!$trans_id) {
            // Create new translated post
            $new_post = [
                'post_type' => 'sw_attribute',
                'post_status' => $attr->post_status,
                'post_author' => $attr->post_author,
                'post_title' => $translated_title,
                'post_content' => $attr->post_content,
            ];

            $trans_id = wp_insert_post($new_post);
            if (is_wp_error($trans_id)) {
                echo "   ❌ {$attr->post_title}: " . $trans_id->get_error_message() . "\n";
                $errors++;
                continue;
            }

            // Set language and link translations
            pll_set_post_language($trans_id, $lang);
            $translations = pll_get_post_translations($attr->ID);
            $translations[$lang] = $trans_id;
            $translations[$default_lang] = $attr->ID;
            pll_save_post_translations($translations);

            $created++;
        }
        else {
            // Update existing
            wp_update_post([
                'ID' => $trans_id,
                'post_title' => $translated_title,
            ]);
            $updated++;
        }

        // Save translated _attr_options
        if (!empty($translated_opts)) {
            update_post_meta($trans_id, '_attr_options', $translated_opts);
        }

        // Copy non-translatable meta (type, icon, positions, etc.)
        foreach ($copy_meta_keys as $mk) {
            $val = get_post_meta($attr->ID, $mk, true);
            if ($val !== '' && $val !== false) {
                update_post_meta($trans_id, $mk, $val);
            }
        }

        // Copy category position meta (_attr_cat_XX)
        $all_meta = get_post_meta($attr->ID);
        foreach ($all_meta as $mk => $mv) {
            if (strpos($mk, '_attr_cat_') === 0) {
                update_post_meta($trans_id, $mk, $mv[0]);
            }
        }

        // Copy thumbnail
        $thumb_id = get_post_thumbnail_id($attr->ID);
        if ($thumb_id) {
            set_post_thumbnail($trans_id, $thumb_id);
        }

        // Save timestamp
        update_post_meta($trans_id, '_softmir_translated_at', current_time('mysql'));
        update_post_meta($trans_id, '_softmir_source_post_id', $attr->ID);
        update_post_meta($trans_id, '_softmir_source_modified', $attr->post_modified);
    }

    echo "   📊 Created: {$created} | Updated: {$updated} | Errors: {$errors}\n\n";
    flush();

    sleep(3); // pause between languages
}

echo "=== DONE ===\n";
echo '</pre>';
