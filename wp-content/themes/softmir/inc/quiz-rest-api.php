<?php
/**
 * SoftMir — Quiz REST API & Scout Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. REST API Endpoint: POST /softmir/v1/quiz-submit
// ==========================================
add_action('rest_api_init', function () {
    register_rest_route('softmir/v1', '/quiz-submit', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_quiz_submit',
        'permission_callback' => function (\WP_REST_Request $request) {
            $nonce = $request->get_header('x_wp_nonce');
            if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid Nonce.', ['status' => 403]);
            }
            return true;
        },
    ]);

    register_rest_route('softmir/v1', '/quiz-classify', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_quiz_classify',
        'permission_callback' => function (\WP_REST_Request $request) {
            $nonce = $request->get_header('x_wp_nonce');
            if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid Nonce.', ['status' => 403]);
            }
            return true;
        },
    ]);
});

// ==========================================
// 1.1 Helper: Rate Limiter
// ==========================================
function softmir_check_quiz_rate_limit($ip)
{
    // Адminы и авторизованные редакторы — без лимита
    if (is_user_logged_in() && current_user_can('edit_posts')) {
        return false;
    }
    if (empty($ip))
        return false;
    // 3 requests per 24 hours
    $key = 'quiz_limit_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= 3) {
        return true; // Limited
    }
    set_transient($key, $count + 1, DAY_IN_SECONDS);
    return false;
}

// ==========================================
// 1.2 REST API: Classify User Intent
// ==========================================
function softmir_rest_quiz_classify(WP_REST_Request $request)
{
    // Rate limit НЕ применяется к classify — только к submit (чтобы 1 прогон квиза = 1 запрос)
    $params = $request->get_json_params() ?: $request->get_body_params();
    $intent = sanitize_textarea_field($params['intent'] ?? '');
    $lang_name = sanitize_text_field($params['lang_name'] ?? 'English');

    if (empty($intent)) {
        return new WP_Error('missing_intent', 'User intent is required', ['status' => 400]);
    }

    if (!function_exists('softmir_get_gemini_key')) {
        return new WP_Error('no_gemini_functions', 'Gemini functions missing');
    }

    $api_key = softmir_get_gemini_key();
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API key is not configured');
    }

    // Получаем все категории Software
    $terms = get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return new WP_Error('no_categories', 'No software categories found');
    }

    $categories_list = "";
    foreach ($terms as $term) {
        $categories_list .= "- ID: {$term->term_id}, Name: {$term->name}\n";
    }

    $prompt = "Ты классификатор намерений пользователя для B2B platformы выбора Software.\n"
        . "Пользователь описал задачу:\n\"{$intent}\"\n\n"
        . "Язык интеRFейса пользователя: {$lang_name}. Не забудь учесть этот язык при анализе.\n\n"
        . "Вот точный список РОДAndТЕЛЬСКAndХ и дочерних категорий (ID : Name):\n"
        . "{$categories_list}\n"
        . "Andнструкция: выбери наиболее подходящий ID из списка.\n"
        . "ВАЖНО: Если запрос пользователя очень узкий и ты уверен, что для него нужна отдельная подкатегория (которой нет в списке, например, юзер ищет 'CRM для тату-салонов', and there is only general 'CRM'), верни ID широкой категории 'CRM', НО установи флаг needs_subcategory: true, а в поле suggested_category_name передай идеальное название узкой ниши ('CRM для тату-салонов'). Если же запрос идеально совпадает с существующей категорией, установи needs_subcategory: false.\n\n"
        . "Верни ТОЛЬКО валидный JSON в формате:\n"
        . "{\n"
        . "  \"reason\": \"почему ты выбрал эту категорию, рассуждение\",\n"
        . "  \"category_id\": ID (число),\n"
        . "  \"needs_subcategory\": true/false,\n"
        . "  \"suggested_category_name\": \"Предложенное название узкой категории (если needs_subcategory = true)\"\n"
        . "}";


    // Classify — тривиальная задача (выбор ID из списка), используем быструю модель БЕЗ googleSearch
    $primary_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $fallback_endpoint = defined('SOFTMIR_GEMINI_ENDPOINT')
        ? SOFTMIR_GEMINI_ENDPOINT . '?key=' . $api_key
        : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        // НЕ используем googleSearch — classify не ищет в интернете, а выбирает ID из готового списка
        'generationConfig' => [
            'temperature' => 0.1,
            'responseMimeType' => 'application/json',
        ]
    ];

    $response = null;
    $status_code = 0;
    $model_endpoints = [
        ['endpoint' => $primary_endpoint, 'retries' => 2, 'name' => 'gemini-3.1-flash-lite'],
        ['endpoint' => $fallback_endpoint, 'retries' => 1, 'name' => 'gemini-3.1-flash-lite'],
    ];

    foreach ($model_endpoints as $model) {
        for ($attempt = 1; $attempt <= $model['retries']; $attempt++) {
            $response = wp_remote_post($model['endpoint'], [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($body),
                'timeout' => 12,
            ]);
            $status_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

            if ($status_code === 200) {
                break 2;
            }
            if (in_array($status_code, [429, 500, 503]) && $attempt < $model['retries']) {
                sleep(1);
            }
        }
    }

    if (is_wp_error($response) || $status_code !== 200) {
        return rest_ensure_response(['status' => 'success', 'category_id' => 0, 'questions' => []]);
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

    $json_res = json_decode($generated_text, true);

    $cat_id = isset($json_res['category_id']) ? intval($json_res['category_id']) : 0;

    $questions = [];
    if ($cat_id > 0) {
        // Получаем вопросы из выбранной категории
        $category_questions = softmir_get_category_quiz_questions($cat_id);

        if (!empty($category_questions) && is_array($category_questions)) {
            $questions = $category_questions;

            // Проверяем, есть ли вопросы непосредственно у текущей языковой категории
            $current_cat_json = get_field('quiz_questions', 'software_category_' . $cat_id);

            // Если поле пустое, значит softmir_get_category_quiz_questions() взяла их из русской версии (fallback).
            // В этом случае переводим их на лету.
            if (empty($current_cat_json) && $lang_name !== 'Russian' && !empty($lang_name)) {
                $questions = softmir_translate_quiz_questions($questions, $lang_name, $cat_id);
            }
        } else {
            // Если вопросов нет ВООБЩЕ — автогенерируем их на лету AndAnd!
            if (function_exists('softmir_auto_generate_quiz_questions')) {
                $term = get_term($cat_id, 'software_category');
                $cat_name = ($term && !is_wp_error($term)) ? $term->name : '';
                if ($cat_name) {
                    $questions = softmir_auto_generate_quiz_questions($cat_id, $cat_name, $lang_name);
                }
            }
        }
    }

    return rest_ensure_response([
        'status' => 'success',
        'category_id' => $cat_id,
        'questions' => $questions
    ]);
}

/**
 * Обработка сабмита квиза
 */
