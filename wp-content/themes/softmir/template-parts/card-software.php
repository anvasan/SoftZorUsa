<?php
/**
 * Template Part: Software Card (Grid View)
 * 3-part layout matching list view: Top (logo+title+rating) | Middle (attrs+desc) | Bottom (footer attrs + CTA)
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
<div class="software-card <?php echo $is_featured ? 'featured' : ''; ?>">

    <!-- Compare toggle icon -->
    <a href="#" class="card-compare-icon btn-compare-toggle"
        data-id="<?php echo esc_attr($post_id); ?>"
        data-default-text="⇄"
        data-added-text="✓"
        title="<?php esc_attr_e('Add to compare', 'softmir'); ?>">⇄</a>

    <!-- ===== TOP: Logo + Category + Title + Rating ===== -->
    <div class="card-header">
        <?php if ($is_featured): ?>
            <span class="card-badge">⭐ TOP</span>
            <?php
        endif; ?>
        <?php if ($logo): ?>
            <a href="<?php the_permalink(); ?>">
                <img src="<?php echo esc_url($logo); ?>" alt="<?php the_title_attribute(); ?> logo"
                    title="<?php the_title_attribute(); ?> logo" class="card-logo" loading="lazy" width="120"
                    height="80">
            </a>
            <?php
        else: ?>
            <a href="<?php the_permalink(); ?>" class="card-logo card-logo-placeholder">
                <?php echo mb_substr(get_the_title(), 0, 2); ?>
            </a>
            <?php
        endif; ?>
        <div class="card-header-info">
            <?php if ($category): ?>
                <span class="card-category"><?php echo esc_html($category); ?></span>
                <?php
            endif; ?>
            <h3 class="card-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            <?php if ($rating > 0): ?>
                <div class="card-rating">
                    <?php echo softmir_stars($rating); ?>
                    <span class="card-rating-text"><?php echo $rating; ?> (<?php echo intval($review_count); ?>)</span>
                </div>
                <?php
            endif; ?>
        </div>
    </div>

    <!-- ===== MIDDLE: Attrs (middle) + Description ===== -->
    <div class="card-middle">
        <?php
        if (function_exists('softmir_render_key_functions') && empty($GLOBALS['softmir_similar_card'])) {
            echo softmir_render_key_functions($post_id);
        }
        ?>
        <?php
        $desc_limit = empty($GLOBALS['softmir_similar_card']) ? 130 : 200;
        $card_desc = '';
        if ($short_desc) {
            $card_desc = softmir_truncate($short_desc, $desc_limit);
        } elseif (has_excerpt()) {
            $card_desc = softmir_truncate(get_the_excerpt(), $desc_limit);
        } else {
            $content = get_the_content();
            $card_desc = softmir_truncate(wp_strip_all_tags($content), $desc_limit);
        }
        if ($card_desc): ?>
            <p class="card-description"><?php echo esc_html($card_desc); ?></p>
        <?php endif; ?>
    </div>

    <!-- ===== BOTTOM: Footer attrs + CTA ===== -->
    <div class="card-bottom">
        <?php
        if (empty($GLOBALS['softmir_similar_card'])):
            $footer_html = softmir_render_attrs_block($post_id, '_attr_card_position', 'footer', 'card-attrs-footer');
            if ($footer_html):
                echo $footer_html;
            endif;
        endif;
        ?>
    </div>

    <div class="card-footer" style="flex-direction:column; align-items:stretch;">
        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
            <?php if ($price && empty($GLOBALS['softmir_similar_card'])): ?>
                <span class="card-price"><?php echo esc_html($price); ?></span>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($website): ?>
                <a href="<?php echo esc_url($website); ?>" class="btn btn-primary btn-sm card-cta-btn" target="_blank"
                    rel="noopener">
                    <?php esc_html_e('Visit website', 'softmir'); ?>
                </a>
            <?php else: ?>
                <a href="<?php the_permalink(); ?>" class="btn btn-primary btn-sm card-cta-btn">
                    <?php esc_html_e('View details', 'softmir'); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php
        $gb_count = softmir_get_gb_count(get_the_ID());
        ?>
        <div
            style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-top: 10px;">
            <button type="button" class="btn btn-sm card-cta-btn gb-cta-btn"
                title="<?php esc_attr_e('Leave a request — we will negotiate an exclusive price with the developer.', 'softmir'); ?>"
                onclick="openGbModal(<?php echo get_the_ID(); ?>, '<?php echo esc_js(get_the_title()); ?>')"
                style="width: 100%;">
                🤝 <?php esc_html_e('Group buying', 'softmir'); ?> (<?php echo $gb_count; ?>)
            </button>
        </div>
    </div>
</div>