<?php
/**
 * SoftMir — Centralized AI Prompt Templates & API Wrapper
 * 
 * Single source of truth for all AI prompt templates, card HTML structures,
 * quality rules, and the unified Gemini API call function.
 * 
 * When you need to change a heading, style rule, or SEO instruction — change it HERE ONLY.
 */

if (!defined('ABSPATH'))
    exit;

// ======================== CARD HTML TEMPLATES ========================

/**
 * Returns the standardized HTML template for full_description of a software card.
 * Used by: ai-background-enricher (Step 2), admin-autofill, quiz-rest-api (scout).
 *
 * @param string $url Product website URL (for pricing fallback link)
 * @return string HTML template string
 */
function softmir_card_html_template($url = '')
{
    $pricing_fallback = $url
        ? "If there are no exact prices, it is strictly forbidden to write 'Pricing не указаны'. Вместо этого выводи текст: С более подробной информацией о тарифах ознакомьтесь на <a href=\"{$url}\" target=\"_blank\">official website</a>"
        : "If there are no exact prices, it is strictly forbidden to write 'Pricing not specified'";

    return '<p>🟢 <b>Relevance: [Percentage]% ([Harsh verdict])</b><br><b>Why SoftZor recommends this product:</b> “[2 lines of analytical conclusion - WHY this software and not its competitors. DO NOT repeat short_description!].”</p>'
        . ' <p>⚡ <b>[Name]: [Brief summary]</b><br>[1-2 sentences about what this program is and the main result of implementation].</p>'
        . ' <h3>⚙️ Main functions (and why they are needed)</h3>'
        . ' <ul><li>[Smile] <b>[Function]</b> ➔ [Benefit]</li></ul>'
        . ' <h3>📊 Difficulty of implementation</h3>'
        . ' <p><b>Entry threshold:</b> [Easy/Medium/Difficult. Description].<br><b>Interface:</b> [Description].<br><b>For whom it is created:</b> [Business Profile].<br><b>Who is friends with:</b> [Integrations].</p>'
        . ' <h3>🌐Using experience: pros and cons</h3>'
        . ' <p>👍 <b>What users praise:</b> [Summary of the main advantages].<br>👎 <b>Weaknesses:</b> [Summary of typical disadvantages].</p>'
        . ' <h3>💰 Prices and tariffs</h3>'
        . ' <p><b>Payment model:</b> [' . $pricing_fallback . '].<br><b>Cost for business:</b> [Approximate price ranges or packages, if known. Don\'t invent benefits].</p>';
}

// ======================== WRITING STYLE RULES ========================

/**
 * Returns the unified writing style and quality rules for any product-facing prompt.
 * Used by: ai-background-enricher (Steps 1-2), admin-autofill, quiz-rest-api (scout).
 *
 * @param string $lang_name Target language (e.g. 'Russian', 'Ukrainian')
 * @return string Rules text for prompt injection
 */
function softmir_style_rules($lang_name = 'English')
{
    return "Your writing style (CRITICAL):\n"
        . "- Burstiness: jagged rhythm, alternate short sentences (2-3 words) with long ones. Write like a living person.\n"
        . "- Taboo on AI words: 'certainly', 'besides', 'important to note', 'in the modern world', 'innovative', 'Swiss knife', 'indispensable assistant', 'single window', 'everything in one place', 'universal tool'. These phrases are PROHIBITED.\n"
        . "- 'Pain → Medicine': start with the client's problem, then offer the product as a solution.\n"
        . "- Metaphors: Use comparisons of terms ('It's like Trello, but for accountants').\n"
        . "- Honesty: Be sure to indicate real disadvantages.\n"
        . "- КРAndТAndЧЕСКAnd ВАЖНО: АБСОЛЮТНО ВЕСЬ текст ДОЛЖЕН БЫТЬ SoftwareЛНOSТЬЮ на языке: {$lang_name}!\n";
}

// ======================== UKRAINE MARKET RULES ========================

/**
 * Returns the strict Ukraine market compliance rules.
 * Used by: ai-background-enricher, admin-autofill, quiz-rest-api (scout).
 *
 * @param string $lang_name Target language
 * @return string Rules text
 */
