<?php
// Categories Module — pure WP data, no ACF dependency
$title = '🔍 ' . __('Software Categories', 'softmir');
$subtitle = __('Choose a category of interest', 'softmir');

// Always fetch categories from the default language
// so all translations show the same set, just translated
$default_lang = function_exists('pll_default_language') ? pll_default_language() : '';
$current_lang = function_exists('pll_current_language') ? pll_current_language() : '';

$categories = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => true,
    'parent' => 0,
    'number' => 18,
    'orderby' => 'count',
    'order' => 'DESC',
    'lang' => $default_lang ?: '',
]);

// Translate term IDs to the current language
if (!empty($categories) && !is_wp_error($categories) && $current_lang && $current_lang !== $default_lang && function_exists('pll_get_term')) {
    $translated_cats = [];
    foreach ($categories as $cat) {
        $trans_id = pll_get_term($cat->term_id, $current_lang);
        if ($trans_id) {
            $trans_term = get_term($trans_id, 'software_category');
            if ($trans_term && !is_wp_error($trans_term)) {
                $translated_cats[] = $trans_term;
                continue;
            }
        }
        $translated_cats[] = $cat; // fallback to original
    }
    $categories = $translated_cats;
}
?>
<section class="section section-alt">
    <div class="container">
        <div class="categories-header">
            <div>
                <h2 class="section-title mb-05"><?php echo esc_html($title); ?></h2>
                <p class="section-subtitle mb-0"><?php echo esc_html($subtitle); ?></p>
            </div>
            <a href="<?php echo get_post_type_archive_link('software'); ?>"
                class="categories-more-link"><?php esc_html_e('All Categories', 'softmir'); ?> →</a>
        </div>

        <?php if ($categories && !is_wp_error($categories)): ?>
            <div class="categories-cards-grid" id="homepage-categories-grid">
                <?php foreach ($categories as $index => $cat):
                    // Скрываем служебные категории (AI Inbox) от публичных пользователей
                    if (strpos($cat->name, '[AI Inbox]') !== false) continue;
                    $cat_link = add_query_arg('sw_cat', $cat->term_id, get_post_type_archive_link('software'));
                    // Count posts in this category AND all its children
                    $total_count = $cat->count;
                    $child_ids = get_term_children($cat->term_id, 'software_category');
                    if (!is_wp_error($child_ids) && !empty($child_ids)) {
                        foreach ($child_ids as $child_id) {
                            $child_term = get_term($child_id, 'software_category');
                            if ($child_term && !is_wp_error($child_term)) {
                                $total_count += $child_term->count;
                            }
                        }
                    }
                    ?>
                    <a href="<?php echo esc_url($cat_link); ?>" class="category-card" data-index="<?php echo $index; ?>">
                        <span class="category-card-icon">▷</span>
                        <span class="category-card-title"><?php echo esc_html($cat->name); ?></span>
                        <span class="category-card-count">(<?php echo $total_count; ?>)</span>
                    </a>
                    <?php
                endforeach; ?>
            </div>

            <?php if (count($categories) > 3): ?>
                <div class="categories-mobile-toggle-wrapper">
                    <button type="button" class="btn btn-outline mobile-categories-toggle" id="toggle-homepage-categories">
                        <?php esc_html_e('Show all categories', 'softmir'); ?> ▾
                    </button>
                </div>
                <script>
                    (function() {
                        // Use event delegation in case the DOM is loaded via AJAX or builder
                        document.body.addEventListener('click', function(e) {
                            var toggleBtn = e.target.closest('.mobile-categories-toggle');
                            if (!toggleBtn) return;
                            
                            var wrapper = toggleBtn.closest('.categories-mobile-toggle-wrapper');
                            if (!wrapper) return;
                            
                            var grid = wrapper.previousElementSibling;
                            if (!grid || !grid.classList.contains('categories-cards-grid')) return;

                            grid.classList.toggle('expanded');
                            if (grid.classList.contains('expanded')) {
                                toggleBtn.innerHTML = '<?php echo esc_js(esc_html__('Collapse', 'softmir')); ?> ▴';
                            } else {
                                toggleBtn.innerHTML = '<?php echo esc_js(esc_html__('Show all categories', 'softmir')); ?> ▾';
                                grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        });
                    })();
                </script>
            <?php endif; ?>
            <?php
        endif; ?>
    </div>
</section>