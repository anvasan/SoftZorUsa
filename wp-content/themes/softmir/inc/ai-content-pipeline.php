<?php
/**
 * AI Content Pipeline - Unified module for generating software cards
 *
 * 4-step pipeline:
 *   1. Analyst - collecting invoices and JSON
 *   2. Copywriter - generating HTML full_description
 *   3. Editor - water extraction, HTML checking
 *   4. Critic (QA) - quality assessment, with score < 6 → regeneration
 *
 * Used by all channels: admin, crown, quiz.
 *
 * @package SoftMir
 */

if (!defined('ABSPATH')) exit;

/* ========================================================================
 * 1. GENERAL QUALITY RULES (one place for all channels)
 * ======================================================================== */

function softmir_get_quality_rules(): string {
    return
        "ANTI-HALLUCINATION: IT IS PROHIBITED to invent facts. No data → 'Data collection in progress'.\n\n"

        . "═══ SEPARATION OF BLOCKS ═══\n"
        . "advantages/disadvantages = TECHNICAL review (functions, limitations, user reviews).\n"
        . "best_for/bad_for = BUSINESS advice (type of company + situation, WITHOUT repetition of technology).\n\n"

        . "═══ LENGTH AND STYLE OF POINTS (ALL BLOCKS) ═══\n"
        . "Each point is ONE sentence, 10-18 words. Don't blow it up!\n"
        . "EXTENSION PHRASES ARE PROHIBITED: 'which allows', 'which ensures', 'which is ideal', 'significantly speeding up', 'which simplifies', 'which makes it difficult'. Write WITHOUT them - one thought, one sentence.\n"
        . "INTRODUCTION PROHIBITED: 'starting their journey', 'all over the world', 'from a single center'. This is water.\n\n"

        . "RULES advantages (✅ Why is this TOP):\n"
        . "   - Max 7 points. Each 10-18 words.\n"
        . "   - TECHNICAL advantages: functions, capabilities, features.\n"
        . "   - Add a brief CONTEXT of the benefit, but without bloating.\n"
        . "   - CONTEXT TEST: is digital a plus for the industry? 'Deliverability 78%%' - BAD (normal 95%%+), in disadvantages.\n"
        . "   - PROHIBITED: adjectives without facts ('comfortable', 'powerful', 'flexible').\n"
        . "   ✅ 'Generous free plan with 15,000 emails/month, suitable for startups and small businesses.'\n"
        . "   ✅ 'Drag-and-Drop builder for newsletters, websites and chatbots that does not require technical skills.'\n"
        . "   ✅ 'Automation 360 to customize touch sequences based on user behavior.'\n"
        . "   ❌ 'The generous free plan allows you to send up to 15,000 emails per month for up to 500 subscribers, ideal for startups and small businesses getting started in marketing.' (30 words - BLOATED!)\n\n"

        . "RULES disadvantages (⚠️ Nuances and Risks):\n"
        . "   - FROM 3 TO 7 points. MINIMUM 3 MINUSES MANDATORY! Each 10-18 words.\n"
        . "   - RULE OF THE GOLDEN MEAN: Disadvantages should be situational, not fatal.\n"
        . "   - IT IS PROHIBITED to write that the software 'doesn't work', 'freezes' or is 'terrible'. Instead, write for whom or under what conditions this limitation is critical.\n"
        . "   - FORBIDDEN: 'No mention of...', 'No information about...'\n"
        . "   ✅ 'The interface takes time to master due to the large number of professional settings.'\n"
        . "   ✅ 'There are noticeable delays when processing heavy databases (over 100,000 contacts).'\n"
        . "   ✅ 'Technical support is available primarily during business hours and in English.'\n"
        . "   ❌ 'Very complex and overloaded interface.' (Too tough, it's scary!)\n"
        . "   ❌ 'The platform constantly freezes and slows down.' (Fatal flaw, you can’t write something like that!)\n\n"

        . "RULES best_for (🚀 SUITABLE FOR YOU IF):\n"
        . "   - Max 5 points. Each 10-18 words.\n"
        . "   - BUSINESS PROFILE + its NEED. Not product features.\n"
        . "   ✅ 'Small and medium-sized businesses seeking to centralize marketing communications.'\n"
        . "   ✅ 'Online schools and information businessmen for creating and promoting courses.'\n"
        . "   ❌ 'Small and medium business' (no context)\n\n"

        . "RULES bad_for (❌ It’s better not to take if):\n"
        . "   - Max 5 points. Each 10-18 words. WITHOUT the prefix 'Do not take if:' - it is already in the block header.\n"
        . "   - AUDIENCE FILTER: Indicate what size or type of business this is not suitable for (due to redundancy or lack of specific functions).\n"
        . "   - IT IS PROHIBITED to write that the software is bad or buggy. This is a block about the mismatch of needs!\n"
        . "   ✅ 'Microbusinesses and freelancers (the functionality will be redundant and the cost will be high).'\n"
        . "   ✅ 'Large Enterprise business that requires individual modification of the system core to suit its processes.'\n"
        . "   ❌ 'You need a reliable system without bugs.' (This is anti-advertising!)\n\n"

        . "RULES scenarios:\n"
        . "   - Max 3 points. Format: STORYTELLING (Stories from working life). Formula: 'Plot pain -> How software saves the situation'.\n"
        . "   - PROHIBITED: Write in dry corporate language or simply name functions (For example: 'Disconnected communication channels', 'Network management').\n"
        . "   - WRITE ABOUT PEOPLE AND THEIR EMOTIONS: 'The manager gets confused in the tabs', 'Forgot to put pressure on the client', 'Internet lost during rush hour'.\n"
        . "   ✅ 'The manager is confused in the windows: Clients write to WhatsApp, Telegram and email. Applications get lost in the chaos. The software brings everything into a single window - the manager responds faster and doesn’t forget anything.'\n"
        . "   ✅ 'The deal fell through due to data loss: The history of the agreements remained in the notebook of the sick employee. The built-in CRM stores the entire history of touches, and any colleague will pick up the deal.'\n"
        . "   ❌ 'Manual creation of mailings: Automatic launch of email chains.' (Too dry, this is not a story!)\n\n"

        . "TABOO ON CLICHES: 'Swiss knife', 'single window', 'everything in one place', 'universal tool', 'comprehensive solution', 'single platform', 'flexible', 'scalable', 'market leader'.\n\n"

        . "STYLE: Ragged rhythm. Taboos on 'certainly', 'in addition', 'important to note', 'innovative'. Language of use.\n\n"

        . "═══ LENGTH OF PARAGRAPHS ═══\n"
        . "A paragraph cannot be longer than 3 sentences. Ideal - 2 sentences. Use a choppy rhythm (alternate long and short sentences).\n";
}

