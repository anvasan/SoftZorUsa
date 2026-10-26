<?php
if (!defined('ABSPATH')) {
    exit;
}

class SZ_Promo_CPT {

    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_sz_promo', [$this, 'save_meta_boxes'], 10, 2);
        add_filter('manage_sz_promo_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_sz_promo_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
    }

    public function register_post_type() {
        $labels = [
            'name'                  => 'Промо-баннеры',
            'singular_name'         => 'Промо-баннер',
            'menu_name'             => 'Реклама и Промо',
            'name_admin_bar'        => 'Промо-баннер',
            'add_new'               => 'Добавить баннер',
            'add_new_item'          => 'Новый промо-баннер / оффер',
            'new_item'              => 'Новый промо-баннер',
            'edit_item'             => 'Редактировать промо',
            'view_item'             => 'Просмотр промо',
            'all_items'             => 'Все баннеры и офферы',
            'search_items'          => 'Найти баннер',
            'not_found'             => 'Баннеров не найдено',
            'not_found_in_trash'    => 'В корзине баннеров нет'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 30,
            'menu_icon'          => 'dashicons-chart-pie',
            'supports'           => ['title'],
        ];

        register_post_type('sz_promo', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'sz_promo_settings',
            '⚙️ Настройки оффера, размеров баннеров и таргетинга',
            [$this, 'render_settings_metabox'],
            'sz_promo',
            'normal',
            'high'
        );

        add_meta_box(
            'sz_promo_stats_preview',
            '📊 Статистика эффективности по местам',
            [$this, 'render_stats_metabox'],
            'sz_promo',
            'side',
            'default'
        );
    }

