<?php
// Hero Module — uses ACF page fields (registered in acf-home-options.php)
$page_id = get_option('page_on_front');
$title = get_field('home_header_title', $page_id) ?: __('Find the perfect software for your business', 'softmir');
$text = get_field('home_header_subtitle', $page_id) ?: __('Catalog of proven CRM systems, project management tools, and e-commerce solutions.', 'softmir');
$btn_text = get_field('home_header_btn_text', $page_id) ?: __('Go to catalog', 'softmir');
$btn_link = get_field('home_header_btn_url', $page_id) ?: get_post_type_archive_link('software');
?>
<section class="hero">
    <div class="container">
        <div class="hero-row">
            <div class="hero-content">
                <h1><?php echo wp_kses_post($title); ?></h1>
                <p><?php echo esc_html($text); ?></p>
                <?php if ($btn_link): ?>
                    <a href="<?php echo esc_url($btn_link); ?>" class="btn btn-primary hero-btn-cta">
                        <?php echo esc_html($btn_text); ?> →
                    </a>
                <?php
endif; ?>
            </div>

            <div class="hero-quiz">
                <?php echo do_shortcode('[softmir_quiz]'); ?>
            </div>
        </div>
    </div>
</section>