/* ========================================================================
 * 2. JSON RESPONSE SCHEME (single)
 * ======================================================================== */

function softmir_get_json_schema(): string {
    return '{
        "title": "[What does + for whom]. It is PROHIBITED to write Reviews/Prices/Reviews. Max 60 characters.",
        "short_description": "Short description 1-2 sentences",
        "price_summary": "Specific price (eg From $10/month). MAX 60 characters!",
        "origin": "Country of origin",
        "tech_specs": "Technical data",
        "integrations": ["Trello", "Slack"],
        "scenarios": [ {"title": "Scenario", "desc": "Description"} ],
        "business_areas_list": [ {"area": "Sphere", "benefit": "Benefit"} ],
        "pricing_list": [ {"name": "Plan 1", "price": "100", "features": "f1, f2"} ],
        "features": ["feature 1"],
        "advantages": ["TECHNICAL advantage of the product (10-18 words). Max 7 points"],
        "disadvantages": ["TECHNICAL limitation of the product from reviews (10-18 words). Max 7 points"],
        "best_for": ["BUSINESS PROFILE + need (10-18 words). Max 5 points"],
        "bad_for": ["BUSINESS PROFILE + reason (10-18 words). WITHOUT prefix. DO NOT duplicate disadvantages. Max 5"],
        "logo_url": "url",
        "verdict": "Benefit verdict: Why it\'s suitable (1-2 sentences)",
        "focus_keyword": "SEO keyword",
        "rank_math_title": "SEO title (about 50-70 characters)",
        "rank_math_description": "SEO description (140-160 characters)",
        "rank_math_permalink": "slug-na-angliyskom",
        "category_key_functions": ["Function 1"],
        "attributes": [{"id": "id", "value": "value"}],
        "external_reviews": [{"source": "Capterra", "rating": "4.5", "text": "Quote", "review_url": "https://..."}],
        "scraping_failed": false,
        "is_referral": false,
        "seo_strategy": {
            "target_audience": "Target Target Portrait",
            "core_pain": "Home pain",
            "tone_of_voice": "Business/Expert",
            "lsi_keywords": ["LSI 1", "LSI 2"]
        }
    }';
}

