<?php
/**
 * Blog Card Template Part
 * Reusable card for blog archive and related posts
 */
$post_id = get_the_ID();
$categories = get_the_category();
$first_cat = !empty($categories) ? $categories[0] : null;

// Reading time estimate
$content = get_the_content();
$word_count = str_word_count(wp_strip_all_tags($content));
$reading_time = max(1, ceil($word_count / 200));
?>
<article class="blog-card" id="blog-card-<?php echo $post_id; ?>">
    <a href="<?php the_permalink(); ?>" class="blog-card-image-link">
        <?php if (has_post_thumbnail()): ?>
            <img src="<?php echo get_the_post_thumbnail_url(null, 'medium_large'); ?>" alt="<?php the_title_attribute(); ?>"
                title="<?php the_title_attribute(); ?>" class="blog-card-image" loading="lazy">
        <?php else: ?>
            <div class="blog-card-image blog-card-image--placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
                    <path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" />
                </svg>
            </div>
        <?php endif; ?>
        <?php if ($first_cat): ?>
            <span class="blog-card-badge">
                <?php echo esc_html($first_cat->name); ?>
            </span>
        <?php endif; ?>
    </a>
    <div class="blog-card-body">
        <div class="blog-card-meta">
            <span class="blog-card-date">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <?php echo get_the_date(); ?>
            </span>
            <span class="blog-card-reading-time">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                <?php printf(esc_html__('%d min', 'softmir'), $reading_time); ?>
            </span>
        </div>
        <h3 class="blog-card-title"><a href="<?php the_permalink(); ?>">
                <?php the_title(); ?>
            </a></h3>
        <p class="blog-card-excerpt">
            <?php echo get_the_excerpt(); ?>
        </p>
    </div>
</article>