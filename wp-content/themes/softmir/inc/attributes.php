<?php
/**
 * SoftMir — Universal Attributes System
 * Helper functions for dynamic software attributes
 */

// ========== Get all attribute definitions ==========
function softmir_get_attributes($args = [])
{
    $defaults = [
        'post_type' => 'sw_attribute',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ];
    // Always get attributes from default language (meta keys are bound to original IDs)
    if (function_exists('pll_default_language')) {
        $defaults['lang'] = pll_default_language();
    }
    $query = new WP_Query(array_merge($defaults, $args));
    return $query->posts;
}

// ========== Get attributes filtered by position ==========
function softmir_get_attrs_by_position($position_key, $position_value)
{
    return softmir_get_attributes([
        'meta_query' => [
            [
                'key' => $position_key,
                'value' => $position_value,
                'compare' => '=',
            ],
        ],
    ]);
}

// ========== Get attributes for a specific category ==========
function softmir_get_attrs_for_category($term_id)
{
    $all_attrs = softmir_get_attributes();
    $result = [];

    foreach ($all_attrs as $attr) {
        $bound_cats = get_post_meta($attr->ID, '_attr_categories', true);
        // If no categories bound — show for all
        if (empty($bound_cats) || !is_array($bound_cats)) {
            $result[] = $attr;
        } elseif (in_array($term_id, $bound_cats)) {
            $result[] = $attr;
        }
    }

    return $result;
}

// ========== Check if attribute should show for a software post ==========
function softmir_attr_applies_to_software($attr_id, $software_id)
{
    $bound_cats = get_post_meta($attr_id, '_attr_categories', true);

    // No binding = universal
    if (empty($bound_cats) || !is_array($bound_cats)) {
        return true;
    }

    $software_terms = wp_get_post_terms($software_id, 'software_category', ['fields' => 'ids']);
    if (is_wp_error($software_terms)) {
        return false;
    }

    // Polylang: Map post term IDs back to default language IDs to match bound_cats
    if (function_exists('pll_get_term') && function_exists('pll_default_language')) {
        $default_lang = pll_default_language();
        $software_terms = array_map(function ($term_id) use ($default_lang) {
            return pll_get_term($term_id, $default_lang) ?: $term_id;
        }, $software_terms);
    }

    return !empty(array_intersect($bound_cats, $software_terms));
}

// ========== Get attribute value for a software post ==========
function softmir_get_software_attr_value($software_id, $attr_id)
{
    return get_post_meta($software_id, '_sw_attr_' . $attr_id, true);
}

// ========== Get attribute meta (settings) ==========
function softmir_get_attr_meta($attr_id)
{
    return [
        'type' => get_post_meta($attr_id, '_attr_type', true) ?: 'text',
        'icon' => get_post_meta($attr_id, '_attr_icon', true) ?: '',
        'filterable' => (bool) get_post_meta($attr_id, '_attr_filterable', true),
        'card_position' => get_post_meta($attr_id, '_attr_card_position', true) ?: 'none',
        'page_position' => get_post_meta($attr_id, '_attr_page_position', true) ?: 'none',
        'options' => get_post_meta($attr_id, '_attr_options', true) ?: '',
        'multiple' => (bool) get_post_meta($attr_id, '_attr_multiple', true),
    ];
}

// ========== Parse comma-separated options ==========
function softmir_parse_options($options_string)
{
    if (empty($options_string))
        return [];
    return array_map('trim', explode(',', $options_string));
}

