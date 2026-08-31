<?php
/**
 * SoftMir — Admin Autofill from URL
 * Meta box for auto-populating software card fields by scraping the product website.
 * Meta box for auto-populating software card fields using Gemini AI with Google Search grounding.
 */

// ======================== META BOX ========================

add_action('add_meta_boxes', 'softmir_autofill_meta_box');
function softmir_autofill_meta_box()
{
    add_meta_box(
        'softmir_autofill',
        '🔍 Автозаполнение с сайта',
        'softmir_autofill_meta_box_html',
        'software',
        'side',
        'high'
    );
}

function softmir_autofill_meta_box_html($post)
{
    $website_url = get_field('website_url', $post->ID) ?: '';
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';

    if (empty($api_key)) {
        echo '<div class="notice notice-error inline"><p>';
        echo esc_html__('Gemini API ключ не настроен.', 'softmir') . ' ';
        echo '<a href="' . esc_url(admin_url('options-general.php?page=softmir-ai-settings')) . '">';
        echo esc_html__('Настроить →', 'softmir');
        echo '</a></p></div>';
        return;
    }

    wp_nonce_field('softmir_autofill', 'softmir_autofill_nonce');
    ?>
    <div id="softmir-autofill-box">
        <p class="softmir-af-source">
            <span style="color:#00a32a;">🔍 Gemini + Google Search</span>
        </p>

        <div style="display:flex; gap:4px; margin-bottom:8px;">
            <input type="url" id="softmir-af-url" placeholder="https://example.com"
                value="<?php echo esc_attr($website_url); ?>" style="flex:1;">
            <a href="<?php echo esc_attr($website_url); ?>" id="softmir-af-visit-btn" class="button" target="_blank"
                title="Перейти на сайт"
                style="display: <?php echo empty($website_url) ? 'none' : 'inline-flex'; ?>; align-items: center; justify-content: center; padding: 0 8px;">
                <span class="dashicons dashicons-external" style="margin-top:2px;"></span>
            </a>
        </div>

        <div id="softmir-af-options" style="margin-bottom:8px;">
            <label style="display:block;font-size:11px;margin-bottom:4px;">
                <input type="checkbox" id="softmir-af-overwrite" checked>
                Перезаписать заполненные поля
            </label>
            <label style="display:block;font-size:11px;margin-bottom:4px;">
                <input type="checkbox" id="softmir-af-force-refresh">
                Обновить данные из интернета (игнорировать кэш)
            </label>


            <div id="softmir-af-progress" style="display:none;">
                <div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;">
                    <div id="softmir-af-bar"
                        style="background:#2271b1;height:100%;width:0%;transition:width 0.5s;border-radius:4px;"></div>
                </div>
                <p id="softmir-af-status" style="font-size:11px;color:#666;margin:4px 0 0;"></p>
            </div>

            <div id="softmir-af-result"
                style="display:none;margin-top:8px;padding:6px 8px;border-radius:4px;font-size:12px;">
            </div>

            <button type="button" id="softmir-af-btn" class="button button-primary" style="width:100%;margin-top:8px;">
                🔍 Заполнить с сайта
            </button>
        </div>

        <style>
            #softmir-autofill-box {
                padding: 4px 0;
            }

            .softmir-af-source {
                font-size: 12px;
                margin: 0 0 8px;
                font-weight: 600;
            }

            #softmir-af-result.success {
                background: #edfaef;
                color: #00a32a;
            }

            #softmir-af-result.error {
                background: #fcf0f1;
                color: #d63638;
            }
        </style>

        <script>
            jQuery(function ($) {
                var $btn = $('#softmir-af-btn');
                var $url = $('#softmir-af-url');
                var $visitBtn = $('#softmir-af-visit-btn');
                var $progress = $('#softmir-af-progress');
                var $bar = $('#softmir-af-bar');
                var $status = $('#softmir-af-status');
                var $result = $('#softmir-af-result');
                var pollTimer = null;
                var postId = <?php echo $post->ID; ?>;

                $url.on('input change', function () {
                    var val = $(this).val().trim();
                    if (val) {
                        try {
                            new URL(val);
                            $visitBtn.attr('href', val).css('display', 'inline-flex');
                        } catch (e) {
                            $visitBtn.hide();
                        }
                    } else {
                        $visitBtn.hide();
                    }
                });

                if ($url.val().trim()) {
                    $visitBtn.attr('href', $url.val().trim());
                }

                $btn.on('click', function () {
                    var url = $url.val().trim();
                    if (!url) {
                        alert('Enter Website URL');
                        return;
                    }

                    $btn.prop('disabled', true).text('⏳ Starting...');
                    $progress.show();
                    $result.hide();
                    $bar.css('width', '10%');
                    $status.text('Sending request...');

                    // Step 1: Send quick START request
                    $.post(ajaxurl, {
                        action: 'softmir_autofill_start',
                        nonce: $('#softmir_autofill_nonce').val(),
                        post_id: postId,
                        url: url,
                        overwrite: $('#softmir-af-overwrite').is(':checked') ? 1 : 0,
                        force_refresh: $('#softmir-af-force-refresh').is(':checked') ? 1 : 0
                    }, function (response) {
                        if (response.success) {
                            if (response.data && response.data.warning) {
                                $result.removeClass('error').addClass('success').html('⚠️ ' + response.data.warning).show();
                            }
                            $bar.css('width', '20%');
                            $status.text('🤖 AndAnd генерирует карточку...');
                            // Step 2: Start polling for result
                            startPolling();
                        } else {
                            var errMsg = response.data?.message || 'Unknown error';
                            $result.removeClass('success').addClass('error').html('❌ ' + errMsg).show();
                            $btn.prop('disabled', false).text('🔍 Fill from site');
                            $status.text('Error');
                            $bar.css('width', '100%');
                        }
                    }).fail(function (xhr) {
                        $result.removeClass('success').addClass('error')
                            .html('❌ Error starting: HTTP ' + xhr.status).show();
                        $btn.prop('disabled', false).text('🔍 Fill from site');
                        $bar.css('width', '100%');
                    });
                });

                function startPolling() {
                    var pollCount = 0;
                    var maxPolls = 120; // 120 * 5sec = 10 min max
                    var stages = [
                        {at: 2, pct: '30%', text: '🕵️ Аналитик собирает фактуру...'},
                        {at: 6, pct: '45%', text: '✍️ Copywriter is writing text...'},
                        {at: 10, pct: '60%', text: '🧐 Редактор отжимает воду...'},
                        {at: 14, pct: '75%', text: '🔍 Critic is checking quality...'},
                        {at: 18, pct: '85%', text: '💾 Сохранение данных...'}
                    ];

                    pollTimer = setInterval(function () {
                        pollCount++;

                        // Update progress animation
                        for (var i = stages.length - 1; i >= 0; i--) {
                            if (pollCount >= stages[i].at) {
                                $bar.css('width', stages[i].pct);
                                $status.text(stages[i].text);
                                break;
                            }
                        }

                        if (pollCount >= maxPolls) {
                            clearInterval(pollTimer);
                            $result.removeClass('success').addClass('error')
                                .html('❌ Timeout: generation took too long').show();
                            $btn.prop('disabled', false).text('🔍 Fill from site');
                            return;
                        }

                        $.post(ajaxurl, {
                            action: 'softmir_autofill_status',
                            nonce: $('#softmir_autofill_nonce').val(),
                            post_id: postId
                        }, function (response) {
                            if (!response.success) return; // skip transient errors

                            var st = response.data.status;

                            if (st === 'done') {
                                clearInterval(pollTimer);
                                $bar.css('width', '100%');
                                $status.text('Applying data and reloading...');
                                $result.removeClass('error').addClass('success')
                                    .html('✅ Filled! Reloading page to display attributes and categories...').show();
                                $btn.prop('disabled', false).text('🔍 Fill from site');
                                
                                // Disable unload warnings so reload happens smoothly
                                $(window).off('beforeunload');
                                window.onbeforeunload = null;
                                
                                setTimeout(function() {
                                    if (window.location.href.indexOf('post-new.php') !== -1) {
                                        window.location.href = 'post.php?post=' + postId + '&action=edit';
                                    } else {
                                        window.location.reload();
                                    }
                                }, 1500);
                            } else if (st === 'error') {
                                clearInterval(pollTimer);
                                $bar.css('width', '100%');
                                var errMsg = response.data.message || 'Unknown error пайплайна';
                                $result.removeClass('success').addClass('error').html('❌ ' + errMsg).show();
                                $status.text('Error');
                                $btn.prop('disabled', false).text('🔍 Fill from site');
                            }
                            // if 'processing' — do nothing, wait for next poll
                        });
                    }, 5000);
                }

                function applyData(d) {
                    if (!d) return;
                    var overwrite = $('#softmir-af-overwrite').is(':checked');

                    // Fill ACF fields
                    fillField('field_sw_short_desc', 'short_description', d);
                    var $websiteField = $('#acf-field_sw_website');
                    if ($websiteField.length && !$websiteField.val().trim()) {
                        $websiteField.val(d.website_url || '');
                    }
                    fillField('field_sw_price_summary', 'price_summary', d);

                    // Fill Title and Content (Support for both Classic and Gutenberg)
                    var isGutenberg = typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor');
                    if (isGutenberg) {
                        var updates = {};
                        var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                        var currentContent = wp.data.select('core/editor').getEditedPostAttribute('content');
                        if (d.title && (overwrite || !currentTitle)) updates.title = d.title;
                        if (d.full_description && (overwrite || !currentContent)) {
                            if (wp.blocks && wp.blocks.parse) {
                                var blocks = wp.blocks.parse(d.full_description);
                                if (wp.data.dispatch('core/block-editor')) {
                                    wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                                } else {
                                    updates.content = d.full_description;
                                }
                            } else {
                                updates.content = d.full_description;
                            }
                        }
                        if (Object.keys(updates).length > 0) {
                            wp.data.dispatch('core/editor').editPost(updates);
                        }
                    } else {
                        if (d.full_description && typeof tinymce !== 'undefined' && tinymce.get('content')) {
                            var tc = tinymce.get('content').getContent();
                            if (overwrite || !tc) tinymce.get('content').setContent(d.full_description);
                        }
                        if (d.title) {
                            var $title = $('#title');
                            if ($title.length && (overwrite || !$title.val())) {
                                $title.val(d.title).trigger('change').trigger('focus');
                                $('#title-prompt-text').addClass('screen-reader-text');
                            }
                        }
                    }

                    // Fill meta fields
                    setMetaField('verdict', d.verdict);
                    setMetaField('origin', d.origin);
                    setMetaField('tech_specs', d.tech_specs);
                    setMetaField('scenarios_md', d.scenarios_md);
                    setMetaField('key_features', d.key_features);
                    setMetaField('top_reasons', d.top_reasons);
                    setMetaField('disadvantages', d.disadvantages);
                    setMetaField('best_for', d.best_for);
                    setMetaField('bad_for', d.bad_for);

                    // Fill attribute fields
                    if (d.attributes) {
                        $.each(d.attributes, function (attrId, val) {
                            var $field = $('[name="_sw_attr_' + attrId + '"]');
                            if ($field.length) {
                                if (Array.isArray(val)) {
                                    $('[name="_sw_attr_' + attrId + '[]"]').each(function () {
                                        $(this).prop('checked', val.indexOf($(this).val()) !== -1);
                                    });
                                } else {
                                    $field.val(val);
                                }
                            }
                        });
                    }

                    // Fill integrations text field
                    if (d.integrations_string) {
                        $('[name^="_sw_attr_"]').each(function () {
                            var $row = $(this).closest('tr');
                            var label = $row.find('th label').text().toLowerCase();
                            if (label.indexOf('integrat') !== -1 || label.indexOf('integrat') !== -1 || label.indexOf('integration') !== -1) {
                                $(this).val(d.integrations_string);
                            }
                        });
                    }

                    // Visually update the Category Key Functions checkboxes
                    if (d.category_key_functions && Array.isArray(d.category_key_functions)) {
                        $('[name="sw_selected_functions[]"]').each(function () {
                            var val = $(this).val().trim();
                            $(this).prop('checked', d.category_key_functions.indexOf(val) !== -1);
                        });
                    }

                    // If logo was loaded, refresh the featured image box
                    if (d.logo_loaded && d.logo_attachment_id) {
                        wp.media.featuredImage.set(d.logo_attachment_id);
                    }
                }

                function fillField(acfKey, dataKey, data, fallback) {
                    var val = data[dataKey] || fallback || '';
                    if (!val) return;
                    var $field = $('#acf-' + acfKey);
                    if ($field.length) {
                        if (!$('#softmir-af-overwrite').is(':checked') && $field.val()) return;
                        $field.val(val);
                    }
                }

                function setMetaField(key, val) {
                    if (!val) return;
                    var $field = $('[name="acf[field_sw_' + key.replace(/_/g, '_') + ']"]');
                    if (!$field.length) {
                        $field = $('[id*="' + key + '"]').filter('input,textarea,select').first();
                    }
                    if ($field.length) {
                        if (!$('#softmir-af-overwrite').is(':checked') && $field.val()) return;
                        $field.val(val);
                    }
                }
            });
        </script>
        <?php
}


