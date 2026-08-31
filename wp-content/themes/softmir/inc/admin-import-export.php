<?php
/**
 * SoftMir — Software Import/Export Module
 * Allows exporting 'software' CPT to JSON/CSV and importing back.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper block for CSV row parsing
if (!function_exists('fputcsv_get_row')) {
    function fputcsv_get_row($handle)
    {
        $data = fgetcsv($handle);
        // clean BOM from first cell
        if ($data !== false && count($data) > 0) {
            $data[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $data[0]);
        }
        return $data;
    }
}

// ========== 1. Add Submenu Page ==========
add_action('admin_menu', 'softmir_register_import_export_page');
function softmir_register_import_export_page()
{
    add_submenu_page(
        'edit.php?post_type=software',
        __('Импорт / Экспорт БД', 'softmir'),
        __('Импорт / Экспорт', 'softmir'),
        'manage_options',
        'softmir-import-export',
        'softmir_render_import_export_page'
    );
}

// ========== 2. Render Admin Page ==========
function softmir_render_import_export_page()
{
    // Fetch categories for the export filter
    $categories = get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => false,
    ]);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php _e('Import/Export software cards', 'softmir'); ?>
        </h1>
        <hr class="wp-header-end">

        <?php
        if (isset($_GET['imported']) && isset($_GET['count'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Successfully imported cards: <strong>' . (int) $_GET['count'] . '</strong>.</p></div>';
        }
        if (isset($_GET['import_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Import error: ' . esc_html(urldecode($_GET['import_error'])) . '</p></div>';
        }
        ?>

        <style>
            .softmir-dashboard-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
                padding: 20px;
                margin-top: 20px;
                max-width: 600px;
            }

            .softmir-dashboard-card h2 {
                margin-top: 0;
            }
        </style>

        <!-- EXPORT SECTION -->
        <div class="softmir-dashboard-card">
            <h2>📤
                <?php _e('Export software cards', 'softmir'); ?>
            </h2>
            <p>
                <?php _e('Upload software cards to a file (CSV or JSON).', 'softmir'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('softmir_export_nonce', 'softmir_export_verify'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="export_category">
                                <?php _e('Category', 'softmir'); ?>
                            </label></th>
                        <td>
                            <select name="export_category" id="export_category">
                                <option value="all">
                                    <?php _e('— All Categories —', 'softmir'); ?>
                                </option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                                        <?php echo esc_html($cat->name); ?> (
                                        <?php echo $cat->count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Select a category to filter your upload.', 'softmir'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="export_source">
                                <?php _e('Source', 'softmir'); ?>
                            </label></th>
                        <td>
                            <select name="export_source" id="export_source">
                                <option value="all">
                                    <?php _e('— All sources —', 'softmir'); ?>
                                </option>
                                <option value="scout">
                                    <?php _e('🤖 Scout (Quiz)', 'softmir'); ?>
                                </option>
                                <option value="manual">
                                    <?php _e('✍️ Manual addition', 'softmir'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Select a source to filter.', 'softmir'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <br>
                <button type="submit" name="softmir_export" value="json" class="button button-primary">
                    <?php _e('Download JSON file', 'softmir'); ?>
                </button>
                <button type="submit" name="softmir_export" value="csv" class="button button-secondary">
                    <?php _e('Download CSV file', 'softmir'); ?>
                </button>
            </form>
        </div>

        <!-- IMPORT SECTION -->
        <div class="softmir-dashboard-card">
            <h2>📥
                <?php _e('Importing software cards', 'softmir'); ?>
            </h2>
            <p>
                <?php _e('Load changed (or new) cards from CSV or JSON. The comparison is based on the ID or Slug (post_name) column. If the ID is not specified, a new card will be created.', 'softmir'); ?>
            </p>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('softmir_import_nonce', 'softmir_import_verify'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="import_file">
                                <?php _e('Local file', 'softmir'); ?>
                            </label></th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv,.json" required>
                            <p class="description">
                                <?php _e('Maximum size: ', 'softmir');
                                echo ini_get('upload_max_filesize'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <br>
                <button type="submit" name="softmir_import" value="1" class="button button-primary">
                    <?php _e('Start import', 'softmir'); ?>
                </button>
            </form>
        </div>

    </div>
    <?php
}

// ========== 3. Process Export ==========
add_action('admin_init', 'softmir_process_export');
function softmir_process_export()
{
    if (!isset($_POST['softmir_export']) || !isset($_POST['softmir_export_verify'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['softmir_export_verify'], 'softmir_export_nonce') || !current_user_can('manage_options')) {
        wp_die('Security check failed.');
    }

    $format = sanitize_text_field($_POST['softmir_export']); // json or csv
    $cat_id = isset($_POST['export_category']) ? sanitize_text_field($_POST['export_category']) : 'all';
    $source = isset($_POST['export_source']) ? sanitize_text_field($_POST['export_source']) : 'all';

    // Query Software
    $args = [
        'post_type' => 'software',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => [],
    ];

    $args['tax_query'] = ['relation' => 'AND'];

    if (function_exists('pll_default_language')) {
        $args['tax_query'][] = [
            'taxonomy' => 'language',
            'field' => 'slug',
            'terms' => pll_default_language(),
        ];
    }

    if ($cat_id !== 'all' && is_numeric($cat_id)) {
        $args['tax_query'][] = [
            'taxonomy' => 'software_category',
            'field' => 'term_id',
            'terms' => (int) $cat_id,
        ];
    }

    // Filter by Source
    if ($source === 'scout') {
        $args['meta_query'][] = [
            'key' => 'software_status',
            'value' => 'external_scout',
            'compare' => '=',
        ];
    } elseif ($source === 'manual') {
        $args['meta_query'][] = [
            'relation' => 'OR',
            ['key' => 'software_status', 'compare' => 'NOT EXISTS'],
            ['key' => 'software_status', 'value' => 'external_scout', 'compare' => '!='],
        ];
    }

    $query = new WP_Query($args);
    $data = [];

    // Fields mapping
    $acf_text_fields = ['website_url', 'video_url', 'short_description', 'price_summary', 'scenarios_md', 'key_features', 'business_areas', 'pricing'];
    $acf_array_fields = ['top_reasons', 'disadvantages', 'best_for', 'bad_for'];
    $all_attributes = function_exists('softmir_get_attributes') ? softmir_get_attributes() : [];

    foreach ($query->posts as $post) {
        $row = [
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_content' => $post->post_content,
            'language' => function_exists('pll_get_post_language') ? pll_get_post_language($post->ID) : '',
        ];

        // Taxonomy
        $terms = wp_get_object_terms($post->ID, 'software_category', ['fields' => 'slugs']);
        $row['software_category'] = !is_wp_error($terms) ? implode(',', $terms) : '';

        // Key Functions
        $key_funcs = get_post_meta($post->ID, '_selected_key_functions', true);
        $row['_selected_key_functions'] = (!empty($key_funcs) && is_array($key_funcs)) ? implode(',', $key_funcs) : '';

        // Special primary category
        $row['primary_category_id'] = get_post_meta($post->ID, 'primary_category', true);

        // Simple ACF Texts
        foreach ($acf_text_fields as $field) {
            $row[$field] = get_post_meta($post->ID, $field, true);
        }

        // ACF Arrays (lists)
        foreach ($acf_array_fields as $field) {
            $val = get_post_meta($post->ID, $field, true);
            // Convert to string for flat export
            if (!empty($val)) {
                if ($format === 'csv') {
                    // For CSV, join array items with |
                    if (is_array($val)) {
                        $string_vals = array_map(function ($v) {
                            return is_array($v) ? ($v['text'] ?? '') : $v;
                        }, $val);
                        $row[$field] = implode('|', $string_vals);
                    } else {
                        // Might be raw newline-separated text (legacy)
                        $row[$field] = str_replace(["\r\n", "\n"], '|', trim(wp_strip_all_tags($val)));
                    }
                } else {
                    // JSON keeps array structure
                    if (is_string($val)) {
                        // Fallback logic if it's text
                        $text = str_replace(array('</tr>', '</td>', '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>'), "\n", $val);
                        $text = wp_strip_all_tags($text);
                        $row[$field] = array_filter(array_map('trim', explode("\n", $text)));
                    } else {
                        // Normalize array items (if it's array of arrays ACF repeater)
                        $string_vals = array_map(function ($v) {
                            return is_array($v) ? ($v['text'] ?? '') : $v;
                        }, $val);
                        $row[$field] = array_values(array_filter($string_vals));
                    }
                }
            } else {
                $row[$field] = ($format === 'csv') ? '' : [];
            }
        }

        // Dynamic Attributes (_sw_attr_*)
        if (!empty($all_attributes)) {
            foreach ($all_attributes as $attr) {
                $attr_val = get_post_meta($post->ID, '_sw_attr_' . $attr->ID, true);
                if (is_array($attr_val)) {
                    $row['_sw_attr_' . $attr->ID] = implode('|', array_map('esc_html', $attr_val));
                } else {
                    $row['_sw_attr_' . $attr->ID] = ($attr_val) ? esc_html($attr_val) : '';
                }
            }
        }

        $data[] = $row;
    }

    $filename = 'softmir-software-' . date('Y-m-d') . '-' . $cat_id . '.' . $format;

    // Disable debug output and clear buffers so files are clean
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($format === 'json') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    } elseif ($format === 'csv') {
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fputs($out, "\xEF\xBB\xBF");

        if (!empty($data)) {
            fputcsv($out, array_keys($data[0]));
            foreach ($data as $item) {
                fputcsv($out, array_values($item));
            }
        }
        fclose($out);
        exit;
    }
}

// ========== 4. Process Import ==========
add_action('admin_init', 'softmir_process_import');
function softmir_process_import()
{
    set_time_limit(0); // Prevent timeouts during bulk import and translations

    if (!isset($_POST['softmir_import']) || !isset($_POST['softmir_import_verify'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['softmir_import_verify'], 'softmir_import_nonce') || !current_user_can('manage_options')) {
        wp_die('Security check failed.');
    }

    if (empty($_FILES['import_file']['tmp_name'])) {
        $url = add_query_arg('import_error', urlencode('The file has not been uploaded.'), admin_url('edit.php?post_type=software&page=softmir-import-export'));
        wp_redirect($url);
        exit;
    }

    $file = $_FILES['import_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $data_rows = [];

    // Parse JSON
    if ($ext === 'json') {
        $content = file_get_contents($file['tmp_name']);
        $data_rows = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $url = add_query_arg('import_error', urlencode('JSON parsing error: ' . json_last_error_msg()), admin_url('edit.php?post_type=software&page=softmir-import-export'));
            wp_redirect($url);
            exit;
        }
    }
    // Parse CSV
    elseif ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle !== false) {
            // Strip BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $headers = fputcsv_get_row($handle); // custom helper? wait, fgetcsv
            if (!$headers) {
                rewind($handle); // try without removing bom
                $headers = fgetcsv($handle);
                // Fix BOM in first header just in case
                if (isset($headers[0]))
                    $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($headers)) {
                    $data_rows[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        } else {
            $url = add_query_arg('import_error', urlencode('Failed to read CSV.'), admin_url('edit.php?post_type=software&page=softmir-import-export'));
            wp_redirect($url);
            exit;
        }
    } else {
        $url = add_query_arg('import_error', urlencode('Only CSV and JSON are supported.'), admin_url('edit.php?post_type=software&page=softmir-import-export'));
        wp_redirect($url);
        exit;
    }

    if (empty($data_rows) || !is_array($data_rows)) {
        $url = add_query_arg('import_error', urlencode('The file is empty or incorrectly formatted.'), admin_url('edit.php?post_type=software&page=softmir-import-export'));
        wp_redirect($url);
        exit;
    }

    $imported_count = 0;
    $acf_array_fields = ['top_reasons', 'disadvantages', 'best_for', 'bad_for'];

    foreach ($data_rows as $row) {
        if (empty($row['post_title']))
            continue; // title is required

        // 1. Process Core Post Data
        $post_data = [
            'post_title' => sanitize_text_field($row['post_title']),
            'post_type' => 'software',
            'post_status' => 'publish',
        ];
        if (isset($row['post_content'])) {
            // allow HTML in content
            $post_data['post_content'] = wp_kses_post($row['post_content']);
        }
        if (isset($row['post_name']) && !empty($row['post_name'])) {
            $post_data['post_name'] = sanitize_title($row['post_name']);
        }

        $post_id = 0;

        // Find existing by ID
        if (!empty($row['ID']) && is_numeric($row['ID'])) {
            $existing_post = get_post($row['ID']);
            if ($existing_post && $existing_post->post_type === 'software') {
                $post_id = $existing_post->ID;
                $post_data['ID'] = $post_id;
            }
        }
        // Find existing by Slug
        if (!$post_id && !empty($post_data['post_name'])) {
            $existing_by_slug = get_page_by_path($post_data['post_name'], OBJECT, 'software');
            if ($existing_by_slug) {
                $post_id = $existing_by_slug->ID;
                $post_data['ID'] = $post_id;
            }
        }

        // Insert or Update
        if ($post_id) {
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id)) {
                continue;
            }
        }
        $imported_count++;

        // 2. Set Language (Polylang)
        if (isset($row['language']) && !empty($row['language']) && function_exists('pll_set_post_language')) {
            pll_set_post_language($post_id, sanitize_text_field($row['language']));
        }

        // 3. Set Taxonomy
        if (isset($row['software_category'])) {
            $slugs = array_filter(array_map('trim', explode(',', $row['software_category'])));
            $term_ids = [];
            foreach ($slugs as $slug) {
                // Determine language context for category creation
                $term_lang = function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : '';

                $term = get_term_by('slug', $slug, 'software_category');

                // If term doesn't exist, we skip creation to avoid messing up multilingual trees, 
                // OR we can create it. Let's create safely.
                if (!$term) {
                    $new_term = wp_insert_term($slug, 'software_category', ['slug' => sanitize_title($slug)]);
                    if (!is_wp_error($new_term)) {
                        $term_id = $new_term['term_id'];
                        if ($term_lang && function_exists('pll_set_term_language')) {
                            pll_set_term_language($term_id, $term_lang);
                        }
                        $term_ids[] = (int) $term_id;
                    }
                } else {
                    $term_ids[] = (int) $term->term_id;
                }
            }
            if (!empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'software_category', false); // overwrite
            }
        }

        // 4. Custom Meta / ACF
        // Key functions (array of strings)
        if (isset($row['_selected_key_functions'])) {
            $key_funcs = array_filter(array_map('trim', explode(',', $row['_selected_key_functions'])));
            update_post_meta($post_id, '_selected_key_functions', $key_funcs);
        }
        // Primary category ID
        if (isset($row['primary_category_id'])) {
            update_post_meta($post_id, 'primary_category', (int) $row['primary_category_id']);
        }

        // Text fields
        $acf_text_fields = ['website_url', 'video_url', 'short_description', 'price_summary', 'key_features', 'business_areas', 'pricing'];
        foreach ($acf_text_fields as $field) {
            if (isset($row[$field])) {
                update_post_meta($post_id, $field, wp_kses_post($row[$field]));
            }
        }

        // Scenarios MD (allow markdown chars)
        if (isset($row['scenarios_md'])) {
            update_post_meta($post_id, 'scenarios_md', wp_kses_post($row['scenarios_md']));
        }

        // ACF Array / Repeater Fields
        foreach ($acf_array_fields as $field) {
            if (!isset($row[$field]))
                continue;

            $val = $row[$field];
            $text_val = '';

            if ($ext === 'csv') {
                // Split by | and join by newline for Textarea
                $items = array_filter(array_map('trim', explode('|', $val)));
                $text_val = implode("\n", array_map('wp_kses_post', $items));
            } else {
                // JSON - might be an array directly
                if (is_array($val)) {
                    $items = [];
                    foreach ($val as $i) {
                        $items[] = is_array($i) ? ($i['text'] ?? '') : $i;
                    }
                    $text_val = implode("\n", array_map('wp_kses_post', array_filter($items)));
                } elseif (is_string($val) && !empty($val)) {
                    $text_val = wp_kses_post($val);
                }
            }

            // Storing as a string (Textarea)
            update_post_meta($post_id, $field, $text_val);
        }

        // 5. Dynamic Attributes (_sw_attr_*)
        foreach ($row as $key => $val) {
            if (strpos($key, '_sw_attr_') === 0) {
                if ($ext === 'csv' && is_string($val) && strpos($val, '|') !== false) {
                    $val_arr = array_filter(array_map('trim', explode('|', $val)));
                    update_post_meta($post_id, $key, $val_arr);
                } else {
                    // JSON import might already give us an array
                    if (is_array($val)) {
                        update_post_meta($post_id, $key, array_values(array_filter($val)));
                    } else {
                        // Single value or empty
                        update_post_meta($post_id, $key, sanitize_text_field($val));
                    }
                }
            }
        }

        // 6. Logo Sideloading
        if (!empty($row['logo_url']) && filter_var($row['logo_url'], FILTER_VALIDATE_URL)) {
            // Only if no featured image set
            if (!get_post_thumbnail_id($post_id)) {
                if (function_exists('softmir_sideload_logo')) {
                    $attach_id = softmir_sideload_logo($row['logo_url'], $post_id);
                    if ($attach_id) {
                        set_post_thumbnail($post_id, $attach_id);
                    }
                }
            }
        }

        // 7. Mark as processed
        update_post_meta($post_id, '_ai_enriched', '1');

        // 8. Auto-Translate to other languages
        if (function_exists('pll_default_language') && function_exists('pll_languages_list') && function_exists('softmir_translate_post')) {
            $default_lang = pll_default_language();
            $target_langs = array_filter(pll_languages_list(), fn($lang) => $lang !== $default_lang);
            foreach ($target_langs as $lang) {
                softmir_translate_post($post_id, $lang);
            }
        }
    }

    $url = add_query_arg(['imported' => 1, 'count' => $imported_count], admin_url('edit.php?post_type=software&page=softmir-import-export'));
    wp_redirect($url);
    exit;
}