// ========== Render attribute value for display ==========
function softmir_render_attr_value($attr_id, $value)
{
    if (empty($value))
        return '';

    $meta = softmir_get_attr_meta($attr_id);
    $icon = $meta['icon'] ? '<span class="attr-icon">' . esc_html($meta['icon']) . '</span> ' : '';

    // Use translated attribute title for current language
    $display_attr_id = $attr_id;
    if (function_exists('pll_get_post') && function_exists('pll_current_language')) {
        $trans_id = pll_get_post($attr_id, pll_current_language());
        if ($trans_id) {
            $display_attr_id = $trans_id;
        }
    }
    $label = get_the_title($display_attr_id);

    switch ($meta['type']) {
        case 'checkbox':
            if (is_array($value)) {
                $badges = '';
                foreach ($value as $v) {
                    $badges .= '<span class="attr-badge">' . esc_html($v) . '</span>';
                }
                return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . ':</span> <span class="attr-badges">' . $badges . '</span></div>';
            }
            // Single checkbox = yes/no
            return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . '</span> <span class="attr-value">✓</span></div>';

        case 'select':
        case 'text':
        default:
            if (is_array($value)) {
                $badges = '';
                foreach ($value as $v) {
                    // Extract text if it is saved in ACF repeater format
                    $str_val = is_array($v) ? ($v['text'] ?? '') : $v;
                    if (!empty($str_val)) {
                        $badges .= '<span class="attr-badge">' . esc_html($str_val) . '</span>';
                    }
                }
                return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . ':</span> <span class="attr-badges">' . $badges . '</span></div>';
            }

            // Single value text or select
            return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . ':</span> <span class="attr-value">' . esc_html($value) . '</span></div>';

        case 'url':
            return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . ':</span> <a href="' . esc_url($value) . '" target="_blank" class="attr-link">' . esc_html($value) . '</a></div>';

        case 'number':
            return '<div class="attr-item">' . $icon . '<span class="attr-label">' . esc_html($label) . ':</span> <span class="attr-value">' . esc_html($value) . '</span></div>';
    }
}

function softmir_render_attrs_block($software_id, $position_key, $position_value, $wrapper_class = '', $max_visible = 0)
{
    static $attrs_toggle_counter = 0;

    $attrs = softmir_get_attrs_by_position($position_key, $position_value);
    if (empty($attrs))
        return '';

    // Filter applicable attributes first
    $applicable_attrs = [];
    foreach ($attrs as $attr) {
        // Skip 'Integrations' attribute — it has a dedicated render block now
        $attr_title = mb_strtolower(get_the_title($attr->ID));
        if (mb_strpos($attr_title, 'integration') !== false || mb_strpos($attr_title, 'Integrate') !== false || mb_strpos($attr_title, 'integration') !== false) {
            continue;
        }
        if (softmir_attr_applies_to_software($attr->ID, $software_id)) {
            $value = softmir_get_software_attr_value($software_id, $attr->ID);
            if (!empty($value)) {
                $applicable_attrs[] = [
                    'id' => $attr->ID,
                    'value' => $value
                ];
            }
        }
    }

    if (empty($applicable_attrs))
        return '';

    // If "middle" position, we need to split by "Card Position - Middle" (Top) vs Others (Bottom)
    if ($position_value === 'middle') {

        $render_columns = function ($items) {
            if (empty($items))
                return '';
            $mid = ceil(count($items) / 2);
            $col1 = array_slice($items, 0, $mid);
            $col2 = array_slice($items, $mid);

            $html = '<div class="attrs-columns">';
            $html .= '<div class="attrs-col">';
            foreach ($col1 as $item)
                $html .= softmir_render_attr_value($item['id'], $item['value']);
            $html .= '</div>';
            $html .= '<div class="attrs-col">';
            foreach ($col2 as $item)
                $html .= softmir_render_attr_value($item['id'], $item['value']);
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        };

        $output = '';

        // Card view: flat list with collapsible toggle
        if ($max_visible > 0) {
            $total = count($applicable_attrs);
            if ($total > $max_visible) {
                $visible_items = array_slice($applicable_attrs, 0, $max_visible);
                $hidden_items = array_slice($applicable_attrs, $max_visible);
                $hidden_count = count($hidden_items);
                $attrs_toggle_counter++;
                $toggle_id = 'attrs-hidden-' . $attrs_toggle_counter;

                $output .= '<div class="attrs-section attrs-visible">';
                $output .= $render_columns($visible_items);
                $output .= '</div>';
                $output .= '<div class="attrs-section attrs-hidden" id="' . $toggle_id . '">';
                $output .= $render_columns($hidden_items);
                $output .= '</div>';
                $output .= '<button type="button" class="attrs-toggle-btn" onclick="softmirToggleAttrs(\'' . $toggle_id . '\', this)">';
                $output .= sprintf(__('Another %d attr. ▾', 'softmir'), $hidden_count);
                $output .= '</button>';
            } else {
                $output .= '<div class="attrs-section">';
                $output .= $render_columns($applicable_attrs);
                $output .= '</div>';
            }
        }
        // Product page: original top/bottom split by card_position
        else {
            $top_attrs = [];
            $bottom_attrs = [];

            foreach ($applicable_attrs as $item) {
                $meta = softmir_get_attr_meta($item['id']);
                if ($meta['card_position'] === 'middle') {
                    $top_attrs[] = $item;
                } else {
                    $bottom_attrs[] = $item;
                }
            }

            // Render Top Section
            if (!empty($top_attrs)) {
                $output .= '<div class="attrs-section attrs-top">';
                $output .= $render_columns($top_attrs);
                $output .= '</div>';
            }

            // Render Bottom Section
            if (!empty($bottom_attrs)) {
                if (!empty($top_attrs)) {
                    $output .= '<hr class="attrs-separator">';
                }
                $output .= '<div class="attrs-section attrs-bottom">';
                $output .= $render_columns($bottom_attrs);
                $output .= '</div>';
            }
        }

    }
    // Default flow (sidebar or other)
    else {
        $output = '';
        foreach ($applicable_attrs as $item) {
            $output .= softmir_render_attr_value($item['id'], $item['value']);
        }
    }

    $class = $wrapper_class ? ' ' . esc_attr($wrapper_class) : '';

    return '<div class="' . $class . '">' . $output . '</div>';
}

