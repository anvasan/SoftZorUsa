<?php
if (!defined('ABSPATH'))
    exit;

add_action('acf/init', 'softmir_register_home_flexible');

function softmir_register_home_flexible()
{
    if (!function_exists('acf_add_local_field_group'))
        return;

    // Define the Flexible Content layout
    acf_add_local_field_group([
        'key' => 'group_home_flexible',
        'title' => 'Home Page Builder',
        'fields' => [
            [
                'key' => 'field_home_modules',
                'label' => 'Page modules',
                'name' => 'home_modules',
                'type' => 'flexible_content',
                'button_label' => 'Add module',
                'layouts' => [
                    // 1. HERO
                    [
                        'key' => 'layout_hero',
                        'name' => 'hero',
                        'label' => 'Hero (First screen)',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_hero_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => 'Find the perfect software'],
                            ['key' => 'field_hero_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 3],
                            ['key' => 'field_hero_btn_text', 'label' => 'Button text', 'name' => 'btn_text', 'type' => 'text', 'default_value' => 'Go to catalog'],
                            ['key' => 'field_hero_btn_link', 'label' => 'Button link', 'name' => 'btn_link', 'type' => 'text'],
                            // Future: Background image field
                        ]
                    ],
                    // 2. CATEGORIES
                    [
                        'key' => 'layout_categories',
                        'name' => 'categories',
                        'label' => 'Categories (Grid)',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_cat_title', 'label' => 'Section title', 'name' => 'title', 'type' => 'text', 'default_value' => '🔍 Software categories'],
                            ['key' => 'field_cat_subtitle', 'label' => 'Subtitle', 'name' => 'subtitle', 'type' => 'text', 'default_value' => 'Find the right solution'],
                            ['key' => 'field_cat_count', 'label' => 'Number of categories', 'name' => 'count', 'type' => 'number', 'default_value' => 8],
                        ]
                    ],
                    // 3. POPULAR PRODUCTS (TABS)
                    [
                        'key' => 'layout_popular_products',
                        'name' => 'popular_products',
                        'label' => 'Popular Products (Tabs)',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_pop_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => '🔥 Popular selections'],
                            [
                                'key' => 'field_pop_tabs',
                                'label' => 'Category Tabs',
                                'name' => 'tabs',
                                'type' => 'repeater',
                                'button_label' => 'Add a tab',
                                'sub_fields' => [
                                    ['key' => 'field_pop_cat', 'label' => 'Category', 'name' => 'category', 'type' => 'taxonomy', 'taxonomy' => 'software_category', 'field_type' => 'select', 'return_format' => 'id'],
                                    ['key' => 'field_pop_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'text', 'default_value' => '🔹'],
                                ]
                            ]
                        ]
                    ],
                    // 4. ADVANTAGES
                    [
                        'key' => 'layout_advantages',
                        'name' => 'advantages',
                        'label' => 'Advantages',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_adv_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => 'Why choose us'],
                            ['key' => 'field_adv_subtitle', 'label' => 'Subtitle', 'name' => 'subtitle', 'type' => 'text'],
                            [
                                'key' => 'field_adv_items',
                                'label' => 'Elements',
                                'name' => 'items',
                                'type' => 'repeater',
                                'button_label' => 'Add benefit',
                                'sub_fields' => [
                                    ['key' => 'field_adv_icon', 'label' => 'Icon', 'name' => 'icon', 'type' => 'text', 'default_value' => '✅'],
                                    ['key' => 'field_adv_name', 'label' => 'Name', 'name' => 'title', 'type' => 'text'],
                                    ['key' => 'field_adv_desc', 'label' => 'Description', 'name' => 'text', 'type' => 'textarea', 'rows' => 2],
                                ]
                            ]
                        ]
                    ],
                    // 5. TESTIMONIALS
                    [
                        'key' => 'layout_testimonials',
                        'name' => 'testimonials',
                        'label' => 'Reviews',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_testi_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => 'Customer Reviews'],
                            ['key' => 'field_testi_desc', 'label' => 'Description', 'name' => 'subtitle', 'type' => 'text'],
                            [
                                'key' => 'field_testi_items',
                                'label' => 'Reviews',
                                'name' => 'items',
                                'type' => 'repeater',
                                'sub_fields' => [
                                    ['key' => 'field_t_text', 'label' => 'Review text', 'name' => 'text', 'type' => 'textarea'],
                                    ['key' => 'field_t_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text'],
                                    ['key' => 'field_t_role', 'label' => 'Position/Company', 'name' => 'role', 'type' => 'text'],
                                    ['key' => 'field_t_avatar', 'label' => 'Initials (2 letters)', 'name' => 'initials', 'type' => 'text', 'maxlength' => 2],
                                ]
                            ]
                        ]
                    ],
                    // 6. BLOG
                    [
                        'key' => 'layout_blog',
                        'name' => 'blog',
                        'label' => 'Blog (Latest Posts)',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_blog_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => 'Latest articles'],
                            ['key' => 'field_blog_subtitle', 'label' => 'Subtitle', 'name' => 'subtitle', 'type' => 'text'],
                            ['key' => 'field_blog_count', 'label' => 'Number of entries', 'name' => 'count', 'type' => 'number', 'default_value' => 3],
                        ]
                    ],
                    // 7. CTA
                    [
                        'key' => 'layout_cta',
                        'name' => 'cta',
                        'label' => 'CTA (subscription form)',
                        'display' => 'block',
                        'sub_fields' => [
                            ['key' => 'field_cta_title', 'label' => 'Heading', 'name' => 'title', 'type' => 'text', 'default_value' => 'Need help?'],
                            ['key' => 'field_cta_text', 'label' => 'Text', 'name' => 'text', 'type' => 'textarea', 'rows' => 2],
                            ['key' => 'field_cta_btn', 'label' => 'Button text', 'name' => 'btn_text', 'type' => 'text', 'default_value' => 'Send'],
                        ]
                    ],
                ] // end layouts
            ] // end field
        ],
        'location' => [
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'page']
            ]
        ]
    ]);
}
