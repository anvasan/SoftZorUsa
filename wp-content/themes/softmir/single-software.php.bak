<?php get_header(); ?>

<?php while (have_posts()):
    the_post();
    $logo = get_field('company_logo');
    $website = get_field('website_url');
    $video = get_field('video_url');
    $short_desc = get_field('short_description');
    $pricing = get_field('pricing');
    $features = get_field('key_features');
    $advantages = get_field('advantages');
    $areas = get_field('business_areas');
    $markets = get_field('target_markets');
    $is_featured = get_field('is_featured');
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
                    <p class="detail-short-desc"><?php echo esc_html($short_desc); ?></p>
                    <?php
                endif; ?>

                <!-- Full Description (collapsible) -->
                <?php if (get_the_content()): ?>
                    <div class="description-wrapper">
                        <div class="description-content page-content detail-description-content" id="softDescription">
                            <?php the_content(); ?>
                        </div>
                        <div class="description-fade" id="softDescFade"></div>
                        <button class="description-toggle" id="softDescToggle" onclick="softmirToggleDesc()">
                            <?php esc_html_e('Показать больше...', 'softmir'); ?>
                        </button>
                    </div>

                    <?php
                endif; ?>

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

                <!-- Attributes (Sidebar - now in main content) -->
                <?php echo softmir_render_attrs_block(get_the_ID(), '_attr_page_position', 'sidebar', 'detail-block'); ?>

                <!-- Categories Block (Moved from Sidebar) -->
                <?php if ($terms && !is_wp_error($terms)): ?>
                    <div class="detail-block">
                        <h3 class="section-heading--sm"><?php esc_html_e('Категории', 'softmir'); ?></h3>
                        <div class="categories-2col">
                            <?php foreach ($terms as $term): ?>
                                <a href="<?php echo get_term_link($term); ?>" class="category-item-link">
                                    <?php echo esc_html($term->name); ?>
                                </a>
                                <?php
                            endforeach; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

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

                <!-- Advantages -->
                <?php if ($advantages): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>💪 <?php esc_html_e('Преимущества', 'softmir'); ?></span>
                            <span class="icon">+</span>
                        </button>
                        <div class="accordion-body">
                            <div class="wysiwyg-content">
                                <?php echo wp_kses_post($advantages); ?>
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