/* ========================================================================
 * 3. GEMINI API WRAP WITH RETRY AND FALLBACK
 * ======================================================================== */

function softmir_pipeline_gemini_call(string $prompt, array $options = []): array|WP_Error {
    $api_key = function_exists('softmir_get_gemini_key')
        ? softmir_get_gemini_key()
        : (defined('GOOGLE_GEMINI_KEY') ? GOOGLE_GEMINI_KEY : '');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API Key is missing.');
    }

    $temperature = $options['temperature'] ?? 0.1;
    $timeout     = $options['timeout'] ?? 90;
    $use_search  = $options['use_search'] ?? false;

    $body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => $temperature],
    ];
    if ($use_search) {
        $body['tools'] = [['googleSearch' => new stdClass()]];
    }
    if (!empty($options['response_mime'])) {
        $body['generationConfig']['responseMimeType'] = $options['response_mime'];
    }

    // Model fallback chain
    $primary_endpoint = defined('SOFTMIR_GEMINI_ENDPOINT')
        ? SOFTMIR_GEMINI_ENDPOINT . '?key=' . $api_key
        : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $fallback_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;

    $models = [
        ['endpoint' => $primary_endpoint, 'retries' => 3, 'name' => 'gemini-3.1-flash-lite'],
        ['endpoint' => $fallback_endpoint, 'retries' => 2, 'name' => 'gemini-3.1-flash-lite'],
    ];

    $res = null;
    $status_code = 0;

    foreach ($models as $model) {
        for ($attempt = 1; $attempt <= $model['retries']; $attempt++) {
            if ($attempt == $model['retries']) {
                $body['generationConfig']['temperature'] = max(0.05, $temperature - 0.05);
            }
            $res = wp_remote_post($model['endpoint'], [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($body),
                'timeout' => $timeout,
            ]);
            $status_code = is_wp_error($res) ? 0 : wp_remote_retrieve_response_code($res);
            if ($status_code === 200) {
                if ($model['name'] !== 'gemini-3.1-flash-lite' && function_exists('softmir_log_ai_action')) {
                    softmir_log_ai_action("⚠️ Фолбэк: используется {$model['name']}.");
                }
                break 2;
            }
            if (in_array($status_code, [429, 500, 503]) && $attempt < $model['retries']) {
                sleep(5);
            }
        }
        sleep(3);
    }

    if (is_wp_error($res) || $status_code !== 200) {
        $err = is_wp_error($res) ? $res->get_error_message() : $status_code . ' ' . wp_remote_retrieve_body($res);
        return new WP_Error('gemini_api', 'Gemini Error: ' . substr($err, 0, 300));
    }

    // Extract text from all parts (Google Search returns multi-part)
    $data = json_decode(wp_remote_retrieve_body($res), true);
    $text = '';
    foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (isset($part['text'])) {
            $text .= $part['text'];
        }
    }

    if (empty($text) && isset($data['candidates'][0]['finishReason'])) {
        return new WP_Error('empty_response', 'Empty: ' . $data['candidates'][0]['finishReason']);
    }

    return ['text' => $text, 'raw' => $data];
}

/* ========================================================================
 * 4. MAIN 4-STEP PIPLINE
 * ======================================================================== */

/**
 * Generating a complete software card through a 4-step pipeline.
 *
 * @param string $url Product site URL
 * @param string $lang_name Output language (Russian, Ukrainian, etc.)
 * @param array  $options     [
 *   'attrs_prompt' => string, // category attributes prompt
 *   'cat_funcs_prompt' => string, // category function prompt
 *   'post_title' => string, // for logs
 * ]
 * @return array|WP_Error JSON card data
 */
