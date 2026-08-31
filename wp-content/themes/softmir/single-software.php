<?php get_header(); ?>

<?php while (have_posts()):
    the_post();
    $logo = softmir_get_company_logo();
    $website = get_field('website_url');
    $is_affiliate = get_post_meta(get_the_ID(), 'is_affiliate', true);
    if ($is_affiliate && $website) {
        $website = softmir_get_software_outbound_link(get_the_ID());
    }
    $video = get_field('video_url');
    $short_desc = softmir_get_field_with_lang_fallback('short_description');
    // [REMOVED] $pricing, $features — WYSIWYG fields removed per content strategy

    // Парсим сценарии из одного Markdown-поля (### Заголовок\nDescription)
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
    // Functions softmir_parse_text_list() and softmir_get_text_field()
    // are now loaded from inc/template-helpers.php

    $advantages = softmir_parse_text_list(softmir_get_text_field('top_reasons'));
    $disadvantages = softmir_parse_text_list(softmir_get_text_field('disadvantages'));
    $best_for = softmir_parse_text_list(softmir_get_text_field('best_for'));
    $bad_for = softmir_parse_text_list(softmir_get_text_field('bad_for'));



    // [REMOVED] $areas — WYSIWYG field removed per content strategy
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
        <nav class="breadcrumbs" aria-label="<?php esc_attr_e('Navigation', 'softmir'); ?>">
            <a href="<?php echo home_url('/'); ?>"><?php esc_html_e('Home', 'softmir'); ?></a>
            <span class="sep">›</span>
            <a href="<?php echo get_post_type_archive_link('software'); ?>"><?php esc_html_e('Catalog', 'softmir'); ?></a>
            <span class="sep">›</span>
            <?php if ($term_to_display && !is_wp_error($term_to_display)): ?>
                <a
                    href="<?php echo esc_url(get_term_link($term_to_display)); ?>"><?php echo esc_html($term_to_display->name); ?></a>
                <span class="sep">›</span>
            <?php endif; ?>
            <span><?php the_title(); ?></span>
        </nav>
    </div>

    <article class="container">
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
                    <span class="view-counter__label"><?php esc_html_e('views', 'softmir'); ?></span>
                </div>

                <?php if ($short_desc): ?>
                    <div class="detail-short-desc-box">
                        <p class="detail-short-desc"><?php echo esc_html($short_desc); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Promo Alert Box -->
                <?php 
                $promo_text = get_field('promo_text');
                $promo_deadline = get_field('promo_deadline');
                if ($promo_text): 
                    $promo_website = get_field('website_url');
                    $promo_is_affiliate = get_post_meta(get_the_ID(), 'is_affiliate', true);
                    $promo_target_url = '';
                    $promo_target_blank = '';
                    
                    if ($promo_is_affiliate && $promo_website) {
                        $promo_target_url = softmir_get_software_outbound_link(get_the_ID());
                        $promo_target_blank = ' target="_blank" rel="noopener noreferrer"';
                    } elseif ($promo_website) {
                        $promo_target_url = $promo_website;
                        $promo_target_blank = ' target="_blank" rel="noopener noreferrer"';
                    }
                ?>
                    <div class="promo-alert-box detail-block" style="display: flex; flex-direction: column; gap: 1rem; align-items: flex-start; justify-content: space-between;">
                        <div class="promo-alert-content" style="flex-grow: 1;">
                            <span class="promo-alert-icon">🔥</span>
                            <div class="promo-alert-text">
                                <div class="promo-text"><?php echo nl2br(esc_html($promo_text)); ?></div>
                                <?php if ($promo_deadline): ?>
                                    <div class="promo-deadline"><strong><?php esc_html_e('Offer expires on:', 'softmir'); ?></strong> <?php echo esc_html($promo_deadline); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($promo_target_url): ?>
                        <div class="promo-alert-action" style="margin-left: 3rem; margin-top: 0.5rem;">
                            <a href="<?php echo esc_url($promo_target_url); ?>"<?php echo $promo_target_blank; ?> style="display: inline-block; background-color: #e11d48; color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; box-shadow: 0 4px 6px -1px rgba(225, 29, 72, 0.2); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#be123c'" onmouseout="this.style.backgroundColor='#e11d48'">
                                <?php esc_html_e('Use offer on website →', 'softmir'); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Full Description (collapsible) -->
                <?php if (get_the_content()): ?>
                    <div class="description-wrapper">
                        <div class="description-content page-content detail-description-content" id="softDescription">
                            <?php
                            // Heading emojis are now applied via the_content filter
                            // in inc/template-helpers.php (softmir_content_heading_emojis)
                            the_content();
                            ?>
                        </div>
                        <div class="description-fade" id="softDescFade"></div>
                        <button class="description-toggle" id="softDescToggle" onclick="softmirToggleDesc()">
                            <?php echo esc_html(softmir_quiz_t('sw_show_more', 'Show more...')); ?>
                        </button>
                    </div>

                    <?php
                endif; ?>

                <!-- SoftZor Advice: Disclaimer + CTA to vendor -->
                <?php if ($website): ?>
                    <div class="softzor-disclaimer-tip">
                        <span class="softzor-disclaimer-tip__icon">💡</span>
                        <p>
                            <strong><?php esc_html_e('SoftZor Advice:', 'softmir'); ?></strong>
                            <?php esc_html_e('Always check more recent and detailed tech specs on', 'softmir'); ?>
                            <a href="<?php echo esc_url($website); ?>" target="_blank"
                                rel="noopener noreferrer"><?php esc_html_e('the manufacturer\'s website', 'softmir'); ?>
                                →</a>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Mobile-only CTA -->
                <div class="mobile-cta-wrapper hide-on-desktop">
                    <div class="group-buy-box">
                        <?php
                        $discount_opt = get_field('discount_available', get_the_ID());
                        $discount_amount = get_field('discount_amount', get_the_ID()) ?: esc_html__('discount', 'softmir');
                        $gb_count = softmir_get_gb_count(get_the_ID());
                        ?>
                        <button type="button" class="btn btn-primary w-full"
                            title="<?php esc_attr_e('Leave a request — we will negotiate an exclusive price with the developer.', 'softmir'); ?>"
                            onclick="openGbModal(<?php echo get_the_ID(); ?>, '<?php echo esc_js(get_the_title()); ?>', '<?php echo $discount_opt === 'yes' ? esc_js($discount_amount) : ''; ?>')">
                            🤝 <?php esc_html_e('Joint purchase', 'softmir'); ?>
                        </button>
                        <span class="card-gb-trust">
                            🛡️ <?php esc_html_e('Safe. No obligations.', 'softmir'); ?>
                        </span>
                    </div>
                    <?php if ($website): ?>
                        <div class="btn-block-wrap">
                            <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"
                                class="btn btn-primary w-full">
                                <?php
                                $slug = urldecode(get_post_field('post_name', get_the_ID()));
                                $slug_pattern = str_replace('-', '[\\s\\-]*', preg_quote($slug, '/'));
                                $sw_name = get_the_title();
                                if (preg_match('/' . $slug_pattern . '/iu', $sw_name, $m)) {
                                    $sw_name = $m[0];
                                } else {
                                    $sw_name = ucwords(str_replace('-', ' ', $slug));
                                }
                                printf(esc_html__('Try %s for free', 'softmir'), esc_html($sw_name));
                                ?> →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Scenarios Block -->
                <?php if (!empty($scenarios) && is_array($scenarios)): ?>
                    <section class="scenarios-section detail-block"
                        aria-label="<?php esc_attr_e('Use Cases', 'softmir'); ?>">
                        <div class="section-heading">
                            <h2>
                                ⚙️ <?php echo esc_html(softmir_quiz_t('sw_scenarios', 'Use Cases')); ?>
                            </h2>
                        </div>
                        <div class="scenarios-list">
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
                                <div class="scenario-row">
                                    <div class="scenario-icon">
                                        <?php echo $svg; ?>
                                    </div>
                                    <div class="scenario-content">
                                        <h4 class="scenario-title">
                                            <?php echo esc_html($scene['title']); ?>
                                        </h4>
                                        <p class="scenario-desc">
                                            <?php echo esc_html($scene['desc']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Pros & Cons Grid -->
                <?php if (!empty($advantages) || !empty($disadvantages)): ?>
                    <section class="pros-cons-grid" aria-label="<?php esc_attr_e('Плюсы и minусы', 'softmir'); ?>">

                        <?php if (!empty($advantages) && is_array($advantages)): ?>
                            <div class="pros-box detail-list-box">
                                <h3>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_why_top', 'Why It\'s TOP')); ?>
                                </h3>
                                <ul>
                                    <?php foreach ($advantages as $adv): ?>
                                        <li>
                                            <span class="marker">✓</span>
                                            <?php echo esc_html(is_array($adv) ? ($adv['text'] ?? '') : $adv); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($disadvantages) && is_array($disadvantages)): ?>
                            <div class="cons-box detail-list-box">
                                <h3>
                                    <span class="cons-icon">⚠️</span>
                                    <?php echo esc_html(softmir_quiz_t('sw_nuances', 'Nuances & Risks')); ?>
                                </h3>
                                <ul>
                                    <?php foreach ($disadvantages as $dis): ?>
                                        <li>
                                            <span class="marker">✕</span>
                                            <?php echo esc_html(is_array($dis) ? ($dis['text'] ?? '') : $dis); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    </section>
                <?php endif; ?>

                <!-- Best / Bad For Block (Full Width) -->
                <?php if (!empty($best_for) || !empty($bad_for)): ?>
                    <section class="recommendations-block" aria-label="<?php esc_attr_e('Recommendations', 'softmir'); ?>">

                        <?php if (!empty($best_for) && is_array($best_for)): ?>
                            <div class="best-for-box detail-list-box">
                                <h3>
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_best_for', '🚀 BEST FOR you if:')); ?>
                                </h3>
                                <ul>
                                    <?php foreach ($best_for as $bf): ?>
                                        <li>
                                            <span class="marker">✓</span>
                                            <?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($bad_for) && is_array($bad_for)): ?>
                            <div class="bad-for-box detail-list-box">
                                <h3>
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    <?php echo esc_html(softmir_quiz_t('sw_bad_for', 'Better to avoid if:')); ?>
                                </h3>
                                <ul>
                                    <?php foreach ($bad_for as $bf): ?>
                                        <li>
                                            <span class="marker">✕</span>
                                            <?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                    </section>
                <?php endif; ?>

                <!-- Key Functions -->
                <?php if (function_exists('softmir_render_key_functions')): ?>
                    <div class="detail-block detail-key-functions">
                        <div class="attrs-section">
                            <h3 class="attrs-title"><?php esc_html_e('Key features', 'softmir'); ?></h3>
                            <div class="attrs-list attrs-list--key-functions">
                                <?php echo softmir_render_key_functions(get_the_ID(), 0); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Attributes (Middle) -->
                <?php echo softmir_render_attrs_block(get_the_ID(), '_attr_page_position', 'middle', 'detail-block attrs-middle-block'); ?>

                <!-- Integrations Tags Block -->
                <?php
                if (function_exists('softmir_render_integrations_block')) {
                    $integrations_html = softmir_render_integrations_block(get_the_ID());
                    if ($integrations_html) {
                        echo $integrations_html;
                    }
                }
                ?>

                <!-- [REMOVED] Feature List accordion (WYSIWYG) -->

                <!-- [REMOVED] Pricing accordion (WYSIWYG) -->

                <!-- [REMOVED] Business Areas accordion (WYSIWYG) -->

                <!-- Additional Attributes Accordion -->
                <?php
                $added_options_html = softmir_render_attrs_block(get_the_ID(), '_attr_page_position', 'sidebar', '');
                if (!empty(trim(strip_tags($added_options_html)))): ?>
                    <div class="accordion">
                        <button class="accordion-header"
                            onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                            <span>⚙️ <?php esc_html_e('Additional features', 'softmir'); ?></span>
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
                            <h2 class="section-heading"><?php esc_html_e('Video review', 'softmir'); ?></h2>
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
                        <h2 class="section-heading"><?php esc_html_e('Screenshots', 'softmir'); ?></h2>
                        <div class="gallery-grid popup-gallery">
                            <?php foreach ($screenshots as $image_url): ?>
                                <a href="<?php echo esc_url($image_url); ?>" target="_blank">
                                    <img src="<?php echo esc_url($image_url); ?>"
                                        alt="<?php printf(esc_attr__('Interface screenshot %s', 'softmir'), get_the_title()); ?>"
                                        title="<?php printf(esc_attr__('Interface screenshot %s', 'softmir'), get_the_title()); ?>" loading="lazy">
                                </a>
                                <?php
                            endforeach; ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

                <!-- Reviews Block -->
                <section id="reviews" class="reviews-section" aria-label="<?php esc_attr_e('Reviews', 'softmir'); ?>">
                    <div class="reviews-header">
                        <h2 class="section-heading mb-0"><?php esc_html_e('User Reviews', 'softmir'); ?></h2>
                    </div>

                    <!-- Reviews List -->
                    <div class="reviews-list">
                        <?php echo do_shortcode('[site_reviews pagination="ajax" hide="title" assigned_posts="post_id"]'); ?>
                    </div>

                    <!-- Hidden Review Form -->
                    <div id="review-form-wrapper" class="hidden review-form-box">
                        <h3 class="review-form-title"><?php esc_html_e('Leave review', 'softmir'); ?></h3>
                        <?php echo do_shortcode('[site_reviews_form hide="title" assigned_posts="post_id"]'); ?>
                    </div>
                </section>

                <!-- Send Info Form -->
                <div class="email-info-section detail-block">
                    <h3>
                        <?php esc_html_e('Save the selection for approval: send info about this product to your email', 'softmir'); ?>
                    </h3>
                    <form id="send-sw-info-form" class="mt-section">
                        <div class="form-group mb-1"
                            <?php echo is_user_logged_in() ? 'style="display:none;"' : ''; ?>>
                            <label for="sw-info-name"><?php esc_html_e('Your name', 'softmir'); ?></label>
                            <input type="text" id="sw-info-name" name="name" class="form-control"
                                placeholder="<?php esc_attr_e('Alexander', 'softmir'); ?>"
                                value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>">
                        </div>
                        <div class="form-group mb-1">
                            <label for="sw-info-email"><?php esc_html_e('Email address', 'softmir'); ?>
                                <span class="text-danger">*</span></label>
                            <input type="email" id="sw-info-email" name="email" class="form-control" required
                                value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                            <input type="hidden" id="sw-info-post-id" value="<?php echo get_the_ID(); ?>">
                        </div>
                        <p class="form-hint">
                            <?php esc_html_e('By continuing, you agree to our Terms of Use and Privacy Policy.', 'softmir'); ?>
                        </p>
                        <button type="submit" class="btn btn-primary" id="sw-info-submit">
                            <?php esc_html_e('Send me information', 'softmir'); ?>
                        </button>
                        <div id="sw-info-result" class="mt-1" style="display:none;"></div>
                    </form>
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
                        <h2 class="section-heading text-center"><?php esc_html_e('Similar products', 'softmir'); ?></h2>
                        <div class="software-grid similar-grid">
                            <?php
                            $GLOBALS['softmir_similar_card'] = true;
                            while ($similar->have_posts()):
                                $similar->the_post();
                                get_template_part('template-parts/card', 'software');
                            endwhile;
                            wp_reset_postdata();
                            unset($GLOBALS['softmir_similar_card']);
                            ?>
                        </div>
                    </div>
                    <?php
                endif; ?>

            </div>

            <!-- Sidebar -->
            <aside class="detail-sidebar">
                <div class="sidebar-box text-center">
                    <?php if ($logo):
                        $logo_id = attachment_url_to_postid($logo);
                        if ($logo_id):
                            echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'sidebar-logo', 'alt' => get_the_title() . ' logo']);
                        else:
                            ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php the_title_attribute(); ?> logo"
                                title="<?php the_title_attribute(); ?> logo" class="sidebar-logo" loading="lazy">
                            <?php
                        endif;
                    endif; ?>

                    <?php if ($website): ?>
                        <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-primary w-full mb-05">
                            <?php echo esc_html__('Visit site', 'softmir'); ?> →
                        </a>
                        <?php
                    endif; ?>

                    <!-- Category removed from here -->

                    <?php
                    $price_summary = softmir_get_field_with_lang_fallback('price_summary');
                    if ($price_summary): ?>
                        <div class="sidebar-price-badge">
                            <span class="price-label"><?php esc_html_e('Price', 'softmir'); ?></span>
                            <span class="price-value"><?php echo esc_html($price_summary); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Group Buying CTA -->
                    <?php
                    $discount_opt = get_field('discount_available', get_the_ID());
                    $discount_amount = get_field('discount_amount', get_the_ID()) ?: esc_html__('индивидуальную discount', 'softmir');
                    // Lead counter
                    $gb_count = softmir_get_gb_count(get_the_ID());
                    ?>
                    <div class="sidebar-box group-buy-box">
                        <?php if ($discount_opt === 'yes'): ?>
                            <h3>🔥
                                <?php esc_html_e('Discount', 'softmir'); ?>         <?php echo esc_html($discount_amount); ?>
                            </h3>
                            <p>
                                <?php echo wp_kses_post(__('Leave a request — we will negotiate a partner price with the vendor.', 'softmir')); ?>
                            </p>
                        <?php else: ?>
                            <h3>🤝
                                <?php esc_html_e('Joint purchase', 'softmir'); ?>
                            </h3>
                            <p>
                                <?php echo wp_kses_post(__('Leave a request — we will negotiate an exclusive price with the developer.', 'softmir')); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($gb_count > 0): ?>
                            <div class="gb-count-badge">
                                👥
                                <?php printf(wp_kses_post(_n('Уже <strong>%s</strong> request', 'Already <strong>%s</strong> заявок', $gb_count, 'softmir')), number_format_i18n($gb_count)); ?>
                            </div>
                        <?php else: ?>
                            <p class="gb-empty-hint">
                                <?php esc_html_e('Be the first to submit a request!', 'softmir'); ?>
                            </p>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary w-full"
                            title="<?php esc_attr_e('Leave a request — we will negotiate an exclusive price with the developer.', 'softmir'); ?>"
                            onclick="openGbModal(<?php echo get_the_ID(); ?>, '<?php echo esc_js(get_the_title()); ?>', '<?php echo $discount_opt === 'yes' ? esc_js($discount_amount) : ''; ?>')"><?php esc_html_e('Submit request', 'softmir'); ?></button>
                        <span class="card-gb-trust">
                            🛡️ <?php esc_html_e('Safe. No obligations.', 'softmir'); ?>
                        </span>
                        <div id="gb-result-sidebar-<?php echo get_the_ID(); ?>" class="gb-result-inline"></div>
                    </div>

                    <?php if ($is_featured): ?>
                        <p class="sidebar-featured-badge">
                            ⭐ <?php esc_html_e('Recommended product', 'softmir'); ?>
                        </p>
                        <?php
                    endif; ?>

                    <a href="#" class="detail-compare-btn btn-compare-toggle"
                        data-id="<?php echo esc_attr(get_the_ID()); ?>"
                        data-default-text="<?php esc_attr_e('＋ Add to compare', 'softmir'); ?>"
                        data-added-text="<?php esc_attr_e('✓ In comparison', 'softmir'); ?>">
                        <?php esc_html_e('＋ Add to compare', 'softmir'); ?>
                    </a>
                </div>



                <!-- Attributes (Sidebar - moved to accordion) -->

                <!-- Reviews Summary (Sidebar) -->
                <div class="sidebar-box">
                    <h3 class="section-heading--sm"><?php esc_html_e('Rating', 'softmir'); ?></h3>
                    <?php echo do_shortcode('[site_reviews_summary hide="title,summary" assigned_posts="post_id"]'); ?>

                    <button class="btn btn-primary btn-block w-full mt-section" onclick="softmirOpenReviewForm()">
                        <?php esc_html_e('Write a review', 'softmir'); ?>
                    </button>
                </div>



                <!-- [REMOVED] Target Markets block -->

                <!-- CTA -->
                <div class="sidebar-box sidebar-cta-box">
                    <h3 class="section-heading--xs">💡 <?php esc_html_e('Need help?', 'softmir'); ?></h3>
                    <p><?php esc_html_e('Our experts will help with selection and implementation.', 'softmir'); ?></p>
                    <a href="<?php echo home_url('/contacts/'); ?>"
                        class="btn btn-primary w-full"><?php esc_html_e('Contact', 'softmir'); ?></a>
                </div>
            </aside>

        </div>
    </article>

    <?php
endwhile; ?>

<?php get_footer(); ?>