// ======================== AJAX: START (fast - puts the task in the background) ========================

add_action('wp_ajax_softmir_autofill_start', 'softmir_ajax_autofill_start');
function softmir_ajax_autofill_start()
{
    check_ajax_referer('softmir_autofill', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Access denied']);
    }

    $post_id       = intval($_POST['post_id'] ?? 0);
    $url           = esc_url_raw($_POST['url'] ?? '');
    $overwrite     = intval($_POST['overwrite'] ?? 1);
    $force_refresh = intval($_POST['force_refresh'] ?? 0);

    if (!$post_id || !$url) {
        wp_send_json_error(['message' => 'Missing post_id or URL']);
    }

    // Save the website_url only if currently empty
    $current_url = get_field('website_url', $post_id);
    if (empty($current_url)) {
        update_field('website_url', $url, $post_id);
    }

    // --- Quick Validation: Category Key Functions ---
    $cat_id = get_post_meta($post_id, 'primary_category', true);
    if (!$cat_id) {
        $cats = wp_get_post_terms($post_id, 'software_category', ['fields' => 'ids']);
        if (!empty($cats) && !is_wp_error($cats)) {
            $cat_id = $cats[0];
        }
    }

    $warning_msg = '';
    if ($cat_id) {
        $all_funcs = function_exists('softmir_get_all_key_functions_for_category')
            ? softmir_get_all_key_functions_for_category($cat_id)
            : [];

        if (empty($all_funcs)) {
            $term = get_term($cat_id, 'software_category');
            $term_name = $term && !is_wp_error($term) ? $term->name : 'this category';
            $warning_msg = "Внимание: функции для категории «{$term_name}» не заполнены. AndAnd сгенерирует их автоматически в процессе работы.";
        }
    } else {
        $warning_msg = "Attention: no category selected. The AI ​​will try to detect it automatically.";
    }

    // --- Quick Validation: Gemini API ---
    if (function_exists('softmir_ping_gemini')) {
        $ping_res = softmir_ping_gemini(true);
        if ($ping_res !== true) {
            wp_send_json_error([
                'message' => 'Gemini API Error: ' . $ping_res
            ]);
        }
    }

    // Save task params to post meta (more reliable than transients with Object Cache)
    $task_key = '_autofill_task_data';
    update_post_meta($post_id, $task_key, [
        'url'           => $url,
        'overwrite'     => $overwrite,
        'force_refresh' => $force_refresh,
        'status'        => 'processing',
        'started'       => time(),
    ]);

    // Schedule the background processing via WP-Cron
    $hook = 'softmir_autofill_background';
    // Clear any previously scheduled event for this post
    $existing = wp_next_scheduled($hook, [$post_id]);
    if ($existing) {
        wp_unschedule_event($existing, $hook, [$post_id]);
    }
    wp_schedule_single_event(time(), $hook, [$post_id]);

    // Trigger WP-Cron immediately via a non-blocking loopback request
    spawn_cron();

    wp_send_json_success([
        'status' => 'started',
        'warning' => $warning_msg
    ]);
}