function softmir_run_content_pipeline(string $url, string $lang_name = 'English', array $options = []): array|WP_Error {
    $attrs_prompt     = $options['attrs_prompt'] ?? '';
    $cat_funcs_prompt = $options['cat_funcs_prompt'] ?? '';
    $post_title       = $options['post_title'] ?? $url;
    $post_id          = $options['post_id'] ?? 0;
    $force_refresh    = $options['force_refresh'] ?? 0;
    $step_1_only      = $options['step_1_only'] ?? false;
    $quality_rules    = softmir_get_quality_rules();
    $json_schema      = softmir_get_json_schema();

    // ─── STEP 0: STOP LIST (Blocking RU/BY domains) ───────────────
    if (softmir_check_ru_by_vendor($url, $post_title)) {
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("⛔ ОШAndБКА: Домен or бренд '{$post_title}' ({$url}) найден в RU/BY стоп-листе! Генерация отменена.");
        }
        return new WP_Error('blocked_origin', 'RU/BY origin detected in stoplist or domain.');
    }

    // ─── STEP 1: ANALYST ───────────────────── ──────────────────────
    $ai_json = [];
    $use_cache = false;

    $url_hash = md5($url . '_' . $lang_name);
    $cache_key = '_softzor_ai_facts_' . $url_hash;

    if (!$force_refresh) {
        // Checking the global cache by URL
        $cached_facts = get_option($cache_key);
        
        // If it is not in the global cache, but is in the local cache (legacy support)
        if (empty($cached_facts) && $post_id > 0) {
            $cached_facts = get_post_meta($post_id, '_ai_raw_facts', true);
            if (!empty($cached_facts)) {
                update_option($cache_key, $cached_facts, false); // We are migrating to the global
            }
        }

        if (!empty($cached_facts) && is_array($cached_facts)) {
            $ai_json = $cached_facts;
            $use_cache = true;
            if (function_exists('softmir_log_ai_action')) {
                softmir_log_ai_action("🕵️ Агент 1 (Аналитик): Загружен ГЛОБАЛЬНЫЙ кэш фактуры для '{$post_title}' (Google Search skipped).");
            }
        }
    }

    if (!$use_cache) {
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("🕵️ Агент 1 (Аналитик): Сбор фактуры для '{$post_title}'...");
        }

    $step1_prompt = "ROLE: You are a Senior Product Analyst & Data Miner.\n"
        . "ЦЕЛЬ: Проанализируй контент сайта ({$url}) и извлеки 100% специфицированную фактуру без 'воды'.\n\n"
        . "AndСТОЧНAndК ДАННЫХ: Andспользуй Google Search для получения РЕАЛЬНОЙ информации с {$url}. "
        . "Be sure to look for /pricing, /features, /integrations, /about and real reviews.\n\n"
        . "RULES:\n"
        . "1. If the site is unavailable (Cloudflare, 403, 525, Captcha) - set \"scraping_failed\": true.\n"
        . "2. Определи, является ли {$url} реферальной ссылкой (?ref=, aff=, partner). Если да — \"is_referral\": true.\n"
        . "3. ONLY specifics (names of modules, numbers, real integrations). NO abstraction.\n"
        . "{$quality_rules}\n"
        . "INTERNET SEARCH (IMPORTANT): For external_reviews, price_summary and disadvantages, MUST use Google Search. For reviews, BE SURE to save the source URL (review_url).\n"
        . "SEO:\n"
        . "   - 'Prices', '2024', 'Reviews' are PROHIBITED in headings.\n"
        . "   - focus_keyword: [Product name].\n"
        . "   - rank_math_title: '[Product]: Review [Category] ([Benefit])' (about 50-70 characters).\n"
        . "   - rank_math_description: 140-160 characters, selling description with CTA.\n"
        . "   - rank_math_permalink: SHORT slug in Latin, max 2 words.\n"
        . "UNIQUENESS short_description: DO NOT duplicate the 'Why SoftZor Recommends' block from full_description.\n\n"
        . "STRICT RULES FOR THE UKRAINE MARKET:\n"
        . "1. Exclude software of RU/BY origin (under sanctions).\n"
        . "2. Prices are ONLY in USD, EUR or UAH.\n"
        . "3. ВЕСЬ текст на языке: {$lang_name}!\n"
        . "5. category_key_functions — CHOOSE STRICTLY from the 'FUNCTION RATING', don't make things up.\n\n"
        . ($attrs_prompt ? "АТРAndБУТЫ:\n{$attrs_prompt}\n" : '')
        . ($cat_funcs_prompt ? "КАТЕГОРAndAnd ФУНКЦAndЙ:\n{$cat_funcs_prompt}\n" : '')
        . "Язык: {$lang_name}\n\n"
        . "ОТВЕТ — СТРОГО JSON:\n{$json_schema}";

    $res1 = softmir_pipeline_gemini_call($step1_prompt, ['temperature' => 0.1, 'use_search' => true]);
    if (is_wp_error($res1)) return $res1;

    $ai_json = json_decode(trim(preg_replace('/^```json\s*|\s*```$/i', '', $res1['text'])), true);
    if (!is_array($ai_json)) {
        return new WP_Error('invalid_json', 'Step 1: Invalid JSON. Raw: ' . substr($res1['text'], 0, 300));
    }

    if (!empty($ai_json['scraping_failed'])) {
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("🚫 Site '{$url}' unavailable. Generation aborted.");
        }
        return new WP_Error('scraping_failed', "Website {$url} недоступен (Cloudflare/403/525).");
    }

    // Save to global cache
    update_option($cache_key, $ai_json, false);

    if ($post_id > 0) {
        update_post_meta($post_id, '_ai_raw_facts', $ai_json);
        update_post_meta($post_id, '_ai_raw_facts_date', current_time('mysql'));
    }
    }

    if ($step_1_only) {
        unset($ai_json['seo_strategy']); // Cleanup
        return $ai_json;
    }

    // ─── STEP 2: COPYWRITER ──────────────────── ─────────────────────
    if (function_exists('softmir_log_ai_action')) {
        softmir_log_ai_action("✍️ Агент 2 (Копирайтер): Генерация HTML для '{$post_title}'...");
    }

    $seo_strat = json_encode($ai_json['seo_strategy'] ?? [], JSON_UNESCAPED_UNICODE);
    $factura = json_encode([
        'features'      => $ai_json['features'] ?? [],
        'integrations'  => $ai_json['integrations'] ?? [],
        'price'         => $ai_json['price_summary'] ?? '',
        'advantages'    => $ai_json['advantages'] ?? [],
        'disadvantages' => $ai_json['disadvantages'] ?? [],
        'real_reviews'  => $ai_json['external_reviews'] ?? [],
    ], JSON_UNESCAPED_UNICODE);

    // We attract competitors from the database
    $competitors_prompt = "IMPORTANT: Offer analogues from UKRAINIAN developers, as well as Western solutions THAT ARE CURRENT IN UKRAINE. IT IS STRICTLY PROHIBITED to offer software from the Russian Federation or Belarus.";
    if ($post_id > 0) {
        $db_competitors = softmir_get_category_competitors($post_id, 3);
        if (!empty($db_competitors)) {
            $competitors_prompt = $db_competitors;
        }
    }

    $step2_prompt = "ROLE: Expert B2B Copywriter (at the level of Maxim Ilyakhov).\n"
        . "GOAL: Write a 'Full description' block of the product in strict HTML according to the SoftZor Conversion Standard.\n\n"
        . "=== СТРАТЕГAndЯ (от Analytics) ===\n{$seo_strat}\n"
        . "=== ФAKТЫ ===\n{$factura}\n\n"
        . "RULES:\n"
        . "- Ragged rhythm. Language of benefit. Real terms from the invoice.\n"
        . "- Paragraph MAXIMUM 3 sentences. Ideal - 2.\n"
        . "- Язык: {$lang_name}.\n"
        . "- PROHIBITED: 'Swiss knife', 'indispensable assistant', 'single window', 'everything in one place', 'innovative', 'comprehensive solution', 'flexible', 'scalable', 'market leader'.\n"
        . "- The 'Why SoftZor recommends' block does NOT duplicate short_description.\n"
        . "- Wrap tables in <div class=\"table-responsive\"><table class=\"table table-striped\">...</table></div>.\n\n"
        . "FORMAT (HTML ONLY, no markdown/JSON). Strictly 6 blocks in this order:\n\n"
        . "BLOCK 1 - LEAD (Why SoftZor recommends):\n"
        . "<p>🟢 <b>Relevance: [85-99]% ([Verdict])</b><br><b>Why SoftZor recommends:</b> [3-4 sentences. Describe in detail the essence of the product and the main pain solved. Without words, innovative/comprehensive solution].</p>\n\n"
        . "BLOCK 2 - EXPERT VERDICT SoftZor:\n"
        . "<h3>⚖️ SoftZor expert verdict</h3>\n"
        . "<p><b>Who to take:</b> [1 sentence - a clear company profile].</p>\n"
        . "<p><b>Who should avoid:</b> [1 sentence - for whom it definitely won’t suit].</p>\n"
        . "<p><b>⚡ Entry threshold (Migration complexity):</b> [Evaluate the speed of implementation and the complexity of employee training].</p>\n"
        . "<blockquote><b>💡 Practical advice:</b> [Under what conditions to implement, 1-2 sentences].</blockquote>\n\n"
        . "BLOCK 3 - MAIN FUNCTIONS (Table):\n"
        . "<h3>⚙️ Main functions</h3>\n"
        . "<div class=\"table-responsive\"><table class=\"table table-striped\"><thead><tr><th>Function</th><th>What problem does it solve</th></tr></thead><tbody><tr><td>[Function name]</td><td>[Short phrase up to 10 words]</td></tr></tbody></table></div>\n\n"
        . "BLOCK 4 - MAIN COMPETITORS (Analogs):\n"
        . "<h3>🔄 Main competitors (Analogues)</h3>\n"
        . "<ul><li><b>[Competitor Name]:</b> [Briefly, what it is better at or who it suits better. {$competitors_prompt}].</li></ul>\n\n"
        . "BLOCK 5 - WHAT USERS SAY (Reviews):\n"
        . "<h3>🗣️ What real users say</h3>\n"
        . "<p>[Base it on the real_reviews array from facts or find fresh reviews on Capterra/G2. IMPORTANT: Always write the quotes in {$lang_name}, preserving the original meaning].</p>\n"
        . "<blockquote><b>[Username or source (e.g. Capterra)]:</b> \"[Real quote about a pros or cons of the product, translated to {$lang_name}]\"</blockquote>\n\n"
        . "BLOCK 6 - PRICES AND TARIFFS:\n"
        . "<h3>💰 Prices and tariffs</h3>\n"
        . "<p><b>Pricing model:</b> [Description. If there are no prices - 'See tariffs on the <a href=\"{$url}\" target=\"_blank\">official website</a>'].<br>\n"
        . "<b>Cost:</b> [Price range, if known].<br>\n"
        . "<b>⚠️ Hidden fees:</b> [Indicate what you will have to pay extra for: integrations, support, training, etc. If there is no data - 'No explicit hidden commissions found'].</p>";

    $res2 = softmir_pipeline_gemini_call($step2_prompt, ['temperature' => 0.4, 'use_search' => true]);
    $draft_html = is_wp_error($res2) ? '' : trim($res2['text']);

    // ─── STEP 3: EDITOR ───────────────────── ──────────────────────
    if (!empty($draft_html)) {
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("🧐 Агент 3 (Редактор): Отжим воды для '{$post_title}'...");
        }

        $step3_prompt = "ROLE: Strict Chief Editor.\n"
            . "GOAL: Clear HTML from cliches, cliches, water, and bureaucracy. Leave the meat and benefits.\n\n"
            . "═══ BAN LIST (HARD BAN) ═══\n"
            . "If you see any of these phrases, DELETE or replace with an action verb:\n"
            . "- Innovative\n"
            . "- Integrated solution / Integrated tool\n"
            . "- Universal window / Single window\n"
            . "- Wide range\n"
            . "- Allows automation → replace with 'Automates'\n"
            . "- It is important to note\n"
            . "- Of course\n"
            . "- In the modern world\n"
            . "- Unique product\n"
            . "- Swiss knife\n"
            . "- Everything in one place\n"
            . "- Flexible / Flexible solution\n"
            . "- Scalable\n"
            . "- Market leader\n"
            . "- Dynamically developing\n"
            . "- Will help your business reach a new level\n\n"
            . "Checklist:\n"
            . "1. Apply a BAN LIST - remove or replace ALL prohibited phrases.\n"
            . "2. Evaluative adjectives without evidence → replace with facts.\n"
            . "3. Paragraph MAXIMUM 3 sentences. Longer → break.\n"
            . "4. Check the integrity of the HTML (p, h3, table, ul, li, b, blockquote). Remove ```html.\n"
            . "5. Check that the tables are wrapped in <div class=\"table-responsive\"> and have the class table table-striped.\n"
            . "6. 'Why SoftZor recommends' is MANDATORY in <b>.\n"
            . "7. Язык: {$lang_name}\n\n"
            . "Text:\n{$draft_html}\n\n"
            . "RETURN ONLY THE CLEANED HTML.";

        $res3 = softmir_pipeline_gemini_call($step3_prompt, ['temperature' => 0.1, 'timeout' => 60]);
        if (!is_wp_error($res3)) {
            $clean = preg_replace('/^```html\s*|\s*```$/i', '', trim($res3['text']));
            if (!empty($clean)) $draft_html = $clean;
        }
    }

    $ai_json['full_description'] = $draft_html;

    // ─── STEP 4: CRITIC (QA) ──────────────────── ────────────────────
    if (function_exists('softmir_log_ai_action')) {
        softmir_log_ai_action("🔍 Агент 4 (Критик): Проверка качества для '{$post_title}'...");
    }

    $qa_result = softmir_pipeline_qa_check($ai_json);
    if (!is_wp_error($qa_result)) {
        $score = intval($qa_result['score'] ?? 0);
        $feedback = $qa_result['feedback'] ?? '';
        if (function_exists('softmir_log_ai_action')) {
            softmir_log_ai_action("📊 QA Score: {$score}/10 — {$feedback}");
        }
        $ai_json['_qa_score'] = $score;
        $ai_json['_qa_feedback'] = $feedback;

        // If the critic gave <6, we will regenerate Step 2-3 with feedback
        if ($score < 6 && !empty($factura)) {
            if (function_exists('softmir_log_ai_action')) {
                softmir_log_ai_action("🔄 Перегенерация: QA дал {$score}/10. Reason: {$feedback}");
            }
            $retry_prompt = $step2_prompt . "\n\nОБРАТНАЯ СВЯЗЬ ОТ QA (AndСПРАВЬ!):\n{$feedback}";
            $res2r = softmir_pipeline_gemini_call($retry_prompt, ['temperature' => 0.3, 'use_search' => true]);
            if (!is_wp_error($res2r)) {
                $retry_html = preg_replace('/^```html\s*|\s*```$/i', '', trim($res2r['text']));
                if (!empty($retry_html)) {
                    $ai_json['full_description'] = $retry_html;
                    $ai_json['_qa_score'] = $score . ' → retry';
                }
            }
        }
    }

    unset($ai_json['seo_strategy']); // Cleanup
    return $ai_json;
}

