<?php
/**
 * Template Name: Front Page
 *
 * The template for displaying the front page.
 * Uses get_template_part for sections to ensure full width control.
 */

get_header();
?>

<main id="primary" class="site-main">

    <?php
    // Output Hero Section explicitly
    get_template_part('template-parts/builder/hero');
    ?>

    <div class="container search-bar-overlap">
        <?php get_template_part('template-parts/search', 'bar'); ?>
    </div>

    <?php
    // Output Categories Section
    get_template_part('template-parts/builder/categories');


    // Output Popular Products Section
    get_template_part('template-parts/builder/popular_products');

    // Output Hot Offers Section
    get_template_part('template-parts/builder/hot_offers');

    // Output Group Buying Promo
    get_template_part('template-parts/builder/group_buying');

    // Output Partnership CTA
    get_template_part('template-parts/builder/partnership');

    // Output Advantages Section
    get_template_part('template-parts/builder/advantages');

    // Output Testimonials Section
    get_template_part('template-parts/builder/testimonials');

    // Output Blog Section
    get_template_part('template-parts/builder/blog');

    // Output New Software Section (SEO indexing signal)
    get_template_part('template-parts/builder/new_software');

    // [REMOVED] CTA email collector — no protection, spam risk, no business value
// get_template_part('template-parts/builder/cta');
    ?>

</main>

<?php
get_footer();
