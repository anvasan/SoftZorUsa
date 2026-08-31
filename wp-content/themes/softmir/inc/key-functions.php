<?php
/**
 * SoftMir — Key Functions System
 * Handles category-bound key functions and their display on software cards
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========== 1. Category Form Fields (Term Meta) ==========

/**
 * Add field to Add Category screen
 */
function softmir_key_functions_add_form_field()
{
    ?>
    <div class="form-field">
        <label for="sw_key_functions">
            <?php esc_html_e('Key Features', 'softmir'); ?>
        </label>
        <textarea name="sw_key_functions" id="sw_key_functions" rows="5" cols="40"></textarea>
        <p class="description">
            <?php esc_html_e('List the key features specific to this category, separated by commas (for example: Analytics, CRM, API Integration). Subcategories will automatically inherit these features.', 'softmir'); ?>
        </p>
    </div>
    <?php
}
add_action('software_category_add_form_fields', 'softmir_key_functions_add_form_field');

/**
 * Add field to Edit Category screen
 */
function softmir_key_functions_edit_form_field($term)
{
    $key_functions = get_term_meta($term->term_id, '_key_functions', true);

    // Also show inherited functions for reference
    $inherited = softmir_get_inherited_key_functions($term->term_id);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top">
            <label for="sw_key_functions">
                <?php esc_html_e('Key Features (Unique)', 'softmir'); ?>
            </label>
        </th>
        <td>
            <textarea name="sw_key_functions" id="sw_key_functions" rows="5" cols="50"
                class="large-text"><?php echo esc_textarea($key_functions); ?></textarea>
            <p class="description" style="margin-bottom: 10px;">
                <?php esc_html_e('List the features separated by commas ONLY for that specific category.', 'softmir'); ?>
            </p>
            <div style="margin-bottom: 15px;">
                <button type="button" class="button button-secondary ai-generate-functions-btn"
                    data-term-id="<?php echo esc_attr($term->term_id); ?>">
                    🤖 <?php esc_html_e('Generate Macro Features Using AI', 'softmir'); ?>
                </button>
                <span class="ai-generate-status" style="margin-left: 10px; font-weight: bold;"></span>
            </div>
            <script>
                jQuery(document).ready(function ($) {
                    $('.ai-generate-functions-btn').on('click', function (e) {
                        e.preventDefault();
                        var btn = $(this);
                        var termId = btn.data('term-id');
                        var status = btn.siblings('.ai-generate-status');
                        var textArea = $('#sw_key_functions');

                        if (!confirm('Это автоматически подберет базовые функции через AndAnd. Заменить текущий список?')) {
                            return;
                        }

                        btn.prop('disabled', true);
                        status.text('⏳ Ожидайте, AndAnd анализирует отрасль...');
                        status.css('color', '#d63638');

                        $.ajax({
                            url: '/wp-json/softmir/v1/generate-category-features',
                            method: 'POST',
                            beforeSend: function (xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
                            },
                            data: {
                                term_id: termId
                            },
                            success: function (res) {
                                if (res.success) {
                                    textArea.val(res.features);
                                    status.text('✅ Done! Click "Update" at the bottom of the page.');
                                    status.css('color', '#00a32a');
                                } else {
                                    status.text('❌ Error: ' + (res.message || 'Unknown error'));
                                }
                                btn.prop('disabled', false);
                            },
                            error: function (xhr) {
                                var errMsg = xhr.statusText;
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errMsg = xhr.responseJSON.message;
                                }
                                status.text('❌ Error API: ' + errMsg);
                                btn.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>

            <?php if (!empty($inherited)): ?>
                <div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6;">
                    <strong>
                        <?php esc_html_e('Inherited from parent categories:', 'softmir'); ?>
                    </strong><br>
                    <?php echo esc_html(implode(', ', $inherited)); ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}
add_action('software_category_edit_form_fields', 'softmir_key_functions_edit_form_field');

/**
 * Save Category Meta
 */
function softmir_key_functions_save_term_meta($term_id)
{
    if (isset($_POST['sw_key_functions'])) {
        $functions = sanitize_textarea_field($_POST['sw_key_functions']);
        // Clean up format (remove extra spaces around commas)
        $functions_array = array_map('trim', explode(',', $functions));
        $functions_array = array_filter($functions_array); // Remove empty
        update_term_meta($term_id, '_key_functions', implode(', ', $functions_array));
    }
}
add_action('created_software_category', 'softmir_key_functions_save_term_meta');
add_action('edited_software_category', 'softmir_key_functions_save_term_meta');

// ========== 2. Data Retrieval (Inheritance Logic) ==========

/**
 * Get inherited functions from ALL parent categories
 */
function softmir_get_inherited_key_functions($term_id)
{
    $inherited = [];
    $ancestors = get_ancestors($term_id, 'software_category');

    if (!empty($ancestors)) {
        // Reverse to get top-level first for logical ordering
        $ancestors = array_reverse($ancestors);
        foreach ($ancestors as $ancestor_id) {
            $funcs = get_term_meta($ancestor_id, '_key_functions', true);
            if (!empty($funcs)) {
                $funcs_array = array_map('trim', explode(',', $funcs));
                $inherited = array_merge($inherited, $funcs_array);
            }
        }
    }

    return array_unique(array_filter($inherited));
}

/**
 * Get ALL key functions for a category (Own + Inherited)
 */
function softmir_get_all_key_functions_for_category($term_id)
{
    if (!$term_id)
        return [];

    $own_funcs = [];
    $raw_own = get_term_meta($term_id, '_key_functions', true);
    if (!empty($raw_own)) {
        $own_funcs = array_map('trim', explode(',', $raw_own));
    }

    $inherited_funcs = softmir_get_inherited_key_functions($term_id);

    $all = array_merge($inherited_funcs, $own_funcs);
    return array_unique(array_filter($all));
}

// ========== 3. Product Meta Box ==========

/**
 * Add Meta Box to Software Edit Page
 */
function softmir_key_functions_meta_box()
{
    add_meta_box(
        'softmir_software_key_functions',
        '🔑 Key features (from category)',
        'softmir_software_key_functions_render',
        'software',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'softmir_key_functions_meta_box');

function softmir_software_key_functions_render($post)
{
    wp_nonce_field('softmir_key_functions', 'softmir_key_functions_nonce');

    // Get primary category from ACF
    $primary_cat_id = get_post_meta($post->ID, 'primary_category', true);

    if (!$primary_cat_id) {
        // Fallback to the first assigned software_category if primary isn't explicitly set yet
        $terms = get_the_terms($post->ID, 'software_category');
        if ($terms && !is_wp_error($terms)) {
            $primary_cat_id = $terms[0]->term_id;
        }
    }

    if (!$primary_cat_id) {
        echo '<p style="color:#d63638; font-weight:bold;">⚠️ Select Main Category (or any category in the right panel) and save a draft to see available features.</p>';
        return;
    }

    $term = get_term($primary_cat_id, 'software_category');
    if (!$term || is_wp_error($term)) {
        echo '<p>Category not found.</p>';
        return;
    }

    $available_functions = softmir_get_all_key_functions_for_category($primary_cat_id);

    if (empty($available_functions)) {
        echo '<p style="color:#d63638;">In category "<strong>' . esc_html($term->name) . '</strong>" no key functions are specified.</p>';
        ?>
        <div style="margin-bottom: 15px;">
            <button type="button" class="button button-primary ai-generate-functions-btn-inline"
                data-term-id="<?php echo esc_attr($primary_cat_id); ?>">
                🤖 Сгенерировать функции категории через AndAnd
            </button>
            <span class="ai-generate-status-inline" style="margin-left: 10px; font-weight: bold;"></span>
        </div>
        <script>
            jQuery(document).ready(function ($) {
                $('.ai-generate-functions-btn-inline').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var termId = btn.data('term-id');
                    var status = btn.siblings('.ai-generate-status-inline');

                    btn.prop('disabled', true);
                    status.text('⏳ Ожидайте, AndAnd генерирует макро-функции для категории...');
                    status.css('color', '#d63638');

                    $.ajax({
                        url: '/wp-json/softmir/v1/generate-category-features',
                        method: 'POST',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
                        },
                        data: { term_id: termId },
                        success: function (res) {
                            if (res.success) {
                                status.text('✅ Done! Reload the page to select features.');
                                status.css('color', '#00a32a');
                                setTimeout(function() { location.reload(); }, 2000);
                            } else {
                                status.text('❌ Error: ' + (res.message || 'Unknown error'));
                            }
                            btn.prop('disabled', false);
                        },
                        error: function (xhr) {
                            var errMsg = xhr.statusText;
                            if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
                            status.text('❌ Error API: ' + errMsg);
                            btn.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
        return;
    }

    // Get currently selected functions
    $selected_functions = get_post_meta($post->ID, '_selected_key_functions', true);
    if (!is_array($selected_functions)) {
        $selected_functions = [];
    }

    $custom_features = get_post_meta($post->ID, 'custom_features', true);

    echo '<div style="margin-bottom: 15px;">';
    echo '<label for="sw_custom_features" style="font-weight: 600; display: block; margin-bottom: 5px;">' . esc_html__('Product Key Features (from website)', 'softmir') . '</label>';
    echo '<textarea name="sw_custom_features" id="sw_custom_features" rows="3" class="large-text">' . esc_textarea($custom_features) . '</textarea>';
    echo '<p class="description" style="margin-top: 5px; margin-bottom: 0;">' . esc_html__('Comma-separated. Filled automatically during enrichment. Displayed on the frontend of the card.', 'softmir') . '</p>';
    echo '</div>';

    echo '<details style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">';
    echo '<summary style="cursor:pointer; font-weight:600; color:#666;">📊 ' . esc_html__('Standard Category Features (for Compare)', 'softmir') . '</summary>';
    echo '<p style="margin-top: 10px;">' . sprintf(esc_html__('Select the features supported by the product (varies by category %s):', 'softmir'), '<strong>' . esc_html($term->name) . '</strong>') . '</p>';
    echo '<div style="column-count: 2; column-gap: 20px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: 10px;">';

    foreach ($available_functions as $func) {
        $checked = in_array($func, $selected_functions) ? 'checked' : '';
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="checkbox" name="sw_selected_functions[]" value="' . esc_attr($func) . '" ' . $checked . '> ' . esc_html($func);
        echo '</label>';
    }

    echo '</div>';
    echo '</details>';
}

/**
 * Save Software Meta Box
 */
function softmir_key_functions_save_post($post_id)
{
    if (!isset($_POST['softmir_key_functions_nonce']) || !wp_verify_nonce($_POST['softmir_key_functions_nonce'], 'softmir_key_functions')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'software') {
        return;
    }

    if (isset($_POST['sw_selected_functions']) && is_array($_POST['sw_selected_functions'])) {
        $funcs = array_map('sanitize_text_field', $_POST['sw_selected_functions']);
        update_post_meta($post_id, '_selected_key_functions', $funcs);
    } else {
        update_post_meta($post_id, '_selected_key_functions', []);
    }

    if (isset($_POST['sw_custom_features'])) {
        $custom = sanitize_textarea_field($_POST['sw_custom_features']);
        $custom_array = array_map('trim', explode(',', $custom));
        $custom_array = array_filter($custom_array);
        update_post_meta($post_id, 'custom_features', implode(', ', $custom_array));
    }
}
add_action('save_post', 'softmir_key_functions_save_post');

// ========== 4. Frontend Rendering ==========

/**
 * Render Key Functions for Software Card (Middle Section)
 */
function softmir_render_key_functions($post_id, $max_rows = 4)
{
    // Priority: custom_features (real product features from website)
    $custom_features_raw = get_post_meta($post_id, 'custom_features', true);
    if (!empty($custom_features_raw)) {
        $selected_functions = is_array($custom_features_raw) ? $custom_features_raw : explode(',', $custom_features_raw);
        $selected_functions = array_map('trim', $selected_functions);
        $selected_functions = array_filter($selected_functions);
    } else {
        // Fallback: category-based key functions (for backwards compatibility)
        $selected_functions = get_post_meta($post_id, '_selected_key_functions', true);
    }

    if (empty($selected_functions) || !is_array($selected_functions)) {
        return '';
    }

    $max_visible = $max_rows * 2; // 2 columns

    $render_item = function ($func) {
        $html = '<div class="attr-item kf-item">';
        // Replacing checkmark with requested HTML entity &#9989;
        $html .= '<span class="attr-icon">&#9989;</span> ';
        $html .= '<span class="attr-label">' . esc_html($func) . '</span> ';
        $html .= '</div>';
        return $html;
    };

    $render_columns = function ($items) use ($render_item) {
        if (empty($items))
            return '';
        $mid = ceil(count($items) / 2);
        $col1 = array_slice($items, 0, $mid);
        $col2 = array_slice($items, $mid);

        $html = '<div class="attrs-columns kf-columns">';
        $html .= '<div class="attrs-col">';
        foreach ($col1 as $func) {
            $html .= $render_item($func);
        }
        $html .= '</div>';
        $html .= '<div class="attrs-col">';
        foreach ($col2 as $func) {
            $html .= $render_item($func);
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    };

    $total = count($selected_functions);
    static $kf_toggle_counter = 0;

    $html = '';

    // In archive pages (cards), we limit to $max_visible
    // (We apply the logic if $max_rows > 0 and total > limit)
    if ($max_rows > 0 && $total > $max_visible) {
        $visible_items = array_slice($selected_functions, 0, $max_visible);
        $hidden_items = array_slice($selected_functions, $max_visible);
        $hidden_count = count($hidden_items);
        $kf_toggle_counter++;
        $toggle_id = 'kf-hidden-' . $post_id . '-' . $kf_toggle_counter;

        $html .= '<div class="attrs-section attrs-visible" style="margin-bottom:0px;">';
        $html .= $render_columns($visible_items);
        $html .= '</div>';
        $html .= '<div class="attrs-section attrs-hidden" id="' . $toggle_id . '">';
        $html .= $render_columns($hidden_items);
        $html .= '</div>';

        $html .= '<button type="button" class="attrs-toggle-btn" onclick="softmirToggleAttrs(\'' . $toggle_id . '\', this)">';
        $html .= sprintf(__('Another %d attr. ▾', 'softmir'), $hidden_count);
        $html .= '</button>';
    } else {
        $html .= '<div class="attrs-section" style="margin-bottom:0px;">';
        $html .= $render_columns($selected_functions);
        $html .= '</div>';
    }

    return $html;
}

// ========== 5. Integrations Block Rendering ==========

/**
 * Render Integrations Block for Software Card
 * Reads from 'integrations' post meta and renders as tags
 */
function softmir_render_integrations_block($post_id)
{
    // Getting raw value. Falls back to default if necessary.
    $integrations_raw = get_post_meta($post_id, 'integrations', true);

    // If not found, try softmir_get_field_with_lang_fallback just in case it's synchronized differently
    if (empty($integrations_raw) && function_exists('softmir_get_field_with_lang_fallback')) {
        $integrations_raw = softmir_get_field_with_lang_fallback('integrations', $post_id);
    }

    if (empty($integrations_raw)) {
        return '';
    }

    // Handle both array and comma-separated string formats
    if (is_array($integrations_raw)) {
        $integrations = array_map('trim', $integrations_raw);
    } else {
        $integrations = array_map('trim', explode(',', $integrations_raw));
    }
    $integrations = array_filter($integrations);

    if (empty($integrations)) {
        return '';
    }

    $html = '<div class="integrations-section detail-block" style="margin-top: 1.5rem; margin-bottom: 2rem;">';
    $html .= '<h3 class="section-heading--sm" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">';
    $html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';

    // Attempt localized title if array/function exists
    $title = 'Integrations';
    if (function_exists('softmir_quiz_t')) {
        $title = softmir_quiz_t('sw_integrations', 'Integrations');
    }

    $html .= esc_html($title);
    $html .= '</h3>';

    $html .= '<div class="integrations-tags" style="display: flex; flex-wrap: wrap; gap: 8px;">';
    foreach ($integrations as $integration) {
        $html .= '<span style="background: #f3f4f6; color: #374151; padding: 6px 12px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; border: 1px solid #e5e7eb;">' . esc_html($integration) . '</span>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

// ========== Columns for Category List ==========
add_filter('manage_edit-software_category_columns', function ($columns) {
    $columns['ai_features'] = 'Key Features (AI)';
    return $columns;
});

add_filter('manage_software_category_custom_column', function ($content, $column_name, $term_id) {
    if ($column_name === 'ai_features') {
        $features = get_term_meta($term_id, '_key_functions', true);
        if (empty($features)) {
            $content = '<span style="color:#d63638;">— Queue (Empty) —</span>';
        } else {
            $excerpt = mb_substr($features, 0, 80) . (mb_strlen($features) > 80 ? '...' : '');
            $content = '<span style="color:#00a32a;">✅ ' . esc_html($excerpt) . '</span>';
        }
    }
    return $content;
}, 10, 3);