// ======================== AJAX: STATUS CHECK (quick - readiness poll) ========================

add_action('wp_ajax_softmir_autofill_status', 'softmir_ajax_autofill_status');
function softmir_ajax_autofill_status()
{
    check_ajax_referer('softmir_autofill', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Access denied']);
    }

    $post_id  = intval($_POST['post_id'] ?? 0);
    $task_key = '_autofill_task_data';
    $task     = get_post_meta($post_id, $task_key, true);

    if (empty($task)) {
        wp_send_json_success(['status' => 'error', 'message' => 'Task not found or expired']);
        return;
    }

    if ($task['status'] === 'done') {
        // Return the result and clean up
        $result_key = '_autofill_result_data';
        $result     = get_post_meta($post_id, $result_key, true);
        delete_post_meta($post_id, $task_key);
        delete_post_meta($post_id, $result_key);
        wp_send_json_success(['status' => 'done', 'result' => $result]);
        return;
    }

    if ($task['status'] === 'error') {
        $error_msg = $task['error_message'] ?? 'Unknown error';
        delete_post_meta($post_id, $task_key);
        wp_send_json_success(['status' => 'error', 'message' => $error_msg]);
        return;
    }

    // Still processing — check for stuck tasks (> 5 min)
    if (isset($task['started']) && (time() - $task['started']) > 300) {
        delete_post_meta($post_id, $task_key);
        wp_send_json_success(['status' => 'error', 'message' => 'Timeout: generation took more than 5 minutes']);
        return;
    }

    wp_send_json_success(['status' => 'processing']);
}