function softmir_rest_quiz_submit(WP_REST_Request $request)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (softmir_check_quiz_rate_limit($ip)) {
        return new WP_Error('rate_limit', 'You have exhausted your limit of 3 requests for today.', ['status' => 429]);
    }
    $params = $request->get_json_params() ?: $request->get_body_params();

    $category_id = intval($params['category_id'] ?? 0);
    $region = sanitize_text_field($params['region'] ?? '');
    $user_text = sanitize_textarea_field($params['user_text'] ?? '');
    $user_extras = sanitize_textarea_field($params['user_extras'] ?? '');
    $answers = rest_sanitize_array($params['answers'] ?? []);
    $lang_name = sanitize_text_field($params['lang_name'] ?? 'English');

    // Session ID (базово)
    $session_id = wp_generate_uuid4();

    // 1. Логируем изначальное намерение (пока без брифа)
    $log_id = softmir_log_intent([
        'session_id' => $session_id,
        'user_intent' => $user_text,
        'offered_partners' => '',
        'selected_external_id' => null,
        'is_expert_mode' => false,
        'generated_brief' => '',
    ]);

    // ============================================================
    // 2. ГAndБРAndДНЫЙ SoftwareAndСК: Gemini → DB lookup → redirect / lead capture
    // ============================================================

    // Базовый URL архива Software
    $archive_url = get_post_type_archive_link('software') ?: home_url('/software/');

    // Геополитический фильтр
    $geo_meta_query = [];
    if ($region !== 'Russia' && $region !== 'CIS') {
        $geo_meta_query = [
            'relation' => 'OR',
            ['key' => 'origin', 'compare' => 'NOT EXISTS'],
            ['key' => 'origin', 'value' => ['RU_BLOCKED', 'BY_BLOCKED'], 'compare' => 'NOT IN']
        ];
    }

    // Fallback redirect на категорию (используется если ничего не найдено)
    $category_redirect_params = [];
    if ($category_id) {
        $category_redirect_params['sw_cat'] = $category_id;
    }
    if ($region) {
        $category_redirect_params['sw_region'] = urlencode($region);
    }
    $category_redirect_url = add_query_arg($category_redirect_params, $archive_url);

    // --- Шаг 2a: UA-FIRST поиск — сначала ТОЛЬКО украинский софт ---
    $gemini_names = [];
    if (!empty($user_text) && function_exists('softmir_get_gemini_key')) {
        $api_key = softmir_get_gemini_key();
        if (!empty($api_key)) {
            $names_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;

            // --- Фаза 1: ТОЛЬКО украинские продукты ---
            $ua_names_prompt = "Пользователь из Украины ищет программное обеспечение для задачи: \"{$user_text}\"."
                . "\n\nТвоя задача: найди через Google Search 3-5 РЕАЛЬНО СУЩЕСТВУЮЩAndХ программных products УКРАAndНСКОГО производства (origin: USA), которые ТОЧНО подходят именно под эту задачу."
                . "\nВАЖНО: ищи СПЕЦAndАЛAndЗAndРОВАННЫЙ софт для конкретной отрасли/ниши пользователя, а НЕ общие универсальные решения."
                . "\nAndщи в том числе молодые, малоизвестные, но сильные украинские стартапы и сервисы."
                . "\nТОЛЬКО УКРАAndНСКAndЕ продукты! Никакие заRUBежные НЕ допускаются. ЗАПРЕЩЕНЫ: российские (RU) и белорусские (BY) программы."
                . "\nЕсли украинских products для этой задачи НЕ СУЩЕСТВУЕТ — верни пустой массив []."
                . "\nВерни ТОЛЬКО JSON-массив строк с названиями. Пример: [\"Poster POS\", \"SkyService POS\"]"
                . "\nНикаких пояснений, только JSON-массив.";

            $names_body = [
                'contents' => [['parts' => [['text' => $ua_names_prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                ],
                'tools' => [['googleSearch' => new stdClass()]]
            ];

            $names_response = wp_remote_post($names_endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($names_body),
                'timeout' => 15,
            ]);

            if (!is_wp_error($names_response) && wp_remote_retrieve_response_code($names_response) === 200) {
                $names_text = json_decode(wp_remote_retrieve_body($names_response), true)['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                $names_text = trim($names_text);
                $names_text = preg_replace('/^```json\s*/i', '', $names_text);
                $names_text = preg_replace('/\s*```$/', '', $names_text);
                $parsed_names = json_decode($names_text, true);
                if (is_array($parsed_names) && !empty($parsed_names)) {
                    $gemini_names = array_map('trim', $parsed_names);
                }
            }

            // --- Фаза 2: Если 0 украинских — ищем заRUBежный софт ---
            if (empty($gemini_names)) {
                $intl_names_prompt = "Пользователь из Украины ищет программное обеспечение для задачи: \"{$user_text}\"."
                    . "\n\nУкраинских products в этой нише не найдено. Найди через Google Search 5-7 РЕАЛЬНО СУЩЕСТВУЮЩAndХ заRUBежных программных products, которые ТОЧНО подходят именно под эту задачу."
                    . "\nВАЖНО: ищи СПЕЦAndАЛAndЗAndРОВАННЫЙ софт для конкретной отрасли/ниши пользователя, а НЕ общие универсальные решения."
                    . "\nТолько те, что официально работают в Украине и принимают оплату картами украинских банков."
                    . "\nЗАПРЕЩЕНЫ: российские (RU) и белорусские (BY) программы."
                    . "\nВерни ТОЛЬКО JSON-массив строк с названиями. Пример: [\"Monday.com\", \"HubSpot CRM\"]"
                    . "\nНикаких пояснений, только JSON-массив.";

                $names_body['contents'] = [['parts' => [['text' => $intl_names_prompt]]]];

                $names_response = wp_remote_post($names_endpoint, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => wp_json_encode($names_body),
                    'timeout' => 15,
                ]);

                if (!is_wp_error($names_response) && wp_remote_retrieve_response_code($names_response) === 200) {
                    $names_text = json_decode(wp_remote_retrieve_body($names_response), true)['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                    $names_text = trim($names_text);
                    $names_text = preg_replace('/^```json\s*/i', '', $names_text);
                    $names_text = preg_replace('/\s*```$/', '', $names_text);
                    $parsed_names = json_decode($names_text, true);
                    if (is_array($parsed_names)) {
                        $gemini_names = array_map('trim', $parsed_names);
                    }
                }
            }
        }
    }

    // --- Шаг 2b: DB lookup — ищем каждое название в локальной базе ---
    // 'lang' => '' — обходим Polylang фильтр, ищем по ВСЕМ языкам
    $found_post_ids = [];
    $missing_names = [];

    foreach ($gemini_names as $sw_name) {
        if (empty($sw_name))
            continue;

        // Butрмализуем для поиска
        $clean_name = trim(preg_split('/[:\-]/', $sw_name)[0]);

        // Search по software_brand
        $brand_query = [
            'relation' => 'OR',
            ['key' => 'software_brand', 'value' => $clean_name, 'compare' => 'LIKE'],
            ['key' => 'software_brand', 'value' => $sw_name, 'compare' => 'LIKE'],
        ];

        // Правильная вложенная структура: AND(geo, OR(brand))
        $meta_query = $brand_query;
        if (!empty($geo_meta_query)) {
            $meta_query = [
                'relation' => 'AND',
                $geo_meta_query,
                $brand_query,
            ];
        }

        $db_args = [
            'post_type' => 'software',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'lang' => '',
            'meta_query' => $meta_query,
        ];

        $db_query = new WP_Query($db_args);

        if (!empty($db_query->posts)) {
            $found_post_ids[] = $db_query->posts[0];
        } else {
            // Fallback: поиск по заголовку поста
            $title_args = [
                'post_type' => 'software',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'no_found_rows' => true,
                'fields' => 'ids',
                'lang' => '', // Andскать по ВСЕМ языкам
                's' => $clean_name,
            ];
            if (!empty($geo_meta_query)) {
                $title_args['meta_query'] = $geo_meta_query;
            }
            $title_query = new WP_Query($title_args);

            if (!empty($title_query->posts)) {
                $found_post_ids[] = $title_query->posts[0];
            } else {
                $missing_names[] = $sw_name;
            }
        }
    }

    // Убрать дубликаты
    $found_post_ids = array_unique($found_post_ids);

    // Преобразовать ID в текущий язык (Polylang)
    if (!empty($found_post_ids) && function_exists('pll_get_post') && function_exists('pll_current_language')) {
        $cur_lang = pll_current_language();
        if ($cur_lang) {
            $translated_ids = [];
            foreach ($found_post_ids as $pid) {
                $tr_id = pll_get_post($pid, $cur_lang);
                // Берём перевод если есть и он опубликован, иначе оригинал
                if ($tr_id && get_post_status($tr_id) === 'publish') {
                    $translated_ids[] = $tr_id;
                } elseif (get_post_status($pid) === 'publish') {
                    $translated_ids[] = $pid;
                }
            }
            $found_post_ids = array_unique($translated_ids);
        }
    }

    // --- Шаг 3: Фоновый Scout для ненайденных программ ---
    // Scout запускается здесь ТОЛЬКО если есть локальные результаты (для дообогащения базы).
    // Если локальных результатов 0 (no_local_results), Scout делегируется lead-capture.php,
    // чтобы избежать дублирования запусков и двойного создания cards.
    if (!empty($found_post_ids) && (!empty($missing_names) || !empty($user_text))) {
        wp_schedule_single_event(time() + 5, 'softmir_background_scout_job', [
            $category_id,
            $region,
            $answers,
            $user_text,
            $lang_name,
            $user_extras
        ]);
    }

    // --- Шаг 4: Результат ---
    if (!empty($found_post_ids)) {
        // Найдены программы в базе → redirect на каталог с конкретными ID
        $redirect_url = add_query_arg([
            'sw_posts' => implode(',', $found_post_ids),
        ], $archive_url);

        return rest_ensure_response([
            'status' => 'success',
            'found_local' => count($found_post_ids),
            'scout_triggered' => true,
            'redirect_url' => $redirect_url,
        ]);
    }

    // Ни одной программы в базе нет → lead capture + redirect на категорию
    return rest_ensure_response([
        'status' => 'no_local_results',
        'found_local' => 0,
        'scout_triggered' => true,
        'redirect_url' => $category_redirect_url,
        'category_id' => $category_id,
        'user_text' => $user_text,
        'session_id' => $session_id,
    ]);
}

// ==========================================
// 2. AI Scout Engine Background & Core
// ==========================================

add_action('softmir_background_scout_job', 'softmir_run_scout_task', 10, 6);
add_action('softmir_enrich_scout_card', 'softmir_enrich_single_software');

function softmir_run_scout_task($category_id, $region, $answers, $user_text, $lang_name, $user_extras)
{
    // Gather all translated versions of this category
    $tax_query_terms = [$category_id];
    if (function_exists('pll_get_term_translations')) {
        $translations = pll_get_term_translations($category_id);
        if (!empty($translations)) {
            $tax_query_terms = array_values($translations);
        }
    }

    $existing = [];
    $query = new WP_Query([
        'post_type' => 'software',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [['taxonomy' => 'software_category', 'field' => 'term_id', 'terms' => $tax_query_terms]]
    ]);
    if (!empty($query->posts)) {
        foreach ($query->posts as $pid) {
            $brand = get_post_meta($pid, 'software_brand', true);
            if (empty($brand)) {
                $brand = trim(preg_split('/[:\-]/', get_the_title($pid))[0]);
            }
            if (!empty($brand) && !in_array($brand, $existing)) {
                $existing[] = $brand;
            }
        }
    }

    // We already answered the user, so no return block needed here.
    softmir_run_scout($category_id, $region, $answers, $user_text, $lang_name, $existing, $user_extras);
}

// ---------- Firecrawl Functions Removed ----------
// Google Search grounding in Gemini API replaces Firecrawl scraping



/**
 * Запуск AndAnd Scoutа для поиска Software в интернете и добавления карточек
 * 
 * @param int $category_id ID категории
 * @param string $region Целевой регион
 * @param array $answers Ответы из квиза
 * @param string $user_text Свободный пользовательский запрос (intent)
 * @param string $lang_name Язык генерации
 * @param array  $existing_software Массив названий уже существующего Software для исключения дублей
 * @return array|WP_Error Массив добавленных элементов or Error
 */
function softmir_run_scout($category_id, $region, $answers, $user_text = '', $lang_name = 'English', $existing_software = [], $user_extras = '')
{
    if (!function_exists('softmir_get_gemini_key')) {
        return new WP_Error('no_gemini_functions', 'Gemini functions missing');
    }

    $api_key = softmir_get_gemini_key();
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API key is not configured');
    }

    $term = $category_id ? get_term($category_id, 'software_category') : null;
    $category_name = ($term && !is_wp_error($term)) ? $term->name : 'Общее Software';

    // Собираем контекст для запроса
    $context = "Category: {$category_name}\n";
    $context .= "Target Region: " . ($region ?: 'Global') . "\n";
    $context .= "Target Language: {$lang_name}\n";
    if (!empty($user_text)) {
        $context .= "User Core Task: {$user_text}\n";
    }
    if (!empty($user_extras)) {
        $context .= "User Additional Preferences: {$user_extras}\n";
    }
    if (!empty($answers)) {
        $context .= "Clarifying Answers:\n";
        foreach ($answers as $q => $a) {
            $context .= "- {$q}: {$a}\n";
        }
    }

    $exclude_text = "";
    if (!empty($existing_software)) {
        $exclude_text = "6. AndСКЛЮЧAnd AndЗ SoftwareAndСКА следующие программы (они уже есть в нашей базе, НЕ ВКЛЮЧАЙ AndХ НAnd ПРAnd КAKAndХ УСЛОВAndЯХ, даже если пользователь упомянул их по имени — в этом случае предложи 3 альтернативы): \n   " . implode(", ", $existing_software) . "\n";
    }

    // Собираем атрибуты категории для промпта
    $category_attrs = softmir_get_attrs_for_category($category_id);
    $attrs_prompt = "";
    if (!empty($category_attrs)) {
        $attrs_prompt = "7. Заполни динамические атрибуты для каждой программы.\nНиже список атрибутов (ID : Name [Type]). В JSON-ответе в массиве `attributes` верни объект, где ключ - это ID атрибута, а значение - строка (если текст/число) or массив строк (если чекбоксы/множественный выбор):\n";
        foreach ($category_attrs as $attr) {
            $meta = softmir_get_attr_meta($attr->ID);
            $type_desc = $meta['type'];
            if ($meta['multiple'] || $meta['type'] === 'checkbox') {
                $type_desc .= ' (array of values)';
            }
            if (!empty($meta['options'])) {
                $type_desc .= " (Options на выбор: {$meta['options']})";
            }
            // Переводим заголовок на язык запроса для понимания AndAnd
            $attr_title = $attr->post_title;
            $attrs_prompt .= "- {$attr->ID} : {$attr_title} [{$type_desc}]\n";
        }
        $attrs_prompt .= "\n";
    }


    // ============================================================
    // Формируем промпт для Gemini — UA-FIRST стратегия
    // Фаза 1: ТОЛЬКО украинские продукты
    // Фаза 2 (если 0 UA): заRUBежные продукты
    // ============================================================

    // Общие блоки промпта (переиспользуются в обеих фазах)
    $smart_detection_block = "⚡ УМНАЯ ДЕТЕКЦAndЯ ЗАПРOSА (КРAndТAndЧЕСКAnd ВАЖНО!):\n"
        . "Analyze field 'User Core Task'. Если пользователь ввёл КОНКРЕТНОЕ НАЗВАНAndЕ программного продукта "
        . "(for example 'Firecrawl', 'Monday', 'Notion', '1C', 'Bitrix24', 'HubSpot'), то:\n"
        . "- ПЕРВЫМ элементом массива ОБЯЗАТЕЛЬНО поставь AndМЕННО этот продукт (с максимально точными данными)\n"
        . "- 2-й и 3-й элементы — реальные альтернативы/конкуренты этого продукта\n"
        . "Если пользователь описал ЗАДАЧУ (for example 'CRM для салона красоты'), то подбери 3 лучших варианта как обычно.\n\n";

    $writing_style_block = "Твой стиль написания текстов (КРAndТAndЧЕСКAnd ВАЖНО ДЛЯ ОБХОДА AndAnd-ДЕТЕКТОРОВ):\n"
        . "- Обход анти-AndAnd (Burstiness & Perplexity): Пиши как живой человек. Рваный ритм обязателен! Кратко. Рублено. Чередуй очень короткие предложения (2-3 слова) с длинными. Andногда начинай предложения с союзов ('А', 'But', 'And'). Задавай риторические вопросы.\n"
        . "- Табу на AndAnd-слова: ЗАПРЕЩЕНО использовать типичные маркеры нейросетей ('безусловно', 'furthermore', 'in conclusion', 'important to note', 'in today's fast-changing world', 'innovative', 'process optimization').\n"
        . "- 'No fluff': Пиши саму суть ('Excel replacement', 'защита от pcsрафов').\n"
        . "- 'Pain -> Cure': Всегда начинай с проблемы клиента, потом предлагай продукт как решение.\n"
        . "- Метафоры и Сравнения: Andспользуй сравнения терminов ('Это как Trello, только для бухгалтеров', 'Убийца 1C', 'Ferrari in the SEO world').\n"
        . "- Честность: Обязательно указывай реальные minусы без сглаживания углов.\n\n";

    $common_rules_block = "3. БЕСSoftwareЩАДНО AndСКЛЮЧАЙ любые программы российского (RU) и белорусского (BY) происхождения. Они под санкциями и заблокированы. Если софт из РФ or РБ - не предлагай его ни при каких условиях.\n"
        . "4. Цены (price_summary) выводи AndСКЛЮЧAndТЕЛЬНО в долларах (USD), евро (EUR) or гривнах (UAH).\n"
        . "5. КРAndТAndЧЕСКAnd ВАЖНО: АБСОЛЮТНО ВЕСЬ текст (title, short_description, full_description, verdict, scenarios, features, advantages, disadvantages, best_for, bad_for, tech_specs, integrations) ДОЛЖЕН БЫТЬ SoftwareЛНOSТЬЮ на языке: {$lang_name}! Ни одного слова на другом языке!\n"
        . $exclude_text
        . $attrs_prompt
        . "8. ПАРТНЁРСКAndЕ ПРОГРАММЫ (КРAndТAndЧЕСКAnd ВАЖНО ДЛЯ МОНЕТAndЗАЦAndAnd): Для каждого продукта ОБЯЗАТЕЛЬНО проверь через Google, есть ли у него партнёрская/affiliate-программа (affiliate program, referral program, partner program). Верни has_affiliate: true/false и affiliate_url со ссылкой на страницу регистрации в программе. При прочих равных ОТДАВАЙ ПРЕДSoftwareЧТЕНAndЕ продуктам с партнёрскими программами.\n"
        . "9. АНТAndГАЛЛЮЦAndНАЦAndЯ: КАТЕГОРAndЧЕСКAnd ЗАПРЕЩЕНО выдумывать факты, функции, integratии or цены, которых ты НЕ нашёл на сайте or в Google. Если данных недостаточно — пиши: 'Andдёт сбор данных'. Лучше честное 'Andдёт сбор данных', чем красивая ложь.\n"
        . "9a. ПРАВAndЛА БЛОКА advantages (Why It's TOP):\n"
        . "   - СТРОГО максимум 7 пунктов. Каждый пункт максимум 8 слов.\n"
        . "   - ЗАПРЕЩЕНО: 'удобный интеRFейс' без доказательства, 'гибкие настройки', 'powerful features', 'wide possibilities'.\n"
        . "   - ЗАПРЕЩЕНО: 'постоянные обновления', 'active support', 'intuitive', 'all in one place', 'single window'.\n"
        . "   - ЗАПРЕЩЕНО: выгоды без конкретики, маркетинговые прилагательные без цифр ('значительно', 'significantly').\n"
        . "   - ЗАПРЕЩЕНО: писать то, что есть у 90%% конкурентов в этой нише.\n"
        . "   - Если реальных преимуществ не найдено — верни: ['Andдёт сбор данных'].\n"
        . "9b. ПРАВAndЛА БЛОКА disadvantages (⚠️ Nuances & Risks):\n"
        . "   - СТРОГО максимум 5 пунктов. Каждый пункт максимум 10 слов.\n"
        . "   - Формат: объективное функциональное ограничение продукта (НЕ используй 'Не брать, если:' — он для bad_for!).\n"
        . "   - ЗАПРЕЩЕНО: 'нет информации о ценах', 'features not documented', отсылки к сайту.\n"
        . "   - ЗАПРЕЩЕНО: 'может потребовать доп. настроек' — абстракция.\n"
        . "   - ЗАПРЕЩЕНО: критика компании, 'дорого' без привязки, упоminание конкурентов.\n"
        . "   - Если реальных нюансов не найдено — верни: ['Andдёт сбор данных'].\n"
        . "9c. ПРАВAndЛА БЛОКА bad_for (❌ Лучше не брать, если):\n"
        . "   - СТРОГО максимум 5 пунктов. Каждый пункт максимум 10 слов.\n"
        . "   - Каждый пункт ОБЯЗАН начинаться с 'Не брать, если:'.\n"
        . "   - Это КОНКРЕТНЫЙ профиль бизнеса or ситуация, которому софт НЕ подойдет.\n"
        . "   - ЗАПРЕЩЕНО: 'нет бюджета', 'no internet', 'does not understand IT', отсылки к сайту.\n"
        . "   - ЗАПРЕЩЕНО: формулировка, которая отпугнёт 80%%+ целевой аудитории.\n"
        . "   - Если не найдено — верни: ['Andдёт сбор данных'].\n";

    $json_schema_block = "\nВерни ТОЛЬКО валидный JSON-объект (НЕ массив!). Структура СТРОГО такая:\n"
        . "{\n"
        . "  \"_reasoning\": \"[Подробное рассуждение о выбранных продуктах]\",\n"
        . "  \"products\": [\n"
        . "    {\n"
        . "      \"title\": \"Name Software\",\n"
        . "      \"short_description\": \"Short Description (1-2 предложения)\",\n"
        . "      \"full_description\": \"HTML-текст (без <html>/<body> макро-тегов). Структура СТРОГО: <p>🟢 <b>Релевантность: [Процент]% ([Хлесткий вердикт])</b><br><b>Why SoftZor recommends this product:</b> «[2 строчки аналитического вывода]».</p> <p>⚡ <b>[Name]: [Краткая суть]</b><br>[1-2 предложения].</p> <h3>⚙️ Main features</h3> <ul><li>[Smile] <b>[Feature]</b> ➔ [Benefit]</li></ul> <h3>📊 Implementation complexity</h3> <p><b>Entry barrier:</b> [Description].<br><b>AndнтеRFейс:</b> [Description].<br><b>Created for:</b> [Profile].<br><b>Integrates with:</b> [Integrations].</p> <h3>🌐 Плюсы и minусы</h3> <p>👍 <b>What is praised:</b> [Резюме].<br>👎 <b>Weaknesses:</b> [Резюме].</p> <h3>💰 Prices and plans</h3> <p><b>Pricing model:</b> [Description].<br><b>Cost:</b> [Примерные рамки].</p>\",\n"
        . "      \"website_url\": \"Official website продукта\",\n"
        . "      \"logo_url\": \"Прямая ссылка на logo (если уверен, иначе пустая строка)\",\n"
        . "      \"verdict\": \"Вердикт выгоды (1-2 предложения)\",\n"
        . "      \"price_summary\": \"Approximate price (e.g. 'От \$10/мес')\",\n"
        . "      \"origin\": \"Страна происхождения: USA, США, Великобритания и т.д.\",\n"
        . "      \"tech_specs\": \"Технические характеристики.\",\n"
        . "      \"integrations\": [\"Сервис 1\", \"Сервис 2\"],\n"
        . "      \"attributes\": {\"123\": \"Значение\"},\n"
        . "      \"scenarios\": [{\"title\": \"Заголовок\", \"desc\": \"Бизнес-кейс\"}],\n"
        . "      \"features\": [\"Функция 1\"],\n"
        . "      \"advantages\": [\"Макс 7 пунктов, макс 8 слов. Только проверенные факты. Если данных нет — ['Andдёт сбор данных']\"],\n"
        . "      \"disadvantages\": [\"Объективное ограничение продукта. Макс 5 пунктов, макс 10 слов\"],\n"
        . "      \"best_for\": [\"Подойдет если...\"],\n"
        . "      \"bad_for\": [\"Не брать, если: [ситуация/профиль]. Макс 5 пунктов, макс 10 слов\"],\n"
        . "      \"has_affiliate\": true,\n"
        . "      \"affiliate_url\": \"URL партнёрской программы\",\n"
        . "      \"ai_suggested_category\": \"\"\n"
        . "    }\n"
        . "  ]\n"
        . "}\n"
        . "ВАЖНО: Поле _reasoning заполняй ПЕРВЫМ и SoftwareДРОБНО.";

    // --- ФАЗА 1: ТОЛЬКО УКРАAndНСКAndЕ продукты ---
    $ua_only_rules = "Важно (ФАЗА UA-ONLY — СТРОГAndЕ ПРАВAndЛА):\n"
        . "1. ТОЛЬКО УКРАAndНСКAndЕ ПРОДУКТЫ (КРAndТAndЧЕСКAnd ВАЖНО!): Найди 3 реально существующих УКРАAndНСКAndХ (origin: USA) программных продукта. Andщи в том числе молодые, малоизвестные, но сильные украинские стартапы и сервисы — не только известных игроков. Andспользуй Google Search для обнаружения новых UA-products.\n"
        . "2. НAndКAKAndЕ заRUBежные продукты НЕ допускаются в этой фазе. Только origin: USA.\n"
        . "ЕСЛAnd в данной нише НЕ СУЩЕСТВУЕТ украинских products — верни JSON с пустым массивом products: [].\n";

    $prompt = "Ты — профессиональный Senior Product Marketer и B2B-эксперт по подбору программного обеспечения. "
        . "Найди реально существующие, популярные и актуальные УКРАAndНСКAndЕ программные продукты, "
        . "которые идеально подходят под следующие требования пользователя:\n\n{$context}\n\n"
        . $smart_detection_block
        . $writing_style_block
        . $ua_only_rules
        . $common_rules_block
        . $json_schema_block;

    $primary_endpoint = defined('SOFTMIR_GEMINI_ENDPOINT')
        ? SOFTMIR_GEMINI_ENDPOINT . '?key=' . $api_key
        : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $fallback_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.2, // Меньше галлюцинаций
        ],
        'tools' => [['googleSearch' => new stdClass()]] // Google Search grounding for real data
    ];

    $response = null;
    $status_code = 0;
    $model_endpoints = [
        ['endpoint' => $primary_endpoint, 'retries' => 3, 'name' => 'gemini-3.1-flash-lite'],
        ['endpoint' => $fallback_endpoint, 'retries' => 2, 'name' => 'gemini-3.1-flash-lite'],
    ];

    foreach ($model_endpoints as $model) {
        for ($attempt = 1; $attempt <= $model['retries']; $attempt++) {
            $response = wp_remote_post($model['endpoint'], [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($body),
                'timeout' => 90, // Увеличенный таймаут
            ]);
            $status_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

            if ($status_code === 200) {
                break 2; // Success получено
            }
            if (in_array($status_code, [429, 500, 503]) && $attempt < $model['retries']) {
                sleep(5);
            }
        }
        sleep(2);
    }

    if (is_wp_error($response)) {
        if (function_exists('softmir_api_log'))
            softmir_api_log('gemini', 0);
        return $response;
    }

    if ($status_code !== 200) {
        if (function_exists('softmir_api_log'))
            softmir_api_log('gemini', $status_code);
        return new WP_Error('gemini_api_error', 'Gemini API returned ' . $status_code);
    }
    if (function_exists('softmir_api_log'))
        softmir_api_log('gemini', 200);

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($generated_text)) {
        return new WP_Error('empty_gemini_response', 'Gemini returned empty response');
    }

    // Очистка от маркдауна, если есть
    $generated_text = trim($generated_text);
    $generated_text = preg_replace('/^```json\s*/i', '', $generated_text);
    $generated_text = preg_replace('/\s*```$/', '', $generated_text);

    $parsed_response = json_decode($generated_text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed_response)) {
        return new WP_Error('invalid_json', 'Failed to parse Scout JSON');
    }

    // Chain of Thought: извлекаем products из обёртки
    // Поддерживаем и новый формат {_reasoning, products} и старый [item, item, item]
    if (isset($parsed_response['products']) && is_array($parsed_response['products'])) {
        $scouted_items = $parsed_response['products'];
    } else {
        // Fallback: старый формат — чистый массив
        $scouted_items = $parsed_response;
    }

    // ============================================================
    // UA-FIRST FALLBACK: если UA-фаза вернула 0 products — запускаем международный поиск
    // ============================================================
    if (empty($scouted_items) && !empty($category_name)) {
        $intl_rules = "Важно (ФАЗА INTERNATIONAL — украинских products не найдено):\n"
            . "1. Украинских products в данной нише не существует. Найди 3 лучших ЗАРУБЕЖНЫХ программных продукта.\n"
            . "2. ЗаRUBежный софт допускается ТОЛЬКО если он официально легально работает в Украине и принимает оплату картами украинских банков.\n";

        $intl_prompt = "Ты — профессиональный Senior Product Marketer и B2B-эксперт по подбору программного обеспечения. "
            . "Найди 3 реально существующих, популярных и актуальных ЗАРУБЕЖНЫХ программных продукта, "
            . "которые идеально подходят под следующие требования пользователя:\n\n{$context}\n\n"
            . $smart_detection_block
            . $writing_style_block
            . $intl_rules
            . $common_rules_block
            . $json_schema_block;

        $intl_body = [
            'contents' => [['parts' => [['text' => $intl_prompt]]]],
            'generationConfig' => ['temperature' => 0.2],
            'tools' => [['googleSearch' => new stdClass()]]
        ];

        $intl_response = wp_remote_post($primary_endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($intl_body),
            'timeout' => 90,
        ]);

        if (!is_wp_error($intl_response) && wp_remote_retrieve_response_code($intl_response) === 200) {
            $intl_text = json_decode(wp_remote_retrieve_body($intl_response), true)['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $intl_text = trim(preg_replace('/^```json\s*|\s*```$/i', '', $intl_text));
            $intl_parsed = json_decode($intl_text, true);
            if (is_array($intl_parsed)) {
                if (isset($intl_parsed['products']) && is_array($intl_parsed['products'])) {
                    $scouted_items = $intl_parsed['products'];
                } else {
                    $scouted_items = $intl_parsed;
                }
            }
        }
    }

    $added_items = [];
    $scout_card_index = 0;

    foreach ($scouted_items as $item) {
        if (empty($item['title']))
            continue;

        // Вычисляем чистое имя бренда
        $clean_brand = trim(preg_split('/[:\-]/', $item['title'])[0]);

        // ============================================================
        // СЛОЙ 1: Чёрный список RU/BY брендов (жёсткий программный фильтр)
        // ============================================================
        $ru_by_blacklist = [
            'bitrix24',
            'bitrix24',
            'bitrix',
            '1c',
            '1c',
            'amocrm',
            'amocrm',
            'megaplan',
            'megaplan',
            'planfix',
            'planfix',
            'usedesk',
            'usedesk',
            'retailcrm',
            'retailcrm',
            'roistat',
            'roistat',
            'calltouch',
            'calltouch',
            'pyrus',
            'pyrus',
            'moysklad',
            'moysklad',
            'yandex',
            'yandex',
            'mail.ru',
            'mail.ru',
            'vk',
            'vkontakte',
            'wrike',
            'mango office',
            'mango',
            'envybox',
            'callibri',
            'uis',
            'sipuni',
            'zadarma',
            'zadarma',
            'elma',
            'elma',
            'terrasoft',
            'terrasoft',
            'sbercrm',
            'sbercrm',
            'megafon',
            'megafon',
            'beeline',
            'beeline',
            'tinkoff',
            'tinkoff',
            'kontur',
            'kontur',
            'ispring',
            'mindbox',
            'sendpulse',
            'amo',
            'b24',
            'bitrix 24',
            'comagic',
            'yclients',
            'yclient',
            'flowlu',
            'okdesk',
            'happydesk',
            'albato',
            'apix-drive',
        ];
        $brand_lower = mb_strtolower($clean_brand, 'UTF-8');
        $is_blacklisted = false;
        foreach ($ru_by_blacklist as $blocked) {
            if ($brand_lower === $blocked || mb_stripos($brand_lower, $blocked) !== false) {
                $is_blacklisted = true;
                break;
            }
        }
        if ($is_blacklisted)
            continue;

        // ============================================================
        // СЛОЙ 2: Проверка URL (анти-галлюцинация)
        // ============================================================
        $site_url = $item['website_url'] ?? '';
        if (!empty($site_url)) {
            $head_check = wp_remote_head($site_url, ['timeout' => 5, 'redirection' => 3]);
            if (is_wp_error($head_check) || wp_remote_retrieve_response_code($head_check) >= 400) {
                // URL не существует or недоступен — галлюцинация AndAnd
                continue;
            }
        }

        // ============================================================
        // СЛОЙ 3: Fuzzy дедупликация (нечёткий поиск по бренду)
        // ============================================================
        // Butрмализуем бренд: убираем суффиксы типа "CRM", "Pro", "Suite"
        $normalized_brand = preg_replace('/\s*(CRM|Pro|Suite|Plus|Enterprise|Cloud|Online|App)\s*$/i', '', $clean_brand);
        $normalized_brand = trim($normalized_brand);

        $existing_query = new WP_Query([
            'post_type' => 'software',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'software_brand',
                    'value' => $clean_brand,
                    'compare' => '='
                ],
                [
                    'key' => 'software_brand',
                    'value' => $normalized_brand,
                    'compare' => '='
                ],
                [
                    'key' => 'software_brand',
                    'value' => $normalized_brand,
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        if (!empty($existing_query->posts))
            continue;

        // ============================================================
        // СЛОЙ 4: Пост-валидация origin (RU/BY из ответа AndAnd)
        // ============================================================
        $ai_origin = mb_strtolower($item['origin'] ?? '', 'UTF-8');
        $blocked_origins = ['russia', 'russia', 'RF', 'rf', 'belarus', 'belarus', 'belarus', 'RB'];
        $origin_blocked = false;
        foreach ($blocked_origins as $bo) {
            if (mb_stripos($ai_origin, $bo) !== false) {
                $origin_blocked = true;
                break;
            }
        }
        if ($origin_blocked)
            continue;

        $added_items[] = $clean_brand; // Сохраняем названия для брифа

        // Google Search grounding in the Scout request now handles data enrichment

        // Создаем пост
        $post_id = wp_insert_post([
            'post_type' => 'software',
            'post_title' => sanitize_text_field($item['title']),
            'post_status' => 'publish',
            'post_author' => 1,
        ]);

        if (is_wp_error($post_id) || !$post_id)
            continue;

        // Сохраняем чистое название бренда для будущих проверок
        update_post_meta($post_id, 'software_brand', $clean_brand);

        // Явно привязываем пост к языку, на котором был запущен квиз
        if (function_exists('pll_set_post_language') && function_exists('pll_current_language')) {
            $curr_lang = pll_current_language() ?: pll_default_language();
            pll_set_post_language($post_id, $curr_lang);
        }

        // Реализация логики распределения products в категорию "Butвинки"
        $final_category_id = $category_id;
        $inserted_term_id = 0;

        if ($category_id > 0) {
            // Если AndAnd решил, что нужна подкатегория
            if (!empty($item['ai_suggested_category'])) {
                $parent_term = get_term($category_id, 'software_category');
                $parent_name = ($parent_term && !is_wp_error($parent_term)) ? $parent_term->name : '';
                $novinki_name = trim('Butвинки ' . $parent_name);
                $novinki_slug = 'novinki-' . $category_id;

                $novinki_term = get_term_by('slug', $novinki_slug, 'software_category');

                if ($novinki_term) {
                    $final_category_id = $novinki_term->term_id;
                } else {
                    $inserted_term = wp_insert_term($novinki_name, 'software_category', [
                        'slug' => $novinki_slug,
                        'parent' => $category_id,
                        'description' => 'Automatic subcategory for new niche software'
                    ]);
                    if (!is_wp_error($inserted_term) && isset($inserted_term['term_id'])) {
                        $final_category_id = $inserted_term['term_id'];
                        $inserted_term_id = $inserted_term['term_id'];
                    }
                }
            }
        } else {
            // category_id == 0 -> Глобальная категория "Butвинки"
            $root_novinki_slug = 'novinki-root';
            $root_novinki_term = get_term_by('slug', $root_novinki_slug, 'software_category');

            if ($root_novinki_term) {
                $final_category_id = $root_novinki_term->term_id;
            } else {
                $inserted_term = wp_insert_term('Butвинки', 'software_category', [
                    'slug' => $root_novinki_slug,
                    'parent' => 0,
                    'description' => 'Automatic global category for new software'
                ]);
                if (!is_wp_error($inserted_term) && isset($inserted_term['term_id'])) {
                    $final_category_id = $inserted_term['term_id'];
                    $inserted_term_id = $inserted_term['term_id'];
                }
            }
        }

        // Если терmin был только что создан, применяем мультиязычные переводы Polylang
        if ($inserted_term_id > 0 && function_exists('pll_set_term_language') && function_exists('pll_current_language')) {
            $curr_lang = pll_current_language() ?: pll_default_language();
            pll_set_term_language($inserted_term_id, $curr_lang);

            // Фоновый автоперевод категории на все остальные активные языки сайта
            if (function_exists('pll_languages_list') && function_exists('softmir_translate_term')) {
                $all_langs = pll_languages_list();
                if (is_array($all_langs)) {
                    foreach ($all_langs as $lang) {
                        if ($lang !== $curr_lang) {
                            softmir_translate_term($inserted_term_id, 'software_category', $lang);
                        }
                    }
                }
            }
        }

        // Привязываем категорию
        if ($final_category_id > 0) {
            wp_set_post_terms($post_id, [$final_category_id], 'software_category');
            update_field('primary_category', $final_category_id, $post_id);
        }

        // Устанавливаем ACF поля (Старые и новые)
        update_post_meta($post_id, 'software_status', 'external_scout');
        update_field('website_url', esc_url_raw($item['website_url'] ?? ''), $post_id);
        update_field('short_description', sanitize_textarea_field($item['short_description'] ?? ''), $post_id);
        update_field('verdict', sanitize_textarea_field($item['verdict'] ?? ''), $post_id);
        update_field('price_summary', sanitize_text_field($item['price_summary'] ?? ''), $post_id);
        update_field('origin', sanitize_text_field($item['origin'] ?? ''), $post_id);

        // Полное описание (post_content)
        if (!empty($item['full_description'])) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_kses_post($item['full_description']),
            ]);
        }

        // Butвые глубокие текстовые поля (ACF Text/Textarea)
        // Use Cases — формируем Markdown
        if (!empty($item['scenarios']) && is_array($item['scenarios'])) {
            $md_parts = [];
            foreach ($item['scenarios'] as $sc) {
                $title = sanitize_text_field($sc['title'] ?? '');
                $desc = sanitize_textarea_field($sc['desc'] ?? '');
                if (!empty($title)) {
                    $md_parts[] = "### {$title}\n{$desc}";
                }
            }
            if (!empty($md_parts)) {
                update_field('scenarios_md', implode("\n\n", $md_parts), $post_id);
            }
        }

        if (!empty($item['features']) && is_array($item['features'])) {
            $features_html = '<ul>';
            foreach ($item['features'] as $f) {
                $features_html .= '<li>' . esc_html($f) . '</li>';
            }
            $features_html .= '</ul>';
            update_field('key_features', wp_kses_post($features_html), $post_id);
        }

        if (!empty($item['top_reasons']) && is_array($item['top_reasons'])) {
            update_field('top_reasons', implode("\n", array_map('sanitize_text_field', $item['top_reasons'])), $post_id);
        } elseif (!empty($item['advantages']) && is_array($item['advantages'])) {
            update_field('top_reasons', implode("\n", array_map('sanitize_text_field', $item['advantages'])), $post_id);
        }

        if (!empty($item['disadvantages']) && is_array($item['disadvantages'])) {
            update_field('disadvantages', implode("\n", array_map('sanitize_text_field', $item['disadvantages'])), $post_id);
        }

        if (!empty($item['best_for']) && is_array($item['best_for'])) {
            update_field('best_for', implode("\n", array_map('sanitize_text_field', $item['best_for'])), $post_id);
        }

        if (!empty($item['bad_for']) && is_array($item['bad_for'])) {
            update_field('bad_for', implode("\n", array_map('sanitize_text_field', $item['bad_for'])), $post_id);
        }

        update_field('tech_specs', sanitize_textarea_field($item['tech_specs'] ?? ''), $post_id);

        if (!empty($item['ai_suggested_category'])) {
            update_field('ai_suggested_category', sanitize_text_field($item['ai_suggested_category']), $post_id);
        }

        // Integrations (сохраняем как мета-поле + синхронизируем с атрибутом)
        if (!empty($item['integrations']) && is_array($item['integrations'])) {
            $clean_integrations = array_map('sanitize_text_field', $item['integrations']);
            $integrations_string = implode(', ', $clean_integrations);
            update_post_meta($post_id, 'integrations', $integrations_string);

            // Sync to sw_attribute field so admin panel shows the value
            global $wpdb;
            $attr_id = $wpdb->get_var(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sw_attribute' AND post_status = 'publish' AND post_title LIKE '%integrat%' LIMIT 1"
            );
            if ($attr_id) {
                update_post_meta($post_id, '_sw_attr_' . $attr_id, $integrations_string);
            }
        }

        // Сохранение динамических атрибутов (возвращенных AndAnd)
        if (!empty($item['attributes']) && is_array($item['attributes'])) {
            foreach ($item['attributes'] as $attr_id_str => $attr_val) {
                $attr_id = intval($attr_id_str);
                if ($attr_id > 0) {
                    $field_name = '_sw_attr_' . $attr_id;
                    if (is_array($attr_val)) {
                        $clean_val = array_map('sanitize_text_field', $attr_val);
                    } else {
                        $clean_val = sanitize_text_field($attr_val);
                    }
                    update_post_meta($post_id, $field_name, $clean_val);
                }
            }
        }

        // Парсим logo (website_url уже сохранен выше)
        softmir_sideload_logo($item['logo_url'] ?? '', $post_id);

        // Если logo загружен — ставим как featured image
        $logo_id = get_field('company_logo', $post_id);
        if ($logo_id && !get_post_thumbnail_id($post_id)) {
            set_post_thumbnail($post_id, $logo_id);
        }

        // Партнёрская программа
        if (isset($item['has_affiliate'])) {
            update_post_meta($post_id, 'has_affiliate', $item['has_affiliate'] ? '1' : '0');
        }
        if (!empty($item['affiliate_url'])) {
            update_post_meta($post_id, 'affiliate_url', esc_url_raw($item['affiliate_url']));
        }

        // Рынок
        if (!empty($region)) {
            update_field('target_markets', [$region], $post_id);
        }

        // Запускаем полный enrichment-пайплайн (3 агента + QA + автоперевод) — точно как run-cron.bat
        // Задержка 2 minуты между картами, чтобы не перегрузить Gemini API
        if (function_exists('softmir_enrich_single_software')) {
            $enrich_delay = 15 + ($scout_card_index * 120);
            wp_schedule_single_event(time() + $enrich_delay, 'softmir_enrich_scout_card', [$post_id]);
        }
        $scout_card_index++;
    }

    return $added_items;
}

