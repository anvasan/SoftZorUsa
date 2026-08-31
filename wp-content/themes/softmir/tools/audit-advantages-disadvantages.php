<?php
/**
 * Аудит полей top_reasons и disadvantages по новым правилам качества.
 * Запуск: wp eval-file wp-content/themes/softmir/tools/audit-advantages-disadvantages.php
 */

if (!defined('ABSPATH')) {
    // Allow running via wp eval-file
}

// Banned phrases for advantages
$banned_advantages = [
    'удобный интерфейс',
    'гибкие настройки',
    'мощный функционал',
    'широкие возможности',
    'постоянные обновления',
    'активная поддержка',
    'интуитивно понятный',
    'интуитивно-понятный',
    'всё в одном месте',
    'все в одном месте',
    'единое окно',
    'швейцарский нож',
    'незаменимый помощник',
    'универсальный инструмент',
    'инновационный',
    'значительно',
    'существенно',
    'заметно сокращает',
    'заметно ускоряет',
    'автоматизация процессов',
    'оптимизация процессов',
];

// Banned phrases for disadvantages
$banned_disadvantages = [
    'нет информации о ценах',
    'цены не указаны',
    'не указаны цены',
    'тарифы не указаны',
    'не описан функционал',
    'функционал плохо задокументирован',
    'сайт не описывает',
    'не найдена информация',
    'информация не найдена',
    'нет данных о',
    'нет бюджета',
    'нет интернета',
    'не разбирается в IT',
    'не разбирается в технологиях',
    'может потребовать',
    'может быть сложно',
    'лучше взять',
    'лучше выбрать',
];

$max_advantages = 7;
$max_disadvantages = 5;
$max_adv_words = 8;
$max_dis_words = 10;

// Get all published software posts (default language only)
$args = [
    'post_type'      => 'software',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
];

// Only default language
if (function_exists('pll_default_language')) {
    $def_lang = pll_default_language('slug');
    if ($def_lang) {
        $args['tax_query'] = [[
            'taxonomy' => 'language',
            'field'    => 'slug',
            'terms'    => $def_lang,
        ]];
    }
}

$query = new WP_Query($args);
$total = count($query->posts);

$problems = [];

foreach ($query->posts as $post_id) {
    $title = get_the_title($post_id);
    $issues = [];

    // --- Check advantages (top_reasons) ---
    $raw_adv = get_post_meta($post_id, 'top_reasons', true);
    if (empty($raw_adv)) {
        $issues[] = '⚠️ advantages: ПУСТО';
    } else {
        $lines = array_filter(array_map('trim', explode("\n", $raw_adv)));
        $count = count($lines);
        
        if ($count > $max_advantages) {
            $issues[] = "❌ advantages: {$count} пунктов (макс {$max_advantages})";
        }

        foreach ($lines as $line) {
            $word_count = count(preg_split('/\s+/u', trim($line)));
            if ($word_count > $max_adv_words) {
                $short = mb_substr($line, 0, 60) . '...';
                $issues[] = "❌ advantages: «{$short}» — {$word_count} слов (макс {$max_adv_words})";
                break; // One example is enough
            }
        }

        $adv_text_lower = mb_strtolower($raw_adv, 'UTF-8');
        foreach ($banned_advantages as $phrase) {
            if (mb_stripos($adv_text_lower, $phrase) !== false) {
                $issues[] = "🚫 advantages: содержит запрещённое «{$phrase}»";
            }
        }
    }

    // --- Check disadvantages ---
    $raw_dis = get_post_meta($post_id, 'disadvantages', true);
    if (empty($raw_dis)) {
        $issues[] = '⚠️ disadvantages: ПУСТО';
    } else {
        $lines = array_filter(array_map('trim', explode("\n", $raw_dis)));
        $count = count($lines);
        
        if ($count > $max_disadvantages) {
            $issues[] = "❌ disadvantages: {$count} пунктов (макс {$max_disadvantages})";
        }

        foreach ($lines as $line) {
            $word_count = count(preg_split('/\s+/u', trim($line)));
            if ($word_count > $max_dis_words) {
                $short = mb_substr($line, 0, 60) . '...';
                $issues[] = "❌ disadvantages: «{$short}» — {$word_count} слов (макс {$max_dis_words})";
                break;
            }
        }

        // Check format: should start with "Не брать, если:"
        $has_wrong_format = false;
        foreach ($lines as $line) {
            if (mb_stripos($line, 'Не брать, если') === false && mb_stripos($line, 'не брать, если') === false) {
                $has_wrong_format = true;
                break;
            }
        }
        if ($has_wrong_format) {
            $issues[] = "📝 disadvantages: НЕ начинается с «Не брать, если:»";
        }

        $dis_text_lower = mb_strtolower($raw_dis, 'UTF-8');
        foreach ($banned_disadvantages as $phrase) {
            if (mb_stripos($dis_text_lower, $phrase) !== false) {
                $issues[] = "🚫 disadvantages: содержит запрещённое «{$phrase}»";
            }
        }
    }

    if (!empty($issues)) {
        $problems[$post_id] = [
            'title'  => $title,
            'id'     => $post_id,
            'issues' => $issues,
        ];
    }
}

// Output results
$problem_count = count($problems);
echo "\n========================================\n";
echo "📊 АУДИТ КАРТОЧЕК ПО (advantages / disadvantages)\n";
echo "========================================\n";
echo "Всего карточек проверено: {$total}\n";
echo "С проблемами: {$problem_count}\n";
echo "========================================\n\n";

if (empty($problems)) {
    echo "✅ Все карточки соответствуют правилам!\n";
} else {
    $i = 1;
    foreach ($problems as $pid => $data) {
        echo "{$i}. [{$data['id']}] {$data['title']}\n";
        foreach ($data['issues'] as $issue) {
            echo "   {$issue}\n";
        }
        echo "\n";
        $i++;
    }
}

echo "========================================\n";
echo "Легенда:\n";
echo "  ❌ = Нарушение структуры (кол-во, длина)\n";
echo "  🚫 = Запрещённая фраза (вода/клише)\n";
echo "  📝 = Неверный формат (нет 'Не брать, если:')\n";
echo "  ⚠️ = Поле не заполнено\n";
echo "========================================\n";
