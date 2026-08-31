<?php get_header(); ?>

<?php while (have_posts()):
    the_post();
    $logo = softmir_get_company_logo();
    $website = get_field('website_url');
    $video = get_field('video_url');
    $short_desc = get_field('short_description');
    $pricing = get_field('pricing');
    $features = get_field('key_features');

    // Парсим сценарии из одного Markdown-поля (### Заголовок\nОписание)
    $scenarios = [];
    $scenarios_raw = get_field('scenarios_md');
    if (!empty($scenarios_raw)) {
        $parts = preg_split('/^###\s*/m', $scenarios_raw);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part))
                continue;
            $lines = explode("\n", $part, 2);
            $title = trim($lines[0], " :\t\r\n");
            $desc = isset($lines[1]) ? trim($lines[1]) : '';
            if (!empty($title)) {
                $scenarios[] = ['title' => $title, 'desc' => $desc];
            }
        }
    }
    // Очистка от старых HTML таблиц (если в базе остался старый сохраненный код)
    function softmir_parse_text_list($raw)
    {
        if (is_array($raw))
            return $raw;
        if (empty($raw))
            return [];
        // Заменяем закрывающие теги блоков на перенос строки, чтобы текст не слипся
        $text = str_replace(array('</tr>', '</td>', '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>'), "\n", $raw);
        $text = wp_strip_all_tags($text); // Удаляем весь оставшийся HTML
        return array_filter(array_map('trim', explode("\n", $text)));
    }
    // Получение значения поля с фолбэком через get_post_meta 
    // (на случай если ACF-ссылка _field_name отсутствует после очистки)
    function softmir_get_text_field($field_name)
    {
        $val = get_field($field_name);
        if (empty($val)) {
            $val = get_post_meta(get_the_ID(), $field_name, true);
        }
        return $val;
    }

    $advantages = softmir_parse_text_list(softmir_get_text_field('top_reasons'));
    $disadvantages = softmir_parse_text_list(softmir_get_text_field('disadvantages'));
    $best_for = softmir_parse_text_list(softmir_get_text_field('best_for'));
    $bad_for = softmir_parse_text_list(softmir_get_text_field('bad_for'));



    $areas = get_field('business_areas');
    $terms = get_the_terms(get_the_ID(), 'software_category');
    $primary_cat_id = get_post_meta(get_the_ID(), 'primary_category', true);
    $term_to_display = null;
    if ($primary_cat_id) {
        $term_to_display = get_term($primary_cat_id, 'software_category');
    } elseif ($terms && !is_wp_error($terms)) {
        $term_to_display = $terms[0];
    }
    $category = $term_to_display && !is_wp_error($term_to_display) ? $term_to_display->name : '';
    ?>

    <div class="container">
        <div class="breadcrumbs">
            <a href="<?php echo home_url('/'); ?>"><?php esc_html_e('Главная', 'softmir'); ?></a>
            <span class="sep">›</span>
            <a href="<?php echo get_post_type_archive_link('software'); ?>"><?php esc_html_e('Каталог', 'softmir'); ?></a>
            <span class="sep">›</span>
            <?php if ($term_to_display && !is_wp_error($term_to_display)): ?>
                <a
                    href="<?php echo esc_url(get_term_link($term_to_display)); ?>"><?php echo esc_html($term_to_display->name); ?></a>
                <span class="sep">›</span>
            <?php endif; ?>
            <span><?php the_title(); ?></span>
        </div>
    </div>

    <div class="container">
        <div class="detail-layout">

            <!-- Main Content -->
            <div class="detail-main">

                <h1 class="detail-title"><?php the_title(); ?></h1>

                <div class="view-counter">
                    <svg class="view-counter__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span class="view-counter__count"><?php echo number_format_i18n(softmir_get_views()); ?></span>
                    <span class="view-counter__label"><?php esc_html_e('просм.', 'softmir'); ?></span>
                </div>

                <?php if ($short_desc): ?>
                    <div
                        style="background: #eff6ff; color: #1e3a8a; padding: 18px 24px; border-radius: 12px; margin-bottom: 24px; font-size: 1.05rem; line-height: 1.6; border: 1px solid #bfdbfe;">
                        <p class="detail-short-desc" style="margin: 0;"><?php echo esc_html($short_desc); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Full Description (collapsible) -->
                <?php if (get_the_content()): ?>
                    <div class="description-wrapper">
                        <div class="description-content page-content detail-description-content" id="softDescription">
                            <?php the_content(); ?>
                        </div>
                        <div class="description-fade" id="softDescFade"></div>
                        <button class="description-toggle" id="softDescToggle" onclick="softmirToggleDesc()">
                            <?php echo esc_html(softmir_quiz_t('sw_show_more', 'Показать больше...')); ?>
                        </button>
                    </div>

                    <?php
                endif; ?>

                <!-- Scenarios Block -->
                <?php if (!empty($scenarios) && is_array($scenarios)): ?>
                    <div class="scenarios-section detail-block" style="margin-top: 0.5rem;">
                        <div class="section-heading"
                            style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3" />
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                            </svg>
                            <h2
                                style="font-size: 1.25rem; margin: 0; font-weight: 700; color: #111827; border-bottom: none; padding-bottom: 0;">
                                <?php echo esc_html(softmir_quiz_t('sw_scenarios', 'Сценарии использования')); ?>
                            </h2>
                        </div>
                        <div class="scenarios-list" style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php
                            $svg_icons = [
                                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 1 4 12.9V17H8v-2.1A7 7 0 0 1 12 2z"/></svg>',
                                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>',
                            ];
                            $icon_idx = 0;
                            foreach ($scenarios as $scene):
                                $svg = isset($svg_icons[$icon_idx]) ? $svg_icons[$icon_idx] : $svg_icons[0];
                                $icon_idx++;
                                ?>
                                <div class="scenario-row"
                                    style="background: #ffffff; border-radius: 12px; padding: 16px 20px; border: 1px solid #f3f4f6; display: flex; gap: 16px; align-items: flex-start;">
                                    <div class="scenario-icon"
                                        style="background: #e0e7ff; color: #4f46e5; width: 44px; height: 44px; min-width: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <?php echo $svg; ?>
                                    </div>
                                    <div class="scenario-content">
                                        <h4 class="scenario-title"
                                            style="font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; color: #1f2937;">
                                            <?php echo esc_html($scene['title']); ?>
                                        </h4>
                                        <p class="scenario-desc"
                                            style="font-size: 0.95rem; color: #4b5563; line-height: 1.5; margin: 0;">
                                            <?php echo esc_html($scene['desc']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Pros & Cons Grid -->
                <?php if (!empty($advantages) || !empty($disadvantages)): ?>
                    <div class="pros-cons-grid"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 0.5rem;">

                        <?php if (!empty($advantages) && is_array($advantages)): ?>
                            <div class="pros-box"
                                style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 24px;">
                                <h3
                                    style="color: #166534; font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_why_top', 'Почему это ТОП')); ?>
                                </h3>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($advantages as $adv): ?>
                                        <li
                                            style="position: relative; padding-left: 28px; margin-bottom: 12px; font-size: 0.95rem; color: #374151; line-height: 1.4;">
                                            <span style="position: absolute; left: 0; top: 0px; color: #22c55e;">✓</span>
                                            <?php echo esc_html(is_array($adv) ? ($adv['text'] ?? '') : $adv); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($disadvantages) && is_array($disadvantages)): ?>
                            <div class="cons-box"
                                style="background: #fffbeb; border: 1px solid #fef08a; border-radius: 12px; padding: 24px;">
                                <h3
                                    style="color: #b45309; font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.25rem; line-height: 1;">⚠️</span>
                                    <?php echo esc_html(softmir_quiz_t('sw_nuances', 'Нюансы и Риски')); ?>
                                </h3>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($disadvantages as $dis): ?>
                                        <li
                                            style="position: relative; padding-left: 28px; margin-bottom: 12px; font-size: 0.95rem; color: #374151; line-height: 1.4;">
                                            <span
                                                style="position: absolute; left: 0; top: 0px; color: #f59e0b; font-weight: bold;">✕</span>
                                            <?php echo esc_html(is_array($dis) ? ($dis['text'] ?? '') : $dis); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <!-- Best / Bad For Block (Full Width) -->
                <?php if (!empty($best_for) || !empty($bad_for)): ?>
                    <div class="recommendations-block"
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 0.5rem;">

                        <?php if (!empty($best_for) && is_array($best_for)): ?>
                            <div class="best-for-box"
                                style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 12px; padding: 24px;">
                                <h3
                                    style="color: #166534; font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_best_for', '🚀 Вам ПОДОЙДЁТ, если:')); ?>
                                </h3>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($best_for as $bf): ?>
                                        <li
                                            style="position: relative; padding-left: 28px; margin-bottom: 10px; font-size: 0.95rem; color: #374151; line-height: 1.4;">
                                            <span style="position: absolute; left: 0; top: 2px; color: #22c55e;">✓</span>
                                            <?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($bad_for) && is_array($bad_for)): ?>
                            <div class="bad-for-box"
                                style="background: #ffffff; border: 1px solid #fecaca; border-radius: 12px; padding: 24px;">
                                <h3
                                    style="color: #991b1b; font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_bad_for', 'Лучше не брать, если:')); ?>
                                </h3>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($bad_for as $bf): ?>
                                        <li
                                            style="position: relative; padding-left: 28px; margin-bottom: 10px; font-size: 0.95rem; color: #374151; line-height: 1.4;">
                                            <span
                                                style="position: absolute; left: 0; top: 2px; color: #ef4444; font-weight:bold;">✕</span>
                                            <?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <!-- Key Functions -->
                <?php if (function_exists('softmir_render_key_functions')): ?>
                    <div class="detail-block detail-key-functions">
                        <div class="attrs-section">
                            <h3 class="attrs-title"><?php esc_html_e('Ключевые функции', 'softmir'); ?></h3>
                            <div class="attrs-list attrs-list--key-functions">
                                <?php echo softmir_render_key_functions(get_the_ID(), 0); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Attributes (Middle) -->
                <?php echo softmir_render_attrs_block(get_the_ID(), '_attr_page_position', 'middle', 'detail-block'); ?>

                <!-- Integrations Tags Block -->
                <?php
                if (function_exists('softmir_render_integrations_block')) {
                    $integrations_html = softmir_render_integrations_block(get_the_ID());
                    if ($integrations_html) {
                        echo $integrations_html;
                    }
                }
                ?>

                <!-- Feature List -->
                <?php if ($features): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>🔑 <?php esc_html_e('Ключевые особенности', 'softmir'); ?></span>
                            <span class="icon">+</span>
                        </button>
                        <div class="accordion-body">
                            <div class="wysiwyg-content">
                                <?php echo wp_kses_post($features); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- Pricing -->
                <?php if ($pricing): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>💰 <?php esc_html_e('Тарифы и цены', 'softmir'); ?></span>
                            <span class="icon">+</span>
                        </button>
                        <div class="accordion-body">
                            <div class="wysiwyg-content">
                                <?php echo wp_kses_post($pricing); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- Business Areas -->
                <?php if ($areas): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>🏢 <?php esc_html_e('Отрасли', 'softmir'); ?></span>
                            <span class="icon">+</span>
                        </button>
                        <div class="accordion-body">
                            <div class="wysiwyg-content">
                                <?php echo wp_kses_post($areas); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- Additional Attributes Accordion -->
                <?php
                $added_options_html = softmir_render_attrs_block(get_the_ID(), '_attr_page_position', 'sidebar', '');
                if (!empty(trim(strip_tags($added_options_html)))): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>⚙️ <?php esc_html_e('Дополнительные характеристики', 'softmir'); ?></span>
                            <span class="icon">+</span>
                        </button>
                        <div class="accordion-body">
                            <div class="wysiwyg-content">
                                <?php echo $added_options_html; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Video -->
                <?php
                if ($video):
                    preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|watch\?v=))([A-Za-z0-9_-]{11})/', $video, $matches);
                    $video_id = $matches[1] ?? '';
                    if ($video_id):
                        ?>
                        <div>
                            <h2 class="section-heading"><?php esc_html_e('Видео-обзор', 'softmir'); ?></h2>
                            <div class="video-responsive">
                                <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>" frameborder="0"
                                    allowfullscreen>
                                </iframe>
                            </div>
                        </div>
                        <?php
                    endif;
                endif;
                ?>

                <!-- Gallery -->
                <?php
                $screenshots = [];
                for ($i = 1; $i <= 4; $i++) {
                    $img = get_field('screenshot_' . $i);
                    if ($img) {
                        $screenshots[] = $img;
                    }
                }

                if (!empty($screenshots)):
                    ?>
                    <div class="gallery-section">
                        <h2 class="section-heading"><?php esc_html_e('Скриншоты', 'softmir'); ?></h2>
                        <div class="gallery-grid popup-gallery">
                            <?php foreach ($screenshots as $image_url): ?>
                                <a href="<?php echo esc_url($image_url); ?>" target="_blank">
                                    <img src="<?php echo esc_url($image_url); ?>"
                                        alt="<?php esc_attr_e('Скриншот интерфейса', 'softmir'); ?>" loading="lazy">
                                </a>
                                <?php
                            endforeach; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- Reviews Block -->
                <div id="reviews" class="reviews-section">
                    <div class="reviews-header">
                        <h2 class="section-heading mb-0"><?php esc_html_e('Отзывы пользователей', 'softmir'); ?></h2>
                    </div>

                    <!-- Reviews List -->
                    <div class="reviews-list">
                        <?php echo do_shortcode('[site_reviews pagination="ajax" hide="title" assigned_posts="post_id"]'); ?>
                    </div>

                    <!-- Hidden Review Form -->
                    <div id="review-form-wrapper" class="hidden review-form-box">
                        <h3 class="review-form-title"><?php esc_html_e('Оставить отзыв', 'softmir'); ?></h3>
                        <?php echo do_shortcode('[site_reviews_form hide="title" assigned_posts="post_id"]'); ?>
                    </div>
                </div>

                <!-- Similar Products -->
                <?php
                $similar_args = [
                    'post_type' => 'software',
                    'posts_per_page' => 2,
                    'post__not_in' => [get_the_ID()],
                ];
                if ($terms && !is_wp_error($terms)) {
                    $similar_args['tax_query'] = [
                        ['taxonomy' => 'software_category', 'field' => 'term_id', 'terms' => $terms[0]->term_id],
                    ];
                }
                $similar = new WP_Query($similar_args);
                if ($similar->have_posts()):
                    ?>
                    <div class="similar-section">
                        <h2 class="section-heading text-center"><?php esc_html_e('Похожие продукты', 'softmir'); ?></h2>
                        <div class="software-grid similar-grid">
                            <?php
                            while ($similar->have_posts()):
                                $similar->the_post();
                                get_template_part('template-parts/card', 'software');
                            endwhile;
                            wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

            </div>

            <!-- Sidebar -->
            <aside class="detail-sidebar">
                <div class="sidebar-box text-center">
                    <?php if ($logo): ?>
                        <img src="<?php echo esc_url($logo); ?>" alt="<?php the_title_attribute(); ?> логотип"
                            class="sidebar-logo" loading="lazy">
                        <?php
                    endif; ?>

                    <?php if ($website): ?>
                        <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-primary w-full mb-05">
                            <?php echo esc_html__('Посетить сайт', 'softmir'); ?> →
                        </a>
                        <?php
                    endif; ?>

                    <!-- Category removed from here -->

                    <?php if ($is_featured): ?>
                        <p class="sidebar-featured-badge">
                            ⭐ <?php esc_html_e('Рекомендуемый продукт', 'softmir'); ?>
                        </p>
                        <?php
                    endif; ?>

                    <a href="#" class="detail-compare-btn btn-compare-toggle"
                        data-id="<?php echo esc_attr(get_the_ID()); ?>"
                        data-default-text="<?php esc_attr_e('＋ Добавить к сравнению', 'softmir'); ?>"
                        data-added-text="<?php esc_attr_e('✓ В сравнении', 'softmir'); ?>">
                        <?php esc_html_e('＋ Добавить к сравнению', 'softmir'); ?>
                    </a>
                </div>



                <!-- Attributes (Sidebar - moved to accordion) -->

                <!-- Reviews Summary (Sidebar) -->
                <div class="sidebar-box">
                    <h3 class="section-heading--sm"><?php esc_html_e('Рейтинг', 'softmir'); ?></h3>
                    <?php echo do_shortcode('[site_reviews_summary hide="title,summary" assigned_posts="post_id"]'); ?>

                    <button class="btn btn-primary btn-block w-full mt-section" onclick="softmirOpenReviewForm()">
                        <?php esc_html_e('Написать отзыв', 'softmir'); ?>
                    </button>
                </div>



                <?php if ($markets && is_array($markets) && count($markets) > 0): ?>
                    <div class="sidebar-box">
                        <h3 class="section-heading--xs">🌍 <?php esc_html_e('Целевые рынки', 'softmir'); ?></h3>
                        <div class="market-tags">
                            <?php foreach ($markets as $market): ?>
                                <span class="market-tag">
                                    <?php echo esc_html($market); ?>
                                </span>
                                <?php
                            endforeach; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- CTA -->
                <div class="sidebar-box sidebar-cta-box">
                    <h3 class="section-heading--xs">💡 <?php esc_html_e('Нужна помощь?', 'softmir'); ?></h3>
                    <p><?php esc_html_e('Наши эксперты помогут с выбором и внедрением.', 'softmir'); ?></p>
                    <a href="<?php echo home_url('/contacts/'); ?>"
                        class="btn btn-primary w-full"><?php esc_html_e('Связаться', 'softmir'); ?></a>
                </div>
            </aside>

        </div>
    </div>

    <?php
endwhile; ?>

<?php get_footer(); ?>