<?php
/**
 * AI Category Auto-Fill (SEO Content & Rank Math)
 */

if (!defined('ABSPATH'))
    exit;

// 1. Add "Auto-generate AI" button to software_category edit screen
add_action('software_category_edit_form_fields', 'softmir_category_ai_button', 10, 2);

function softmir_category_ai_button($term, $taxonomy)
{
    if (!function_exists('softmir_get_gemini_key') || empty(softmir_get_gemini_key()))
        return;
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label>AI SEO Generator</label></th>
        <td>
            <div id="softmir-cat-ai-box"
                style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;max-width:800px;margin-bottom:15px;">
                <button type="button" id="softmir_btn_cat_ai" class="button button-primary" style="margin-bottom:10px;">
                    ✨ Сгенерировать SEO-описание (AI)
                </button>
                <div id="softmir-cat-progress" style="display:none;margin-bottom:10px;">
                    <div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;">
                        <div id="softmir-cat-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.5s;">
                        </div>
                    </div>
                    <p id="softmir-cat-status" style="font-size:12px;margin-top:4px;">Sending request...</p>
                </div>
                <div id="softmir-cat-result"
                    style="display:none;padding:8px;border-radius:4px;font-size:13px;font-weight:bold;"></div>
                <p class="description">AndAnd проанализирует вложенные программы (до 10 pcs.) и напишет SEO-описание и теги Rank
                    Math для RUBрики.</p>
            </div>
            <script>
                jQuery(document).ready(function ($) {
                    $('#softmir_btn_cat_ai').on('click', function (e) {
                        e.preventDefault();
                        var btn = $(this);
                        var bar = $('#softmir-cat-bar');
                        var status = $('#softmir-cat-status');
                        var result = $('#softmir-cat-result');

                        btn.prop('disabled', true);
                        $('#softmir-cat-progress').show();
                        result.hide();
                        bar.css('width', '20%');
                        status.text('SEO analysis and generation (may take 10-15 sec)...');

                        $.post(ajaxurl, {
                            action: 'softmir_autofill_category',
                            term_id: <?php echo esc_js($term->term_id); ?>,
                            nonce: '<?php echo wp_create_nonce("softmir_cat_ai"); ?>'
                        }, function (res) {
                            btn.prop('disabled', false);
                            if (res.success) {
                                bar.css('width', '100%');
                                status.text('Done! Saving fields on the page...');
                                result.show().css({ 'background': '#edfaef', 'color': '#00a32a' }).text('Content generated successfully!');

                                // Insert into HTML form fields automatically
                                var d = res.data;
                                if ($('#description').length && d.description) $('#description').val(d.description);
                                if ($('#rank_math_title').length && d.rank_math_title) $('#rank_math_title').val(d.rank_math_title);
                                if ($('#rank_math_description').length && d.rank_math_description) $('#rank_math_description').val(d.rank_math_description);
                                if ($('#rank_math_focus_keyword').length && d.rank_math_focus_keyword) $('#rank_math_focus_keyword').val(d.rank_math_focus_keyword);

                                setTimeout(function () { $('#softmir-cat-progress').hide(); }, 2500);
                            } else {
                                status.text('Error!');
                                result.show().css({ 'background': '#fcf0f1', 'color': '#d63638' }).text(res.data || 'Unknown error AndAnd');
                            }
                        }).fail(function () {
                            btn.prop('disabled', false);
                            status.text('Network error');
                            result.show().css({ 'background': '#fcf0f1', 'color': '#d63638' }).text('Server request error');
                        });
                    });
                });
            </script>
        </td>
    </tr>
    <?php
}

// 2. AJAX Handler
add_action('wp_ajax_softmir_autofill_category', 'softmir_ajax_autofill_category');

