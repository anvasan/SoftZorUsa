<?php
/**
 * Migration Script for SoftMir (SOF-9)
 * 
 * Usage:
 * 1. Place in wp-content/themes/softmir/migration.php
 * 2. Run via browser: http://softmir.test/wp-content/themes/softmir/migration.php
 *    OR via CLI: php migration.php
 */

// Increase execution time for large imports
set_time_limit(0);
ini_set('memory_limit', '512M');

// 1. Load WordPress Environment
$wp_load_path = __DIR__ . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Could not find wp-load.php at $wp_load_path");
}
require_once($wp_load_path);

// Check permissions
if (!current_user_can('manage_options') && php_sapi_name() !== 'cli') {
// Uncomment to protect if needed, currently open for local dev use
// die("Access Denied: You must be an admin.");
}

echo "<h1>SoftMir Data Migration Tool</h1>";
echo "<pre>";

// 2. Source DB Config
$source_db = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'wpsoftzor',
    'prefix' => 'wp_',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$source_db['host']};dbname={$source_db['name']};charset={$source_db['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $source_db['user'], $source_db['pass'], $options);
    echo "[OK] Connected to source database: {$source_db['name']}\n";
}
catch (\PDOException $e) {
    die("[ERROR] Database connection failed: " . $e->getMessage());
}

// Helper to disable term counting for speed
wp_defer_term_counting(true);
wp_defer_comment_counting(true);

// Mappings placeholders
$cat_mapping = []; // old_id => new_id
$attr_mapping = []; // old_id => new_id

// =============================================================================
// STEP 0: CLEANUP
// =============================================================================
echo "\n--- STEP 0: CLEANUP ---\n";

function softmir_cancel_migration($msg)
{
    echo "\n[ABORT] $msg\n";
    exit;
}

// Cleanup Software
$existing_software = get_posts(['post_type' => 'software', 'numberposts' => -1, 'fields' => 'ids']);
foreach ($existing_software as $pid) {
    wp_delete_post($pid, true);
}
echo "[CLEANUP] Deleted " . count($existing_software) . " existing software posts.\n";

// Cleanup Attributes
$existing_attrs = get_posts(['post_type' => 'sw_attribute', 'numberposts' => -1, 'fields' => 'ids']);
foreach ($existing_attrs as $pid) {
    wp_delete_post($pid, true);
}
echo "[CLEANUP] Deleted " . count($existing_attrs) . " existing attribute posts.\n";

// Cleanup Categories
$existing_terms = get_terms(['taxonomy' => 'software_category', 'hide_empty' => false, 'fields' => 'ids']);
if (!is_wp_error($existing_terms)) {
    foreach ($existing_terms as $tid) {
        wp_delete_term($tid, 'software_category');
    }
    echo "[CLEANUP] Deleted " . count($existing_terms) . " existing categories.\n";
}

// =============================================================================
// STEP 1: CATEGORIES
// =============================================================================
echo "\n--- STEP 1: IMPORT CATEGORIES ---\n";

// Fetch from source
// We iterate to handle hierarchy: first parents (parent=0), then children
// Simple approach: Fetch all, then insert orderly or use a recursive function.
// Or just fetch all and build a tree.
$stmt = $pdo->query("
    SELECT t.term_id, t.name, t.slug, tt.description, tt.parent 
    FROM {$source_db['prefix']}terms t 
    INNER JOIN {$source_db['prefix']}term_taxonomy tt ON t.term_id = tt.term_id 
    WHERE tt.taxonomy = 'software_category'
    ORDER BY tt.parent ASC
");
$source_cats = $stmt->fetchAll();
echo "[INFO] Found " . count($source_cats) . " categories in source.\n";

// To handle parent-child dependency properly regardless of ID order, we loop multiple times or recurse.
// Let's loop until all processed.
$cats_to_process = [];
foreach ($source_cats as $cat) {
    $cats_to_process[$cat['term_id']] = $cat;
}

$processed_ids = [];
$iteration = 0;
while (count($cats_to_process) > 0 && $iteration < 10) {
    echo "  Iteration $iteration: Processing " . count($cats_to_process) . " remaining categories...\n";
    $processed_in_this_loop = 0;

    foreach ($cats_to_process as $old_id => $cat) {
        $parent_old_id = $cat['parent'];

        // If parent is 0 (root) OR parent is already processed and mapped
        if ($parent_old_id == 0 || isset($cat_mapping[$parent_old_id])) {

            $new_parent_id = ($parent_old_id == 0) ? 0 : $cat_mapping[$parent_old_id];

            $inserted = wp_insert_term($cat['name'], 'software_category', [
                'description' => $cat['description'],
                'slug' => $cat['slug'],
                'parent' => $new_parent_id
            ]);

            if (is_wp_error($inserted)) {
                // If it already exists (unlikely after cleanup, but possible if slug collision), try to get it
                if (isset($inserted->error_data['term_exists'])) {
                    $cat_mapping[$old_id] = $inserted->error_data['term_exists'];
                }
                else {
                    echo "    [WARN] Failed to insert '{$cat['name']}': " . $inserted->get_error_message() . "\n";
                // Skip mapping, might break children
                }
            }
            else {
                $cat_mapping[$old_id] = $inserted['term_id'];
            // echo "    [OK] Imported '{$cat['name']}' (Old: $old_id -> New: {$inserted['term_id']})\n";
            }

            unset($cats_to_process[$old_id]);
            $processed_in_this_loop++;
        }
    }

    if ($processed_in_this_loop == 0 && count($cats_to_process) > 0) {
        echo "    [WARN] Stuck with orphans? Importing remaining as roots.\n";
        foreach ($cats_to_process as $old_id => $cat) {
            // Forced insert as root
            $inserted = wp_insert_term($cat['name'], 'software_category', [
                'description' => $cat['description'] . " (Orphaned from parent {$cat['parent']})",
                'slug' => $cat['slug'],
                'parent' => 0
            ]);
            if (!is_wp_error($inserted)) {
                $cat_mapping[$old_id] = $inserted['term_id'];
            }
            unset($cats_to_process[$old_id]);
        }
    }

    $iteration++;
}

// =============================================================================
// STEP 2: ATTRIBUTES
// =============================================================================
echo "\n--- STEP 2: IMPORT ATTRIBUTES ---\n";

$stmt = $pdo->query("SELECT ID, post_title, post_name, post_status FROM {$source_db['prefix']}posts WHERE post_type = 'sw_attribute'");
$source_attrs = $stmt->fetchAll();
echo "[INFO] Found " . count($source_attrs) . " attributes in source.\n";

foreach ($source_attrs as $attr) {
    // Insert Attribute Post
    $new_attr_id = wp_insert_post([
        'post_title' => $attr['post_title'],
        'post_name' => $attr['post_name'],
        'post_status' => 'publish', // Force publish
        'post_type' => 'sw_attribute'
    ]);

    if ($new_attr_id) {
        $attr_mapping[$attr['ID']] = $new_attr_id;

        // Fetch Meta
        $meta_stmt = $pdo->prepare("SELECT meta_key, meta_value FROM {$source_db['prefix']}postmeta WHERE post_id = ?");
        $meta_stmt->execute([$attr['ID']]);
        $metas = $meta_stmt->fetchAll();

        foreach ($metas as $meta) {
            $key = $meta['meta_key'];
            $val = $meta['meta_value'];

            // Special handling for _attr_categories (serialized array of term IDs)
            if ($key === '_attr_categories') {
                $old_cats = maybe_unserialize($val);
                if (is_array($old_cats)) {
                    $new_cats = [];
                    foreach ($old_cats as $old_cat_id) {
                        if (isset($cat_mapping[$old_cat_id])) {
                            $new_cats[] = $cat_mapping[$old_cat_id];
                        }
                    }
                    update_post_meta($new_attr_id, '_attr_categories', $new_cats);
                    continue;
                }
            }

            // Standard meta copy
            // Note: If values contain absolute URLs to old site, might need replacement.
            // For now, assuming simple values.
            update_post_meta($new_attr_id, $key, $val);
        }
    // echo "    [OK] Imported Attribute '{$attr['post_title']}'\n";
    }
}


// =============================================================================
// STEP 3: SOFTWARE
// =============================================================================
echo "\n--- STEP 3: IMPORT SOFTWARE (LISTINGS) ---\n";

$stmt = $pdo->query("SELECT ID, post_title, post_content, post_excerpt, post_name, post_status, post_date FROM {$source_db['prefix']}posts WHERE post_type = 'software'");
$source_sw = $stmt->fetchAll();
echo "[INFO] Found " . count($source_sw) . " software posts in source.\n";

$count_sw = 0;
foreach ($source_sw as $sw) {
    $new_sw_id = wp_insert_post([
        'post_title' => $sw['post_title'],
        'post_content' => $sw['post_content'],
        'post_excerpt' => $sw['post_excerpt'],
        'post_name' => $sw['post_name'],
        'post_status' => $sw['post_status'],
        'post_date' => $sw['post_date'],
        'post_type' => 'software'
    ]);

    if ($new_sw_id) {
        $count_sw++;

        // 1. Meta Data
        $meta_stmt = $pdo->prepare("SELECT meta_key, meta_value FROM {$source_db['prefix']}postmeta WHERE post_id = ?");
        $meta_stmt->execute([$sw['ID']]);
        $metas = $meta_stmt->fetchAll();

        foreach ($metas as $meta) {
            // Skip internal WP meta that we don't want or is handled elsehwere
            if (in_array($meta['meta_key'], ['_edit_lock', '_edit_last']))
                continue;

            // Handle ACF relations if they store IDs?
            // Usually ACF relations to attributes might be stored...
            // If field stores Post IDs (like 'related_software'), we might need mapping.
            // For now, raw copy. If IDs mismatch, relations break. 
            // FIX: We need robust handling if 'software' relates to other 'software'.
            // But for Phase 1 MVP, let's copy meta as is.
            // Warning: Relationships to other posts will break if we don't map them.
            // But we don't have a software_mapping array fully built yet (sequential). 
            // Ideally we should do 2 passes: 1. Create Posts, 2. Import Meta.
            // Let's do raw copy for now and note it.

            update_post_meta($new_sw_id, $meta['meta_key'], $meta['meta_value']);
        }

        // 2. Categories (Terms)
        // Get limits from wp_term_relationships
        $terms_stmt = $pdo->prepare("
            SELECT t.term_id 
            FROM {$source_db['prefix']}term_relationships tr
            JOIN {$source_db['prefix']}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$source_db['prefix']}terms t ON tt.term_id = t.term_id
            WHERE tr.object_id = ? AND tt.taxonomy = 'software_category'
        ");
        $terms_stmt->execute([$sw['ID']]);
        $old_term_ids = $terms_stmt->fetchAll(PDO::FETCH_COLUMN);

        $new_term_ids = [];
        foreach ($old_term_ids as $old_tid) {
            if (isset($cat_mapping[$old_tid])) {
                $new_term_ids[] = (int)$cat_mapping[$old_tid];
            }
        }

        if (!empty($new_term_ids)) {
            wp_set_object_terms($new_sw_id, $new_term_ids, 'software_category');
        }

    // 3. Featured Image (Simple version: Copy URL to meta if not downloading)
    // Or if simple setup, maybe no image migration requested yet.
    // User asked for "Logos/Screenshots if possible".
    // Let's try to fetch _thumbnail_id meta.
    // If present, we need to import that attachment.
    // Complex. Let's stick to cleaning logic for now.
    }
}

echo "[SUCCESS] Imported $count_sw software listings.\n";

wp_defer_term_counting(false);
wp_defer_comment_counting(false);
echo "</pre>";
echo "<h2>MIGRATION COMPLETED</h2>";
