<?php
// New Software Module — displays the latest added software cards
$title = '🆕 ' . __('New software', 'softmir');

$products = new WP_Query([
    'post_type' => 'software',
    'posts_per_page' => 8,
    'orderby' => 'date',
    'order' => 'DESC',
    // Строгий фильтр: только карточки с заполненным logoом
    'meta_query' => [
        'relation' => 'AND',
        [
            'key'     => 'company_logo',
            'compare' => 'EXISTS',
        ],
        [
            'key'     => 'company_logo',
            'value'   => '',
            'compare' => '!=',
        ],
        [
            'key'     => 'company_logo',
            'value'   => '0',
            'compare' => '!=',
        ]
    ],
]);

if ($products->have_posts()):
    ?>
    <section class="section section-new-software">
        <div class="container">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                <h2 class="section-title mt-section" style="margin-bottom: 0;"><?php echo esc_html($title); ?></h2>
                <a href="<?php echo esc_url(get_post_type_archive_link('software')); ?>" class="btn-link" style="font-weight: 500;">
                    <?php esc_html_e('All software', 'softmir'); ?> →
                </a>
            </div>

            <div class="software-grid-horizontal">
                <?php while ($products->have_posts()):
                    $products->the_post();
                    get_template_part('template-parts/card', 'software-mini');
                endwhile;
                wp_reset_postdata(); ?>
            </div>
        </div>
    </section>
    <?php
endif;