/* ========================================================================
 * 5. QA-CRITIC (quality check)
 * ======================================================================== */

function softmir_pipeline_qa_check(array $ai_data): array|WP_Error {
    $json_payload = wp_json_encode($ai_data, JSON_UNESCAPED_UNICODE);
    $quality_rules = softmir_get_quality_rules();

    $prompt = "ROLE: Strict QA engineer and B2B content editor.\n\n"
        . "ЗАДАЧА: Проверь качество карточки Software. Оцени по этим правилам:\n{$quality_rules}\n\n"
        . "ДАННЫЕ:\n{$json_payload}\n\n"
        . "FORMAT: Strictly JSON with fields:\n"
        . "- 'score': number 1-10 (10 = perfect)\n"
        . "- 'feedback': 1-2 sentences with specific comments\n";

    $res = softmir_pipeline_gemini_call($prompt, [
        'temperature'   => 0.1,
        'timeout'       => 30,
        'use_search'    => false,
        'response_mime' => 'application/json',
    ]);

    if (is_wp_error($res)) return $res;

    $raw = str_replace(['```json', '```'], '', trim($res['text']));
    $result = json_decode(trim($raw), true);

    if (isset($result['score'], $result['feedback'])) {
        return $result;
    }
    return new WP_Error('qa_parse', 'Cannot parse QA JSON');
}