    public function render_settings_metabox($post) {
        wp_nonce_field('sz_promo_save_nonce', 'sz_promo_nonce');

        $format       = get_post_meta($post->ID, '_sz_format', true) ?: 'native';
        $target_url   = get_post_meta($post->ID, '_sz_target_url', true);
        $new_tab      = get_post_meta($post->ID, '_sz_new_tab', true) !== 'no';
        $placements   = (array) get_post_meta($post->ID, '_sz_placements', true);
        $all_cats     = get_post_meta($post->ID, '_sz_all_categories', true) !== 'no';
        $selected_cats= (array) get_post_meta($post->ID, '_sz_categories', true);
        $base_weight  = get_post_meta($post->ID, '_sz_base_weight', true) ?: 5;
        $is_active    = get_post_meta($post->ID, '_sz_is_active', true) !== 'no';

        // Image format fields (different sizes)
        $sidebar_img  = get_post_meta($post->ID, '_sz_sidebar_img', true) ?: get_post_meta($post->ID, '_sz_desktop_img', true);
        $content_img  = get_post_meta($post->ID, '_sz_content_img', true) ?: get_post_meta($post->ID, '_sz_desktop_img', true);
        $mobile_img   = get_post_meta($post->ID, '_sz_mobile_img', true);
        $img_alt      = get_post_meta($post->ID, '_sz_img_alt', true);

        // Native format fields
        $badge_text   = get_post_meta($post->ID, '_sz_badge_text', true) ?: '🔥 Рекомендуем';
        $headline     = get_post_meta($post->ID, '_sz_headline', true);
        $description  = get_post_meta($post->ID, '_sz_description', true);
        $btn_text     = get_post_meta($post->ID, '_sz_btn_text', true) ?: 'Попробовать бесплатно ↗';
        $logo_url     = get_post_meta($post->ID, '_sz_logo_url', true);
        $btn_color    = get_post_meta($post->ID, '_sz_btn_color', true) ?: '#2563eb';

        // HTML format fields (different sizes)
        $sidebar_html = get_post_meta($post->ID, '_sz_sidebar_html', true) ?: get_post_meta($post->ID, '_sz_raw_html', true);
        $content_html = get_post_meta($post->ID, '_sz_content_html', true) ?: get_post_meta($post->ID, '_sz_raw_html', true);
        ?>
        <style>
            .sz-meta-field { margin-bottom: 20px; }
            .sz-meta-field label { display: block; font-weight: 600; margin-bottom: 6px; }
            .sz-meta-field input[type="text"], .sz-meta-field input[type="url"], .sz-meta-field select, .sz-meta-field textarea { width: 100%; max-width: 950px; }
            .sz-field-hint { font-size: 12px; color: #64748b; margin-top: 4px; }
            .sz-tabs-nav { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 16px; padding-bottom: 8px; flex-wrap: wrap; }
            .sz-tab-btn { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
            .sz-tab-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
            .sz-cat-box { max-height: 180px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; max-width: 950px; }
            .sz-size-card { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 22px; margin-bottom: 18px; max-width: 950px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
            .sz-html-textarea { width: 100% !important; max-width: 100% !important; min-height: 160px !important; font-family: Consolas, Monaco, "Courier New", Courier, monospace !important; font-size: 13px !important; line-height: 1.5 !important; background: #ffffff !important; border: 1px solid #94a3b8 !important; border-radius: 6px !important; padding: 12px !important; resize: vertical !important; color: #0f172a !important; }
            .sz-html-textarea:focus { border-color: #2563eb !important; box-shadow: 0 0 0 1px #2563eb !important; outline: none !important; }
            .sz-size-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
            .sz-size-title { font-weight: 700; color: #0f172a; font-size: 14px; }
            .sz-size-badge { background: #e0f2fe; color: #0369a1; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
        </style>

        <div class="sz-meta-field">
            <label>Статус показа:</label>
            <label style="font-weight: normal;">
                <input type="checkbox" name="sz_is_active" value="yes" <?php checked($is_active); ?>> 
                <strong>Активен</strong> (участвует в ротации на сайте)
            </label>
        </div>

        <div class="sz-meta-field">
            <label for="sz_target_url">Единая партнерская ссылка (Pretty Link):</label>
            <input type="url" name="sz_target_url" id="sz_target_url" value="<?php echo esc_url($target_url); ?>" placeholder="https://softzor.com.ua/hostiq">
            <div class="sz-field-hint">Клик по любому размеру баннера будет вести на эту партнерскую ссылку.</div>
            <label style="margin-top: 8px; font-weight: normal; display: block;">
                <input type="checkbox" name="sz_new_tab" value="yes" <?php checked($new_tab); ?>> Открывать в новой вкладке (target="_blank", nofollow sponsored)
            </label>
        </div>

        <div class="sz-meta-field">
            <label>Формат креатива:</label>
            <div class="sz-tabs-nav">
                <button type="button" class="sz-tab-btn <?php echo $format === 'native' ? 'active' : ''; ?>" data-format="native">
                    🎯 Нативная B2B-плашка
                </button>
                <button type="button" class="sz-tab-btn <?php echo $format === 'image' ? 'active' : ''; ?>" data-format="image">
                    🖼 Графические баннеры разных размеров
                </button>
                <button type="button" class="sz-tab-btn <?php echo $format === 'html' ? 'active' : ''; ?>" data-format="html">
                    💻 HTML / Партнерский код
                </button>
            </div>
            <input type="hidden" name="sz_format" id="sz_format" value="<?php echo esc_attr($format); ?>">
        </div>

        <!-- Поля нативной плашки -->
        <div id="sz_native_fields" style="<?php echo $format === 'native' ? '' : 'display:none;'; ?>">
            <div class="sz-meta-field">
                <label for="sz_badge_text">Текст бейджа:</label>
                <input type="text" name="sz_badge_text" id="sz_badge_text" value="<?php echo esc_attr($badge_text); ?>" placeholder="🔥 Рекомендуем редакции">
            </div>

            <div class="sz-meta-field">
                <label for="sz_headline">Заголовок оффера:</label>
                <input type="text" name="sz_headline" id="sz_headline" value="<?php echo esc_attr($headline); ?>" placeholder="KeyCRM — удобная украинская CRM для торговли">
            </div>

            <div class="sz-meta-field">
                <label for="sz_description">Текст оффера / Описание (1–2 предложения):</label>
                <textarea name="sz_description" id="sz_description" rows="3" placeholder="Подключение за 15 минут, встроенная интеграция с Нова Пошта, Rozetka, Prom и банками. Попробуйте 14 дней бесплатно."><?php echo esc_textarea($description); ?></textarea>
            </div>

            <div class="sz-meta-field">
                <div style="display:flex; gap:14px; max-width:950px;">
                    <div style="flex: 2;">
                        <label for="sz_btn_text">Текст на кнопке:</label>
                        <input type="text" name="sz_btn_text" id="sz_btn_text" value="<?php echo esc_attr($btn_text); ?>" placeholder="Попробовать бесплатно ↗">
                    </div>
                    <div style="flex: 1;">
                        <label for="sz_btn_color">Цвет кнопки:</label>
                        <input type="text" name="sz_btn_color" id="sz_btn_color" value="<?php echo esc_attr($btn_color); ?>" class="sz-color-picker">
                    </div>
                </div>
            </div>

            <div class="sz-meta-field">
                <label for="sz_logo_url">Логотип или иконка сервиса (URL):</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="sz_logo_url" id="sz_logo_url" value="<?php echo esc_url($logo_url); ?>" placeholder="https://...">
                    <button type="button" class="button sz-upload-btn" data-target="#sz_logo_url">Выбрать из медиа</button>
                </div>
            </div>
        </div>

        <!-- Поля графического баннера (РАЗНЫЕ РАЗМЕРЫ) -->
        <div id="sz_image_fields" style="<?php echo $format === 'image' ? '' : 'display:none;'; ?>">
            <p style="color:#475569; font-size:13px; margin-bottom:16px;">
                Загрузите баннеры нужных размеров. Система <strong>автоматически покажет соответствующий размер</strong> в сайдбаре и в тексте статьи!
            </p>

            <!-- Размер 1: Сайдбар -->
            <div class="sz-size-card">
                <div class="sz-size-header">
                    <span class="sz-size-title">📌 Размер для Сайдбара (Боковая колонка)</span>
                    <span class="sz-size-badge">250x250, 300x250, 300x600</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="sz_sidebar_img" id="sz_sidebar_img" value="<?php echo esc_url($sidebar_img); ?>" placeholder="https://hostiq.ua/images/aff-banners/VPS_servers_250x250.png">
                    <button type="button" class="button sz-upload-btn" data-target="#sz_sidebar_img">Выбрать</button>
                </div>
                <div class="sz-field-hint">Идеально подходит квадратный или вертикальный баннер.</div>
            </div>

            <!-- Размер 2: Внутрь статьи -->
            <div class="sz-size-card">
                <div class="sz-size-header">
                    <span class="sz-size-title">📄 Размер для Статей блога (В контент по ширине текста)</span>
                    <span class="sz-size-badge">728x90, 970x250, 1200x300</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="sz_content_img" id="sz_content_img" value="<?php echo esc_url($content_img); ?>" placeholder="https://hostiq.ua/images/aff-banners/VPS_servers_728x90.png">
                    <button type="button" class="button sz-upload-btn" data-target="#sz_content_img">Выбрать</button>
                </div>
                <div class="sz-field-hint">Широкий горизонтальный баннер на всю ширину статьи блога или карточки софта.</div>
            </div>

            <!-- Размер 3: Смартфоны -->
            <div class="sz-size-card">
                <div class="sz-size-header">
                    <span class="sz-size-title">📱 Размер для Смартфонов (Опционально)</span>
                    <span class="sz-size-badge">320x100, 300x250</span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="sz_mobile_img" id="sz_mobile_img" value="<?php echo esc_url($mobile_img); ?>" placeholder="https://...">
                    <button type="button" class="button sz-upload-btn" data-target="#sz_mobile_img">Выбрать</button>
                </div>
            </div>

            <div class="sz-meta-field">
                <label for="sz_img_alt">Общее Alt-описание для SEO:</label>
                <input type="text" name="sz_img_alt" id="sz_img_alt" value="<?php echo esc_attr($img_alt); ?>" placeholder="Быстрый хостинг и VPS в Украине - HOSTiQ">
            </div>
        </div>

        <!-- Поля HTML-кода от партнера (РАЗНЫЕ РАЗМЕРЫ) -->
        <div id="sz_html_fields" style="<?php echo $format === 'html' ? '' : 'display:none;'; ?>">
            <p style="color:#475569; font-size:13px; margin-bottom:16px;">
                Если партнер дал разные HTML-коды (например, один 250x250, а второй 728x90), вставьте каждый в свое поле:
            </p>

            <div class="sz-size-card">
                <div class="sz-size-header">
                    <span class="sz-size-title">📌 HTML-код для Сайдбара (250x250, 300x250)</span>
                </div>
                <textarea name="sz_sidebar_html" id="sz_sidebar_html" rows="7" class="sz-html-textarea" placeholder='<a href="https://hostiq.ua/..."><img src="...250x250.png" .../></a>'><?php echo esc_textarea($sidebar_html); ?></textarea>
                <button type="button" class="button button-secondary sz-parse-single-btn" data-source="#sz_sidebar_html" data-img="#sz_sidebar_img" style="margin-top:6px;">⚡ Извлечь ссылку в поля «Графический баннер»</button>
            </div>

            <div class="sz-size-card">
                <div class="sz-size-header">
                    <span class="sz-size-title">📄 HTML-код для Статей блога (Горизонтальный 728x90, 970x250)</span>
                </div>
                <textarea name="sz_content_html" id="sz_content_html" rows="7" class="sz-html-textarea" placeholder='<a href="https://hostiq.ua/..."><img src="...728x90.png" .../></a>'><?php echo esc_textarea($content_html); ?></textarea>
                <button type="button" class="button button-secondary sz-parse-single-btn" data-source="#sz_content_html" data-img="#sz_content_img" style="margin-top:6px;">⚡ Извлечь ссылку в поля «Графический баннер»</button>
            </div>
        </div>

        <hr style="border:0; border-top: 1px solid #e2e8f0; margin: 24px 0;">

        <h3>🎯 Где и как показывать</h3>

        <div class="sz-meta-field">
            <label>Разрешенные места показа (Плейсменты):</label>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label><input type="checkbox" name="sz_placements[]" value="sidebar" <?php checked(in_array('sidebar', $placements)); ?>> <strong>Сайдбар</strong> (боковая колонка — покажет размер для сайдбара)</label>
                <label><input type="checkbox" name="sz_placements[]" value="in_content" <?php checked(in_array('in_content', $placements)); ?>> <strong>Внутри контента</strong> (покажет горизонтальный размер для статей)</label>
                <label><input type="checkbox" name="sz_placements[]" value="top_bar" <?php checked(in_array('top_bar', $placements)); ?>> <strong>Top Bar</strong> (тонкая промо-полоса над шапкой)</label>
                <label><input type="checkbox" name="sz_placements[]" value="custom" <?php checked(in_array('custom', $placements)); ?>> <strong>Ручная вставка</strong> (через шорткод <code>[sz_promo id="<?php echo $post->ID; ?>"]</code>)</label>
            </div>
        </div>

        <div class="sz-meta-field">
            <label for="sz_base_weight">Базовый вес / Приоритет (от 1 до 10):</label>
            <input type="number" min="1" max="10" name="sz_base_weight" id="sz_base_weight" value="<?php echo esc_attr($base_weight); ?>" style="width: 100px;">
            <div class="sz-field-hint">Стартовый приоритет для новых баннеров (по умолчанию 5).</div>
        </div>

        <div class="sz-meta-field">
            <label>Таргетинг по категориям:</label>
            <label style="margin-bottom: 8px; display: block;">
                <input type="radio" name="sz_all_categories" value="yes" <?php checked($all_cats); ?>> Показывать во всех рубриках сайта
            </label>
            <label style="margin-bottom: 8px; display: block;">
                <input type="radio" name="sz_all_categories" value="no" <?php checked(!$all_cats); ?>> Показывать только в выбранных рубриках:
            </label>

            <div id="sz_categories_selector" class="sz-cat-box" style="<?php echo $all_cats ? 'display:none;' : ''; ?>">
                <?php
                // Рубрики каталога ПО
                $sw_cats = get_terms(['taxonomy' => 'software_category', 'hide_empty' => false]);
                if (!empty($sw_cats) && !is_wp_error($sw_cats)) {
                    echo '<strong>Категории софта:</strong><br>';
                    foreach ($sw_cats as $cat) {
                        $checked = in_array('sw_' . $cat->term_id, $selected_cats) ? 'checked' : '';
                        echo "<label style='display:inline-block; width:48%; margin-bottom:4px;'><input type='checkbox' name='sz_categories[]' value='sw_{$cat->term_id}' {$checked}> " . esc_html($cat->name) . "</label>";
                    }
                    echo '<hr style="border:0; border-top:1px dashed #cbd5e1; margin: 8px 0;">';
                }

                // Рубрики блога
                $blog_cats = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
                if (!empty($blog_cats) && !is_wp_error($blog_cats)) {
                    echo '<strong>Рубрики блога:</strong><br>';
                    foreach ($blog_cats as $cat) {
                        $checked = in_array('blog_' . $cat->term_id, $selected_cats) ? 'checked' : '';
                        echo "<label style='display:inline-block; width:48%; margin-bottom:4px;'><input type='checkbox' name='sz_categories[]' value='blog_{$cat->term_id}' {$checked}> " . esc_html($cat->name) . "</label>";
                    }
                }
                ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('.sz-tab-btn').on('click', function(){
                $('.sz-tab-btn').removeClass('active');
                $(this).addClass('active');
                var fmt = $(this).data('format');
                $('#sz_format').val(fmt);
                $('#sz_native_fields, #sz_image_fields, #sz_html_fields').hide();
                if(fmt === 'native'){
                    $('#sz_native_fields').show();
                } else if(fmt === 'image'){
                    $('#sz_image_fields').show();
                } else if(fmt === 'html'){
                    $('#sz_html_fields').show();
                }
            });

            // Кнопка извлечения из HTML-кода
            $('.sz-parse-single-btn').on('click', function(e){
                e.preventDefault();
                var srcSelector = $(this).data('source');
                var targetImgSelector = $(this).data('img');
                var code = $(srcSelector).val().trim();
                if(!code){
                    alert('Сначала вставьте HTML-код в поле!');
                    return;
                }

                try {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(code, 'text/html');
                    var a = doc.querySelector('a');
                    var img = doc.querySelector('img');

                    if(a && a.getAttribute('href')){
                        if(!$('#sz_target_url').val()){
                            $('#sz_target_url').val(a.getAttribute('href'));
                        }
                    }
                    if(img && img.getAttribute('src')){
                        $(targetImgSelector).val(img.getAttribute('src'));
                        if(img.getAttribute('alt') && !$('#sz_img_alt').val()){
                            $('#sz_img_alt').val(img.getAttribute('alt'));
                        }
                    }
                    alert('✓ Ссылка и баннер успешно извлечены!');
                } catch(err) {
                    alert('Ошибка парсинга: ' + err.message);
                }
            });

            $('input[name="sz_all_categories"]').on('change', function(){
                if($(this).val() === 'no'){
                    $('#sz_categories_selector').slideDown();
                } else {
                    $('#sz_categories_selector').slideUp();
                }
            });

            // Media Uploader
            $('.sz-upload-btn').on('click', function(e){
                e.preventDefault();
                var targetInput = $($(this).data('target'));
                var mediaUploader = wp.media({
                    title: 'Выберите изображение баннера или логотип',
                    button: { text: 'Вставить' },
                    multiple: false
                }).on('select', function(){
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    targetInput.val(attachment.url);
                }).open();
            });
        });
        </script>
        <?php
    }

    public function render_stats_metabox($post) {
        $stats = SZ_Promo_DB::get_stats($post->ID);
        if (empty($stats)) {
            echo '<p style="color:#64748b; font-size:13px;">Статистики пока нет. Баннер начнет собирать показы и клики сразу после активации.</p>';
            return;
        }

        echo '<table style="width:100%; border-collapse: collapse; font-size: 13px;">';
        echo '<tr style="border-bottom:1px solid #e2e8f0; text-align:left;"><th>Место</th><th>Показы</th><th>Клики</th><th>CTR</th></tr>';
        $total_views = 0;
        $total_clicks = 0;

        foreach ($stats as $row) {
            $views = absint($row['views']);
            $clicks = absint($row['clicks']);
            $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
            $total_views += $views;
            $total_clicks += $clicks;
            echo "<tr style='border-bottom:1px solid #f1f5f9; padding: 4px 0;'>
                <td style='padding:6px 0;'><code>" . esc_html($row['placement']) . "</code></td>
                <td>{$views}</td>
                <td>{$clicks}</td>
                <td><strong>{$ctr}%</strong></td>
            </tr>";
        }
        $total_ctr = $total_views > 0 ? round(($total_clicks / $total_views) * 100, 2) : 0;
        echo "<tr style='font-weight:bold; background:#f8fafc;'><td style='padding:6px 0;'>Всего:</td><td>{$total_views}</td><td>{$total_clicks}</td><td>{$total_ctr}%</td></tr>";
        echo '</table>';
    }

    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['sz_promo_nonce']) || !wp_verify_nonce($_POST['sz_promo_nonce'], 'sz_promo_save_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'sz_format'         => 'sanitize_text_field',
            'sz_target_url'     => 'esc_url_raw',
            'sz_new_tab'        => 'sanitize_text_field',
            'sz_all_categories' => 'sanitize_text_field',
            'sz_base_weight'    => 'absint',
            'sz_sidebar_img'    => 'esc_url_raw',
            'sz_content_img'    => 'esc_url_raw',
            'sz_mobile_img'     => 'esc_url_raw',
            'sz_img_alt'        => 'sanitize_text_field',
            'sz_badge_text'     => 'sanitize_text_field',
            'sz_headline'       => 'sanitize_text_field',
            'sz_description'    => 'sanitize_textarea_field',
            'sz_btn_text'       => 'sanitize_text_field',
            'sz_btn_color'      => 'sanitize_hex_color',
            'sz_logo_url'       => 'esc_url_raw',
            'sz_sidebar_html'   => 'wp_kses_post',
            'sz_content_html'   => 'wp_kses_post',
        ];

        foreach ($fields as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                $val = call_user_func($sanitizer, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $val);
            }
        }

        // Active status
        $is_active = isset($_POST['sz_is_active']) ? 'yes' : 'no';
        update_post_meta($post_id, '_sz_is_active', $is_active);

        // Placements array
        $placements = isset($_POST['sz_placements']) && is_array($_POST['sz_placements']) 
            ? array_map('sanitize_key', $_POST['sz_placements']) 
            : [];
        update_post_meta($post_id, '_sz_placements', $placements);

        // Categories array
        $categories = isset($_POST['sz_categories']) && is_array($_POST['sz_categories']) 
            ? array_map('sanitize_text_field', $_POST['sz_categories']) 
            : [];
        update_post_meta($post_id, '_sz_categories', $categories);
    }

    public function set_custom_columns($columns) {
        $new_cols = [];
        $new_cols['cb']         = $columns['cb'];
        $new_cols['title']      = 'Оффер / Баннер';
        $new_cols['sz_format']  = 'Формат';
        $new_cols['sz_places']  = 'Места показа';
        $new_cols['sz_status']  = 'Статус';
        $new_cols['sz_views']   = 'Показы';
        $new_cols['sz_clicks']  = 'Клики';
        $new_cols['sz_ctr']     = 'CTR';
        $new_cols['date']       = $columns['date'];
        return $new_cols;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'sz_format':
                $fmt = get_post_meta($post_id, '_sz_format', true);
                if ($fmt === 'native') echo '🎯 Нативная плашка';
                elseif ($fmt === 'image') echo '🖼 Баннеры (мультиформат)';
                elseif ($fmt === 'html') echo '💻 HTML-код';
                break;
            case 'sz_places':
                $places = (array) get_post_meta($post_id, '_sz_placements', true);
                echo !empty($places) ? implode(', ', array_map('esc_html', $places)) : '—';
                break;
            case 'sz_status':
                $active = get_post_meta($post_id, '_sz_is_active', true) !== 'no';
                echo $active 
                    ? '<span style="color:#16a34a; font-weight:600;">● Активен</span>' 
                    : '<span style="color:#94a3b8;">⏸ Пауза</span>';
                break;
            case 'sz_views':
            case 'sz_clicks':
            case 'sz_ctr':
                $stats = SZ_Promo_DB::get_stats($post_id);
                $views = 0;
                $clicks = 0;
                foreach ($stats as $s) {
                    $views += absint($s['views']);
                    $clicks += absint($s['clicks']);
                }
                $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
                if ($column === 'sz_views') echo number_format_i18n($views);
                if ($column === 'sz_clicks') echo number_format_i18n($clicks);
                if ($column === 'sz_ctr') {
                    $badge_color = $ctr >= 2.5 ? '#16a34a' : ($ctr >= 1.0 ? '#d97706' : '#64748b');
                    echo "<strong style='color:{$badge_color};'>{$ctr}%</strong>";
                }
                break;
        }
    }
}
