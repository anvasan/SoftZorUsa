<?php
/**
 * Single Post Template
 * Full-featured blog post with breadcrumbs, ToC, sidebar, related posts, sharing
 */
get_header();

while (have_posts()):
    the_post();

    $post_id = get_the_ID();
    $categories = get_the_category();
    $first_cat = !empty($categories) ? $categories[0] : null;

    // Reading time
    $content = get_the_content();
    $word_count = str_word_count(wp_strip_all_tags($content));
    $reading_time = max(1, ceil($word_count / 200));

    // Build ToC from headings
    $toc_items = [];
    $content_with_ids = preg_replace_callback(
        '/<h([23])([^>]*)>(.*?)<\/h\1>/i',
        function ($m) use (&$toc_items) {
            $level = $m[1];
            $attrs = $m[2];
            $text = wp_strip_all_tags($m[3]);
            $id = sanitize_title($text);
            $toc_items[] = ['level' => $level, 'text' => $text, 'id' => $id];
            return '<h' . $level . $attrs . ' id="' . esc_attr($id) . '">' . $m[3] . '</h' . $level . '>';
        },
        apply_filters('the_content', $content)
    );
    ?>

    <main id="primary" class="site-main">

        <!-- Breadcrumbs -->
        <div class="container">
            <div class="breadcrumbs">
                <a href="<?php echo home_url('/'); ?>"><?php esc_html_e('Home', 'softmir'); ?></a>
                <span class="sep">›</span>
                <a
                    href="<?php echo get_permalink(get_option('page_for_posts')); ?>"><?php esc_html_e('Blog', 'softmir'); ?></a>
                <?php if ($first_cat): ?>
                    <span class="sep">›</span>
                    <a href="<?php echo get_category_link($first_cat); ?>"><?php echo esc_html($first_cat->name); ?></a>
                <?php endif; ?>
                <span class="sep">›</span>
                <span><?php echo softmir_truncate(get_the_title(), 50); ?></span>
            </div>
        </div>

        <div class="container">
            <div class="single-post-layout">

                <!-- Main Content -->
                <article class="single-post-main">

                    <!-- Post Header -->
                    <header class="post-header">
                        <?php if ($first_cat): ?>
                            <a href="<?php echo get_category_link($first_cat); ?>" class="post-category-badge">
                                <?php echo esc_html($first_cat->name); ?>
                            </a>
                        <?php endif; ?>

                        <h1 class="post-title"><?php the_title(); ?></h1>

                        <div class="post-meta">
                            <?php $author_avatar = get_avatar_url(get_the_author_meta('ID'), ['size' => 36]); ?>
                            <div class="post-meta-author">
                                <img src="<?php echo esc_url($author_avatar); ?>"
                                    alt="<?php echo esc_attr(get_the_author()); ?>"
                                    title="<?php echo esc_attr(get_the_author()); ?>" class="post-meta-avatar">
                                <span><?php the_author(); ?></span>
                            </div>
                            <span class="post-meta-sep">·</span>
                            <span class="post-meta-date">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"
                                    height="14">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <?php echo get_the_date(); ?>
                            </span>
                            <span class="post-meta-sep">·</span>
                            <span class="post-meta-reading">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"
                                    height="14">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                <?php printf(esc_html__('%d min чтения', 'softmir'), $reading_time); ?>
                            </span>
                            <span class="post-meta-sep">·</span>
                            <span class="post-meta-views">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"
                                    height="14" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <?php printf(esc_html__('%s views', 'softmir'), number_format_i18n(softmir_get_views())); ?>
                            </span>
                        </div>
                    </header>

                    <!-- Featured Image -->
                    <?php if (has_post_thumbnail()): ?>
                        <div class="post-featured-wrap">
                            <img src="<?php echo get_the_post_thumbnail_url(null, 'large'); ?>"
                                alt="<?php the_title_attribute(); ?>" title="<?php the_title_attribute(); ?>"
                                class="post-featured-image" loading="lazy">
                        </div>
                    <?php endif; ?>

                    <!-- Post Content -->
                    <div class="entry-content">
                        <?php echo $content_with_ids; ?>
                    </div>

                    <!-- Tags -->
                    <?php
                    $tags = get_the_tags();
                    if ($tags): ?>
                        <div class="post-tags">
                            <?php foreach ($tags as $tag): ?>
                                <a href="<?php echo get_tag_link($tag); ?>"
                                    class="post-tag">#<?php echo esc_html($tag->name); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <span class="share-label"><?php esc_html_e('Share:', 'softmir'); ?></span>
                        <div class="share-links">
                            <a href="https://t.me/share/url?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>"
                                target="_blank" rel="noopener" class="share-btn share-btn--telegram" aria-label="Telegram">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                    <path
                                        d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                                </svg>
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>"
                                target="_blank" rel="noopener" class="share-btn share-btn--facebook" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>"
                                target="_blank" rel="noopener" class="share-btn share-btn--twitter" aria-label="Twitter">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                    <path
                                        d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                                </svg>
                            </a>
                            <button type="button" class="share-btn share-btn--copy"
                                aria-label="<?php esc_attr_e('Copy link', 'softmir'); ?>"
                                onclick="navigator.clipboard.writeText(window.location.href).then(()=>{this.classList.add('copied');setTimeout(()=>this.classList.remove('copied'),2000)})">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Post Navigation -->
                    <div class="post-nav">
                        <?php
                        $prev = get_previous_post();
                        $next = get_next_post();
                        ?>
                        <?php if ($prev): ?>
                            <a href="<?php echo get_permalink($prev); ?>" class="post-nav-link post-nav-prev">
                                <span class="post-nav-label">← <?php esc_html_e('Previous', 'softmir'); ?></span>
                                <span class="post-nav-title"><?php echo softmir_truncate($prev->post_title, 40); ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($next): ?>
                            <a href="<?php echo get_permalink($next); ?>" class="post-nav-link post-nav-next">
                                <span class="post-nav-label"><?php esc_html_e('Next', 'softmir'); ?> →</span>
                                <span class="post-nav-title"><?php echo softmir_truncate($next->post_title, 40); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Related Posts -->
                    <?php
                    $related_args = [
                        'post_type' => 'post',
                        'posts_per_page' => 3,
                        'post__not_in' => [$post_id],
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'lang' => '',
                    ];
                    if ($first_cat) {
                        $related_args['cat'] = $first_cat->term_id;
                    }
                    $related = new WP_Query($related_args);
                    if ($related->have_posts()):
                        ?>
                        <section class="related-posts">
                            <h2 class="related-posts-title"><?php esc_html_e('Related articles', 'softmir'); ?></h2>
                            <div class="related-posts-grid">
                                <?php while ($related->have_posts()):
                                    $related->the_post();
                                    get_template_part('template-parts/card', 'blog');
                                endwhile;
                                wp_reset_postdata(); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                </article>

                <!-- Sidebar -->
                <aside class="single-post-sidebar">

                    <!-- Table of Contents -->
                    <?php if (!empty($toc_items)): ?>
                        <div class="post-toc" id="post-toc">
                            <h3 class="post-toc-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"
                                    height="16">
                                    <line x1="8" y1="6" x2="21" y2="6" />
                                    <line x1="8" y1="12" x2="21" y2="12" />
                                    <line x1="8" y1="18" x2="21" y2="18" />
                                    <line x1="3" y1="6" x2="3.01" y2="6" />
                                    <line x1="3" y1="12" x2="3.01" y2="12" />
                                    <line x1="3" y1="18" x2="3.01" y2="18" />
                                </svg>
                                <?php esc_html_e('Contents', 'softmir'); ?>
                            </h3>
                            <nav class="post-toc-nav">
                                <ul>
                                    <?php foreach ($toc_items as $item): ?>
                                        <li class="toc-level-<?php echo $item['level']; ?>">
                                            <a
                                                href="#<?php echo esc_attr($item['id']); ?>"><?php echo esc_html($item['text']); ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>

                    <!-- Author Widget -->
                    <div class="blog-widget blog-widget--author">
                        <div class="author-widget-inner">
                            <?php $author_avatar_lg = get_avatar_url(get_the_author_meta('ID'), ['size' => 64]); ?>
                            <img src="<?php echo esc_url($author_avatar_lg); ?>"
                                alt="<?php echo esc_attr(get_the_author()); ?>"
                                title="<?php echo esc_attr(get_the_author()); ?>" class="author-widget-avatar">
                            <div>
                                <div class="author-widget-name"><?php the_author(); ?></div>
                                <div class="author-widget-bio">
                                    <?php echo esc_html(get_the_author_meta('description') ?: __('Article author', 'softmir')); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </aside>

            </div>
        </div>

    </main>

    <?php
endwhile;
get_footer();
?>