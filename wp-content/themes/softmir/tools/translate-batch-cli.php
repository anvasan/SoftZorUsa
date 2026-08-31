<?php
/**
 * CLI batch translate software posts.
 * Run: $env:TRANSLATE_LANG='uk'; $env:TRANSLATE_LIMIT='10'; wp eval-file ...
 */

$target_lang = getenv('TRANSLATE_LANG') ?: null;
$limit = intval(getenv('TRANSLATE_LIMIT') ?: 10);

if (!$target_lang) {
    // Try positional args from WP-CLI, filtering out '--'
    if (isset($args) && is_array($args)) {
        $clean = array_values(array_filter($args, fn($a) => $a !== '--'));
        $target_lang = $clean[0] ?? null;
        $limit = intval($clean[1] ?? 10);
    }
}

if (!$target_lang) {
    echo "ERROR: No target language specified.\n";
    echo "Set env: \$env:TRANSLATE_LANG='uk'; \$env:TRANSLATE_LIMIT='10'\n";
    exit(1);
}

$default_lang = pll_default_language();
echo "=== BATCH TRANSLATE: software -> {$target_lang} ===\n";
echo "Limit: {$limit} posts\n\n";

$source_posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'lang' => $default_lang,
    'post_status' => 'publish',
]);

$to_translate = [];
foreach ($source_posts as $p) {
    $translations = pll_get_post_translations($p->ID);
    if (!isset($translations[$target_lang])) {
        $to_translate[] = $p;
    }
}

echo "Posts needing [{$target_lang}] translation: " . count($to_translate) . "\n";
if (empty($to_translate)) {
    echo "All posts already translated!\n";
    exit(0);
}

$processed = 0;
$success = 0;
$errors = 0;

foreach ($to_translate as $p) {
    if ($processed >= $limit)
        break;
    $processed++;

    echo "\n[{$processed}/{$limit}] ID {$p->ID}: {$p->post_title}\n";
    echo "  -> [{$target_lang}] Translating... ";
    flush();

    $result = softmir_translate_post($p->ID, $target_lang);

    if (is_wp_error($result)) {
        $err_msg = $result->get_error_message();
        echo "FAILED: {$err_msg}\n";
        $errors++;

        if (strpos($err_msg, '429') !== false || stripos($err_msg, 'rate') !== false) {
            echo "  Rate limited. Waiting 60s...\n";
            flush();
            sleep(60);

            $result = softmir_translate_post($p->ID, $target_lang);
            if (is_wp_error($result)) {
                echo "  Retry FAILED: " . $result->get_error_message() . "\n";
            }
            else {
                echo "  Retry OK -> ID: {$result['translation_id']}\n";
                $success++;
                $errors--;
            }
        }
    }
    elseif (isset($result['status']) && $result['status'] === 'success') {
        echo "OK -> ID: {$result['translation_id']}\n";
        $success++;
    }
    else {
        echo "Skipped: " . ($result['message'] ?? 'unknown') . "\n";
    }

    flush();
    sleep(3);
}

echo "\n=== BATCH DONE ===\n";
echo "Processed: {$processed} | Success: {$success} | Errors: {$errors}\n";

$remaining = count($to_translate) - $processed;
if ($remaining > 0) {
    echo "Remaining: {$remaining} posts still need [{$target_lang}] translation.\n";
}