// ========== Get filterable attributes ==========
function softmir_get_filterable_attributes()
{
    $attrs = softmir_get_attributes([
        'meta_query' => [
            [
                'key' => '_attr_filterable',
                'value' => '1',
                'compare' => '=',
            ],
        ],
    ]);

    $filtered = [];
    foreach ($attrs as $attr) {
        $attr_title = mb_strtolower(get_the_title($attr->ID));
        // Hide Integrations from the catalog filter Sidebar
        if (mb_strpos($attr_title, 'integration') !== false || mb_strpos($attr_title, 'Integrate') !== false || mb_strpos($attr_title, 'integration') !== false) {
            continue;
        }
        $filtered[] = $attr;
    }
    return $filtered;
}

// ========== Build meta_query from GET params for filtering ==========
function softmir_build_filter_meta_query()
{
    $meta_query = [];
    $filterable = softmir_get_filterable_attributes();

    foreach ($filterable as $attr) {
        $param = 'sw_attr_' . $attr->ID;
        if (!isset($_GET[$param]) || empty($_GET[$param]))
            continue;

        $values = $_GET[$param];
        $meta = softmir_get_attr_meta($attr->ID);

        if (is_array($values)) {
            // Multiple values selected — need to match any
            $sub_query = ['relation' => 'OR'];
            foreach ($values as $val) {
                $sub_query[] = [
                    'key' => '_sw_attr_' . $attr->ID,
                    'value' => sanitize_text_field($val),
                    'compare' => 'LIKE',
                ];
            }
            $meta_query[] = $sub_query;
        } else {
            $meta_query[] = [
                'key' => '_sw_attr_' . $attr->ID,
                'value' => sanitize_text_field($values),
                'compare' => 'LIKE',
            ];
        }
    }

    if (!empty($meta_query)) {
        $meta_query['relation'] = 'AND';
    }

    return $meta_query;
}
