<?php
/**
 * Taxonomy Template: Software Category
 * 
 * We route this directly to the main archive-software.php template,
 * injecting the current category ID into the $_GET array so that
 * the existing filtering logic picks it up automatically.
 */

$term = get_queried_object();
if ($term && !is_wp_error($term)) {
    $_GET['sw_cat'] = $term->term_id;
}

// Load the main software archive template
require get_template_directory() . '/archive-software.php';
