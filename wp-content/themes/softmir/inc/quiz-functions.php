<?php
/**
 * SoftMir — Quiz Functions System
 * Handles logic for category-specific quiz questions, including inheritance
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get quiz questions for the category taking into account inheritance from parents.
 *
 * @param int $term_id Category ID software_category
 * @return array Array of questions (each element is an associative array of 'q' and 'options') or an empty array
 */
function softmir_get_category_quiz_questions($term_id)
{
    if (!$term_id) {
        return [];
    }

    // Helper function for getting questions by term ID (Polylang included)
    $get_qs_for_term = function ($tid) {
        $quiz_json = get_field('quiz_questions', 'software_category_' . $tid);
        if (!empty($quiz_json)) {
            $decoded = json_decode($quiz_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Search in related translations (if the current language has no questions)
        if (function_exists('pll_get_term_translations')) {
            $translations = pll_get_term_translations($tid);
            if (!empty($translations)) {
                foreach ($translations as $lang => $trans_id) {
                    if ($trans_id == $tid)
                        continue;
                    $quiz_json = get_field('quiz_questions', 'software_category_' . $trans_id);
                    if (!empty($quiz_json)) {
                        $decoded = json_decode($quiz_json, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                            return $decoded;
                        }
                    }
                }
            }
        }
        return false;
    };

    // 1. Check for questions in the category itself
    $found = $get_qs_for_term($term_id);
    if ($found !== false) {
        return $found;
    }

    // 2. If the current category is empty, go up the tree to the parents
    $term = get_term($term_id, 'software_category');

    // Potential loop protection
    $visited_terms = [$term_id];

    if ($term && !is_wp_error($term) && $term->parent != 0) {
        $parent_id = $term->parent;

        while ($parent_id != 0) {
            // Preventing an infinite loop
            if (in_array($parent_id, $visited_terms)) {
                break;
            }
            $visited_terms[] = $parent_id;

            // Checking if the parent has any questions
            $found_parent = $get_qs_for_term($parent_id);
            if ($found_parent !== false) {
                return $found_parent;
            }

            // If this parent is also empty, go higher
            $parent_term = get_term($parent_id, 'software_category');
            if ($parent_term && !is_wp_error($parent_term)) {
                $parent_id = $parent_term->parent;
            } else {
                $parent_id = 0; // Abort if parent not found
            }
        }
    }

    // 3. If nothing is found either in the category itself or in the parents
    return [];
}

/**
 * Translate quiz questions into the target language via Gemini.
 * The result is cached in transient for 30 days.
 *
 * @param array $questions Array of questions [{q, options}, ...]
 * @param string $target_lang Target language (for example 'Ukrainian', 'English')
 * @param int $cat_id Category ID for cache key
 * @return array Translated array of questions
 */
function softmir_translate_quiz_questions($questions, $target_lang, $cat_id = 0)
{
    if (empty($questions) || !is_array($questions)) {
        return $questions;
    }

    // Short language code for cache key
    $lang_key = strtolower(substr($target_lang, 0, 2));
    $cache_key = 'quiz_q_' . $cat_id . '_' . $lang_key;

    // Checking the cache
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached) && !empty($cached)) {
        return $cached;
    }

    // API key
    if (!function_exists('softmir_get_gemini_key')) {
        return $questions;
    }
    $api_key = softmir_get_gemini_key();
    if (empty($api_key)) {
        return $questions;
    }

    $json_input = wp_json_encode($questions, JSON_UNESCAPED_UNICODE);

    $prompt = "Переведи следующий JSON-массив вопросов квиза на язык: {$target_lang}.\n"
        . "Translate ONLY string values ​​(questions and answer options). Don't change the JSON structure.\n"
        . "Return ONLY a valid JSON array, no explanation.\n\n"
        . $json_input;

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return $questions; // Fallback - we give it to the Russians
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return $questions;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($generated_text)) {
        return $questions;
    }

    $json_str = trim($generated_text);
    if (strpos($json_str, '```json') === 0) {
        $json_str = substr($json_str, 7);
        $json_str = substr($json_str, 0, -3);
    } elseif (strpos($json_str, '```') === 0) {
        $json_str = substr($json_str, 3);
        $json_str = substr($json_str, 0, -3);
    }
    $json_str = trim($json_str);

    $translated = json_decode($json_str, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($translated) || empty($translated)) {
        return $questions;
    }

    // We cache for 30 days
    set_transient($cache_key, $translated, 30 * DAY_IN_SECONDS);

    return $translated;
}

/**
 * Automatically generates quiz questions through AI for an empty category and saves them to the database (forever).
 * 
 * @param int $cat_id Category ID.
 * @param string $cat_name Category name.
 * @param string $lang_name Generation language (e.g. 'Russian', 'English').
 * @return array Generated questions or an empty array on error.
 */
function softmir_auto_generate_quiz_questions($cat_id, $cat_name, $lang_name = 'English')
{
    if (!function_exists('softmir_get_gemini_key')) {
        return [];
    }

    // cat_name is required (can be the category name OR the user's request text)
    if (empty($cat_name)) {
        return [];
    }

    $api_key = softmir_get_gemini_key();
    if (empty($api_key)) {
        return [];
    }

    // Let's adapt the prompt: if cat_id = 0, use the query text instead of the category
    if ($cat_id > 0) {
        $context = "User wants to find software in category: '{$cat_name}'.";
    } else {
        $context = "Пользователь описал свою задачу: '{$cat_name}'. Category не определена — помоги уточнить потребности.";
    }

    $prompt = "Ты B2B-эксперт по программному обеспечению. {$context}\n"
        . "Your task: come up with 3 great qualifying questions that will help narrow down the choice (for example, company size, main goal, budget, process specifics).\n"
        . "For each question, come up with 3-5 short answer options.\n\n"
        . "Ответь СТРОГО на языке: {$lang_name}!\n\n"
        . "Return ONLY a valid JSON array of 3 elements. Here is the exact structure of each element:\n"
        . "{\n"
        . "  \"q\": \"Question text?\",\n"
        . "  \"options\": [\"Option 1\", \"Option 2\", \"Option 3\"]\n"
        . "}\n"
        . "Do not output anything other than a JSON array.";

    // We use the fast and cheap flash-lite model, since the task is simple
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'responseMimeType' => 'application/json',
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($generated_text)) {
        return [];
    }

    $json_str = trim($generated_text);
    $json_str = preg_replace('/^```json\s*/i', '', $json_str);
    $json_str = preg_replace('/\s*```$/', '', $json_str);

    $questions = json_decode($json_str, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions) || empty($questions)) {
        return [];
    }

    // We save generated questions in ACF only if there is a real category
    if ($cat_id > 0) {
        $json_to_save = wp_json_encode($questions, JSON_UNESCAPED_UNICODE);
        update_field('quiz_questions', $json_to_save, 'software_category_' . $cat_id);
    }

    return $questions;
}
