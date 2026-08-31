<?php
/**
 * SoftMir — AI Translation Admin UI
 * Meta box for post editing screens + taxonomy term screens
 */

// ======================== META BOX FOR POSTS ========================

add_action('add_meta_boxes', 'softmir_add_translate_meta_box');
function softmir_add_translate_meta_box()
{
    $post_types = ['software', 'integrator', 'sw_attribute'];
    foreach ($post_types as $pt) {
        add_meta_box(
            'softmir_ai_translate',
            '🌐 ' . __('AI Auto Translation', 'softmir'),
            'softmir_translate_meta_box_html',
            $pt,
            'side',
            'high'
        );
    }
}

function softmir_translate_meta_box_html($post)
{
    if (!function_exists('pll_languages_list') || !function_exists('pll_default_language')) {
        echo '<p>' . esc_html__('Polylang is not active', 'softmir') . '</p>';
        return;
    }

    // Only show for default language posts
    $post_lang = pll_get_post_language($post->ID);
    $default_lang = pll_default_language();

    if ($post_lang && $post_lang !== $default_lang) {
        $source_id = get_post_meta($post->ID, '_softmir_source_post_id', true);
        $translated_at = get_post_meta($post->ID, '_softmir_translated_at', true);
        echo '<div class="softmir-translate-info">';
        echo '<p>📋 ' . esc_html__('This is a translated copy', 'softmir') . '</p>';
        if ($source_id) {
            echo '<p><a href="' . get_edit_post_link($source_id) . '">' . esc_html__('→ Open original', 'softmir') . '</a></p>';
        }
        if ($translated_at) {
            echo '<p><small>' . esc_html__('Translated:', 'softmir') . ' ' . esc_html($translated_at) . '</small></p>';
        }
        echo '</div>';
        return;
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';

    if (empty($api_key)) {
        echo '<div class="notice notice-error inline"><p>';
        echo esc_html__('Gemini API key is not configured.', 'softmir') . ' ';
        echo '<a href="' . esc_url(admin_url('options-general.php?page=softmir-ai-settings')) . '">';
        echo esc_html__('Configure →', 'softmir');
        echo '</a>';
        echo '</p></div>';
        return;
    }

    $all_langs = pll_languages_list(['fields' => 'slug']);
    $lang_names = pll_languages_list(['fields' => 'name']);
    $target_langs = [];

    foreach ($all_langs as $i => $slug) {
        if ($slug !== $default_lang) {
            $target_langs[$slug] = $lang_names[$i];
        }
    }

    wp_nonce_field('softmir_translate', 'softmir_translate_nonce');
    ?>
    <div id="softmir-translate-box" data-post-id="<?php echo esc_attr($post->ID); ?>">
        <div id="softmir-translate-statuses">
            <?php foreach ($target_langs as $slug => $name):
                $status = softmir_get_translation_status($post->ID, $slug);
                ?>
                <div class="softmir-lang-status" data-lang="<?php echo esc_attr($slug); ?>">
                    <span class="softmir-lang-name"><?php echo esc_html($name); ?>:</span>
                    <?php if ($status['status'] === 'translated'): ?>
                        <span class="softmir-status-ok">✅ <?php echo esc_html($status['translated_at']); ?></span>
                        <?php
                    elseif ($status['status'] === 'outdated'): ?>
                        <span class="softmir-status-warn">⚠️ <?php esc_html_e('Outdated', 'softmir'); ?></span>
                        <?php
                    elseif ($status['status'] === 'manual'): ?>
                        <span class="softmir-status-info">📝 <?php esc_html_e('Manually', 'softmir'); ?></span>
                        <?php
                    else: ?>
                        <span class="softmir-status-none">❌ <?php esc_html_e('No translation', 'softmir'); ?></span>
                        <?php
                    endif; ?>
                    <button type="button" class="button button-small softmir-translate-single"
                        data-lang="<?php echo esc_attr($slug); ?>"
                        title="<?php printf(esc_attr__('Translate to %s', 'softmir'), $name); ?>">
                        ↻
                    </button>
                </div>
                <?php
            endforeach; ?>
        </div>

        <div id="softmir-translate-progress" style="display:none;">
            <div class="softmir-progress-bar">
                <div class="softmir-progress-fill" id="softmir-progress-fill"></div>
            </div>
            <p id="softmir-progress-text" class="softmir-progress-text"></p>
        </div>

        <div id="softmir-translate-result" style="display:none;"></div>

        <button type="button" class="button button-primary button-large softmir-translate-all"
            id="softmir-translate-all-btn" style="width:100%;margin-top:8px;">
            🌐 <?php esc_html_e('Translate into all languages', 'softmir'); ?>
        </button>
    </div>

    <style>
        #softmir-translate-box {
            padding: 4px 0;
        }

        .softmir-lang-status {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 12px;
        }

        .softmir-lang-status:last-of-type {
            border-bottom: none;
        }

        .softmir-lang-name {
            font-weight: 600;
            min-width: 80px;
        }

        .softmir-status-ok {
            color: #00a32a;
        }

        .softmir-status-warn {
            color: #dba617;
        }

        .softmir-status-info {
            color: #2271b1;
        }

        .softmir-status-none {
            color: #d63638;
        }

        .softmir-translate-single {
            margin-left: auto !important;
            min-width: 28px;
            padding: 0 4px !important;
        }

        .softmir-progress-bar {
            background: #e0e0e0;
            border-radius: 4px;
            height: 8px;
            margin: 10px 0 4px;
            overflow: hidden;
        }

        .softmir-progress-fill {
            background: #2271b1;
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .softmir-progress-text {
            font-size: 11px;
            color: #666;
            margin: 2px 0 0;
        }

        #softmir-translate-result {
            margin-top: 8px;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        #softmir-translate-result.success {
            background: #edfaef;
            color: #00a32a;
        }

        #softmir-translate-result.error {
            background: #fcf0f1;
            color: #d63638;
        }

        .softmir-translate-info {
            font-size: 12px;
            color: #666;
        }

        .softmir-translate-info a {
            text-decoration: none;
        }
    </style>
    <?php
}

