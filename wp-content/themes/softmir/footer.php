<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">

            <div class="footer-brand">
                <h3>SoftZor</h3>
                <p><?php echo esc_html(softmir_quiz_t('footer_brand_desc', 'Helping businesses grow by implementing the best IT solutions.')); ?>
                </p>
                <?php
                // Social links — render only if real URLs are set in theme options
                $social_links = [
                    'facebook' => get_option('softmir_social_facebook', ''),
                    'linkedin' => get_option('softmir_social_linkedin', ''),
                    'telegram' => get_option('softmir_social_telegram', ''),
                ];
                $social_links = array_filter($social_links);
                if (!empty($social_links)): ?>
                    <div class="footer-social mt-1">
                        <?php if (!empty($social_links['telegram'])): ?>
                            <a href="<?php echo esc_url($social_links['telegram']); ?>" target="_blank" rel="noopener"
                                aria-label="Telegram">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['facebook'])): ?>
                            <a href="<?php echo esc_url($social_links['facebook']); ?>" target="_blank" rel="noopener"
                                aria-label="Facebook">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['linkedin'])): ?>
                            <a href="<?php echo esc_url($social_links['linkedin']); ?>" target="_blank" rel="noopener"
                                aria-label="LinkedIn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2zM4 2a2 2 0 110 4 2 2 0 010-4z" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer-links">
                <h4><?php echo esc_html(softmir_quiz_t('footer_nav', 'Navigation')); ?></h4>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container' => false,
                    'fallback_cb' => function () {
                        echo '<ul>';
                        echo '<li><a href="' . home_url('/') . '">' . esc_html__('Home', 'softmir') . '</a></li>';
                        echo '<li><a href="' . get_post_type_archive_link('software') . '">' . esc_html__('Software Catalog', 'softmir') . '</a></li>';
                        echo '<li><a href="' . home_url('/about/') . '">' . esc_html__('About Us', 'softmir') . '</a></li>';
                        echo '<li><a href="' . home_url('/contacts/') . '">' . esc_html__('Contacts', 'softmir') . '</a></li>';
                        echo '</ul>';
                    },
                ]);
                ?>
            </div>

            <div class="footer-links">
                <h4><?php echo esc_html(softmir_quiz_t('footer_info', 'Information')); ?></h4>
                <ul>
                    <?php
                    $privacy_page = get_page_by_path('privacy-policy');
                    $terms_page = get_page_by_path('terms-of-use');
                    $cookie_page = get_page_by_path('cookie-policy');

                    $p_id = $privacy_page ? (function_exists('pll_get_post') ? pll_get_post($privacy_page->ID) : $privacy_page->ID) : 0;
                    $t_id = $terms_page ? (function_exists('pll_get_post') ? pll_get_post($terms_page->ID) : $terms_page->ID) : 0;
                    $c_id = $cookie_page ? (function_exists('pll_get_post') ? pll_get_post($cookie_page->ID) : $cookie_page->ID) : 0;
                    ?>
                    <li><a href="<?php echo $p_id ? esc_url(get_permalink($p_id)) : '#'; ?>">
                            <?php esc_html_e('Privacy Policy', 'softmir'); ?>
                        </a></li>
                    <li><a href="<?php echo $t_id ? esc_url(get_permalink($t_id)) : '#'; ?>">
                            <?php esc_html_e('Terms of Use', 'softmir'); ?>
                        </a></li>
                    <li><a href="<?php echo $c_id ? esc_url(get_permalink($c_id)) : '#'; ?>">
                            <?php esc_html_e('Cookies & Affiliate', 'softmir'); ?>
                        </a></li>
                </ul>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> SoftZor —
                <?php echo esc_html(softmir_quiz_t('footer_copyright', 'Business Software Catalog. All rights reserved.')); ?>
            </p>
            <p style="font-size: 0.8em; opacity: 0.7; margin-top: 5px;">
                <?php echo esc_html(softmir_quiz_t('footer_disclaimer', 'Some links on the site are affiliate links. We may receive a commission from vendors at no additional cost to you.')); ?>
            </p>
        </div>
    </div>
</footer>

<?php get_template_part('template-parts/compare-floating-bar'); ?>
<?php get_template_part('template-parts/group-buying-modal'); ?>
<?php get_template_part('template-parts/partner-modal'); ?>

<?php wp_footer(); ?>
</body>

</html>