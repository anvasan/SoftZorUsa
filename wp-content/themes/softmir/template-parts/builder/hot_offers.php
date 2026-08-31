<?php
// Hot Offers Module - displays software with active promo
$title = '🔥 ' . __('Hot Offers', 'softmir');

$offers = new WP_Query([
    'post_type' => 'software',
    'posts_per_page' => 12,
    'orderby' => 'rand',
    'meta_query' => [
        'relation' => 'AND',
        [
            'key'     => 'promo_text',
            'compare' => 'EXISTS',
        ],
        [
            'key'     => 'promo_text',
            'value'   => '',
            'compare' => '!=',
        ]
    ],
]);

if ($offers->have_posts()):
    ?>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    
    <section class="section section-hot-offers" style="background-color: var(--bg-secondary); padding: 3rem 0; margin-top: 3rem;">
        <div class="container">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                <h2 class="section-title mt-section" style="margin-bottom: 0;"><?php echo esc_html($title); ?></h2>
            </div>

            <!-- Swiper Container -->
            <div class="swiper promo-swiper" style="padding-bottom: 40px;">
                <div class="swiper-wrapper">
                    <?php while ($offers->have_posts()):
                        $offers->the_post();
                        
                        $logo = get_field('company_logo');
                        $logo_url = '';
                        if ($logo) {
                            if (is_array($logo)) {
                                $logo_url = $logo['url'];
                            } elseif (is_numeric($logo)) {
                                $logo_url = wp_get_attachment_url($logo);
                            } else {
                                $logo_url = $logo;
                            }
                        }
                        
                        $promo_text = get_field('promo_text');
                        $promo_deadline = get_field('promo_deadline');
                        
                        // Affiliate link logic
                        $target_url = get_permalink();
                        $target_blank = '';
                        
                        $website = get_field('website_url');
                        $is_affiliate = get_post_meta(get_the_ID(), 'is_affiliate', true);
                        
                        if ($is_affiliate && $website) {
                            $target_url = softmir_get_software_outbound_link(get_the_ID());
                            $target_blank = ' target="_blank" rel="noopener noreferrer"';
                        }
                        ?>
                        <div class="swiper-slide">
                            <a href="<?php echo esc_url($target_url); ?>"<?php echo $target_blank; ?> class="card-mini promo-card-mini" style="display: flex; height: 100%; flex-direction: column; background: var(--bg-card, #fff); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; color: inherit; border: 1px solid var(--border-color, #e2e8f0); position: relative; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;">
                                <div class="promo-badge" style="position: absolute; top: 0; right: 0; background: #e11d48; color: white; padding: 4px 12px; font-size: 0.75rem; font-weight: bold; border-bottom-left-radius: 8px;">PROMO</div>
                                
                                <div class="promo-card-header" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                    <?php if ($logo_url): ?>
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" style="width: 48px; height: 48px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);">
                                    <?php else: ?>
                                        <div class="no-logo" style="width: 48px; height: 48px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #94a3b8;"><?php echo mb_substr(get_the_title(), 0, 1); ?></div>
                                    <?php endif; ?>
                                    <h3 style="font-size: 1.1rem; margin: 0; font-weight: 600; line-height: 1.2; color: var(--text-color, #1e293b);"><?php the_title(); ?></h3>
                                </div>
                                
                                <div class="promo-card-body" style="flex-grow: 1;">
                                    <p style="margin: 0 0 0.5rem; font-size: 0.95rem; color: var(--text-color, #334155); line-height: 1.4;">
                                        <?php echo wp_trim_words(esc_html($promo_text), 15); ?>
                                    </p>
                                    <?php if ($promo_deadline): ?>
                                        <p style="margin: 0; font-size: 0.8rem; color: #e11d48; font-weight: 600;">
                                            ⏱ <?php esc_html_e('Ends:', 'softmir'); ?> <?php echo esc_html($promo_deadline); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endwhile;
                    wp_reset_postdata(); ?>
                </div>
                <!-- Navigation arrows -->
                <div class="swiper-button-prev" style="color: #e11d48;"></div>
                <div class="swiper-button-next" style="color: #e11d48;"></div>
            </div>
        </div>
    </section>
    
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const promoSwiper = new Swiper('.promo-swiper', {
            slidesPerView: 1.2,
            spaceBetween: 16,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 2,
                    spaceBetween: 20,
                },
                992: {
                    slidesPerView: 3,
                    spaceBetween: 20,
                },
                1200: {
                    slidesPerView: 4,
                    spaceBetween: 20,
                }
            }
        });
    });
    </script>

    <style>
    .promo-card-mini:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        border-color: #e11d48 !important;
    }
    .promo-swiper {
        position: relative;
    }
    .promo-swiper .swiper-button-next,
    .promo-swiper .swiper-button-prev {
        background: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        top: 45%;
    }
    .promo-swiper .swiper-button-next:after,
    .promo-swiper .swiper-button-prev:after {
        font-size: 18px;
        font-weight: bold;
    }
    .promo-swiper .swiper-slide {
        height: auto;
    }
    </style>
    <?php
endif;
