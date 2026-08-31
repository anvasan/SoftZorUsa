<?php
/**
 * Template Part: Software Card (Horizontal / List View)
 * 3-part layout: Top (logo+title+rating+CTA) | Middle (attrs+desc) | Bottom (price+footer attrs)
 */
$is_featured = get_field('is_featured');
$short_desc = softmir_get_field_with_lang_fallback('short_description');
$logo = softmir_get_company_logo();
$price = softmir_get_field_with_lang_fallback('price_summary');
$website = get_field('website_url');
$is_affiliate = get_post_meta(get_the_ID(), 'is_affiliate', true);
if ($is_affiliate && $website) {
    $website = softmir_get_software_outbound_link(get_the_ID());
}
$terms = get_the_terms(get_the_ID(), 'software_category');

// Primary Category logic
$primary_cat_id = get_post_meta(get_the_ID(), 'primary_category', true);
$category = '';
if ($primary_cat_id) {
    $term_to_display = get_term($primary_cat_id, 'software_category');
    if ($term_to_display && !is_wp_error($term_to_display)) {
        $category = $term_to_display->name;
    }
} elseif ($terms && !is_wp_error($terms)) {
    $category = $terms[0]->name;
}
$post_id = get_the_ID();

$rating = 0;
$review_count = 0;
if (function_exists('glsr_get_ratings')) {
    $ratings = glsr_get_ratings(['assigned_posts' => $post_id]);
    if ($ratings && isset($ratings->average)) {
        $rating = round($ratings->average, 1);
        $review_count = $ratings->reviews;
    }
}
?>
<div class="list-card <?php echo $is_featured ? 'featured' : ''; ?>">

    <!-- ===== TOP: Logo + Title/Rating + CTA ===== -->
    <div class="list-card-top">
        <a href="<?php the_permalink(); ?>" class="list-card-logo-link">
            <?php if ($logo): ?>
                <img src="<?php echo esc_url($logo); ?>" alt="<?php the_title_attribute(); ?> logo"
                    class="list-card-logo" loading="lazy" width="120" height="80">
            <?php else: ?>
                <div class="list-card-logo-placeholder">
                    <?php echo mb_substr(get_the_title(), 0, 2); ?>
                </div>
            <?php endif; ?>
        </a>
        <div class="list-card-top-info">
            <h3 class="card-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            <?php if ($rating > 0): ?>
                <div class="card-rating">
                    <?php echo softmir_stars($rating); ?>
                    <span class="card-rating-text"><?php echo $rating; ?>
                        (<?php echo intval($review_count); ?>)</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="list-card-cta">
            <?php if ($website): ?>
                <a href="<?php echo esc_url($website); ?>" class="btn btn-primary btn-sm" target="_blank"
                    rel="noopener">
                    <?php esc_html_e('Visit website', 'softmir'); ?> ≫
                </a>
            <?php else: ?>
                <a href="<?php the_permalink(); ?>" class="btn btn-outline btn-sm">
                    <?php esc_html_e('Learn more', 'softmir'); ?> ≫
                </a>
            <?php endif; ?>

            <?php
            $discount_opt = get_field('discount_available', get_the_ID());
            if ($discount_opt === 'yes' || $discount_opt === 'trial'):
                global $wpdb;
                $gb_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(id) FROM {$wpdb->prefix}softzor_group_buying WHERE software_id = %d AND status = 'pending'",
                    get_the_ID()
                ));
            ?>
                <button type="button" class="btn btn-sm gb-cta-btn"
                    onclick="openGbModal(<?php echo get_the_ID(); ?>, '<?php echo esc_js(get_the_title()); ?>')">
                    🤝 <?php esc_html_e('Group buying', 'softmir'); ?> (<?php echo $gb_count; ?>)
                </button>
            <?php endif; ?>

            <a href="#" class="btn btn-outline btn-sm btn-compare-toggle"
                data-id="<?php echo esc_attr($post_id); ?>"
                data-default-text="<?php esc_attr_e('⇄ Compare', 'softmir'); ?>"
                data-added-text="<?php esc_attr_e('✓ Added to compare', 'softmir'); ?>">
                <?php esc_html_e('⇄ Compare', 'softmir'); ?>
            </a>
        </div>
    </div>

    <!-- ===== MIDDLE: Attrs + Description ===== -->
    <div class="list-card-middle">
        <?php
        if (function_exists('softmir_render_key_functions')) {
            echo softmir_render_key_functions($post_id, 2);
        }
        ?>
        <?php
        $desc_text = '';
        if ($short_desc) {
            $desc_text = $short_desc;
        } elseif (has_excerpt()) {
            $desc_text = get_the_excerpt();
        } else {
            $content = get_the_content();
            $desc_text = wp_strip_all_tags($content);
        }
        if ($desc_text): ?>
            <p class="list-card-desc"><?php echo esc_html(softmir_truncate($desc_text, 200)); ?></p>
        <?php endif; ?>
    </div>

    <?php
    $footer_html = softmir_render_attrs_block($post_id, '_attr_card_position', 'footer', 'list-card-attrs-footer');
    if ($footer_html || $price): ?>
        <!-- ===== BOTTOM: Footer attrs + Price ===== -->
        <div class="list-card-bottom">
            <?php if ($footer_html) echo $footer_html; ?>
            <?php if ($price): ?>
                <span class="list-card-price"><?php echo esc_html($price); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>