function softmir_ukraine_rules($lang_name = 'English')
{
    return "STRICT RULES FOR THE UKRAINE MARKET:\n"
        . "1. Exclude any software of Russian (RU) and Belarusian (BY) origin. They are under sanctions.\n"
        . "2. Display prices ONLY in dollars (USD), euros (EUR) or hryvnia (UAH).\n"
        . "3. КРAndТAndЧЕСКAnd ВАЖНО: АБСОЛЮТНО ВЕСЬ текст ДОЛЖЕН БЫТЬ SoftwareЛНOSТЬЮ на языке: {$lang_name}!\n";
}

// ======================== CONTENT QUALITY RULES ========================

/**
 * Returns content quality constraints for card generation.
 * Used by: ai-background-enricher (Step 1), admin-autofill.
 *
 * @param string $url Product URL
 * @return string Rules text
 */
function softmir_content_quality_rules($url = '')
{
    return "CONTENT QUALITY RULES (CRITICAL):\n"
        . "1. NEVER write 'information not found', 'prices not listed', 'integrations not listed' - most SaaS services have /pricing, /integrations, /features pages. Search the data carefully!\n"
        . "2. disadvantages - write ONLY the real functional limitations of the product. It is PROHIBITED to write 'no information about prices' as a minus!\n"
        . "3. bad_for - a SPECIFIC business profile for which the software is NOT suitable. It is STRICTLY PROHIBITED to mention 'users who search for prices on the site'.\n"
        . "4. integrations - carefully look for the integrations section on the website.\n"
        . "5. price_summary — look for the tariffs/pricing page. Indicate specific prices (eg 'From €29/month'). MAXIMUM 60 characters!\n"
        . "6. INTERNET SEARCH: You MUST use the built-in Google Search (googleSearch) to find real reviews, user experience and open prices. Don't make things up out of your head, SEARCH!\n";
}

// ======================== RANK MATH SEO RULES ========================

/**
 * Returns SEO/Rank Math rules for card generation.
 *
 * @return string Rules text
 */
function softmir_seo_rules()
{
    return "RANK MATH SEO AND HEADLINES:\n"
        . "   - It is PROHIBITED to use the words 'Prices', '2024', 'Reviews' in the header fields.\n"
        . "   - focus_keyword: [Product name].\n"
        . "   - rank_math_title: strictly in the format: '[Product name]: Review [Category] ([Home benefit]) | SoftZor'. MUST end with ' | SoftZor'. (50-60 characters excluding suffix).\n"
        . "   - rank_math_description: 140-160 characters. Selling description for Google search results with a powerful CTA.\n"
        . "   - rank_math_permalink: VERY SHORT link (slug) in Latin, maximum 2 words.\n";
}

// ======================== JSON SCHEMA FOR CARD ========================

/**
 * Returns the JSON output schema for a software card.
 * Used by: admin-autofill, quiz-rest-api (scout).
 *
 * @param string $url Product URL
 * @return string JSON schema text for prompt
 */
function softmir_card_json_schema($url = '')
{
    $html_template = softmir_card_html_template($url);

    return "Return ONLY a valid JSON object:\n"
        . "{\n"
        . "  \"title\": \"[Short Killer Title with benefits]. It is PROHIBITED to write 'Review, Prices, Reviews'.\",\n"
        . "  \"short_description\": \"Short Description (1-2 sentences). MUST NOT duplicate the 'Why SoftZor Recommends' block.\",\n"
        . "  \"full_description\": \"HTML-текст (без <html>/<body> макро-тегов). Структура СТРОГО: {$html_template} Требование: не добавляй лишних фраз типа 'вот структура', только чистый HTML.\",\n"
        . "  \"website_url\": \"{$url}\",\n"
        . "  \"logo_url\": \"Direct link to the logo image (if you know, otherwise empty string)\",\n"
        . "  \"verdict\": \"Benefit verdict: Why it suits the user (1-2 sentences)\",\n"
        . "  \"price_summary\": \"Price (e.g. 'From \$10/month'). MAX 60 characters!\",\n"
        . "  \"origin\": \"Country of origin\",\n"
        . "  \"tech_specs\": \"Technical specifications in general text\",\n"
        . "  \"integrations\": [\"Service 1\", \"Service 2\"],\n"
        . "  \"focus_keyword\": \"commercial SEO keyword for the article\",\n"
        . "  \"rank_math_title\": \"SEO title (50-60 characters, includes Keyword)\",\n"
        . "  \"rank_math_description\": \"SEO description (140-160 characters, includes Keyword, CTA)\",\n"
        . "  \"rank_math_permalink\": \"slug-na-angliyskom\",\n"
        . "  \"category_key_functions\": [\"Function 1\", \"Function 2\"],\n"
        . "  \"custom_features\": [\"Feature 1 (2-5 words)\", \"Feature 2\"],\n"
        . "  \"attributes\": [ {\"id\": \"1302\", \"value\": [\"Choice 1\"]} ],\n"
        . "  \"scenarios\": [{\"title\": \"Title with emoji\", \"desc\": \"A specific business case.\"}],\n"
        . "  \"features\": [\"Key feature 1\"],\n"
        . "  \"advantages\": [\"Plus 1\"],\n"
        . "  \"disadvantages\": [\"Actual functional limitation of the product\"],\n"
        . "  \"best_for\": [\"Suitable for you if...\"],\n"
        . "  \"bad_for\": [\"A specific business profile that will NOT suit\"]\n"
        . "}\n"
        . "Return JSON ONLY, no markdown.";
}

