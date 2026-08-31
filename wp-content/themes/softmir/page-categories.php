<?php
/**
 * Template Name: Карта категорий Software
 * Description: Полный перечень категорий и подкатегорий Software
 */
get_header();
?>

<div class="container">
    <div class="breadcrumbs">
        <a href="<?php echo home_url('/'); ?>"><?php esc_html_e('Home', 'softmir'); ?></a>
        <span class="sep">›</span>
        <?php esc_html_e('Categories Software', 'softmir'); ?>
    </div>
</div>

<div class="container">
    <div class="categories-page">
        <h1 class="categories-page-title"><?php esc_html_e('List of categories and subcategories', 'softmir'); ?></h1>

        <!-- Search -->
        <div class="categories-search-box">
            <input type="text" id="catSearch" class="categories-search-input" placeholder="🔍 <?php esc_attr_e('Search by categories...', 'softmir'); ?>" oninput="softmirFilterCats(this.value)">
        </div>

        <div class="categories-full-grid" id="catGrid">
            <?php
$parent_cats = get_terms([
    'taxonomy' => 'software_category',
    'hide_empty' => true,
    'parent' => 0,
    'orderby' => 'name',
    'order' => 'ASC',
]);

if ($parent_cats && !is_wp_error($parent_cats)):
    foreach ($parent_cats as $cat):
        $cat_link = add_query_arg('sw_cat', $cat->term_id, get_post_type_archive_link('software'));
        $children = get_terms([
            'taxonomy' => 'software_category',
            'hide_empty' => true,
            'parent' => $cat->term_id,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
?>
            <div class="category-block" data-name="<?php echo esc_attr(mb_strtolower($cat->name)); ?>">
                <h2 class="category-block-title">
                    <a href="<?php echo esc_url($cat_link); ?>">
                        <span class="category-icon">⊕</span>
                        <?php echo esc_html($cat->name); ?>
                    </a>
                </h2>
                <?php if ($children && !is_wp_error($children)): ?>
                <ul class="category-block-list">
                    <?php foreach ($children as $child):
                $child_link = add_query_arg('sw_cat', $child->term_id, get_post_type_archive_link('software'));
?>
                    <li data-name="<?php echo esc_attr(mb_strtolower($child->name)); ?>">
                        <a href="<?php echo esc_url($child_link); ?>"><?php echo esc_html($child->name); ?></a>
                    </li>
                    <?php
            endforeach; ?>
                </ul>
                <?php
        endif; ?>
            </div>
<?php
    endforeach;
endif;
?>
        </div>
    </div>
</div>

<script>
function softmirFilterCats(query) {
    var q = query.toLowerCase().trim();
    var blocks = document.querySelectorAll('#catGrid .category-block');
    blocks.forEach(function(block) {
        if (!q) {
            block.style.display = '';
            block.querySelectorAll('li').forEach(function(li) { li.style.display = ''; });
            return;
        }
        var parentMatch = block.getAttribute('data-name').indexOf(q) !== -1;
        var children = block.querySelectorAll('li');
        var childMatch = false;
        children.forEach(function(li) {
            if (li.getAttribute('data-name').indexOf(q) !== -1) {
                li.style.display = '';
                childMatch = true;
            } else {
                li.style.display = parentMatch ? '' : 'none';
            }
        });
        block.style.display = (parentMatch || childMatch) ? '' : 'none';
    });
}
</script>

<?php get_footer(); ?>