// ======================== ENQUEUE ADMIN JS ========================

add_action('admin_enqueue_scripts', 'softmir_enqueue_translate_js');
function softmir_enqueue_translate_js($hook)
{
    if (!in_array($hook, ['post.php', 'post-new.php'])) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['software', 'integrator', 'sw_attribute'])) {
        return;
    }

    wp_enqueue_script(
        'softmir-ai-translate',
        get_template_directory_uri() . '/js/ai-translate.js',
        ['jquery'],
        filemtime(get_template_directory() . '/js/ai-translate.js'),
        true
    );

    wp_localize_script('softmir-ai-translate', 'softmirTranslate', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('softmir_translate'),
        'strings' => [
            'translating' => __('Translating...', 'softmir'),
            'translated' => __('Translated!', 'softmir'),
            'error' => __('Translation error', 'softmir'),
            'confirm' => __('Translate the post into all languages?', 'softmir'),
            'done' => __('Ready! Translated into %d language.', 'softmir'),
            'processing' => __('Processing %s...', 'softmir'),
        ],
    ]);
}

// ======================== TAXONOMY TERM TRANSLATION BUTTON ========================

add_action('software_category_edit_form_fields', 'softmir_term_translate_fields', 99, 2);
function softmir_term_translate_fields($term, $taxonomy)
{
    if (!function_exists('pll_default_language')) {
        return;
    }

    $term_lang = pll_get_term_language($term->term_id);
    $default_lang = pll_default_language();

    if ($term_lang && $term_lang !== $default_lang) {
        return; // Only show for default language terms
    }

    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : '';
    ?>
    <tr class="form-field">
        <th scope="row"><?php esc_html_e('🌐AI Translation', 'softmir'); ?></th>
        <td>
            <?php if (empty($api_key)): ?>
                <p class="description" style="color:#d63638;">
                    <?php esc_html_e('The API key is not configured.', 'softmir'); ?>
                    <a
                        href="<?php echo esc_url(admin_url('options-general.php?page=softmir-ai-settings')); ?>"><?php esc_html_e('Configure →', 'softmir'); ?></a>
                </p>
                <?php
            else: ?>
                <button type="button" class="button button-primary softmir-translate-term-btn"
                    data-term-id="<?php echo esc_attr($term->term_id); ?>" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                    🌐 <?php esc_html_e('Translate into all languages', 'softmir'); ?>
                </button>
                <span class="softmir-term-translate-status" style="margin-left:10px;"></span>
                <script>
                    jQuery(function ($) {
                        $('.softmir-translate-term-btn').on('click', function () {
                            var $btn = $(this);
                            var $status = $btn.next('.softmir-term-translate-status');
                            $btn.prop('disabled', true).text('⏳ ...');
                            $status.text('');

                            $.post(ajaxurl, {
                                action: 'softmir_translate_term_action',
                                nonce: '<?php echo wp_create_nonce('softmir_translate'); ?>',
                                term_id: $btn.data('term-id'),
                                taxonomy: $btn.data('taxonomy'),
                            }, function (response) {
                                $btn.prop('disabled', false).html('🌐 <?php echo esc_js(__('Translate into all languages', 'softmir')); ?>');
                                if (response.success) {
                                    $status.html('<span style="color:#00a32a;">✅ <?php echo esc_js(__('Translated!', 'softmir')); ?></span>');
                                } else {
                                    $status.html('<span style="color:#d63638;">❌ ' + (response.data?.message || 'Error') + '</span>');
                                }
                            }).fail(function () {
                                $btn.prop('disabled', false).html('🌐 <?php echo esc_js(__('Translate into all languages', 'softmir')); ?>');
                                $status.html('<span style="color:#d63638;">❌ Network error</span>');
                            });
                        });
                    });
                </script>
                <?php
            endif; ?>
        </td>
    </tr>
    <?php
}

