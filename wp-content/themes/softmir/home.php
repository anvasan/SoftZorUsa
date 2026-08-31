<?php
/**
 * Blog Archive Template (home.php)
 * Displays the blog listing page with sidebar, pagination and hero
 */
get_header();

$paged = get_query_var('paged') ? get_query_var('paged') : 1;
?>

<main id="primary" class="site-main">

    <!-- Blog Hero -->
    <section class="blog-hero">
        <div class="container">
            <div class="breadcrumbs">
                <a href="<?php echo home_url('/'); ?>">
                    <?php esc_html_e('Home', 'softmir'); ?>
                </a>
                <span class="sep">›</span>
                <?php esc_html_e('Blog', 'softmir'); ?>
            </div>
            <h1 class="blog-hero-title">
                <?php esc_html_e('Blog', 'softmir'); ?>
            </h1>
            <p class="blog-hero-subtitle">
                <?php esc_html_e('Полезные статьи, обзоры и руководства по бизнес-Software', 'softmir'); ?>
            </p>
        </div>
    </section>

    <div class="container">
        <div class="blog-archive-layout">

            <!-- Main Content -->
            <div class="blog-archive-main">
                <?php if (have_posts()): ?>
                    <div class="blog-grid">
                        <?php while (have_posts()):
                            the_post();
                            get_template_part('template-parts/card', 'blog');
                        endwhile; ?>
                    </div>

                    <!-- Pagination -->
                    <div class="blog-pagination">
                        <?php
                        echo paginate_links([
                            'prev_text' => '← ' . __('Prev', 'softmir'),
                            'next_text' => __('Next', 'softmir') . ' →',
                            'mid_size' => 2,
                            'type' => 'list',
                        ]);
                        ?>
                    </div>
                <?php else: ?>
                    <div class="blog-empty">
                        <div class="blog-empty-icon">📝</div>
                        <h2>
                            <?php esc_html_e('No articles yet', 'softmir'); ?>
                        </h2>
                        <p>
                            <?php esc_html_e('Interesting materials about business software will appear here soon.', 'softmir'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="blog-sidebar">

                <!-- Search Widget -->
                <div class="blog-widget">
                    <h3 class="blog-widget-title">
                        <?php esc_html_e('Search', 'softmir'); ?>
                    </h3>
                    <form role="search" method="get" action="<?php echo home_url('/'); ?>" class="blog-search-form">
                        <input type="hidden" name="post_type" value="post">
                        <div class="blog-search-input-wrap">
                            <input type="text" name="s"
                                placeholder="<?php esc_attr_e('Search blog...', 'softmir'); ?>"
                                value="<?php echo get_search_query(); ?>" class="blog-search-input">
                            <button type="submit" class="blog-search-btn"
                                aria-label="<?php esc_attr_e('Andскать', 'softmir'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"
                                    height="18">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Categories Widget -->
                <?php
                $blog_cats = get_categories([
                    'hide_empty' => true,
                    'orderby' => 'count',
                    'order' => 'DESC',
                    'number' => 10,
                ]);
                if ($blog_cats): ?>
                    <div class="blog-widget">
                        <h3 class="blog-widget-title">
                            <?php esc_html_e('Categories', 'softmir'); ?>
                        </h3>
                        <ul class="blog-cat-list">
                            <?php foreach ($blog_cats as $cat): ?>
                                <li>
                                    <a href="<?php echo get_category_link($cat); ?>">
                                        <span class="blog-cat-name">
                                            <?php echo esc_html($cat->name); ?>
                                        </span>
                                        <span class="blog-cat-count">
                                            <?php echo $cat->count; ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Popular Posts Widget -->
                <?php
                $popular = new WP_Query([
                    'post_type' => 'post',
                    'posts_per_page' => 5,
                    'meta_key' => 'softmir_views',
                    'orderby' => 'meta_value_num',
                    'order' => 'DESC',
                    'ignore_sticky_posts' => true,
                ]);
                if ($popular->have_posts()): ?>
                    <div class="blog-widget">
                        <h3 class="blog-widget-title">
                            <?php esc_html_e('Popular articles', 'softmir'); ?>
                        </h3>
                        <div class="blog-popular-list">
                            <?php while ($popular->have_posts()):
                                $popular->the_post(); ?>
                                <a href="<?php the_permalink(); ?>" class="blog-popular-item">
                                    <?php if (has_post_thumbnail()): ?>
                                        <img src="<?php echo get_the_post_thumbnail_url(null, 'thumbnail'); ?>"
                                            alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>"
                                            class="blog-popular-thumb" loading="lazy">
                                    <?php else: ?>
                                        <div class="blog-popular-thumb blog-popular-thumb--placeholder">📄</div>
                                    <?php endif; ?>
                                    <div class="blog-popular-info">
                                        <span class="blog-popular-title">
                                            <?php the_title(); ?>
                                        </span>
                                        <span class="blog-popular-date">
                                            <?php echo get_the_date(); ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endwhile;
                            wp_reset_postdata(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tags Widget -->
                <?php
                $tags = get_tags(['number' => 20, 'orderby' => 'count', 'order' => 'DESC']);
                if ($tags): ?>
                    <div class="blog-widget">
                        <h3 class="blog-widget-title">
                            <?php esc_html_e('Tags', 'softmir'); ?>
                        </h3>
                        <div class="blog-tag-cloud">
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?php echo get_tag_link($tag); ?>" class="blog-tag">
                                    #
                                    <?php echo esc_html($tag->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>

        </div>
    </div>

</main>

<?php get_footer(); ?>