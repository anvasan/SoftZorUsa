<?php
/**
 * Template Part: Software Card (Table Row)
 * Used in archive-software.php for table view mode
 */
$short_desc = get_field('short_description');
$logo = get_field('company_logo');
$is_featured = get_field('is_featured');
$terms = get_the_terms(get_the_ID(), 'software_category');
$category = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
$rating = 0;
$review_count = 0;
$post_id = get_the_ID();

if (function_exists('glsr_get_ratings')) {
    $ratings = glsr_get_ratings(['assigned_posts' => $post_id]);
    if ($ratings) {
        $rating = $ratings->average;
        $review_count = $ratings->reviews;
    }
}

$clicks = softmir_get_clicks($post_id);
?>
<tr class="table-card-row <?php echo $is_featured ? 'featured' : ''; ?>">
    <td class="table-cell-name">
        <div class="table-name-wrap">
            <?php if ($logo): ?>
                <a href="<?php the_permalink(); ?>">
                    <img src="<?php echo esc_url($logo); ?>" alt="<?php the_title_attribute(); ?>"
                        title="<?php the_title_attribute(); ?>" class="table-logo" loading="lazy">
                </a>
                <?php
            else: ?>
                <a href="<?php the_permalink(); ?>" class="table-logo table-logo-placeholder">
                    <?php echo mb_substr(get_the_title(), 0, 2); ?>
                </a>
                <?php
            endif; ?>
            <a href="<?php the_permalink(); ?>" class="table-title"><?php the_title(); ?></a>
        </div>
    </td>
    <td class="table-cell-cat">
        <?php if ($category): ?>
            <span class="card-category"><?php echo esc_html($category); ?></span>
            <?php
        endif; ?>
    </td>
    <td class="table-cell-rating">
        <?php echo softmir_stars($rating, 5); ?>
        <span class="review-count"><?php echo number_format($rating, 1); ?>
            (<?php echo intval($review_count); ?>)</span>
    </td>
    <td class="table-cell-desc">
        <?php
        $desc = $short_desc ? $short_desc : get_the_excerpt();
        echo esc_html(softmir_truncate($desc, 100));
        ?>
    </td>
    <td class="table-cell-clicks">
        <span class="card-click-counter card-click-counter--table" title="<?php esc_attr_e('Clicks', 'softmir'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <polyline points="10 17 15 12 10 7" />
                <line x1="15" y1="12" x2="3" y2="12" />
            </svg>
            <span class="click-count"
                data-post-id="<?php echo esc_attr($post_id); ?>"><?php echo intval($clicks); ?></span>
        </span>
    </td>
    <td class="table-cell-action">
        <a href="<?php the_permalink(); ?>" class="btn btn-outline btn-sm table-action-btn"
            data-track-click="<?php echo esc_attr($post_id); ?>"><?php esc_html_e('View details', 'softmir'); ?></a>
        <a href="#" class="btn btn-outline btn-sm btn-compare-toggle" data-id="<?php echo esc_attr($post_id); ?>"
            data-default-text="<?php esc_attr_e('+ Compare', 'softmir'); ?>"
            data-added-text="<?php esc_attr_e('✓ Added to compare', 'softmir'); ?>" class="table-action-btn">
            <?php esc_html_e('+ Compare', 'softmir'); ?>
        </a>
    </td>
</tr>