function softmir_ajax_autofill_category()
{
    check_ajax_referer('softmir_cat_ai', 'nonce');
    if (!current_user_can('manage_categories')) {
        wp_send_json_error('Permission denied');
    }

    $term_id = intval($_POST['term_id']);
    $term = get_term($term_id, 'software_category');
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Invalid term ID');
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        wp_send_json_error('API Key Missing');
    }

    if (function_exists('softmir_ping_gemini') && !softmir_ping_gemini()) {
        wp_send_json_error('Google Gemini is currently overloaded (error 503). Try again in a couple of minutes.');
    }

    // Language detection via Polylang
    $lang_name = 'English';
    if (function_exists('pll_get_term_language')) {
        $lang_slug = pll_get_term_language($term_id);
        if ($lang_slug === 'uk')
            $lang_name = 'UKRAINIAN';
        if ($lang_slug === 'en')
            $lang_name = 'ENGLISH';
    }

    // Fetch related software for context (up to 15 apps)
    $args = [
        'post_type' => 'software',
        'posts_per_page' => 15,
        'tax_query' => [['taxonomy' => 'software_category', 'field' => 'term_id', 'terms' => $term_id]]
    ];
    $programs = get_posts($args);
    $programs_list = [];
    foreach ($programs as $p) {
        $programs_list[] = $p->post_title;
    }
    $context = empty($programs_list) ? "No programs added yet" : "Notable programs in this category: " . implode(', ', $programs_list);

    $prompt = "ROLE: You are an Expert B2B SEO Specialist and Copywriter.\n\n"
        . "ОБЪЕКТ: Текущая RUBрика программного обеспечения: '{$term->name}'.\n"
        . "CONTEXT:\n" . $context . "\n\n"
        . "TASK:\n"
        . "Напиши КРАТКОЕ, емкое SEO-описание для этой RUBрики каталога и сгенерируй метатеги для Rank Math. Пиши СТРОГО НА {$lang_name} языке.\n\n"
        . "RULES FOR DESCRIPTION (Text for the category page):\n"
        . "1. Size: As short as possible, exactly 1-2 sentences (no more than 160 characters!).\n"
        . "2. Format: Pure HTML (use only <p>, no lists or headings).\n"
        . "3. Structure: What kind of software is this and why does business need it.\n"
        . "4. Strictly without “water”, cliches and common phrases. Write purely for a B2B audience.\n\n"
        . "RULES FOR SEO (Rank Math):\n"
        . "1. rank_math_title: Selling SEO title (up to 60 characters, necessarily with a key).\n"
        . "2. rank_math_description: Relevant Snippet for Google (exactly up to 160 characters).\n"
        . "3. rank_math_focus_keyword: Short commercial 1 key (2-3 words).\n\n"
        . "FORMAT REQUIREMENT (100% valid JSON only):\n"
        . "{\n"
        . "  \"description\": \"HTML text\",\n"
        . "  \"rank_math_title\": \"SEO Title\",\n"
        . "  \"rank_math_description\": \"SEO Snippet\",\n"
        . "  \"rank_math_focus_keyword\": \"Keyword\"\n"
        . "}";

    // API Request
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
    $body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.3
        ]
    ];

    $max_retries = 5;
    $attempt = 0;
    $status_code = 0;
    $response_body = '';

    while ($attempt < $max_retries) {
        $attempt++;

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Request Failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            break;
        }

        if (in_array($status_code, [429, 500, 503]) && $attempt < $max_retries) {
            sleep(10); // Wait 10 seconds and retry
            continue;
        }

        wp_send_json_error('API Error: ' . $status_code . ' - ' . $response_body);
    }

    $data = json_decode($response_body, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    $text = trim($text);
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $json = json_decode($text, true);
    if (!$json || !is_array($json)) {
        wp_send_json_error('ERROR: AI returned incorrect format ' . json_last_error_msg());
    }

    // Update database!
    // 1. Description
    if (!empty($json['description'])) {
        wp_update_term($term_id, 'software_category', [
            'description' => wp_kses_post($json['description'])
        ]);
    }

    // 2. Rank Math Meta
    if (!empty($json['rank_math_title'])) {
        update_term_meta($term_id, 'rank_math_title', sanitize_text_field($json['rank_math_title']));
    }
    if (!empty($json['rank_math_description'])) {
        update_term_meta($term_id, 'rank_math_description', sanitize_textarea_field($json['rank_math_description']));
    }
    if (!empty($json['rank_math_focus_keyword'])) {
        update_term_meta($term_id, 'rank_math_focus_keyword', sanitize_text_field($json['rank_math_focus_keyword']));
    }

    wp_send_json_success($json);
}
