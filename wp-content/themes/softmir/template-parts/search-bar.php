<?php
/**
 * Search Bar Component
 * 
 * Usage: get_template_part('template-parts/search', 'bar');
 * 
 * Displays:
 * 1. Search bar (category dropdown + keyword input + button)
 * 2. Breadcrumb path (Home / Category / Subcategory)
 * 3. Subcategories grid (when parent category is selected)
 */

$archive_url = get_post_type_archive_link('software');

// Current filters
$current_cat = isset($_GET['sw_cat']) ? intval($_GET['sw_cat']) : 0;
$current_search = isset($_GET['s_search']) ? sanitize_text_field($_GET['s_search']) : '';

// Get parent categories for dropdown
$parent_cats = softmir_pll_get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => true,
    'parent' => 0,
    'orderby' => 'name',
    'order' => 'ASC',
]);

// Determine active parent: if current_cat is a child, find its parent
$active_parent_id = 0;
$active_child_id = 0;
$current_term = null;

if ($current_cat > 0) {
    $current_term = get_term($current_cat, 'software_category');
    if ($current_term && !is_wp_error($current_term)) {
        if ($current_term->parent === 0) {
            // It's a parent category
            $active_parent_id = $current_term->term_id;
        }
        else {
            // It's a child category
            $active_child_id = $current_term->term_id;
            $active_parent_id = $current_term->parent;
        }
    }
}

// Get subcategories of active parent
$subcats = [];
if ($active_parent_id > 0) {
    $subcats = softmir_pll_get_terms([
        'taxonomy' => 'software_category',
        'hide_empty' => true,
        'parent' => $active_parent_id,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($subcats))
        $subcats = [];
}

$parent_term = $active_parent_id ? get_term($active_parent_id, 'software_category') : null;
?>

<div class="search-bar-wrapper">
    <form class="search-bar" method="get" action="<?php echo esc_url($archive_url); ?>">
        <div class="search-bar-field search-bar-category">
            <select name="sw_cat" class="search-bar-select" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('All Categories', 'softmir'); ?></option>
                <?php if ($parent_cats && !is_wp_error($parent_cats)): ?>
                    <?php foreach ($parent_cats as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>"
                            <?php selected($active_parent_id, $cat->term_id); ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php
    endforeach; ?>
                <?php
endif; ?>
            </select>
        </div>
        <div class="search-bar-field search-bar-keyword">
            <input type="text" name="s_search" class="search-bar-input" 
                   placeholder="<?php esc_attr_e('Search by name...', 'softmir'); ?>" 
                   value="<?php echo esc_attr($current_search); ?>">
        </div>
        <button type="submit" class="search-bar-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <?php esc_html_e('Search', 'softmir'); ?>
        </button>
    </form>

    <?php if ($active_parent_id > 0 && $parent_term && !is_wp_error($parent_term)): ?>
        <!-- Breadcrumbs -->
        <div class="search-breadcrumbs">
            <a href="<?php echo esc_url($archive_url); ?>">🏠 <?php esc_html_e('Home', 'softmir'); ?></a>
            <span class="sep">›</span>
            <?php if ($active_child_id > 0): ?>
                <a href="<?php echo esc_url(add_query_arg('sw_cat', $active_parent_id, $archive_url)); ?>">
                    <?php echo esc_html($parent_term->name); ?>
                </a>
                <span class="sep">›</span>
                <strong><?php echo esc_html($current_term->name); ?></strong>
            <?php
    else: ?>
                <strong><?php echo esc_html($parent_term->name); ?></strong>
            <?php
    endif; ?>
        </div>

        <!-- Subcategories Grid -->
        <?php if (!empty($subcats)): ?>
            <div class="search-subcats-grid">
                <?php foreach ($subcats as $sub):
            $sub_link = add_query_arg('sw_cat', $sub->term_id, $archive_url);
            $is_active = ($active_child_id === $sub->term_id);
?>
                    <a href="<?php echo esc_url($sub_link); ?>" 
                       class="search-subcat-item <?php echo $is_active ? 'active' : ''; ?>">
                        <span class="subcat-icon">⊕</span>
                        <?php echo esc_html($sub->name); ?>
                    </a>
                <?php
        endforeach; ?>
            </div>
        <?php
    endif; ?>
    <?php
endif; ?>
</div>
