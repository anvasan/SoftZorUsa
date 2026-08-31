<?php
if (!defined('ABSPATH'))
    exit;

add_action('acf/init', 'softmir_register_home_settings');

function softmir_register_home_settings()
{
    if (!function_exists('acf_add_local_field_group'))
        return;

    acf_add_local_field_group([
        'key' => 'group_home_settings',
        'title' => 'Header settings (Home)',
        'fields' => [
            [
                'key' => 'field_home_header_bg',
                'label' => 'Header background image',
                'name' => 'home_header_bg',
                'type' => 'image',
                'return_format' => 'url',
                'preview_size' => 'medium',
                'instructions' => 'Upload an image for the top of the main page (Hero section).',
            ],
            [
                'key' => 'field_home_header_overlay',
                'label' => 'Overlay',
                'name' => 'home_header_overlay',
                'type' => 'true_false',
                'message' => 'Add translucent shading over the image (for text readability)',
                'default_value' => 1,
            ],
            // Content Overlay
            [
                'key' => 'field_home_header_title',
                'label' => 'Заголовок на фоне (Hero Title)',
                'name' => 'home_header_title',
                'type' => 'text',
                'instructions' => 'Крупный текст по центру.',
            ],
            [
                'key' => 'field_home_header_subtitle',
                'label' => 'Подзаголовок (Subtitle)',
                'name' => 'home_header_subtitle',
                'type' => 'textarea',
                'rows' => 2,
                'instructions' => 'Текст под заголовком.',
            ],
            [
                'key' => 'field_home_header_btn_text',
                'label' => 'Текст кнопки',
                'name' => 'home_header_btn_text',
                'type' => 'text',
                'instructions' => 'Например: "Перейти в каталог"',
            ],
            [
                'key' => 'field_home_header_btn_url',
                'label' => 'Ссылка кнопки',
                'name' => 'home_header_btn_url',
                'type' => 'text',
                'instructions' => 'Куда ведет кнопка?',
            ],
            // ── Popular Products ──
            [
                'key' => 'field_home_popular_cats',
                'label' => '🔥 Popular collections - Categories',
                'name' => 'popular_categories',
                'type' => 'taxonomy',
                'taxonomy' => 'software_category',
                'field_type' => 'multi_select',
                'allow_null' => 1,
                'return_format' => 'id',
                'multiple' => 1,
                'instructions' => 'Select categories for the “Popular Collections” block on the main page. If empty, the 4 largest ones will be taken.',
            ],
        ],
        'location' => [
            [
                ['param' => 'page_type', 'operator' => '==', 'value' => 'front_page']
            ]
        ],
        'position' => 'normal',
        'style' => 'default',
    ]);
}
