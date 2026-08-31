<?php
// Hero Module — uses ACF page fields (registered in acf-home-options.php)
$page_id = get_option('page_on_front');
$title = get_field('home_header_title', $page_id) ?: __('Proven software for business automation', 'softmir');
$text = get_field('home_header_subtitle', $page_id) ?: __('Your business can\'t wait. Find effective CRM and ERP solutions ready for implementation today.', 'softmir');
$btn_text = get_field('home_header_btn_text', $page_id) ?: __('Go to catalog', 'softmir');
$btn_link = get_field('home_header_btn_url', $page_id) ?: get_post_type_archive_link('software');
?>
<section class="hero" style="text-align: center;">
    <div class="container">
        <div class="hero-row" style="justify-content: center;">
            <div class="hero-content" style="max-width: 800px; margin: 0 auto;">
                <h1><?php echo wp_kses_post($title); ?></h1>
                <p style="font-size: 1.25rem; color: #374151; font-weight: 400; max-width: 600px; margin: 1rem auto 2rem auto;"><?php echo esc_html($text); ?></p>
                <?php if ($btn_link): ?>
                    <a href="<?php echo esc_url($btn_link); ?>" class="btn btn-primary hero-btn-cta">
                        <?php echo esc_html($btn_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
