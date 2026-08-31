<?php
/**
 * Template part for displaying a mini software card (e.g. in Popular Products accordion)
 */

$post_id = get_the_ID();
$logo_url = get_field('company_logo', $post_id);
$short_desc = get_field('short_description', $post_id);

// Get Rating from Site Reviews
$rating = 0;
$review_count = 0;
if (function_exists('glsr_get_ratings')) {
    $ratings = glsr_get_ratings(['assigned_posts' => get_the_ID()]);
    if ($ratings) {
        $rating = $ratings->average;
        $review_count = $ratings->reviews;
    }
}

// Fallback description
if (empty($short_desc)) {
    $short_desc = get_the_excerpt($post_id);
    if (empty($short_desc)) {
        // Use post content directly if excerpt is empty
        $post_obj = get_post($post_id);
        if ($post_obj && !empty(trim($post_obj->post_content))) {
            $short_desc = $post_obj->post_content;
        } else {
            // SUPER FALLBACK: Try to get content from the original post (Polylang)
            if (function_exists('pll_get_post') && function_exists('pll_default_language')) {
                $default_id = pll_get_post($post_id, pll_default_language());
                if ($default_id && $default_id != $post_id) {
                    $default_post = get_post($default_id);
                    if ($default_post && !empty(trim($default_post->post_content))) {
                        $short_desc = $default_post->post_content;
                    }
                }
            }
        }
    }
}

// Prepare display description (trim HTML and words)
$display_desc = wp_trim_words($short_desc, 25, '...');
$clicks = softmir_get_clicks($post_id);
?>

<a href="<?php the_permalink(); ?>" class="software-mini-card" data-track-click="<?php echo esc_attr($post_id); ?>">
    <div class="software-mini-logo">
        <?php if ($logo_url && is_string($logo_url) && strpos($logo_url, 'http') === 0): ?>
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php the_title_attribute(); ?>"
                title="<?php the_title_attribute(); ?>" loading="lazy">
            <?php
        else: ?>
            <div class="software-mini-placeholder">
                <?php echo mb_substr(get_the_title(), 0, 1); ?>
            </div>
            <?php
        endif; ?>
    </div>

    <div class="software-mini-content">
        <h3 class="software-mini-title"><?php the_title(); ?></h3>

        <div class="software-mini-rating">
            <?php if (function_exists('softmir_stars')): ?>
                <?php echo softmir_stars($rating, 5); ?>
                <?php
            else: ?>
                <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++)
                        echo $i <= round($rating) ? '★' : '<span class="empty">★</span>'; ?>
                </span>
                <?php
            endif; ?>

            <?php if ($review_count > 0): ?>
                <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                <?php
            else: ?>
                <span class="rating-value rating-value--empty">(0)</span>
                <?php
            endif; ?>
        </div>

        <div class="software-mini-desc">
            <?php echo esc_html($display_desc); ?>
        </div>

        <span class="card-click-counter card-click-counter--mini" title="<?php esc_attr_e('Clicks', 'softmir'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <polyline points="10 17 15 12 10 7" />
                <line x1="15" y1="12" x2="3" y2="12" />
            </svg>
            <span class="click-count"
                data-post-id="<?php echo esc_attr($post_id); ?>"><?php echo intval($clicks); ?></span>
        </span>
    </div>
</a>