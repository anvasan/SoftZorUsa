<?php
/**
 * SoftMir — Registration of ACF fields for Pricing Plans
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', 'softmir_register_pricing_fields');
function softmir_register_pricing_fields()
{
    if (function_exists('acf_add_local_field_group')):

        acf_add_local_field_group(array(
            'key' => 'group_softmir_pricing',
            'title' => 'Tariff plans',
            'fields' => array(
                array(
                    'key' => 'field_pricing_plans',
                    'label' => 'Plans',
                    'name' => 'pricing_plans',
                    'type' => 'repeater',
                    'instructions' => 'Add pricing plans to display as beautiful cards.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'collapsed' => '',
                    'min' => 0,
                    'max' => 0,
                    'layout' => 'block',
                    'button_label' => 'Add tariff',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_plan_name',
                            'label' => 'Tariff name',
                            'name' => 'plan_name',
                            'type' => 'text',
                            'instructions' => 'For example: Basic, PRO, Enterprise',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_plan_price',
                            'label' => 'Price',
                            'name' => 'plan_price',
                            'type' => 'text',
                            'instructions' => 'For example: 1000 ₽ or Free',
                        ),
                        array(
                            'key' => 'field_plan_period',
                            'label' => 'Period',
                            'name' => 'plan_period',
                            'type' => 'text',
                            'instructions' => 'For example: /month, /year',
                        ),
                        array(
                            'key' => 'field_plan_features',
                            'label' => 'Features (each on a new line)',
                            'name' => 'plan_features',
                            'type' => 'textarea',
                            'instructions' => 'Each line will be shown as a separate item with a check mark.',
                        ),
                        array(
                            'key' => 'field_plan_btn_url',
                            'label' => 'Link to purchase/demo',
                            'name' => 'plan_btn_url',
                            'type' => 'url',
                        ),
                        array(
                            'key' => 'field_plan_highlight',
                            'label' => 'Select a tariff?',
                            'name' => 'plan_highlight',
                            'type' => 'true_false',
                            'message' => 'Make the card more colorful (for example, “Best Seller”)',
                            'default_value' => 0,
                            'ui' => 1,
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'software',
                    ),
                ),
            ),
            'menu_order' => 5,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ));

    endif;
}