/* ========================================================================
 * 6. STOPLIST & COMPETITORS (Enrichment with business context)
 * ======================================================================== */

/**
 * Checking the domain and brand for presence in the stop list (RU/BY).
 */
function softmir_check_ru_by_vendor(string $vendor_url, string $title = ''): bool {
    // Layer 1: Hard domain check
    $host = parse_url($vendor_url, PHP_URL_HOST);
    if ($host && (preg_match('/\.ru$/i', $host) || preg_match('/\.by$/i', $host))) {
        softmir_log_blocked_vendor($vendor_url, $title, 'domain_ru_by');
        return true;
    }

    // Layer 2: Receipt from the list of brands from the admin panel
    $stoplist_raw = get_option('softzor_ru_stoplist', '');
    if (!empty($stoplist_raw)) {
        $brands = array_map('trim', explode(',', $stoplist_raw));
        // Fallback for newline separated
        if (strpos($stoplist_raw, "\n") !== false && strpos($stoplist_raw, ",") === false) {
             $brands = array_map('trim', explode("\n", $stoplist_raw));
        }
        
        $search_str = mb_strtolower($title . ' ' . $vendor_url);
        foreach ($brands as $brand) {
            if (empty($brand)) continue;
            if (mb_strpos($search_str, mb_strtolower($brand)) !== false) {
                softmir_log_blocked_vendor($vendor_url, $title, 'brand_stoplist: ' . $brand);
                return true;
            }
        }
    }
    return false;
}

