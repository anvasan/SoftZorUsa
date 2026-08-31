<?php
/**
 * Template Name: Comparison программ
 * 
 * Displays a side-by-side comparison of software based on ?ids=1,2,3 from URL.
 * If accessed without IDs, JS will try to fetch them from localStorage and reload.
 */

get_header();

// Get IDs from URL
$ids_raw = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
$ids = [];
if ($ids_raw) {
    $ids = array_map('intval', explode(',', $ids_raw));
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });
    // limit to 4 to prevent abuse or UI breaking
    $ids = array_slice($ids, 0, 4);
}

?>

<main class="site-main compare-page">
    <div class="container">

        <header class="page-header text-center compare-page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
            <?php if (empty($ids)): ?>
                <p class="page-subtitle"><?php esc_html_e('Loading list of programs to compare...', 'softmir'); ?></p>
            <?php else: ?>
                <p class="page-subtitle"><?php printf(esc_html__('Comparison %d программ', 'softmir'), count($ids)); ?></p>
            <?php endif; ?>
        </header>

        <div class="compare-content" id="compare-table-container">
            <?php
            if (empty($ids)) {
                // No IDs in URL. We use JS to check localStorage and redirect if found, or show empty state if not.
                ?>
                <div class="compare-empty-state text-center compare-empty-state--hidden" id="compare-empty-msg">
                    <p><?php esc_html_e('You haven\'t selected any programs for comparison yet.', 'softmir'); ?></p>
                    <a href="<?php echo get_post_type_archive_link('software'); ?>"
                        class="btn btn-primary mt-2"><?php esc_html_e('Go to catalog', 'softmir'); ?></a>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const stored = localStorage.getItem('softmir_compare_ids');
                        if (stored) {
                            try {
                                const localIds = JSON.parse(stored);
                                if (Array.isArray(localIds) && localIds.length > 0) {
                                    // Redirect to URL with IDs
                                    const newUrl = new URL(window.location.href);
                                    newUrl.searchParams.set('ids', localIds.join(','));
                                    window.location.replace(newUrl.toString());
                                    return;
                                }
                            } catch (e) { }
                        }
                        // If we are here, nothing is in local storage
                        document.querySelector('.page-subtitle').style.display = 'none';
                        document.getElementById('compare-empty-msg').style.display = 'block';
                    });
                </script>
                <?php
            } else {
                // We have IDs, let's fetch the posts
                $args = [
                    'post_type' => 'software',
                    'post__in' => $ids,
                    'orderby' => 'post__in', // Maintain the order passed in URL
                    'posts_per_page' => 4
                ];
                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    $softwares = $query->posts;

                    // 1. Gather all unique attributes assigned to THESE specific software
                    $all_attrs = softmir_get_attributes();
                    $used_attrs = [];

                    foreach ($all_attrs as $attr) {
                        $is_used = false;
                        foreach ($softwares as $sw) {
                            $val = softmir_get_software_attr_value($sw->ID, $attr->ID);
                            if (!empty($val)) {
                                $is_used = true;
                                break;
                            }
                        }
                        if ($is_used) {
                            $used_attrs[] = $attr;
                        }
                    }

                    // 2. Render Table
                    echo '<div class="compare-table-wrapper">';
                    echo '<table class="compare-table">';

                    // --- ROW 1: Headers (Logos & Titles) ---
                    echo '<tr>';
                    echo '<th></th>';
                    foreach ($softwares as $sw) {
                        $logo = get_field('company_logo', $sw->ID);
                        $permalink = get_permalink($sw->ID);
                        echo '<th>';
                        echo '<div class="compare-card-remove"><a href="#" data-id="' . esc_attr($sw->ID) . '" onclick="removeCompareItem(' . $sw->ID . '); return false;">' . esc_html__('Delete', 'softmir') . ' ✕</a></div>';

                        if ($logo) {
                            echo '<a href="' . esc_url($permalink) . '"><img src="' . esc_url($logo) . '" alt="' . esc_attr($sw->post_title) . '" class="compare-logo" loading="lazy"></a>';
                        } else {
                            echo '<div class="table-logo-placeholder">' . mb_substr($sw->post_title, 0, 2) . '</div>';
                        }

                        echo '<h3 class="compare-card-title"><a href="' . esc_url($permalink) . '" class="compare-card-title-link">' . esc_html($sw->post_title) . '</a></h3>';
                        echo '</th>';
                    }
                    echo '</tr>';

                    // --- ROW 2: Price Summary ---
                    echo '<tr>';
                    echo '<td>' . esc_html__('Price', 'softmir') . '</td>';
                    foreach ($softwares as $sw) {
                        $price = get_field('price_summary', $sw->ID);
                        echo '<td>' . ($price ? esc_html($price) : '—') . '</td>';
                    }
                    echo '</tr>';

                    // --- ROW 3: Rating ---
                    echo '<tr>';
                    echo '<td>' . esc_html__('Rating', 'softmir') . '</td>';
                    foreach ($softwares as $sw) {
                        $rating = 0;
                        if (function_exists('glsr_get_ratings')) {
                            $ratings = glsr_get_ratings(['assigned_posts' => $sw->ID]);
                            if ($ratings)
                                $rating = $ratings->average;
                        }
                        echo '<td>';
                        if ($rating > 0) {
                            echo softmir_stars($rating, 5) . '<br><span class="rating-sub">' . number_format($rating, 1) . ' / 5</span>';
                        } else {
                            echo '—';
                        }
                        echo '</td>';
                    }
                    echo '</tr>';

                    // --- ROW 4+: Dynamic Attributes ---
                    foreach ($used_attrs as $attr) {
                        $meta = softmir_get_attr_meta($attr->ID);
                        $icon = $meta['icon'] ? '<span class="attr-icon-inline">' . esc_html($meta['icon']) . '</span>' : '';

                        echo '<tr>';
                        echo '<td>' . $icon . esc_html($attr->post_title) . '</td>';

                        foreach ($softwares as $sw) {
                            $val = softmir_get_software_attr_value($sw->ID, $attr->ID);

                            $display_val = '—';
                            if (!empty($val)) {
                                if (is_array($val)) {
                                    $display_val = implode('<br>• ', $val);
                                    $display_val = '• ' . $display_val;
                                } elseif ($meta['type'] === 'checkbox' && $val == '1') {
                                    $display_val = '<span class="compare-yes">✓ ' . esc_html__('Yes', 'softmir') . '</span>';
                                } elseif ($meta['type'] === 'url') {
                                    $domain = parse_url($val, PHP_URL_HOST);
                                    $display_val = '<a href="' . esc_url($val) . '" target="_blank" rel="nofollow noopener">' . esc_html($domain ?: $val) . '</a>';
                                } else {
                                    $display_val = nl2br(esc_html($val));
                                }
                            }

                            echo '<td>' . $display_val . '</td>';
                        }
                        echo '</tr>';
                    }

                    // --- ROW LAST: Action Buttons ---
                    echo '<tr>';
                    echo '<td></td>';
                    foreach ($softwares as $sw) {
                        $website = get_field('website_url', $sw->ID);
                        echo '<td>';
                        if ($website) {
                            echo '<a href="' . esc_url($website) . '" target="_blank" rel="noopener nofollow" class="btn btn-primary btn-sm" style="display:inline-block; margin-bottom:10px;">' . esc_html__('Program website', 'softmir') . ' ≫</a><br>';
                        }
                        echo '<a href="' . get_permalink($sw->ID) . '" class="btn btn-outline btn-sm">' . esc_html__('Overview', 'softmir') . '</a>';
                        echo '</td>';
                    }
                    echo '</tr>';

                    echo '</table>';
                    echo '</div>'; // .compare-table-wrapper
            
                    ?>
                    <script>
                        function removeCompareItem(id) {
                            try {
                                let stored = localStorage.getItem('softmir_compare_ids');
                                if (stored) {
                                    let idsArr = JSON.parse(stored);
                                    idsArr = idsArr.filter(i => parseInt(i) !== parseInt(id));
                                    localStorage.setItem('softmir_compare_ids', JSON.stringify(idsArr));

                                    const newUrl = new URL(window.location.href);
                                    if (idsArr.length > 0) {
                                        newUrl.searchParams.set('ids', idsArr.join(','));
                                    } else {
                                        newUrl.searchParams.delete('ids');
                                    }
                                    window.location.replace(newUrl.toString());
                                }
                            } catch (e) { }
                        }
                    </script>
                    <?php

                } else {
                    echo '<div class="compare-empty-state"><p>' . esc_html__('No programs found for comparison.', 'softmir') . '</p><a href="' . get_post_type_archive_link('software') . '" class="btn btn-primary">' . esc_html__('Go to catalog', 'softmir') . '</a></div>';
                }
                wp_reset_postdata();
            }
            ?>
        </div>

    </div>
</main>

<?php get_footer(); ?>