// ======================== KILL-SWITCH: DAILY API LIMIT ========================

/**
 * Check if the daily API call limit has been reached.
 * Returns true if calls are allowed, WP_Error if budget is exhausted.
 * Also sends a one-time admin email alert when the limit is hit.
 *
 * Default limit: 500 calls/day (configurable via softmir_ai_daily_limit option).
 */
function softmir_check_api_budget()
{
    $daily_limit = (int) get_option('softmir_ai_daily_limit', 500);
    if ($daily_limit <= 0) {
        return true; // 0 = unlimited (owner's explicit choice)
    }

    $date = current_time('Y-m-d');
    $counter_key = 'softmir_ai_calls_' . $date;
    $current_count = (int) get_option($counter_key, 0);

    if ($current_count >= $daily_limit) {
        // Send one-time alert
        $alert_key = 'softmir_budget_alert_' . $date;
        if (!get_transient($alert_key)) {
            $admin_email = get_option('admin_email');
            wp_mail(
                $admin_email,
                '[SoftZor] 🚨 AI Kill-Switch: daily limit reached',
                sprintf(
                    "Daily AI call limit has been reached!\n\n"
                    . "Date: %s\nCalls: %d / %d\n\n"
                    . "All AI features (Quiz, Scout, Translator) are temporarily disabled.\n"
                    . "The limit will reset automatically at midnight.\n\n"
                    . "To change the limit: Settings → SoftMir AI → Daily limit.\n"
                    . "To remove the limit: set the value to 0.",
                    $date,
                    $current_count,
                    $daily_limit
                )
            );
            set_transient($alert_key, true, DAY_IN_SECONDS);
        }

        return new WP_Error(
            'budget_exhausted',
            sprintf('AI Kill-Switch: %d daily call limit reached. Reset at midnight.', $daily_limit)
        );
    }

    // Increment counter (autoload=false to avoid bloating)
    update_option($counter_key, $current_count + 1, false);
    return true;
}

// ======================== PROMPT INJECTION DEFENSE ========================

/**
 * Sanitize external content before injecting into AI prompts.
 * Strips hidden HTML, scripts, styles, and known prompt injection patterns.
 *
 * Use this for ANY user-provided or scraped text that goes into a Gemini prompt.
 *
 * @param string $text  Raw text (e.g., scraped website content, user input)
 * @return string       Cleaned text safe for AI consumption
 */
