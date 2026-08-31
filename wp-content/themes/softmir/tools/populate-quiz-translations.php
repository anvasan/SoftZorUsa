<?php
/**
 * Скрипт массового перевода ВСЕХ категорий ПО с русского на украинский.
 */

define('WP_USE_THEMES', false);
require_once dirname(__FILE__, 5) . '/wp-load.php';

if (php_sapi_name() !== 'cli') {
    die("Only allowed via CLI.");
}

if (!function_exists('softmir_translate_term')) {
    die("Error: softmir_translate_term function not found. Include ai-translate.php\n");
}

echo "Starting bulk translation of software categories...\n";

// Получаем только оригинальные термины (RU)
$terms = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => false,
    'lang' => 'ru'
]);

if (is_wp_error($terms) || empty($terms)) {
    die("No Russian categories found.\n");
}

echo "Found " . count($terms) . " Russian categories.\n";

$target_lang = 'uk';
$count = 0;
$skipped = 0;

foreach ($terms as $i => $term) {
    // Проверка, есть ли уже Украинский перевод без вызова Gemini? 
    // Если да, можно было бы пропустить, но нам надо перевести quiz_questions, 
    // поэтому даем softmir_translate_term отработать. Оно само обновит существующий Укр-термин.

    // Но если укр термин уже есть И quiz_questions не пустой, можно пропустить!
    $existing_uk_id = pll_get_term($term->term_id, $target_lang);
    if ($existing_uk_id) {
        $uk_questions = get_field('quiz_questions', 'software_category_' . $existing_uk_id);
        if (!empty($uk_questions)) {
            echo "[" . ($i + 1) . "/" . count($terms) . "] Skip {$term->name} (already has UK questions)\n";
            $skipped++;
            continue;
        }
    }

    echo "[" . ($i + 1) . "/" . count($terms) . "] Translating: {$term->name}...\n";

    $result = softmir_translate_term($term->term_id, 'software_category', $target_lang);

    if (is_wp_error($result)) {
        if (strpos($result->get_error_message(), '429') !== false) {
            echo "Rate limit hit (429)! Waiting 20 seconds...\n";
            sleep(20);

            // Повторная попытка
            $result = softmir_translate_term($term->term_id, 'software_category', $target_lang);
            if (is_wp_error($result)) {
                echo "FAILED RE-TRY: " . $result->get_error_message() . " (id: {$term->term_id})\n";
                // Спим и идем дальше, не падаем
                sleep(5);
                continue;
            }
        } else {
            echo "FAILED: " . $result->get_error_message() . " (id: {$term->term_id})\n";
            continue;
        }
    }

    echo "SUCCESS: Translated to UK ID {$result['term_id']}\n";
    $count++;

    // Задержка во избежание 429 ошибки от Gemini rate limits (15 RPM)
    sleep(4);
}

echo "\nDone!\nTotal processed: $count\nSkipped: $skipped\n";
