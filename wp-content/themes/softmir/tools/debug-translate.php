<?php
/**
 * Диагностика проблем с переводом карточек ПО
 * Запуск: http://softzor.test/wp-content/themes/softmir/tools/debug-translate.php
 */
require_once dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Только для админа');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== ДИАГНОСТИКА ПЕРЕВОДА ===\n";
echo "Время: " . current_time('mysql') . "\n\n";

// 1. Проверка крон-очереди на события перевода
echo "--- КРОН-ОЧЕРЕДЬ (СОБЫТИЯ ПЕРЕВОДА) ---\n";
$cron = _get_cron_array();
$translate_events = [];
foreach ($cron as $timestamp => $hooks) {
    foreach ($hooks as $hook_name => $events) {
        if (strpos($hook_name, 'translate') !== false || strpos($hook_name, 'softmir_async') !== false) {
            foreach ($events as $key => $event) {
                $translate_events[] = [
                    'hook' => $hook_name,
                    'time' => date('Y-m-d H:i:s', $timestamp),
                    'delta' => $timestamp - time(),
                    'args' => $event['args'],
                ];
            }
        }
    }
}

if (empty($translate_events)) {
    echo "Нет запланированных событий перевода.\n";
} else {
    echo count($translate_events) . " событий найдено:\n";
    foreach ($translate_events as $ev) {
        $status = $ev['delta'] > 0 ? "через {$ev['delta']}с" : "ПРОСРОЧЕНО на " . abs($ev['delta']) . "с!";
        echo "  [{$ev['hook']}] {$ev['time']} ({$status}) args=" . json_encode($ev['args']) . "\n";
    }
}

// 2. Проверка MoreLogin (post_id=1286) — пост из скриншота
echo "\n--- ПРОВЕРКА ПОСТА ID=1286 (MoreLogin) ---\n";
$test_id = 1286;
$post = get_post($test_id);
if ($post) {
    echo "Пост: {$post->post_title} (status={$post->post_status})\n";
    echo "Slug: {$post->post_name}\n";

    if (function_exists('pll_get_post_language')) {
        $lang = pll_get_post_language($test_id);
        echo "Язык: {$lang}\n";
    }

    if (function_exists('pll_get_post_translations')) {
        $translations = pll_get_post_translations($test_id);
        echo "Переводы Polylang: " . json_encode($translations) . "\n";

        foreach ($translations as $lang_code => $trans_id) {
            if ($trans_id != $test_id) {
                $tp = get_post($trans_id);
                if ($tp) {
                    echo "  [{$lang_code}] ID={$trans_id} title='{$tp->post_title}' status={$tp->post_status} slug={$tp->post_name}\n";
                    $tr_lang = pll_get_post_language($trans_id);
                    echo "       Polylang язык поста: {$tr_lang}\n";
                } else {
                    echo "  [{$lang_code}] ID={$trans_id} — ПОСТ НЕ СУЩЕСТВУЕТ!\n";
                }
            }
        }
    }

    // Check _softmir_translated_at on possible translations
    echo "\nSearch постов-переводов по мета _softmir_source_post_id={$test_id}:\n";
    $found = get_posts([
        'post_type' => 'software',
        'post_status' => 'any',
        'meta_key' => '_softmir_source_post_id',
        'meta_value' => $test_id,
        'posts_per_page' => 10,
        'lang' => '',
    ]);
    if (empty($found)) {
        echo "  Не найдено ни одного.\n";
    }
    foreach ($found as $fp) {
        $fp_lang = function_exists('pll_get_post_language') ? pll_get_post_language($fp->ID) : '?';
        $fp_at = get_post_meta($fp->ID, '_softmir_translated_at', true);
        echo "  ID={$fp->ID} title='{$fp->post_title}' lang={$fp_lang} status={$fp->post_status} translated_at={$fp_at}\n";
    }
} else {
    echo "Пост не найден.\n";
}

// 3. Общая статистика переводов software
echo "\n--- ОБЩАЯ СТАТИСТИКА ---\n";
$all_sw = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'lang' => '',
    'fields' => 'ids',
]);
echo "Всего software (publish, все языки): " . count($all_sw) . "\n";

if (function_exists('pll_default_language')) {
    $default = pll_default_language();
    echo "Язык по умолчанию: {$default}\n";
    echo "Все языки: " . implode(', ', pll_languages_list()) . "\n";
}

// 4. Очистка зависших крон-событий
echo "\n--- ОЧИСТКА ЗАВИСШИХ СОБЫТИЙ ---\n";
$cleaned = 0;
foreach ($cron as $timestamp => $hooks) {
    foreach ($hooks as $hook_name => $events) {
        if (strpos($hook_name, 'translate') !== false || strpos($hook_name, 'softmir_async') !== false) {
            foreach ($events as $key => $event) {
                if ($timestamp < time() - 300) { // Просрочено > 5 мин
                    wp_unschedule_event($timestamp, $hook_name, $event['args']);
                    echo "  Удалено: [{$hook_name}] " . date('H:i:s', $timestamp) . " args=" . json_encode($event['args']) . "\n";
                    $cleaned++;
                }
            }
        }
    }
}
echo "Очищено зависших событий: {$cleaned}\n";

echo "\n=== КОНЕЦ ДИАГНОСТИКИ ===\n";