function softmir_sanitize_for_ai($text)
{
    if (empty($text)) {
        return '';
    }

    // 1. Strip HTML tags that can hide malicious instructions
    $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
    $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
    $text = preg_replace('/<noscript[^>]*>.*?<\/noscript>/is', '', $text);

    // 2. Remove elements hidden via inline CSS (display:none, visibility:hidden, opacity:0)
    $text = preg_replace('/<[^>]+(display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0)[^>]*>.*?<\/[^>]+>/is', '', $text);

    // 3. Strip HTML comments (common injection vector)
    $text = preg_replace('/<!--.*?-->/s', '', $text);

    // 4. Remove common prompt injection phrases (case-insensitive)
    $injection_patterns = [
        '/ignore\s+(all\s+)?(previous|above|prior)\s+(instructions?|prompts?|context)/i',
        '/forget\s+(everything|all|your)\s+(above|previous|instructions?)/i',
        '/you\s+are\s+now\s+a/i',
        '/new\s+instructions?\s*:/i',
        '/system\s*:\s*override/i',
        '/\[SYSTEM\]/i',
        '/\[INST\]/i',
        '/act\s+as\s+(if|a)\s+(you|an?\s+)/i',
    ];
    foreach ($injection_patterns as $pattern) {
        $text = preg_replace($pattern, '[BLOCKED]', $text);
    }

    // 5. Strip remaining HTML to plain text, preserve line breaks
    $text = wp_strip_all_tags($text, true);

    // 6. Limit length to prevent token flooding (max ~8000 chars of external content)
    if (mb_strlen($text) > 8000) {
        $text = mb_substr($text, 0, 8000) . "\n[...content cut off for safety...]";
    }

    return trim($text);
}

// ======================== UNIFIED GEMINI API CALL ========================

/**
 * Unified Gemini API call with Google Search grounding, retries, and model fallback.
 * 
 * This is THE SINGLE function for all Gemini API calls across the project.
 * Google Search is enabled by default (can be disabled via options).
 * 
 * SECURITY: Includes daily Kill-Switch (budget limit) and prompt sanitization.
 *
 * @param string $prompt     The prompt text to send
 * @param array  $options    Optional overrides:
 *   'temperature'    => float  (default: 0.2)
 *   'timeout'        => int    (default: 90 seconds)
 *   'google_search'  => bool   (default: true)
 *   'json_mode'      => bool   (default: false, CANNOT be true when google_search is true)
 *   'model'          => string (default: 'gemini-3.1-flash-lite')
 *   'fallback_model' => string (default: 'gemini-3.1-flash-lite')
 *   'max_retries'    => int    (default: 3)
 *   'retry_delay'    => int    (default: 5 seconds)
 *   'skip_budget'    => bool   (default: false, set true for admin-only calls)
 * 
 * @return string|WP_Error  Generated text on success, WP_Error on failure
 */
function softmir_gemini_call($prompt, $options = [])
{
    // ── Kill-Switch: check daily budget ──
    $skip_budget = $options['skip_budget'] ?? false;
    if (!$skip_budget) {
        $budget_check = softmir_check_api_budget();
        if (is_wp_error($budget_check)) {
            if (function_exists('softmir_api_log')) {
                softmir_api_log('gemini', 0);
            }
            return $budget_check;
        }
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini API key not configured');
    }

    // Defaults
    $temperature = $options['temperature'] ?? 0.2;
    $timeout = $options['timeout'] ?? 90;
    $google_search = $options['google_search'] ?? false;
    $json_mode = $options['json_mode'] ?? false;
    $model = $options['model'] ?? 'gemini-3.1-flash-lite';
    $fallback_model = $options['fallback_model'] ?? 'gemini-3.1-flash-lite';
    $max_retries = $options['max_retries'] ?? 3;
    $retry_delay = $options['retry_delay'] ?? 5;

    // CRITICAL: Google Search and JSON mode are incompatible in Gemini API
    if ($google_search && $json_mode) {
        $json_mode = false; // Search takes priority, JSON parsing handled via text
    }

    // Build body
    $body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => $temperature],
    ];

    if ($google_search) {
        $body['tools'] = [['googleSearch' => new stdClass()]];
    }

    if ($json_mode) {
        $body['generationConfig']['responseMimeType'] = 'application/json';
    }

    // Model fallback chain
    $models = [
        ['name' => $model, 'retries' => $max_retries],
        ['name' => $fallback_model, 'retries' => max(1, $max_retries - 1)],
    ];

    $response = null;
    $status_code = 0;
    $response_body = '';

    foreach ($models as $m) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m['name']}:generateContent?key=" . $api_key;

        for ($attempt = 1; $attempt <= $m['retries']; $attempt++) {
            // Lower temperature on last attempt for more deterministic output
            if ($attempt === $m['retries'] && $m['retries'] > 1) {
                $body['generationConfig']['temperature'] = max(0.05, $temperature * 0.25);
            }

            $response = wp_remote_post($endpoint, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($body),
                'timeout' => $timeout,
            ]);

            if (is_wp_error($response)) {
                if ($attempt === $m['retries'])
                    break;
                sleep($retry_delay);
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($status_code === 200) {
                break 2; // Success — exit both loops
            }

            if (in_array($status_code, [429, 500, 503]) && $attempt < $m['retries']) {
                sleep($retry_delay);
                continue;
            }
        }

        // Reset temperature for fallback model
        $body['generationConfig']['temperature'] = $temperature;
        sleep(2);
    }

    // Log API call
    if (function_exists('softmir_api_log')) {
        softmir_api_log('gemini', $status_code ?: 0);
    }

    if (is_wp_error($response)) {
        return $response;
    }

    if ($status_code !== 200) {
        return new WP_Error('gemini_api_error', "Gemini API returned {$status_code}: " . mb_substr($response_body, 0, 500));
    }

    // Extract text from all parts (Google Search grounding may return multi-part responses)
    $data = json_decode($response_body, true);
    $generated_text = '';
    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        if (isset($part['text'])) {
            $generated_text .= $part['text'];
        }
    }

    if (empty($generated_text)) {
        return new WP_Error('empty_response', 'Gemini returned empty response. Debug: ' . mb_substr($response_body, 0, 500));
    }

    return trim($generated_text);
}