function softmir_log_blocked_vendor(string $url, string $title, string $rule): void {
    $log = get_option('softzor_blocked_log', []);
    if (!is_array($log)) $log = [];
    array_unshift($log, [
        'time' => current_time('mysql'),
        'title' => $title,
        'url' => $url,
        'rule' => $rule
    ]);
    // We store only the last 100 records
    if (count($log) > 100) $log = array_slice($log, 0, 100);
    update_option('softzor_blocked_log', $log);
}

/**
 * Receiving a list of analogues from the same category, sorted by benefit.
 */
function softmir_get_category_competitors(int $post_id, int $limit = 3): string {
    $terms = get_the_terms($post_id, 'software_category');
    if (empty($terms) || is_wp_error($terms)) return '';

    $category_id = $terms[0]->term_id;
    
    $args = [
        'post_type' => 'software',
        'post_status' => 'publish',
        'posts_per_page' => 20,
        'post__not_in' => [$post_id],
        'tax_query' => [
            [
                'taxonomy' => 'software_category',
                'field' => 'term_id',
                'terms' => $category_id,
            ]
        ],
        'fields' => 'ids'
    ];
    $competitor_ids = get_posts($args);
    if (empty($competitor_ids)) return '';

    $competitors = [];
    foreach ($competitor_ids as $cid) {
        $is_ref = get_post_meta($cid, 'is_referral', true) ? 1 : 0;
        $origin = get_post_meta($cid, 'origin', true);
        
        $weight = 0;
        if ($is_ref) $weight += 3;
        if (stripos($origin, 'UA') !== false || stripos($origin, 'Ukraine') !== false) $weight += 2;
        elseif (stripos($origin, 'US') !== false || stripos($origin, 'EU') !== false) $weight += 1;

        $competitors[] = [
            'id' => $cid,
            'title' => get_the_title($cid),
            'slug' => get_post_field('post_name', $cid),
            'is_ref' => $is_ref,
            'weight' => $weight
        ];
    }

    // Sort by weight descending
    usort($competitors, function($a, $b) {
        return $b['weight'] <=> $a['weight'];
    });

    $top = array_slice($competitors, 0, $limit);
    
    $result = "Analogues (use ONLY programs from this list, do not invent new ones!):\n";
    foreach ($top as $i => $comp) {
        $num = $i + 1;
        $ref_str = $comp['is_ref'] ? '[Affiliate program: YES]' : '';
        $result .= "{$num}. {$comp['title']} — {$comp['slug']} {$ref_str}\n";
    }
    return $result;
}