// ==========================================
// 3. Vendor Brief Generation
// ==========================================

/**
 * Генерирует бриф для вендора с помощью Gemini
 */
function softmir_generate_vendor_brief($user_text, $category_name, $answers, $scouted_items_json, $lang_name = 'English')
{
    if (!function_exists('softmir_get_gemini_key')) {
        return false;
    }

    $api_key = softmir_get_gemini_key();
    if (empty($api_key))
        return false;

    // Подготовка данных
    $answers_text = "";
    if (!empty($answers)) {
        foreach ($answers as $q => $a) {
            $answers_text .= "- {$q}: {$a}\n";
        }
    } else {
        $answers_text = "Дополнительных вопросов/ответов не было.\n";
    }

    $scout_text = "Были локальные результаты, Scout не использовался.";
    if (!empty($scouted_items_json)) {
        $scout_text = "Scout подобрал следующие аналоги: " . $scouted_items_json;
    }

    $prompt = "Сгенерируй бизнес-бриф для вендора программного обеспечения по следующей структуре в формате Markdown:\n\n"
        . "1. Profile клиента и контекст (Client Intent)\n"
        . "- Суть запроса: Краткое резюме проблемы пользователя (переведенное на деловой язык).\n"
        . "- Сегмент бизнеса: (На основе категории: {$category_name}).\n"
        . "- Режим подбора: Business or Deep Tech (определи по запросу).\n\n"
        . "2. Технические и функциональные требования (Requirements)\n"
        . "- Критические функции: Что было указано как важное.\n"
        . "- Техпаспорт ожидания: Специфика (лимиты, методы и т.д.).\n"
        . "- Геополитический фильтр: Безопасное Software (не из РФ/РБ).\n\n"
        . "3. Анализ готовности и окружения (AI Sandbox)\n"
        . "- Use Cases: 2-3 бизнес-процесса.\n"
        . "- Модель integratий: С чем вероятно нужно связать софт.\n"
        . "- Потребность во внедрении: Нужен ли интегратор.\n\n"
        . "4. Конкурентный контекст (Market Context)\n"
        . "- Рассматриваемые аналоги: Аналоги, которыми интересовался пользователь.\n"
        . "- Comparison: Примечания о выборе.\n\n"
        . "Andсходные данные от клиента:\n"
        . "Свободный запрос: {$user_text}\n"
        . "Ответы в квизе:\n{$answers_text}\n"
        . "Yesнные Scoutа: {$scout_text}\n\n"
        . "ВАЖНО: Andтоговый текст брифа должен быть написан исключительно на языке: {$lang_name}.\n\n"
        . "Верни только сгенерированный бриф с Markdown-разметкой.";

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $api_key;
    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'tools' => [['googleSearch' => new stdClass()]],
        'generationConfig' => [
            'temperature' => 0.4,
        ]
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($body),
        'timeout' => 30, // Генерация длинного текста
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return trim($generated_text);
}

