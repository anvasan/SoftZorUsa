<?php
/**
 * Theme Shortcodes
 */

if (!defined('ABSPATH'))
    exit;

/** /**
 * 1. Categories Grid Shortcode (List Style)
 * Usage: [softmir_categories count="12" title="Software Categories" view_all_text="View all" view_all_link="/categories/"]
 */
function softmir_sc_categories($atts)
{
    $atts = shortcode_atts([
        'count' => 15,
        'title' => __('Software Categories', 'softmir'),
        'view_all_text' => __('View all', 'softmir'),
        'view_all_link' => get_post_type_archive_link('software'), // Default to archive
    ], $atts);

    // Get categories
    $terms = softmir_pll_get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => false,
        'number' => intval($atts['count']),
        'parent' => 0,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    ob_start();
?>
    <section class="home-categories alignfull">
        <div class="container">
            <div class="categories-header">
                <h2 class="section-title mb-0"><?php echo esc_html($atts['title']); ?></h2>
                <?php if ($atts['view_all_text']): ?>
                    <a href="<?php echo esc_url($atts['view_all_link']); ?>" class="btn-view-all">
                        <?php echo esc_html($atts['view_all_text']); ?> ≡
                    </a>
                <?php
    endif; ?>
            </div>

            <div class="categories-list-grid">
                <?php foreach ($terms as $term):
        // Use query arg to stay on main archive with filter
        $link = add_query_arg('sw_cat', $term->term_id, get_post_type_archive_link('software'));
        // Count posts in this category AND all its children
        $count = $term->count;
        $child_ids = get_term_children($term->term_id, 'software_category');
        if (!is_wp_error($child_ids) && !empty($child_ids)) {
            foreach ($child_ids as $child_id) {
                $child_term = get_term($child_id, 'software_category');
                if ($child_term && !is_wp_error($child_term)) {
                    $count += $child_term->count;
                }
            }
        }
?>
                    <a href="<?php echo esc_url($link); ?>" class="cat-list-card">
                        <span class="cat-arrow">▶</span>
                        <span class="cat-name-wrap">
                            <span class="cat-name"><?php echo esc_html($term->name); ?></span>
                            <span class="cat-count-simple">(<?php echo $count; ?>)</span>
                        </span>
                    </a>
                <?php
    endforeach; ?>
            </div>
        </div>
    </section>

    <?php
    return ob_get_clean();
}
add_shortcode('softmir_categories', 'softmir_sc_categories');


/**
 * 2. Popular Products Shortcode
 * Usage: 
 * - Tabs: [softmir_popular_products title="Popular"]
 * - Grid: [softmir_popular_products category="crm" count="6"]
 */
function softmir_sc_popular_products($atts)
{
    $atts = shortcode_atts([
        'title' => '',
        'count' => 6,
        'category' => '', // If set, shows grid instead of tabs
    ], $atts);

    // MODE 1: Specific Category Grid (Mini Cards)
    if (!empty($atts['category'])) {
        $query_args = [
            'post_type' => 'software',
            'posts_per_page' => intval($atts['count']),
            'tax_query' => [
                [
                    'taxonomy' => 'software_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                ]
            ],
            // Order by Featured/Pinned first, then Rating/Date
            'orderby' => 'date',
            'order' => 'DESC',
            'lang' => '',
        ];

        $products = new WP_Query($query_args);

        if (!$products->have_posts()) {
            return ''; // Hide if empty
        }

        ob_start();
?>
        <div class="software-list-mini">
            <?php while ($products->have_posts()):
            $products->the_post(); ?>
                <?php get_template_part('template-parts/card-software-mini'); ?>
            <?php
        endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    // MODE 2: Tabs (Default) - Only if no category specified
    // Get 4 most populated categories for tabs
    $categories = softmir_pll_get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => true,
        'number' => 4,
        'orderby' => 'count',
        'order' => 'DESC',
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        return '';
    }

    ob_start();
?>
    <section class="home-popular alignfull">
        <div class="container">
            <?php if ($atts['title']): ?>
                <div class="section-header text-center">
                    <h2 class="section-title"><?php echo esc_html($atts['title']); ?></h2>
                </div>
            <?php
    endif; ?>

            <div class="popular-tabs-wrapper">
                <!-- Tabs Navigation -->
                <div class="popular-tabs-nav">
                    <?php foreach ($categories as $index => $cat): ?>
                        <button class="tab-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                                data-tab="tab-<?php echo $cat->term_id; ?>">
                            <?php echo esc_html($cat->name); ?>
                        </button>
                    <?php
    endforeach; ?>
                </div>

                <!-- Tabs Content -->
                <div class="popular-tabs-content">
                    <?php foreach ($categories as $index => $cat):
        // Query products for this category
        $products = new WP_Query([
            'post_type' => 'software',
            'posts_per_page' => $atts['count'],
            'lang' => '',
            'tax_query' => [
                [
                    'taxonomy' => 'software_category',
                    'field' => 'term_id',
                    'terms' => $cat->term_id
                ]
            ]
        ]);
?>
                        <div class="tab-pane <?php echo $index === 0 ? 'active fade-in' : ''; ?>"
                             id="tab-<?php echo $cat->term_id; ?>">
                            
                            <?php if ($products->have_posts()): ?>
                                <div class="software-grid">
                                    <?php while ($products->have_posts()):
                $products->the_post();
                // Simple card output (using part if possible, or inline)
                get_template_part('template-parts/card', 'software-horizontal');
            endwhile; ?>
                                </div>
                                <div class="tab-footer text-center mt-section">
                                    <a href="<?php echo get_term_link($cat); ?>" class="btn btn-outline">
                                        <?php printf(esc_html__('Show all in %s', 'softmir'), esc_html($cat->name)); ?>
                                    </a>
                                </div>
                            <?php
        else: ?>
                                <p class="text-center"><?php esc_html_e('No programs in this category yet.', 'softmir'); ?></p>
                            <?php
        endif;
        wp_reset_postdata(); ?>
                        </div>
                    <?php
    endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-pane');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active', 'fade-in'));

                // Add active to clicked
                tab.classList.add('active');
                
                // Show content
                const targetId = tab.getAttribute('data-tab');
                const targetContent = document.getElementById(targetId);
                if(targetContent) {
                    targetContent.classList.add('active', 'fade-in');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('softmir_popular_products', 'softmir_sc_popular_products');


// Testimonials removed by user request
