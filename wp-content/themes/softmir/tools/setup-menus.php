<?php
/**
 * Script to automatically generate and assign localized WordPress Menus
 * for the Primary and Footer locations across UA, RU, and EN languages.
 * 
 * Run with: php wp-content/themes/softmir/tools/setup-menus.php
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run via CLI.');
}

require_once dirname(__FILE__) . '/../../../../wp-load.php';

if (!function_exists('pll_set_term_language')) {
    die('Polylang is not active!' . PHP_EOL);
}

$languages = ['uk', 'ru', 'en'];

// Text definitions
$labels = [
    'uk' => ['home' => 'Головна', 'catalog' => 'Catalog ПО', 'blog' => 'Blog', 'about' => 'Про нас', 'contacts' => 'Контакти'],
    'ru' => ['home' => 'Home', 'catalog' => 'Catalog ПО', 'blog' => 'Blog', 'about' => 'About Us', 'contacts' => 'Contacts'],
    'en' => ['home' => 'Home', 'catalog' => 'Software Catalog', 'blog' => 'Blog', 'about' => 'About Us', 'contacts' => 'Contacts'],
];

$slugs = [
    'uk' => ['about' => 'about', 'contacts' => 'contacts'],
    'ru' => ['about' => 'ru-about', 'contacts' => 'ru-contacts'],
    'en' => ['about' => 'en-about', 'contacts' => 'en-contacts'],
];

// 1. Ensure basic pages exist so we can link to them.
foreach (['about', 'contacts'] as $type) {
    $created_ids = [];
    foreach ($languages as $lang) {
        $slug = $slugs[$lang][$type];
        $title = $labels[$lang][$type];

        $existing = get_page_by_path($slug);
        if ($existing) {
            $post_id = $existing->ID;
            echo "Page exists: {$title} ({$slug})\n";
        } else {
            $post_id = wp_insert_post([
                'post_type' => 'page',
                'post_title' => $title,
                'post_status' => 'publish',
                'post_name' => $slug,
                'post_content' => '<!-- wp:paragraph --><p>' . $title . ' page content placeholder.</p><!-- /wp:paragraph -->'
            ]);
            echo "Created page: {$title} ({$slug})\n";
        }
        pll_set_post_language($post_id, $lang);
        $created_ids[$lang] = $post_id;
    }
    // Link translations
    if (!empty($created_ids['uk']) && function_exists('pll_save_post_translations')) {
        pll_save_post_translations($created_ids);
    }
}

// 2. Clear old theme modifications for menu locations (optional, but ensures clean state)
$theme_locations = get_theme_mod('nav_menu_locations', []);

// 3. Create Menus
foreach ($languages as $lang) {
    // ---- PRIMARY MENU ----
    $menu_name_primary = "Primary Menu (" . strtoupper($lang) . ")";
    $menu_exists = wp_get_nav_menu_object($menu_name_primary);
    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name_primary);
        echo "Created Menu: {$menu_name_primary}\n";
    } else {
        $menu_id = $menu_exists->term_id;
        // clear old items for fresh start
        $items = wp_get_nav_menu_items($menu_id);
        foreach ($items as $item) {
            wp_delete_post($item->ID, true);
        }
    }

    // Set menu language
    pll_set_term_language($menu_id, $lang);

    // Assign to location (Polylang syntax uses primary___langcode as location keys)
    $theme_locations['primary___' . $lang] = $menu_id;

    // Add items to Primary Menu
    // 1. Home
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => $labels[$lang]['home'],
        'menu-item-url' => home_url('/' . ($lang === 'uk' ? '' : $lang . '/')),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    // 2. Catalog (archive)
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => $labels[$lang]['catalog'],
        'menu-item-url' => get_post_type_archive_link('software'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    // 3. Blog (if it exists, using url for now)
    wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title' => $labels[$lang]['blog'],
        'menu-item-url' => home_url('/' . ($lang === 'uk' ? 'blog' : $lang . '/blog')),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    // 4. About
    $about_page = get_page_by_path($slugs[$lang]['about']);
    if ($about_page) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $labels[$lang]['about'],
            'menu-item-status' => 'publish',
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $about_page->ID,
        ]);
    }

    // 5. Contacts
    $contacts_page = get_page_by_path($slugs[$lang]['contacts']);
    if ($contacts_page) {
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => $labels[$lang]['contacts'],
            'menu-item-status' => 'publish',
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $contacts_page->ID,
        ]);
    }

    echo "Populated Menu: {$menu_name_primary}\n";

    // ---- FOOTER MENU ----
    // We can do exact same for footer. Some themes use "footer" location.
    $menu_name_footer = "Footer Menu (" . strtoupper($lang) . ")";
    $menu_exists_footer = wp_get_nav_menu_object($menu_name_footer);
    if (!$menu_exists_footer) {
        $footer_menu_id = wp_create_nav_menu($menu_name_footer);
        echo "Created Menu: {$menu_name_footer}\n";
    } else {
        $footer_menu_id = $menu_exists_footer->term_id;
        $items = wp_get_nav_menu_items($footer_menu_id);
        foreach ($items as $item) {
            wp_delete_post($item->ID, true);
        }
    }

    pll_set_term_language($footer_menu_id, $lang);
    $theme_locations['footer___' . $lang] = $footer_menu_id;

    // Add same items to footer menu (minus blog/catalog usually, but let's copy the fallback: Home, Catalog, About, Contacts)
    wp_update_nav_menu_item($footer_menu_id, 0, [
        'menu-item-title' => $labels[$lang]['home'],
        'menu-item-url' => home_url('/' . ($lang === 'uk' ? '' : $lang . '/')),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    wp_update_nav_menu_item($footer_menu_id, 0, [
        'menu-item-title' => $labels[$lang]['catalog'],
        'menu-item-url' => get_post_type_archive_link('software'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ]);

    if ($about_page) {
        wp_update_nav_menu_item($footer_menu_id, 0, [
            'menu-item-title' => $labels[$lang]['about'],
            'menu-item-status' => 'publish',
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $about_page->ID,
        ]);
    }

    if ($contacts_page) {
        wp_update_nav_menu_item($footer_menu_id, 0, [
            'menu-item-title' => $labels[$lang]['contacts'],
            'menu-item-status' => 'publish',
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $contacts_page->ID,
        ]);
    }
    echo "Populated Menu: {$menu_name_footer}\n";
}

// 4. Save theme locations
set_theme_mod('nav_menu_locations', $theme_locations);
echo "Successfully mapped menus to Polylang Theme Locations.\n";