// ======================== BACKGROUND WORKER (WP-Cron) ========================

add_action('softmir_autofill_background', 'softmir_autofill_background_worker');
function softmir_autofill_background_worker($post_id)
{
    set_time_limit(300);

    $task_key   = '_autofill_task_data';
    $result_key = '_autofill_result_data';
    $task       = get_post_meta($post_id, $task_key, true);

    if (empty($task) || $task['status'] !== 'processing') {
        return;
    }

    $url           = $task['url'];
    $overwrite     = $task['overwrite'] ?? 1;
    $force_refresh = $task['force_refresh'] ?? 0;

    // --- Gemini API key ---
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    if (empty($api_key)) {
        $task['status'] = 'error';
        $task['error_message'] = 'Gemini API key not configured';
        update_post_meta($post_id, $task_key, $task);
        return;
    }

    // Determine language
    $lang_name = 'English';
    if (function_exists('softmir_get_post_lang')) {
        $post_lang = softmir_get_post_lang($post_id);
        if ($post_lang === 'ua') $lang_name = 'Ukrainian';
        elseif ($post_lang === 'ru') $lang_name = 'Russian';
    }

    // Get category
    $category_id = 0;
    $terms = wp_get_post_terms($post_id, 'software_category', ['fields' => 'ids']);
    if (!empty($terms) && !is_wp_error($terms)) {
        $category_id = $terms[0];
    }

    // Fast 1st Pass: Classify Category if missing
    if (!$category_id) {
        // Polylang: get ONLY Russian categories to avoid English/Ukrainian Polylang clones
        $all_cats = get_terms(['taxonomy' => 'software_category', 'hide_empty' => false, 'lang' => 'ru']);
        $cat_list = [];
        foreach ($all_cats as $c) {
            $cat_list[] = "{$c->term_id}: {$c->name}";
        }

        $cat_prompt = "Определи к какой категории относится продукт по URL: {$url}\n\n"
            . "List of existing categories:\n" . implode("\n", $cat_list) . "\n\n"
            . "RULES:\n"
            . "It is STRICTLY PROHIBITED to invent categories in English or take them from your head. You must return the answer strictly in one of three formats:\n"
            . "1. If a product fits perfectly into one of the existing categories, return ONLY ITS ID (one number).\n"
            . "2. If a product does not fit into any category, but you can create a subcategory for it within the existing one, return the line: SUB: [parent_ID] | [Name of subcategory in Russian] New items\n"
            . "3. If the product requires a completely new category, return the line: NEW: [Category name in Russian] New items\n\n"
            . "Example 1: 42\n"
            . "Example 2: SUB: 15 | Mailboxes for agents New items\n"
            . "Example 3: NEW: Facial recognition systems New items\n\n"
            . "YOUR ANSWER:";

        $cat_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . $api_key;
        $cat_body = [
            'contents' => [['parts' => [['text' => $cat_prompt]]]],
            'generationConfig' => ['temperature' => 0.1]
        ];
        $cat_res = wp_remote_post($cat_endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($cat_body),
            'timeout' => 15,
        ]);
        if (!is_wp_error($cat_res) && wp_remote_retrieve_response_code($cat_res) === 200) {
            $cat_data = json_decode(wp_remote_retrieve_body($cat_res), true);
            $cat_text = trim($cat_data['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (function_exists('softmir_log_ai_action')) {
                softmir_log_ai_action("🔍 Ответ AndAnd (категория): «{$cat_text}» для поста #{$post_id}");
            }
            
            $guess_id = 0;
            
            // Parse response
            if (preg_match('/^SUB:\s*(\d+)\s*\|\s*(.+)$/ui', $cat_text, $m)) {
                $parent_id = intval($m[1]);
                $new_cat_name = trim($m[2]);
                // Validate parent actually exists before creating subcategory
                if ($parent_id > 0 && term_exists($parent_id, 'software_category') && !empty($new_cat_name)) {
                    $term = term_exists($new_cat_name, 'software_category', $parent_id);
                    if (!$term) {
                        $term = wp_insert_term($new_cat_name, 'software_category', ['parent' => $parent_id]);
                        if (function_exists('softmir_log_ai_action') && !is_wp_error($term)) {
                            softmir_log_ai_action("📂 Создана подкатегория: «{$new_cat_name}» (Parent: {$parent_id})");
                        }
                    }
                    if (!is_wp_error($term)) {
                        $guess_id = is_array($term) ? intval($term['term_id']) : intval($term);
                    }
                }
            } elseif (preg_match('/^NEW:\s*(.+)$/ui', $cat_text, $m)) {
                $new_cat_name = trim($m[1]);
                if (!empty($new_cat_name)) {
                    $term = term_exists($new_cat_name, 'software_category');
                    if (!$term) {
                        $term = wp_insert_term($new_cat_name, 'software_category');
                        if (function_exists('softmir_log_ai_action') && !is_wp_error($term)) {
                            softmir_log_ai_action("📂 Создана новая категория: «{$new_cat_name}»");
                        }
                    }
                    if (!is_wp_error($term)) {
                        $guess_id = is_array($term) ? intval($term['term_id']) : intval($term);
                    }
                }
            } else {
                // Try to extract an integer
                preg_match('/^\d+/', $cat_text, $m);
                if (!empty($m[0])) {
                    $guess_id = intval($m[0]);
                }
            }

            // Validate that this term actually exists
            if ($guess_id > 0 && term_exists($guess_id, 'software_category')) {
                $category_id = $guess_id;
                // Set taxonomy terms (checkboxes "Software categories")
                wp_set_post_terms($post_id, [$category_id], 'software_category');
                // Set ACF primary_category (dropdown "Primary Category")
                update_field('primary_category', $category_id, $post_id);

                if (function_exists('softmir_log_ai_action')) {
                    $term_obj = get_term($category_id, 'software_category');
                    $cat_name = $term_obj && !is_wp_error($term_obj) ? $term_obj->name : $category_id;
                    softmir_log_ai_action("📂 Авто-классификация: «{$cat_name}» (ID: {$category_id}) для поста #{$post_id}.");
                }
            }
        }
    }

    // Build attribute prompt
    $attrs_prompt = '';
    if ($category_id && function_exists('softmir_get_attrs_for_category')) {
        $category_attrs = softmir_get_attrs_for_category($category_id);
        if (!empty($category_attrs)) {
            $attrs_prompt = "Fill in the dynamic attributes for this program. Below is the list (ID : Name [Type]):\n";
            foreach ($category_attrs as $attr) {
                $meta = softmir_get_attr_meta($attr->ID);
                $type_desc = $meta['type'];
                if ($meta['multiple'] || $meta['type'] === 'checkbox') {
                    $type_desc .= ' (array of values)';
                }
                if (!empty($meta['options'])) {
                    $type_desc .= " (Options: {$meta['options']})";
                }
                $attrs_prompt .= "- {$attr->ID} : {$attr->post_title} [{$type_desc}]\n";
            }
        }
    }

    // Auto-generate category functions if missing
    if ($category_id && function_exists('softmir_get_all_key_functions_for_category') && function_exists('softmir_gemini_generate_category_features')) {
        $inherited_funcs = softmir_get_all_key_functions_for_category($category_id);
        if (empty($inherited_funcs)) {
            $generated = softmir_gemini_generate_category_features($category_id);
            if (!is_wp_error($generated) && !empty($generated)) {
                update_term_meta($category_id, 'sw_key_functions', sanitize_text_field($generated));
            }
        }
    }

    // Build category functions prompt
    $cat_funcs_prompt = "";
    if ($category_id && function_exists('softmir_get_all_key_functions_for_category')) {
        $inherited_funcs = softmir_get_all_key_functions_for_category($category_id);
        if (!empty($inherited_funcs)) {
            $cat_funcs_str = implode(', ', $inherited_funcs);
            $cat_funcs_prompt = "\nРЕЙТAndНГ ФУНКЦAndЙ:\nВот список всех макро-функций (чекбоксов), доступных для этой категории:\n[{$cat_funcs_str}]\n";
        }
    }

    // Detect language override
    if (function_exists('softmir_get_post_lang')) {
        $post_lang = softmir_get_post_lang($post_id);
        if ($post_lang === 'ua') $lang_name = 'Ukrainian';
    }

    // Run the 4-step AI content pipeline
    $pipeline_result = softmir_run_content_pipeline($url, $lang_name, [
        'attrs_prompt'     => $attrs_prompt,
        'cat_funcs_prompt' => $cat_funcs_prompt,
        'post_title'       => get_the_title($post_id),
        'post_id'          => $post_id,
        'force_refresh'    => $force_refresh,
    ]);

    if (is_wp_error($pipeline_result)) {
        $task['status'] = 'error';
        $task['error_message'] = 'Pipeline error: ' . $pipeline_result->get_error_message();
        update_post_meta($post_id, $task_key, $task);
        return;
    }

    $item = $pipeline_result;

    // Inject category data into pipeline result (the pipeline JSON schema doesn't return these)
    if ($category_id > 0) {
        if (empty($item['primary_category_id'])) {
            $item['primary_category_id'] = $category_id;
        }
        if (empty($item['software_categories']) || !is_array($item['software_categories'])) {
            $item['software_categories'] = [$category_id];
        }
    }

    // Save data to post meta
    $save_result = softmir_autofill_save_fields($post_id, $item, $overwrite);

    // Build response for JS
    $js_data = [
        'title'                 => $item['title'] ?? '',
        'short_description'     => $item['short_description'] ?? '',
        'full_description'      => wp_kses_post($item['full_description'] ?? ''),
        'website_url'           => $url,
        'price_summary'         => mb_substr($item['price_summary'] ?? '', 0, 60),
        'verdict'               => $item['verdict'] ?? '',
        'origin'                => $item['origin'] ?? '',
        'tech_specs'            => $item['tech_specs'] ?? '',
        'integrations_string'   => $save_result['integrations_string'] ?? '',
        'attributes'            => $item['attributes'] ?? [],
        'category_key_functions'=> $item['category_key_functions'] ?? [],
        'source'                => 'Gemini + Google Search',
        'scenarios_md'          => $save_result['scenarios_md'] ?? '',
        'key_features'          => $save_result['key_features'] ?? '',
        'top_reasons'           => $save_result['top_reasons'] ?? '',
        'disadvantages'         => $save_result['disadvantages'] ?? '',
        'best_for'              => $save_result['best_for'] ?? '',
        'bad_for'               => $save_result['bad_for'] ?? '',
    ];

    // Save result and update status
    update_post_meta($post_id, $result_key, $js_data);
    $task['status'] = 'done';
    update_post_meta($post_id, $task_key, $task);
}


