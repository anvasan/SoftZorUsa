<?php
/**
 * SoftMir — AJAX Catalog Filter
 * Handles AJAX requests for catalog filtering without page reload
 */

if (!defined('ABSPATH'))
    exit;

function softmir_ajax_filter_catalog()
{
    // Verify nonce
    check_ajax_referer('softmir_catalog_filter', 'nonce');

    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $current_cat = isset($_POST['sw_cat']) ? intval($_POST['sw_cat']) : 0;
    $search = isset($_POST['s_search']) ? sanitize_text_field($_POST['s_search']) : '';

    $args = [
        'post_type' => 'software',
        'posts_per_page' => 12,
        'paged' => $paged,
        'prioritize_partner' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    // Category filter — include children
    if ($current_cat > 0) {
        $term_ids = [$current_cat];
        $children = get_term_children($current_cat, 'software_category');
        if (!is_wp_error($children)) {
            $term_ids = array_merge($term_ids, $children);
        }
        $args['tax_query'] = [
            ['taxonomy' => 'software_category', 'field' => 'term_id', 'terms' => $term_ids],
        ];
    }

    // Search filter
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Attribute filters — parse from POST
    if (function_exists('softmir_get_filterable_attributes')) {
        $filterable = softmir_get_filterable_attributes();
        $meta_query = [];

        foreach ($filterable as $attr) {
            $param = 'sw_attr_' . $attr->ID;
            if (!isset($_POST[$param]) || empty($_POST[$param]))
                continue;

            $values = $_POST[$param];

            if (is_array($values)) {
                $sub_query = ['relation' => 'OR'];
                foreach ($values as $val) {
                    $sub_query[] = [
                        'key' => '_sw_attr_' . $attr->ID,
                        'value' => sanitize_text_field($val),
                        'compare' => 'LIKE',
                    ];
                }
                $meta_query[] = $sub_query;
            } else {
                $meta_query[] = [
                    'key' => '_sw_attr_' . $attr->ID,
                    'value' => sanitize_text_field($values),
                    'compare' => 'LIKE',
                ];
            }
        }

        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
        }
    }

    $software = new WP_Query($args);

    ob_start();

    if ($software->have_posts()):
        ?>
        <div id="catalog-cards-wrapper" class="catalog-cards-wrapper view-grid">

            <!-- GRID VIEW -->
            <div class="cards-grid software-grid catalog-grid-2col">
                <?php while ($software->have_posts()):
                    $software->the_post();
                    get_template_part('template-parts/card', 'software');
                endwhile; ?>
            </div>

            <!-- LIST VIEW -->
            <div class="cards-list" style="display:none;">
                <?php $software->rewind_posts();
                while ($software->have_posts()):
                    $software->the_post();
                    get_template_part('template-parts/card-software', 'horizontal');
                endwhile; ?>
            </div>

            <!-- TABLE VIEW -->
            <div style="overflow-x: auto; width: 100%; display: none;" class="cards-table">
                <table class="software-table" style="width: 100%; min-width: 800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('BY', 'softmir'); ?></th>
                            <th><?php esc_html_e('Category', 'softmir'); ?></th>
                            <th><?php esc_html_e('Rating', 'softmir'); ?></th>
                            <th><?php esc_html_e('Description', 'softmir'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $software->rewind_posts();
                        while ($software->have_posts()):
                            $software->the_post();
                            get_template_part('template-parts/card-software', 'table');
                        endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Pagination -->
        <div class="catalog-pagination">
            <?php
            // Construct proper base URL instead of admin-ajax.php
            $base_url = get_post_type_archive_link('software');
            if ($current_cat > 0) {
                $cat_link = get_term_link($current_cat, 'software_category');
                if (!is_wp_error($cat_link)) {
                    $base_url = $cat_link;
                }
            }

            global $wp_rewrite;
            if ($wp_rewrite->using_permalinks()) {
                $url_parts = explode('?', $base_url);
                $paginate_base = trailingslashit($url_parts[0]) . 'page/%#%/';
                if (isset($url_parts[1])) {
                    $paginate_base .= '?' . $url_parts[1];
                }
            } else {
                $paginate_base = add_query_arg('paged', '%#%', $base_url);
            }

            echo paginate_links([
                'base' => $paginate_base,
                'format' => '',
                'total' => $software->max_num_pages,
                'current' => $paged,
                'prev_text' => '← ' . __('Back', 'softmir'),
                'next_text' => __('Next', 'softmir') . ' →',
            ]);
            ?>
        </div>
        <?php
    else:
        ?>
        <div class="catalog-empty" style="text-align: center; padding: 40px 20px;">
            <?php if (!empty($search)): ?>
                <div class="catalog-empty-ai-cta"
                    style="max-width: 500px; margin: 0 auto; background: #f8f9fa; border-radius: 12px; padding: 30px; border: 1px solid #e2e8f0;">
                    <span style="font-size: 40px; display: block; margin-bottom: 15px;">🤖</span>
                    <h3 style="margin: 0 0 10px; font-size: 20px; color: #1e293b;">
                        <?php printf(esc_html__('We haven\'t added "%s" to the directory yet', 'softmir'), esc_html($search)); ?>
                    </h3>
                    <p style="margin: 0 0 20px; color: #64748b; font-size: 15px; line-height: 1.5;">
                        <?php esc_html_e('But our AI assistant (Scout) can find this product, analyze it and create a detailed report for you right now.', 'softmir'); ?>
                    </p>
                    <a href="<?php echo esc_url(add_query_arg('intent', urlencode($search), home_url('/'))); ?>#softmir-quiz"
                        style="display: inline-block; background: #3b82f6; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; transition: background 0.2s;">
                        <?php esc_html_e('🚀Search via AI (30 sec)', 'softmir'); ?>
                    </a>
                </div>
            <?php else: ?>
                <p class="catalog-empty__title"><?php esc_html_e('No programs found', 'softmir'); ?></p>
                <p class="catalog-empty__text"><?php esc_html_e('Try changing your filtering options', 'softmir'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    endif;
    wp_reset_postdata();

    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'found_posts' => $software->found_posts,
        'max_pages' => $software->max_num_pages,
        'current_page' => $paged,
    ]);
}
add_action('wp_ajax_softmir_filter_catalog', 'softmir_ajax_filter_catalog');
add_action('wp_ajax_nopriv_softmir_filter_catalog', 'softmir_ajax_filter_catalog');

/**
 * Filter to prioritize fully moderated cards (with affiliate links)
 * over newly scouted ones which only have generated external links.
 *
 * When a search query is active, exact title match ranks first,
 * followed by WordPress relevance score, then partner status.
 * When no search, partner status is primary and date is secondary.
 */
add_filter('posts_orderby', function ($orderby, $query) {
    if ($query->get('prioritize_partner')) {
        global $wpdb;
        $partner_clause = "(SELECT COUNT(*) FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = 'software_status' AND pm.meta_value = 'external_scout') ASC";

        if ($query->get('s')) {
            // Search active: exact title match first, then title contains,
            // then partner status, then date
            $search_term = esc_sql($wpdb->esc_like($query->get('s')));
            $new_orderby = "({$wpdb->posts}.post_title = '{$search_term}') DESC, ({$wpdb->posts}.post_title LIKE '%{$search_term}%') DESC, {$partner_clause}, {$wpdb->posts}.post_date DESC";
        } else {
            // No search: partner status first, then date
            $new_orderby = "{$partner_clause}, {$wpdb->posts}.post_date DESC";
        }
        return $new_orderby;
    }
    return $orderby;
}, 10, 2);

