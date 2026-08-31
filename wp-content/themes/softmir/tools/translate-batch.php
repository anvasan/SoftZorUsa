<?php
/**
 * Batch translation tool for SoftMir
 * Processes a small number of posts to avoid server timeouts/rate limits.
 */
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
$post_type = 'software';
$default_lang = pll_default_language();
$all_langs = pll_languages_list();
$target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

echo '<pre style="font-family:monospace;padding:10px;background:#f9f9f9;border:1px solid #ddd;">';
echo "=== BATCH TRANSLATION: $post_type ===\n";
echo "Batch Limit: $limit posts\n\n";

$source_posts = get_posts([
    'post_type' => $post_type,
    'posts_per_page' => -1,
    'lang' => $default_lang,
    'post_status' => 'publish',
]);

$processed = 0;
$translations_made = 0;
$errors = 0;

foreach ($source_posts as $sp) {
    if ($processed >= $limit)
        break;

    $translations = pll_get_post_translations($sp->ID);
    $needs_translation = false;
    foreach ($target_langs as $lang) {
        if (!isset($translations[$lang])) {
            $needs_translation = true;
            break;
        }
    }

    if ($needs_translation) {
        $processed++;
        echo "[$processed] Processing ID {$sp->ID}: <b>{$sp->post_title}</b>\n";

        foreach ($target_langs as $lang) {
            if (isset($translations[$lang])) {
                echo "   → [$lang]: Already exists (ID " . $translations[$lang] . ")\n";
                continue;
            }

            echo "   → [$lang]: Translating... ";
            flush();

            $result = softmir_translate_post($sp->ID, $lang);

            if (is_wp_error($result)) {
                echo "<span style='color:red;'>FAILED: " . $result->get_error_message() . "</span>\n";
                $errors++;
                // If rate limited, stop this batch
                if (strpos($result->get_error_message(), '429') !== false) {
                    echo "\n<span style='color:orange;'>! Rate limited. Stopping current batch. Wait a bit then refresh.</span>\n";
                    break 2;
                }
            } else {
                echo "<span style='color:green;'>SUCCESS (ID " . $result['translation_id'] . ")</span>\n";
                $translations_made++;
            }
            sleep(1); // Small delay between languages
        }
        echo "\n";
        sleep(2); // Delay between posts
    }
}

if ($processed === 0) {
    echo "<b>DONE!</b> All posts are already translated.\n";
} else {
    echo "Summary: Posts processed $processed, Translations made $translations_made, Errors $errors\n";
    echo "\n<a href='?limit=$limit&v=" . time() . "' style='padding:10px;background:#2271b1;color:white;text-decoration:none;border-radius:3px;'>PROCESS NEXT BATCH →</a>";
}

echo '</pre>';
