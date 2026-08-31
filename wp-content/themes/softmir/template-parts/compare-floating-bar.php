<?php
/**
 * Template part for the floating comparison bar
 */

$compare_page_id = function_exists('softmir_get_compare_page_id') ? softmir_get_compare_page_id() : 0;
// If the page doesn't exist yet, just link to home for safety or fallback
$compare_url = $compare_page_id ? get_permalink($compare_page_id) : home_url('/compare/');

?>
<div id="compare-floating-bar" class="compare-floating-bar" data-compare-url="<?php echo esc_url($compare_url); ?>">
    <div class="container compare-bar-inner">
        
        <div class="compare-bar-left">
            <span class="compare-title"><?php esc_html_e('Compare software:', 'softmir'); ?> <span class="compare-count">0</span>/4</span>
            <div id="compare-items-list" class="compare-items-list">
                <!-- AJAX injected titles will go here -->
            </div>
        </div>

        <div class="compare-bar-right">
            <a href="#" class="compare-clear-all"><?php esc_html_e('Clear', 'softmir'); ?></a>
            <a href="<?php echo esc_url($compare_url); ?>" class="btn btn-primary compare-link"><?php esc_html_e('Compare', 'softmir'); ?></a>
        </div>

    </div>
</div>
