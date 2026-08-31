<?php
// Hero Module - uses ACF page fields (registered in acf-home-options.php)
$page_id = get_option('page_on_front');
$title = get_field('home_header_title', $page_id) ?: __('Proven software for business automation', 'softmir');
$text = get_field('home_header_subtitle', $page_id) ?: __('Your business can''t wait. Find effective CRM and ERP solutions ready for implementation today.', 'softmir');
$btn_text = get_field('home_header_btn_text', $page_id) ?: __('Go to catalog', 'softmir');
$btn_link = get_field('home_header_btn_url', $page_id) ?: get_post_type_archive_link('software');
$hero_image = get_field('home_header_bg', $page_id);
?>
<section class="hero">
    <div class="container">
        <div class="hero-row">
            <div class="hero-content">
                <h1><?php echo wp_kses_post($title); ?></h1>
                <p><?php echo esc_html($text); ?></p>
                <?php if ($btn_link): ?>
                    <a href="<?php echo esc_url($btn_link); ?>" class="btn btn-primary hero-btn-cta">
                        <?php echo esc_html($btn_text); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="hero-infographic" style="display: flex; justify-content: center; align-items: center;">
                <?php if ($hero_image): ?>
                    <img src="<?php echo esc_url($hero_image); ?>" alt="<?php echo esc_attr(strip_tags($title)); ?>" style="max-width: 100%; height: auto; border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.08);">
                <?php else: ?>
                    <!-- Placeholder if no image is uploaded -->
                    <div style="width: 100%; aspect-ratio: 4/3; background: #e2e8f0; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.2rem; border: 2px dashed #cbd5e1;">
                        <?php esc_html_e('Upload image in page settings', 'softmir'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
