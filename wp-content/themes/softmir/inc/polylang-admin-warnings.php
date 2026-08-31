<?php
/**
 * Polylang Admin Warnings and Trashing Sync
 *
 * Adds warning messages when trying to delete or trash posts/terms 
 * that have linked translations in other languages.
 * Automatically synchronizes trashing and deletion.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Hook into post/page row actions (List table)
add_filter('post_row_actions', 'softmir_pll_warn_row_actions', 10, 2);
add_filter('page_row_actions', 'softmir_pll_warn_row_actions', 10, 2);

function softmir_pll_warn_row_actions($actions, $post)
{
    if (!function_exists('pll_get_post_translations')) {
        return $actions;
    }

    $translations = pll_get_post_translations($post->ID);
    if (is_array($translations) && count($translations) > 1) {
        if (isset($actions['trash'])) {
            $actions['trash'] = str_replace('submitdelete', 'submitdelete pll-warn-trash', $actions['trash']);
        }
        if (isset($actions['delete'])) {
            $actions['delete'] = str_replace('submitdelete', 'submitdelete pll-warn-trash', $actions['delete']);
        }
    }
    return $actions;
}

// 2. Hook into term row actions (List table)
add_action('admin_init', 'softmir_pll_warn_term_init');
function softmir_pll_warn_term_init()
{
    $taxonomies = get_taxonomies(array('public' => true));
    foreach ($taxonomies as $tax) {
        add_filter("{$tax}_row_actions", 'softmir_pll_warn_term_row_actions', 10, 2);
    }
}

function softmir_pll_warn_term_row_actions($actions, $term)
{
    if (!function_exists('pll_get_term_translations')) {
        return $actions;
    }

    $translations = pll_get_term_translations($term->term_id);
    if (is_array($translations) && count($translations) > 1) {
        if (isset($actions['delete'])) {
            $actions['delete'] = str_replace('delete-tag', 'delete-tag pll-warn-trash', $actions['delete']);
        }
    }
    return $actions;
}

// 3. Output JavaScript to catch clicks on single edit screens and lists
add_action('admin_footer', 'softmir_pll_warn_admin_footer_js');
function softmir_pll_warn_admin_footer_js()
{
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, array('post', 'term', 'edit', 'edit-tags'))) {
        return;
    }

    $has_translations = false;

    if ($screen->base === 'post') {
        global $post;
        if ($post && function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($post->ID);
            if (is_array($translations) && count($translations) > 1) {
                $has_translations = true;
            }
        }
    } elseif ($screen->base === 'term') {
        $term_id = isset($_GET['tag_ID']) ? intval($_GET['tag_ID']) : 0;
        if ($term_id && function_exists('pll_get_term_translations')) {
            $translations = pll_get_term_translations($term_id);
            if (is_array($translations) && count($translations) > 1) {
                $has_translations = true;
            }
        }
    }

    $msg_single = esc_js(__('Attention! This item has language translations. We will AUTOMATICALLY delete (to the trash) ALL associated cards. Continue?', 'softmir'));
    $msg_bulk = esc_js(__('Attention! You are trying to remove items, some of which have translations. THEY WILL ALSO BE DELETED. Are you sure?', 'softmir'));

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var msgSingle = '<?php echo $msg_single; ?>';
            var msgBulk = '<?php echo $msg_bulk; ?>';

            var hasTranslations = <?php echo $has_translations ? 'true' : 'false'; ?>;
            if (hasTranslations) {
                var mainDeleteLinks = document.querySelectorAll('#delete-action .submitdelete, #delete-link .delete');
                mainDeleteLinks.forEach(function (link) {
                    link.classList.add('pll-warn-trash');
                });
            }

            document.body.addEventListener('click', function (e) {
                var parentA = e.target.closest('a.pll-warn-trash');
                if (parentA) {
                    if (!confirm(msgSingle)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                }
            }, true); 

            var bulkActionCheck = function (e) {
                var select = this.id === 'doaction' ? document.getElementById('bulk-action-selector-top') : document.getElementById('bulk-action-selector-bottom');
                if (select && (select.value === 'trash' || select.value === 'delete')) {
                    var checkedBoxes = document.querySelectorAll('tbody .check-column input[type="checkbox"]:checked');
                    var hasWarn = false;
                    checkedBoxes.forEach(function (box) {
                        var tr = box.closest('tr');
                        if (tr && tr.querySelector('.pll-warn-trash')) {
                            hasWarn = true;
                        }
                    });
                    if (hasWarn) {
                        if (!confirm(msgBulk)) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                        }
                    }
                }
            };

            var bulkBtn = document.getElementById('doaction');
            var bulkBtn2 = document.getElementById('doaction2');
            if (bulkBtn) bulkBtn.addEventListener('click', bulkActionCheck, true);
            if (bulkBtn2) bulkBtn2.addEventListener('click', bulkActionCheck, true);
        });
    </script>
    <?php
}

// 4. Server-Side Automatic Synchronization of Deletions

// Trash post sync
add_action('wp_trash_post', 'softmir_pll_sync_trash_post');
function softmir_pll_sync_trash_post($post_id) {
    if (!function_exists('pll_get_post_translations')) return;
    
    static $trashing = false;
    if ($trashing) return;
    
    $trashing = true;
    
    $translations = pll_get_post_translations($post_id);
    if (is_array($translations)) {
        foreach ($translations as $lang => $trans_id) {
            if ($trans_id != $post_id && get_post_status($trans_id) !== 'trash') {
                wp_trash_post($trans_id);
            }
        }
    }
    
    $trashing = false;
}

// Untrash post sync
add_action('untrash_post', 'softmir_pll_sync_untrash_post');
function softmir_pll_sync_untrash_post($post_id) {
    if (!function_exists('pll_get_post_translations')) return;

    static $untrashing = false;
    if ($untrashing) return;

    $untrashing = true;
    
    $translations = pll_get_post_translations($post_id);
    if (is_array($translations)) {
        foreach ($translations as $lang => $trans_id) {
            if ($trans_id != $post_id && get_post_status($trans_id) === 'trash') {
                wp_untrash_post($trans_id);
            }
        }
    }
    
    $untrashing = false;
}

// Permanent delete post sync
add_action('before_delete_post', 'softmir_pll_sync_delete_post');
function softmir_pll_sync_delete_post($post_id) {
    if (!function_exists('pll_get_post_translations')) return;
    
    // WordPress calls before_delete_post when permanent deleting.
    static $deleting = false;
    if ($deleting) return;
    
    $deleting = true;
    
    $translations = pll_get_post_translations($post_id);
    if (is_array($translations)) {
        foreach ($translations as $lang => $trans_id) {
            if ($trans_id != $post_id && get_post_type($trans_id)) {
                wp_delete_post($trans_id, true);
            }
        }
    }
    
    $deleting = false;
}

// Delete term sync
add_action('pre_delete_term', 'softmir_pll_sync_delete_term', 10, 2);
function softmir_pll_sync_delete_term($term_id, $taxonomy) {
    if (!function_exists('pll_get_term_translations')) return;

    static $deleting_term = false;
    if ($deleting_term) return;
    
    $deleting_term = true;
    
    $translations = pll_get_term_translations($term_id);
    if (is_array($translations)) {
        foreach ($translations as $lang => $trans_id) {
            if ($trans_id != $term_id) {
                wp_delete_term($trans_id, $taxonomy);
            }
        }
    }
    
    $deleting_term = false;
}
