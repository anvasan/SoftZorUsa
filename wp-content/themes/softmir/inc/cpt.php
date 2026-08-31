<?php
/**
 * SoftMir — Custom Post Types & Taxonomies
 * Extracted from functions.php for maintainability
 */

if (!defined('ABSPATH'))
    exit;

// ========== Register CPT: Software ==========
function softmir_cpt_software()
{
    register_post_type('software', [
        'labels' => [
            'name' => __('Каталог ПО', 'softmir'),
            'singular_name' => __('Программа', 'softmir'),
            'add_new' => __('Добавить ПО', 'softmir'),
            'add_new_item' => __('Добавить новое ПО', 'softmir'),
            'edit_item' => __('Редактировать ПО', 'softmir'),
            'all_items' => __('Все программы', 'softmir'),
            'search_items' => __('Искать ПО', 'softmir'),
            'not_found' => __('Не найдено', 'softmir'),
            'menu_name' => __('Каталог ПО', 'softmir'),
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'software', 'with_front' => false],
        'menu_icon' => 'dashicons-grid-view',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'softmir_cpt_software');

// ========== Register CPT: Integrator ==========
function softmir_cpt_integrator()
{
    register_post_type('integrator', [
        'labels' => [
            'name' => __('Интеграторы', 'softmir'),
            'singular_name' => __('Интегратор', 'softmir'),
            'add_new' => __('Добавить', 'softmir'),
            'add_new_item' => __('Добавить интегратора', 'softmir'),
            'edit_item' => __('Редактировать интегратора', 'softmir'),
            'all_items' => __('Все интеграторы', 'softmir'),
            'menu_name' => __('Интеграторы', 'softmir'),
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'integrator', 'with_front' => false],
        'menu_icon' => 'dashicons-groups',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'softmir_cpt_integrator');

// ========== Register Taxonomy: Software Category ==========
function softmir_taxonomy_software_category()
{
    register_taxonomy('software_category', 'software', [
        'labels' => [
            'name' => __('Категории ПО', 'softmir'),
            'singular_name' => __('Категория ПО', 'softmir'),
            'search_items' => __('Искать категории', 'softmir'),
            'all_items' => __('Все категории', 'softmir'),
            'parent_item' => __('Родительская категория', 'softmir'),
            'edit_item' => __('Редактировать категорию', 'softmir'),
            'add_new_item' => __('Добавить категорию', 'softmir'),
            'menu_name' => __('Категории', 'softmir'),
        ],
        'hierarchical' => true,
        'public' => true,
        'rewrite' => ['slug' => 'software-category'],
        'show_in_rest' => true,
        'show_admin_column' => true,
    ]);
}
add_action('init', 'softmir_taxonomy_software_category');

// ========== Move Category Meta Box to Main Column ==========
function softmir_move_category_metabox()
{
    remove_meta_box('software_categorydiv', 'software', 'side');
    add_meta_box('software_categorydiv', __('Категории ПО', 'softmir'), 'post_categories_meta_box', 'software', 'normal', 'high', ['taxonomy' => 'software_category']);
}
add_action('add_meta_boxes', 'softmir_move_category_metabox');
