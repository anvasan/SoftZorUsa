<?php
/**
 * Script to migrate old manual content into new ACF fields.
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

$dry_run = false;

echo "Starting migration process...\n";

$posts = get_posts([
    'post_type' => 'software',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);

$migrated_count = 0;

foreach ($posts as $post) {
    $content = $post->post_content;

    if (mb_strpos($content, '🎯') === false) {
        continue;
    }

    echo "=================================================\n";
    echo "Processing Post ID: {$post->ID} ({$post->post_title})\n";

    // 1. INTRO
    $intro = $content;
    if (preg_match('/(.*?)(?:<h[1-6][^>]*>|<p[^>]*>|<div[^>]*>|<strong>|<b>)?\s*🎯/isu', $content, $matches)) {
        $intro = $matches[1];
    } else {
        $intro_end_pos = mb_strpos($content, '🎯');
        $intro = mb_substr($content, 0, $intro_end_pos);
    }
    $intro = preg_replace('/\[showhide[^\]]*\]/', '', $intro);
    $intro = trim($intro);

    // 2. THE REST
    $rest = str_replace($intro, '', $content);
    $rest = str_replace(['</p>', '</li>', '</ul>', '</div>', '<br>', '<br />', '<br/>'], "\n", $rest);
    $rest = wp_strip_all_tags($rest);
    $rest = preg_replace('/[ \t]+/', ' ', $rest);
    $rest = preg_replace('/\n[ \t]+/', "\n", $rest);
    $rest = preg_replace('/\n{3,}/', "\n\n", $rest);

    // Smart extractor function
    $extract_block = function ($text, $start_marker) {
        $start_pos = mb_strpos($text, $start_marker);
        if ($start_pos === false)
            return '';

        // Added 🚀 and ⛔ as boundaries just in case they are used as headings,
        // but normally they are inside 🏁. So we only stop at major headings.
        $boundaries = ['🎯', '⚙️', '⚖️', '✅', '⚠️', '💰', '🏁', '[/showhide]'];

        $data_start = $start_pos + mb_strlen($start_marker);

        $closest_end = false;
        foreach ($boundaries as $boundary) {
            if ($boundary === $start_marker)
                continue;
            $pos = mb_strpos($text, $boundary, $data_start);
            if ($pos !== false) {
                if ($closest_end === false || $pos < $closest_end) {
                    $closest_end = $pos;
                }
            }
        }

        if ($closest_end !== false) {
            $data = mb_substr($text, $data_start, $closest_end - $data_start);
        } else {
            $data = mb_substr($text, $data_start);
        }

        $lines = explode("\n", trim($data));
        if (!empty($lines)) {
            array_shift($lines);
        }

        $cleaned = trim(implode("\n", $lines));
        $cleaned = preg_replace('/\[\/?showhide[^\]]*\]/', '', $cleaned);

        return trim($cleaned);
    };

    $verdict_raw = $extract_block($rest, '🎯');
    $scenarios_raw = $extract_block($rest, '⚙️');
    $pros_raw = $extract_block($rest, '✅');
    $cons_raw = $extract_block($rest, '⚠️');
    $pricing_raw = $extract_block($rest, '💰');

    // For recommendation, extract the whole block
    $recommendation_raw = $extract_block($rest, '🏁');

    // SCENARIOS
    $scenarios = [];
    $scenario_lines = array_filter(explode("\n", $scenarios_raw));
    foreach ($scenario_lines as $line) {
        $line = trim($line);
        if (empty($line) || mb_strpos($line, 'Сценарії використання') !== false || mb_strpos($line, 'Use Cases') !== false) {
            continue;
        }
        if (preg_match('/^(.*?):\s*(.*)$/u', $line, $matches)) {
            $scenarios[] = [
                'icon' => trim($matches[1]), // Usually emoji is here
                'title' => trim($matches[1]),
                'desc' => trim($matches[2])
            ];
        } else {
            if (mb_strlen($line) > 10) {
                $scenarios[] = [
                    'icon' => 'check_circle',
                    'title' => 'Особливість',
                    'desc' => $line
                ];
            }
        }
    }

    // PROS
    $pros = [];
    $pros_lines = array_filter(explode("\n", $pros_raw));
    foreach ($pros_lines as $line) {
        $line = trim($line);
        if (!empty($line) && mb_strpos($line, 'Плюси') === false && mb_strpos($line, 'Плюсы') === false) {
            $pros[] = $line;
        }
    }

    // CONS
    $cons = [];
    $cons_lines = array_filter(explode("\n", $cons_raw));
    foreach ($cons_lines as $line) {
        $line = trim($line);
        if (!empty($line) && mb_strpos($line, 'Нюансы') === false && mb_strpos($line, 'Нюанси') === false) {
            $cons[] = $line;
        }
    }

    // RECOMMENDATIONS (YES and NO)
    $best_for = [];
    $bad_for = [];
    $rec_lines = array_filter(explode("\n", $recommendation_raw));
    foreach ($rec_lines as $line) {
        $line = trim($line);
        if (empty($line))
            continue;

        if (mb_strpos($line, '🚀') !== false) {
            $cleaned = preg_replace('/^.*?🚀.*?(?:якщо|если):\s*/iu', '', $line);
            if (!empty($cleaned))
                $best_for[] = $cleaned;
        } elseif (mb_strpos($line, '⛔') !== false) {
            $cleaned = preg_replace('/^.*?⛔.*?(?:якщо|если):\s*/iu', '', $line);
            if (!empty($cleaned))
                $bad_for[] = $cleaned;
        }
    }

    // If recommendation section was missing or didn't have emojis but the text had them, fallback logic:
    if (empty($best_for) && mb_strpos($rest, '🚀') !== false) {
        $best_lines = explode("\n", mb_substr($rest, mb_strpos($rest, '🚀')));
        $cleaned = preg_replace('/^.*?🚀.*?(?:якщо|если):\s*/iu', '', $best_lines[0]);
        if (!empty($cleaned))
            $best_for[] = trim($cleaned);
    }
    if (empty($bad_for) && mb_strpos($rest, '⛔') !== false) {
        $bad_lines = explode("\n", mb_substr($rest, mb_strpos($rest, '⛔')));
        $cleaned = preg_replace('/^.*?⛔.*?(?:якщо|если):\s*/iu', '', $bad_lines[0]);
        if (!empty($cleaned))
            $bad_for[] = trim($cleaned);
    }

    if ($dry_run) {
        echo "--- DRY RUN (Parsed Data) ---\n";
        echo "VERDICT RAW [LEN: " . mb_strlen($verdict_raw) . "]:\n" . mb_substr($verdict_raw, 0, 80) . "...\n";

        echo "\nSCENARIOS [" . count($scenarios) . "]:\n";
        foreach ($scenarios as $s)
            echo "- {$s['title']} : {$s['desc']}\n";

        echo "\nPROS [" . count($pros) . "]:\n";
        foreach ($pros as $p)
            echo "- $p\n";

        echo "\nCONS [" . count($cons) . "]:\n";
        foreach ($cons as $c)
            echo "- $c\n";

        echo "\nPRICING [LEN: " . mb_strlen($pricing_raw) . "]:\n" . mb_substr($pricing_raw, 0, 80) . "\n";

        echo "\nBEST FOR [" . count($best_for) . "]:\n";
        foreach ($best_for as $b)
            echo "- $b\n";

        echo "\nBAD FOR [" . count($bad_for) . "]:\n";
        foreach ($bad_for as $b)
            echo "- $b\n";
        echo "\n";

        $migrated_count++;
        // Output up to 2 items
        if ($migrated_count >= 2)
            break;
    } else {
        update_field('verdict', $verdict_raw, $post->ID);
        update_field('scenarios', wp_json_encode($scenarios, JSON_UNESCAPED_UNICODE), $post->ID);
        update_field('advantages', wp_json_encode($pros, JSON_UNESCAPED_UNICODE), $post->ID);
        update_field('disadvantages', wp_json_encode($cons, JSON_UNESCAPED_UNICODE), $post->ID);
        update_field('price_summary', $pricing_raw, $post->ID);
        update_field('best_for', wp_json_encode($best_for, JSON_UNESCAPED_UNICODE), $post->ID);
        update_field('bad_for', wp_json_encode($bad_for, JSON_UNESCAPED_UNICODE), $post->ID);

        wp_update_post([
            'ID' => $post->ID,
            'post_content' => $intro
        ]);
        echo "MIGRATED ID {$post->ID}\n";
    }
}

echo "Done.\n";
