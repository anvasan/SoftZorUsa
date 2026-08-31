<?php
/**
 * SoftMir — AI Auto-Posting System
 * Generates blog articles via Google Gemini API on a WP-Cron schedule.
 * Articles are themed around software categories from the catalog.
 */

if (!defined('ABSPATH')) exit;

// ======================== SETTINGS ========================

/**
 * Get autopost settings with defaults.
 */
function softmir_autopost_settings()
{
    return wp_parse_args(get_option('softmir_autopost', []), [
        'enabled'          => false,
        'frequency'        => 'daily',      // daily | every_3_days | weekly
        'default_status'   => 'draft',      // draft | publish
        'max_per_month'    => 30,
        'article_types'    => ['review', 'guide', 'comparison', 'trends'],
        'auto_translate'   => true,
    ]);
}

// ======================== CRON SCHEDULE ========================

/**
 * Register custom cron intervals.
 */
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_3_days'] = [
        'interval' => 3 * DAY_IN_SECONDS,
        'display'  => __('Every 3 days', 'softmir'),
    ];
    return $schedules;
});

/**
 * Schedule/unschedule cron based on settings.
 */
add_action('init', 'softmir_autopost_schedule_cron');
function softmir_autopost_schedule_cron()
{
    $settings = softmir_autopost_settings();
    $hook = 'softmir_autopost_generate';

    if ($settings['enabled']) {
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $settings['frequency'], $hook);
        }
    } else {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
}

// ======================== ARTICLE GENERATION ========================

add_action('softmir_autopost_generate', 'softmir_autopost_run');

/**
 * Main autopost handler — generates one article per run.
 */