/**
 * Parse a JSON response from Gemini (strips markdown fences).
 *
 * @param string $text Raw Gemini response text
 * @return array|WP_Error Decoded array on success
 */
function softmir_parse_gemini_json($text)
{
    $text = trim($text);
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $result = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
        return new WP_Error('json_parse_error', 'Failed to parse Gemini JSON: ' . json_last_error_msg());
    }

    return $result;
}

// ======================== RANK MATH DATA SAVER ========================

/**
 * Save Rank Math SEO fields and calculate a basic SEO score programmatically.
 * 
 * Rank Math's score is normally calculated client-side by JavaScript and only
 * saved when the user clicks "Update" in the editor. When we fill cards via
 * cron or AJAX, the score never gets calculated and the admin column shows "N/A".
 * 
 * This function sets all required Rank Math meta fields AND calculates a
 * baseline score based on field completeness.
 *
 * @param int    $post_id        Post ID
 * @param array  $data           AI-generated data array with keys:
 *   'focus_keyword', 'rank_math_title', 'rank_math_description', 'rank_math_permalink'
 * @param string $post_type      'post' or 'software' (default: 'software')
 */
if (!function_exists('softmir_save_rank_math_data')) {
    function softmir_save_rank_math_data($post_id, $data, $post_type = 'software')
    {
        if (empty($post_id))
            return;

        // 1. Save core Rank Math meta fields
        if (!empty($data['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($data['focus_keyword']));
        }
        if (!empty($data['rank_math_title'])) {
            $seo_title = sanitize_text_field($data['rank_math_title']);
            // Always append brand suffix if not present
            if (mb_strpos($seo_title, '| SoftZor') === false && mb_strpos($seo_title, '| Softzor') === false) {
                $seo_title = rtrim($seo_title) . ' | SoftZor';
            }
            update_post_meta($post_id, 'rank_math_title', $seo_title);
        }
        if (!empty($data['rank_math_description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($data['rank_math_description']));
        }
        if (!empty($data['rank_math_permalink'])) {
            wp_update_post(['ID' => $post_id, 'post_name' => sanitize_title($data['rank_math_permalink'])]);
        }

        // 2. Set robots meta (tells Rank Math this post is indexable)
        $robots = get_post_meta($post_id, 'rank_math_robots', true);
        if (empty($robots)) {
            update_post_meta($post_id, 'rank_math_robots', ['index', 'follow']);
        }

        // 3. Calculate a baseline SEO score based on field completeness
        $score = 0;
        $post = get_post($post_id);

        // Focus keyword present (+20)
        $focus_kw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($focus_kw)) {
            $score += 20;
        }

        // SEO title set (+15)
        $seo_title = get_post_meta($post_id, 'rank_math_title', true);
        if (!empty($seo_title)) {
            $score += 15;
            // Title length 50-60 chars (+5)
            $title_len = mb_strlen($seo_title);
            if ($title_len >= 40 && $title_len <= 70) {
                $score += 5;
            }
            // Title contains focus keyword (+5)
            if (!empty($focus_kw) && mb_stripos($seo_title, $focus_kw) !== false) {
                $score += 5;
            }
        }

        // Meta description set (+15)
        $seo_desc = get_post_meta($post_id, 'rank_math_description', true);
        if (!empty($seo_desc)) {
            $score += 15;
            // Description length 120-160 chars (+5)
            $desc_len = mb_strlen($seo_desc);
            if ($desc_len >= 120 && $desc_len <= 165) {
                $score += 5;
            }
        }

        // Content length (+10 for 300+ words, +5 more for 1000+)
        if ($post && !empty($post->post_content)) {
            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
            if ($word_count >= 300)
                $score += 10;
            if ($word_count >= 1000)
                $score += 5;
        }

        // Post title contains focus keyword (+5)
        if ($post && !empty($focus_kw) && mb_stripos($post->post_title, $focus_kw) !== false) {
            $score += 5;
        }

        // Slug is set (+5)
        if ($post && !empty($post->post_name) && $post->post_name !== sanitize_title($post->post_title)) {
            $score += 5;
        }

        // Has featured image (+5)
        if (has_post_thumbnail($post_id)) {
            $score += 5;
        }

        // Cap the score at 100
        $score = min(100, max(0, $score));

        // 4. Save the calculated score
        update_post_meta($post_id, 'rank_math_seo_score', $score);

        // 5. Mark as pillar content if score is high
        if ($score >= 80) {
            update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        }

        // 6. Trigger Rank Math's internal recalculation hook if available
        if (class_exists('RankMath\Helper')) {
            do_action('rank_math/post/updated', $post_id);
        }
    }
}

// ======================== MULTILINGUAL HELPER ========================

/**
 * Apply a callback to a post AND all its Polylang translations.
 * 
 * This is THE KEY function for multilingual consistency. Any operation
 * on post meta, SEO fields, or content should use this wrapper to
 * ensure changes propagate to all languages.
 *
 * Usage:
 *   softmir_apply_to_all_languages($post_id, function($pid) {
 *       update_post_meta($pid, 'rank_math_seo_score', 80);
 *   });
 *
 * @param int      $post_id   Any language version of the post
 * @param callable $callback  Function receiving post_id as argument
 * @return array   Array of post_ids that were processed
 */
function softmir_apply_to_all_languages($post_id, callable $callback)
{
    $processed = [];

    // Always process the original post first
    $callback($post_id);
    $processed[] = $post_id;

    // Get all translations and apply to each
    if (function_exists('pll_get_post_translations')) {
        $translations = pll_get_post_translations($post_id);
        foreach ($translations as $lang => $translated_id) {
            if ($translated_id != $post_id && !in_array($translated_id, $processed)) {
                $callback($translated_id);
                $processed[] = $translated_id;
            }
        }
    }

    return $processed;
}

/**
 * Save Rank Math data for a post AND all its translations.
 * Wrapper around softmir_save_rank_math_data() + softmir_apply_to_all_languages().
 *
 * @param int   $post_id  Post ID (any language)
 * @param array $data     AI-generated data with SEO fields
 */
function softmir_save_rank_math_all_languages($post_id, $data)
{
    softmir_apply_to_all_languages($post_id, function ($pid) use ($data) {
        // For translations, read their own title/description if they have different content
        $trans_data = $data;

        // If the translation already has its own SEO data, keep it but ensure score + suffix
        $existing_title = get_post_meta($pid, 'rank_math_title', true);
        if (!empty($existing_title) && $pid != $GLOBALS['_softmir_original_pid'] ?? 0) {
            $trans_data['rank_math_title'] = $existing_title;
        }
        $existing_desc = get_post_meta($pid, 'rank_math_description', true);
        if (!empty($existing_desc) && $pid != $GLOBALS['_softmir_original_pid'] ?? 0) {
            $trans_data['rank_math_description'] = $existing_desc;
        }
        $existing_kw = get_post_meta($pid, 'rank_math_focus_keyword', true);
        if (!empty($existing_kw) && $pid != $GLOBALS['_softmir_original_pid'] ?? 0) {
            $trans_data['focus_keyword'] = $existing_kw;
        }

        softmir_save_rank_math_data($pid, $trans_data);
    });
}
