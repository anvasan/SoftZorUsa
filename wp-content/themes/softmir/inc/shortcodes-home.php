<?php
/**
 * Homepage Shortcodes
 */

function softmir_hero_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/hero');
    return ob_get_clean();
}
add_shortcode('softmir_hero', 'softmir_hero_shortcode');

function softmir_categories_grid_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/categories');
    return ob_get_clean();
}
add_shortcode('softmir_categories_grid', 'softmir_categories_grid_shortcode');

function softmir_popular_products_block_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/popular_products');
    return ob_get_clean();
}
add_shortcode('softmir_popular_products_block', 'softmir_popular_products_block_shortcode');

function softmir_advantages_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/advantages');
    return ob_get_clean();
}
add_shortcode('softmir_advantages', 'softmir_advantages_shortcode');

function softmir_testimonials_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/testimonials');
    return ob_get_clean();
}
add_shortcode('softmir_testimonials', 'softmir_testimonials_shortcode');

function softmir_blog_posts_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/blog');
    return ob_get_clean();
}
add_shortcode('softmir_blog_posts', 'softmir_blog_posts_shortcode');

function softmir_cta_shortcode($atts)
{
    ob_start();
    get_template_part('template-parts/builder/cta');
    return ob_get_clean();
}
add_shortcode('softmir_cta', 'softmir_cta_shortcode');