// ======================== BULK ACTIONS FOR POSTS ========================

add_filter('bulk_actions-edit-software', 'softmir_add_translate_bulk_action');
add_filter('bulk_actions-edit-integrator', 'softmir_add_translate_bulk_action');
add_filter('bulk_actions-edit-sw_attribute', 'softmir_add_translate_bulk_action');
function softmir_add_translate_bulk_action($actions)
{
    $actions['softmir_translate_all'] = '🌐 ' . __('Translate into all languages', 'softmir');
    return $actions;
}

add_filter('handle_bulk_actions-edit-software', 'softmir_handle_translate_bulk', 10, 3);
add_filter('handle_bulk_actions-edit-integrator', 'softmir_handle_translate_bulk', 10, 3);
add_filter('handle_bulk_actions-edit-sw_attribute', 'softmir_handle_translate_bulk', 10, 3);
function softmir_handle_translate_bulk($redirect_to, $action, $post_ids)
{
    if ($action !== 'softmir_translate_all') {
        return $redirect_to;
    }

    $default_lang = pll_default_language();
    $all_langs = pll_languages_list();
    $target_langs = array_filter($all_langs, fn($lang) => $lang !== $default_lang);

    $translated = 0;
    $errors = 0;

    foreach ($post_ids as $post_id) {
        // Only translate default-language posts
        $lang = pll_get_post_language($post_id);
        if ($lang && $lang !== $default_lang) {
            continue;
        }

        foreach ($target_langs as $lang) {
            $result = softmir_translate_post($post_id, $lang);
            if (is_wp_error($result)) {
                $errors++;
            } else {
                $translated++;
            }
        }
    }

    return add_query_arg([
        'softmir_translated' => $translated,
        'softmir_errors' => $errors,
    ], $redirect_to);
}

// Show admin notice after bulk translation
add_action('admin_notices', 'softmir_translate_bulk_notice');
function softmir_translate_bulk_notice()
{
    if (isset($_GET['softmir_translated'])) {
        $translated = intval($_GET['softmir_translated']);
        $errors = intval($_GET['softmir_errors'] ?? 0);
        $class = $errors ? 'notice-warning' : 'notice-success';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>';
        printf(
            esc_html__('🌐 AI Translation: %d translations completed', 'softmir'),
            $translated
        );
        if ($errors) {
            printf(', ' . esc_html__('%d errors', 'softmir'), $errors);
        }
        echo '</p></div>';
    }
}