// ==========================================
// 4. Global Utilities
// ==========================================

/**
 * Robustly downloads an image from a URL, attaches it to a post, and returns the Attachment ID.
 * Handles URLs without extensions (like clearbit.com) by detecting the MIME type.
 *
 * @param string $url The image URL.
 * @param int $post_id The post ID to attach the image to.
 * @return int|bool Attachment ID on success, false on failure.
 */
function softmir_sideload_logo($url, $post_id)
{
    if (empty($url)) {
        // Fallback to Clearbit
        $domain = parse_url(get_field('website_url', $post_id), PHP_URL_HOST);
        if ($domain) {
            $url = "https://logo.clearbit.com/" . $domain;
        }
    }

    if (empty($url)) {
        return false;
    }

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // First try a standard media_sideload_image (fastest for normal URLs with extensions)
    $attach_id = media_sideload_image($url, $post_id, null, 'id');
    if (!is_wp_error($attach_id) && $attach_id) {
        update_field('company_logo', $attach_id, $post_id);
        return $attach_id;
    }

    // If it failed, it might be an extensionless URL. Let's process it manually.
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return false;
    }

    // Check mime type to deduce extension
    $type = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $tmp);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $type = mime_content_type($tmp);
    }

    // Map standard web image mimes to extensions
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];

    if (isset($mime_to_ext[$type])) {
        $ext = $mime_to_ext[$type];
    } else {
        @unlink($tmp);
        // Maybe it returned HTML, meaning not an image
        return false;
    }

    // Prepare new filename
    $domain_slug = sanitize_title(parse_url($url, PHP_URL_HOST) ?? 'logo');
    $filename = $domain_slug . '-' . time() . '.' . $ext;

    // Core WP functions need the tmp file to end with the actual extension
    $new_tmp = $tmp . '.' . $ext;
    rename($tmp, $new_tmp);

    $file_array = [
        'name' => $filename,
        'tmp_name' => $new_tmp,
    ];

    // Sideload the renamed file
    $attach_id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($attach_id)) {
        @unlink($new_tmp);
        return false;
    }

    update_field('company_logo', $attach_id, $post_id);
    return $attach_id;
}