function softmir_autopost_run()
{
    $settings = softmir_autopost_settings();

    if (!$settings['enabled']) {
        return;
    }

    // Check monthly limit
    $month_start = date('Y-m-01 00:00:00');
    $posts_this_month = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => ['publish', 'draft'],
        'date_query'     => [['after' => $month_start]],
        'meta_key'       => '_softmir_autopost',
        'meta_value'     => '1',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    if ($posts_this_month->found_posts >= $settings['max_per_month']) {
        return; // Monthly limit reached
    }

    // Get Gemini API key
    $api_key = function_exists('softmir_get_gemini_key') ? softmir_get_gemini_key() : (defined('GOOGLE_GEMINI_KEY') ? GOOGLE_GEMINI_KEY : '');
    if (empty($api_key)) {
        return;
    }

    // Pick a random software category for article topic
    $categories = get_terms([
        'taxonomy'   => 'software_category',
        'hide_empty' => true,
        'parent'     => 0,
        'number'     => 50,
        'lang'       => function_exists('pll_default_language') ? pll_default_language() : '',
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        return;
    }

    $category = $categories[array_rand($categories)];

    // Pick article type
    $types = $settings['article_types'];
    $article_type = $types[array_rand($types)];

    // Get existing article titles to avoid duplicates
    $existing = get_posts([
        'post_type'      => 'post',
        'post_status'    => ['publish', 'draft'],
        'posts_per_page' => 20,
        'meta_key'       => '_softmir_autopost',
        'meta_value'     => '1',
        'fields'         => 'ids',
    ]);
    $existing_titles = array_map(function ($id) {
        return get_the_title($id);
    }, $existing);
    $titles_hint = !empty($existing_titles)
        ? "\n\nDO NOT REPEAT these already existing headings:\n- " . implode("\n- ", array_slice($existing_titles, 0, 10))
        : '';

    // Generate article
    $result = softmir_autopost_generate_article($api_key, $category, $article_type, $titles_hint);

    if (is_wp_error($result)) {
        error_log('[SoftMir Autopost] Error: ' . $result->get_error_message());
        return;
    }

    // Create post
    $post_id = wp_insert_post([
        'post_type'    => 'post',
        'post_status'  => $settings['default_status'],
        'post_title'   => sanitize_text_field($result['title']),
        'post_content' => wp_kses_post($result['content']),
        'post_excerpt' => sanitize_text_field($result['excerpt']),
        'post_author'  => 1,
    ]);

    if (is_wp_error($post_id)) {
        error_log('[SoftMir Autopost] Insert failed: ' . $post_id->get_error_message());
        return;
    }

    // Mark as autopost
    update_post_meta($post_id, '_softmir_autopost', '1');
    update_post_meta($post_id, '_softmir_autopost_type', $article_type);
    update_post_meta($post_id, '_softmir_autopost_category', $category->name);

    // Assign blog category (create if needed)
    $blog_cat_slug = 'software-' . $article_type;
    $type_labels = [
        'review'     => __('Software Reviews', 'softmir'),
        'guide'      => __('Guides', 'softmir'),
        'comparison' => __('Comparisons', 'softmir'),
        'trends'     => __('Trends', 'softmir'),
    ];
    $blog_cat_name = $type_labels[$article_type] ?? __('Articles', 'softmir');
    $blog_cat = get_term_by('slug', $blog_cat_slug, 'category');
    if (!$blog_cat) {
        $result_cat = wp_insert_term($blog_cat_name, 'category', ['slug' => $blog_cat_slug]);
        if (!is_wp_error($result_cat)) {
            $blog_cat = get_term($result_cat['term_id'], 'category');
        }
    }
    if ($blog_cat) {
        wp_set_post_categories($post_id, [$blog_cat->term_id]);
    }

    // Set tags from generated tags
    if (!empty($result['tags'])) {
        wp_set_post_tags($post_id, $result['tags']);
    }

    // Set Polylang language
    if (function_exists('pll_set_post_language')) {
        pll_set_post_language($post_id, pll_default_language());
    }

    // Auto-translate if enabled and post is published
    if ($settings['auto_translate'] && $settings['default_status'] === 'publish') {
        if (!wp_next_scheduled('softmir_do_translate_post', [$post_id])) {
            wp_schedule_single_event(time() + 10, 'softmir_do_translate_post', [$post_id]);
        }
    }

    error_log(sprintf(
        '[SoftMir Autopost] Created post #%d: "%s" (type: %s, category: %s)',
        $post_id,
        $result['title'],
        $article_type,
        $category->name
    ));
}

/**
 * Call Gemini API to generate an article.
 */
function softmir_autopost_generate_article($api_key, $category, $article_type, $titles_hint = '')
{
    $type_prompts = [
        'review'     => "Напиши подробный обзор категории \"{$category->name}\". Расскажи о ключевых решениях, их особенностях, для кого подходят, плюсы и minусы. Добавь практические советы по выбору.",
        'guide'      => "Напиши практическое руководство по выбору Software в категории \"{$category->name}\". Объясни критерии выбора, типичные ошибки, на что обратить внимание, чек-лист для оценки.",
        'comparison' => "Напиши сравнительный анализ решений в категории \"{$category->name}\". Сравни по функционалу, ценам, целевой аудитории, integratиям. Andспользуй структурированные таблицы.",
        'trends'     => "Напиши статью о трендах и тенденциях в категории \"{$category->name}\". Расскажи о новых технологиях, изменениях на рынке, прогнозах развития, новых подходах.",
    ];

    $prompt = "You are a professional writer for a business software blog. Write in Russian.\n\n"
        . ($type_prompts[$article_type] ?? $type_prompts['review']) . "\n\n"
        . "Requirements for the article:\n"
        . "- Length: 1500-2500 words\n"
        . "- Use H2 and H3 headings for structure\n"
        . "- Write in an informative but friendly tone\n"
        . "- Do not mention specific prices (they change often)\n"
        . "- Add useful tips and tricks\n"
        . "- Content must be unique and useful for the reader\n"
        . $titles_hint . "\n\n"
        . "Return JSON with fields:\n"
        . "- \"title\": article title (SEO-optimized, 50-70 characters)\n"
        . "- \"excerpt\": short description (150-160 characters)\n"
        . "- \"content\": full text of the article in HTML format (h2, h3, p, ul, ol, blockquote)\n"
        . "- \"tags\": array of 3-5 tags\n\n"
        . "Return JSON ONLY, no explanation.";

    $model = defined('SOFTMIR_GEMINI_MODEL') ? SOFTMIR_GEMINI_MODEL : 'gemini-3.1-flash-lite';
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

    $body = [
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'tools' => [['googleSearch' => new stdClass()]],
        'generationConfig' => [
            'temperature'      => 0.7,
        ],
    ];

    $response = wp_remote_post($endpoint . '?key=' . $api_key, [
        'headers'     => ['Content-Type' => 'application/json'],
        'body'        => wp_json_encode($body),
        'timeout'     => 300,
        'data_format' => 'body',
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);

    if ($status !== 200) {
        return new WP_Error('api_error', "Gemini API error {$status}: {$body_raw}");
    }

    $data = json_decode($body_raw, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($text)) {
        return new WP_Error('empty_response', 'Gemini returned empty response');
    }

    // Clean markdown code fences
    $text = trim($text);
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $article = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Failed to parse article JSON: ' . json_last_error_msg());
    }

    if (empty($article['title']) || empty($article['content'])) {
        return new WP_Error('invalid_article', 'Article is missing title or content');
    }

    return $article;
}

// ======================== ADMIN SETTINGS ========================

add_action('admin_init', 'softmir_autopost_register_settings');
function softmir_autopost_register_settings()
{
    register_setting('softmir_ai_settings', 'softmir_autopost', [
        'type'              => 'array',
        'sanitize_callback' => 'softmir_autopost_sanitize_settings',
    ]);
}

function softmir_autopost_sanitize_settings($input)
{
    return [
        'enabled'        => !empty($input['enabled']),
        'frequency'      => in_array($input['frequency'] ?? '', ['daily', 'every_3_days', 'weekly']) ? $input['frequency'] : 'daily',
        'default_status' => in_array($input['default_status'] ?? '', ['draft', 'publish']) ? $input['default_status'] : 'draft',
        'max_per_month'  => max(1, min(100, intval($input['max_per_month'] ?? 30))),
        'article_types'  => array_intersect($input['article_types'] ?? [], ['review', 'guide', 'comparison', 'trends']),
        'auto_translate' => !empty($input['auto_translate']),
    ];
}

/**
 * Render autopost settings section in the SoftMir AI admin page.
 */
function softmir_autopost_render_settings()
{
    $settings = softmir_autopost_settings();
    $frequencies = [
        'daily'       => __('Daily', 'softmir'),
        'every_3_days' => __('Every 3 days', 'softmir'),
        'weekly'      => __('Weekly', 'softmir'),
    ];
    $statuses = [
        'draft'   => __('Draft', 'softmir'),
        'publish' => __('Publish', 'softmir'),
    ];
    $article_types = [
        'review'     => __('Reviews', 'softmir'),
        'guide'      => __('Guides', 'softmir'),
        'comparison' => __('Comparisons', 'softmir'),
        'trends'     => __('Trends', 'softmir'),
    ];
    ?>
    <div class="softmir-settings-section">
        <h3>📝 <?php esc_html_e('Auto-posting blog', 'softmir'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="autopost_enabled"><?php esc_html_e('Enable auto posting', 'softmir'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="softmir_autopost[enabled]" id="autopost_enabled" value="1"
                            <?php checked($settings['enabled']); ?>>
                        <?php esc_html_e('Automatically generate articles', 'softmir'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="autopost_frequency"><?php esc_html_e('Frequency', 'softmir'); ?></label></th>
                <td>
                    <select name="softmir_autopost[frequency]" id="autopost_frequency">
                        <?php foreach ($frequencies as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($settings['frequency'], $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="autopost_status"><?php esc_html_e('Default Status', 'softmir'); ?></label></th>
                <td>
                    <select name="softmir_autopost[default_status]" id="autopost_status">
                        <?php foreach ($statuses as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($settings['default_status'], $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('“Draft” - for manual moderation before publication', 'softmir'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="autopost_max"><?php esc_html_e('Maximum per month', 'softmir'); ?></label></th>
                <td>
                    <input type="number" name="softmir_autopost[max_per_month]" id="autopost_max"
                        value="<?php echo esc_attr($settings['max_per_month']); ?>" min="1" max="100" class="small-text">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Article types', 'softmir'); ?></th>
                <td>
                    <?php foreach ($article_types as $val => $label): ?>
                        <label style="margin-right: 1rem;">
                            <input type="checkbox" name="softmir_autopost[article_types][]" value="<?php echo esc_attr($val); ?>"
                                <?php checked(in_array($val, $settings['article_types'])); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th><label for="autopost_translate"><?php esc_html_e('Auto-translation', 'softmir'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="softmir_autopost[auto_translate]" id="autopost_translate" value="1"
                            <?php checked($settings['auto_translate']); ?>>
                        <?php esc_html_e('Automatically translate into all languages', 'softmir'); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
