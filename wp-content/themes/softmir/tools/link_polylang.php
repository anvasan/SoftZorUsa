<?php
/**
 * Polylang Linker Script
 * Groups software posts by their ACF "website_url" field.
 * If multiple posts with the same URL exist in different languages,
 * links them together as translations in Polylang.
 */
define('ABSPATH', 'd:/laragon/www/SoftZor/');
require_once ABSPATH . 'wp-config.php';

if (!function_exists('pll_get_post_language') || !function_exists('pll_save_post_translations')) {
    die("Polylang is not active.\n");
}

global $wpdb;

// 1. Get all published software posts and their website links
$posts = $wpdb->get_results("
    SELECT p.ID, p.post_title, m.meta_value as website_url
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'website_url'
    WHERE p.post_type = 'software' AND p.post_status = 'publish'
");

$groups = [];

foreach ($posts as $post) {
    $url = trim($post->website_url);
    if (empty($url)) {
        continue; // Skip posts without a URL
    }

    // Normalize URL (strip trailing slashes, http/https for grouping)
    $normalized_url = preg_replace('/^https?:\/\/(www\.)?/i', '', $url);
    $normalized_url = rtrim($normalized_url, '/');

    $lang = pll_get_post_language($post->ID);
    if (!$lang) {
        // If the post has NO language, assign one or skip?
        // Let's check pll_default_language and force it if possible, but skip for now to be safe.
        // Actually, let's use the DB query or skip.
        continue;
    }

    $groups[$normalized_url][$lang] = $post->ID;
}

$linked_count = 0;

foreach ($groups as $url => $langs) {
    if (count($langs) > 1) {
        // We have translations to link!

        // Are they already linked? Let's check any of them.
        $first_id = reset($langs);
        $existing_translations = pll_get_post_translations($first_id);

        $needs_linking = false;
        $combined_translations = $existing_translations;

        foreach ($langs as $lang => $id) {
            if (!isset($existing_translations[$lang]) || $existing_translations[$lang] != $id) {
                $needs_linking = true;
                $combined_translations[$lang] = $id;
            }
        }

        if ($needs_linking) {
            echo "Linking Group for URL: {$url}\n";
            print_r($combined_translations);
            pll_save_post_translations($combined_translations);
            echo "--- Successfully linked ---\n";
            $linked_count++;
        }
    }
}

echo "Done. Linked {$linked_count} translation groups.\n";
