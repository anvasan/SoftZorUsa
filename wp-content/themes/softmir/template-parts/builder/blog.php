<?php
// Blog Module — latest posts, no ACF dependency
$title = __('Latest articles', 'softmir');
$subtitle = __('Useful resources about business software', 'softmir');

$blog = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => 3,
    'orderby' => 'date',
    'order' => 'DESC',
]);
?>
<section class="section section-gray">
    <div class="container">
        <h2 class="section-title"><?php echo esc_html($title); ?></h2>
        <p class="section-subtitle"><?php echo esc_html($subtitle); ?></p>

        <div class="blog-grid">
            <?php if ($blog->have_posts()):
                while ($blog->have_posts()):
                    $blog->the_post();
                    ?>
                    <article class="blog-card">
                        <?php if (has_post_thumbnail()): ?>
                            <img src="<?php echo get_the_post_thumbnail_url(null, 'medium_large'); ?>"
                                alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>"
                                class="blog-card-image" loading="lazy">
                            <?php
                        else: ?>
                            <div class="blog-card-image blog-card-image--placeholder">📝</div>
                            <?php
                        endif; ?>
                        <div class="blog-card-body">
                            <div class="blog-card-date"><?php echo get_the_date(); ?></div>
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <p class="blog-card-excerpt"><?php echo get_the_excerpt(); ?></p>
                        </div>
                    </article>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </div>
</section>