// ======================== SAVE FIELDS HELPER ========================

function softmir_autofill_save_fields($post_id, $item, $overwrite = true)
{
    $result = [];

    // Core fields
    if (!empty($item['title']) && ($overwrite || empty(get_the_title($post_id)))) {
        wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($item['title'])]);
    }

    if (!empty($item['short_description'])) {
        $current = get_field('short_description', $post_id);
        if ($overwrite || empty($current)) {
            update_field('short_description', sanitize_textarea_field($item['short_description']), $post_id);
        }
    }

    if (!empty($item['full_description'])) {
        $current = get_post_field('post_content', $post_id);
        if ($overwrite || empty($current)) {
            wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($item['full_description'])]);
        }
    }

    // Simple text fields
    $simple_fields = ['verdict', 'price_summary', 'origin', 'tech_specs', 'price_updated_date', 'partner_link'];
    foreach ($simple_fields as $field) {
        if (isset($item[$field]) && $item[$field] !== '') {
            $current = get_field($field, $post_id);
            if ($overwrite || empty($current)) {
                update_field($field, sanitize_textarea_field($item[$field]), $post_id);
            }
        }
    }

    // Boolean fields
    $boolean_fields = ['works_in_ukraine'];
    foreach ($boolean_fields as $field) {
        if (isset($item[$field])) {
            $current = get_field($field, $post_id);
            if ($overwrite || empty($current)) {
                update_field($field, filter_var($item[$field], FILTER_VALIDATE_BOOLEAN), $post_id);
            }
        }
    }

    // Scenarios
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
            $scenarios_md = implode("\n\n", $md_parts);
            update_field('scenarios_md', $scenarios_md, $post_id);
            $result['scenarios_md'] = $scenarios_md;
        }
    }

    // Array→text fields
    $array_fields = [
        'features' => 'key_features',
        'advantages' => 'top_reasons',
        'disadvantages' => 'disadvantages',
        'best_for' => 'best_for',
        'bad_for' => 'bad_for',
    ];

    foreach ($array_fields as $source_key => $acf_key) {
        if (!empty($item[$source_key]) && is_array($item[$source_key])) {
            $current = get_field($acf_key, $post_id);
            if ($overwrite || empty($current)) {
                if ($acf_key === 'key_features') {
                    $html = '<ul>';
                    foreach ($item[$source_key] as $f) {
                        $html .= '<li>' . esc_html($f) . '</li>';
                    }
                    $html .= '</ul>';
                    update_field($acf_key, wp_kses_post($html), $post_id);
                    $result[$acf_key] = $html;
                } else {
                    $text = implode("\n", array_map('sanitize_text_field', $item[$source_key]));
                    update_field($acf_key, $text, $post_id);
                    $result[$acf_key] = $text;
                }
            }
        }
    }

    // Dynamic attributes
    if (!empty($item['attributes']) && is_array($item['attributes'])) {
        foreach ($item['attributes'] as $attr_data) {
            $attr_id = intval($attr_data['id'] ?? 0);
            $attr_val = $attr_data['value'] ?? '';

            if ($attr_id > 0) {
                $field_name = '_sw_attr_' . $attr_id;
                $current = get_post_meta($post_id, $field_name, true);
                if ($overwrite || empty($current)) {
                    if (is_array($attr_val)) {
                        $clean_val = array_map('sanitize_text_field', $attr_val);
                    } else {
                        $clean_val = sanitize_text_field($attr_val);
                    }
                    update_post_meta($post_id, $field_name, $clean_val);
                }
            }
        }
    }

    // Taxonomy Categories
    if (isset($item['software_categories']) && is_array($item['software_categories'])) {
        $cat_ids = array_map('intval', $item['software_categories']);
        $cat_ids = array_filter($cat_ids);
        if (!empty($cat_ids)) {
            wp_set_object_terms($post_id, $cat_ids, 'software_category', false);
        }
    }

    // Primary Category
    if (!empty($item['primary_category_id'])) {
        $primary_cat_id = intval($item['primary_category_id']);
        if ($primary_cat_id > 0) {
            update_field('primary_category', $primary_cat_id, $post_id);
            // Ensure primary category is in the taxonomy terms
            $current_terms = wp_get_post_terms($post_id, 'software_category', ['fields' => 'ids']);
            if (!in_array($primary_cat_id, $current_terms)) {
                wp_set_object_terms($post_id, [$primary_cat_id], 'software_category', true);
            }
        }
    }

    // Category Key Functions (Checkboxes sync)
    if (!empty($item['category_key_functions']) && is_array($item['category_key_functions'])) {
        update_post_meta($post_id, '_selected_key_functions', array_values(array_filter($item['category_key_functions'])));
    }

    // SEO Meta Rank Math (centralized: saves fields + calculates score)
    if (function_exists('softmir_save_rank_math_data')) {
        softmir_save_rank_math_data($post_id, $item);
    }

    // Is Referral/Affiliate Link
    if (isset($item['is_referral']) && $item['is_referral'] === true) {
        update_field('is_referral', 1, $post_id);
    }

    // ACF Repeaters
    // 1. pricing_list
    if (!empty($item['pricing_list']) && is_array($item['pricing_list'])) {
        $current_pricing = get_field('pricing_list', $post_id);
        if ($overwrite || empty($current_pricing)) {
            $sanitized_pricing = [];
            foreach ($item['pricing_list'] as $row) {
                $features = isset($row['features']) && is_array($row['features']) ? array_map('sanitize_text_field', $row['features']) : [];
                $sanitized_pricing[] = [
                    'plan'     => isset($row['plan']) ? sanitize_text_field($row['plan']) : '',
                    'price'    => isset($row['price']) ? sanitize_text_field($row['price']) : '',
                    'currency' => isset($row['currency']) ? sanitize_text_field($row['currency']) : '',
                    'features' => $features
                ];
            }
            update_field('pricing_list', $sanitized_pricing, $post_id);
            $result['pricing_list'] = $sanitized_pricing;
        }
    }

    // 2. external_reviews
    if (!empty($item['external_reviews']) && is_array($item['external_reviews'])) {
        $current_reviews = get_field('external_reviews', $post_id);
        if ($overwrite || empty($current_reviews)) {
            $sanitized_reviews = [];
            foreach ($item['external_reviews'] as $row) {
                $sanitized_reviews[] = [
                    'source'     => isset($row['source']) ? sanitize_text_field($row['source']) : '',
                    'rating'     => isset($row['rating']) ? sanitize_text_field($row['rating']) : '',
                    'text'       => isset($row['text']) ? wp_kses_post($row['text']) : '',
                    'review_url' => isset($row['review_url']) ? esc_url_raw($row['review_url']) : ''
                ];
            }
            update_field('external_reviews', $sanitized_reviews, $post_id);
            $result['external_reviews'] = $sanitized_reviews;
        }
    }

    // Mark as auto-filled and prevent cron from picking it up
    update_post_meta($post_id, '_softmir_autofilled', current_time('mysql'));
    update_post_meta($post_id, '_softmir_autofill_source', $item['website_url'] ?? '');
    update_post_meta($post_id, '_ai_enriched', '1'); // THIS IS THE KEY FIX

    // Save integrations with sync
    if (!empty($item['integrations']) && is_array($item['integrations'])) {
        $clean = array_map('sanitize_text_field', $item['integrations']);
        $integrations_string = implode(', ', $clean);
        update_post_meta($post_id, 'integrations', $integrations_string);

        // Sync to sw_attribute
        global $wpdb;
        $attr_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'sw_attribute' AND post_status = 'publish' AND post_title LIKE '%integrat%' LIMIT 1"
        );
        if ($attr_id) {
            update_post_meta($post_id, '_sw_attr_' . $attr_id, $integrations_string);
        }
        $result['integrations_string'] = $integrations_string;
    }

    return $result;
}