// ======================== CRON CLEANUP BUTTON ON LIST PAGE ========================

add_action('admin_notices', 'softmir_translate_cron_cleanup_notice');
function softmir_translate_cron_cleanup_notice()
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-software') {
        return;
    }

    // Count stale translation cron events
    $cron = _get_cron_array();
    $stale_count = 0;
    $pending_count = 0;
    $translate_hooks = [
        'softmir_async_translate_scout_cards',
        'softmir_background_translate_post',
        'softmir_do_translate_post',
        'softmir_do_translate_term',
    ];

    foreach ($cron as $timestamp => $hooks) {
        foreach ($hooks as $hook_name => $events) {
            if (in_array($hook_name, $translate_hooks)) {
                foreach ($events as $event) {
                    if ($timestamp < time() - 300) {
                        $stale_count++;
                    } else {
                        $pending_count++;
                    }
                }
            }
        }
    }

    // Show cleanup button if there are stale events
    if ($stale_count > 0) {
        echo '<div class="notice notice-warning" id="softmir-cron-cleanup-notice" style="display:flex;align-items:center;gap:12px;padding:8px 12px;">';
        echo '<p style="margin:0;">⚠️ <strong>Stuck translations:</strong> ' . $stale_count . ' cron events expired (>5 min).</p>';
        echo '<button type="button" class="button" id="softmir-cron-cleanup-btn" style="white-space:nowrap;">🧹 Clean</button>';
        echo '</div>';
    }

    // Also show if cleanup was just performed
    if (isset($_GET['softmir_cron_cleaned'])) {
        $cleaned = intval($_GET['softmir_cron_cleaned']);
        echo '<div class="notice notice-success is-dismissible"><p>';
        printf('🧹 Cleared stuck translation cron events: <strong>%d</strong>', $cleaned);
        echo '</p></div>';
    }

    // Show pending count as info
    if ($pending_count > 0 && $stale_count === 0) {
        echo '<div class="notice notice-info" style="padding:8px 12px;">';
        echo '<p style="margin:0;">⏳ Translations in queue: ' . $pending_count . ' (awaiting execution)</p>';
        echo '</div>';
    }
}

// AJAX handler for cron cleanup
add_action('wp_ajax_softmir_cleanup_translate_cron', 'softmir_ajax_cleanup_translate_cron');
function softmir_ajax_cleanup_translate_cron()
{
    check_ajax_referer('softmir_translate', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    $cron = _get_cron_array();
    $cleaned = 0;
    $translate_hooks = [
        'softmir_async_translate_scout_cards',
        'softmir_background_translate_post',
        'softmir_do_translate_post',
        'softmir_do_translate_term',
    ];

    foreach ($cron as $timestamp => $hooks) {
        foreach ($hooks as $hook_name => $events) {
            if (in_array($hook_name, $translate_hooks)) {
                foreach ($events as $key => $event) {
                    if ($timestamp < time() - 300) {
                        wp_unschedule_event($timestamp, $hook_name, $event['args']);
                        $cleaned++;
                    }
                }
            }
        }
    }

    wp_send_json_success([
        'cleaned' => $cleaned,
        'message' => "Очищено: {$cleaned} зависших событий",
    ]);
}

// JS for the cleanup button
add_action('admin_footer', 'softmir_cron_cleanup_js');
function softmir_cron_cleanup_js()
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-software') {
        return;
    }
    ?>
    <script>
        jQuery(function ($) {
            $('#softmir-cron-cleanup-btn').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Clearing...');
                $.post(ajaxurl, {
                    action: 'softmir_cleanup_translate_cron',
                    nonce: '<?php echo wp_create_nonce('softmir_translate'); ?>'
                }, function (response) {
                    if (response.success) {
                        $btn.text('✅ ' + response.data.message);
                        setTimeout(function () {
                            $('#softmir-cron-cleanup-notice').fadeOut();
                        }, 2000);
                    } else {
                        $btn.prop('disabled', false).text('❌ Error');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('❌ Network error');
                });
            });
        });
    </script>
    <?php
}
