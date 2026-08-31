<?php
if (function_exists('acf_add_local_field_group')):

    acf_add_local_field_group(array(
        'key' => 'group_software_scout_details',
        'title' => 'Software Details (Scout Data)',
        'fields' => array(
            // === USE SCENARIO (Markdown) ===
            array(
                'key' => 'field_sw_scenarios_md',
                'label' => 'Use Cases',
                'name' => 'scenarios_md',
                'type' => 'textarea',
                'rows' => 10,
                'instructions' => 'Format: ### Script title' . "\n" . 'Scenario description text. Each ### block = a separate script.',
            ),

            // === TEXT LISTS (each line = item) ===
            array(
                'key' => 'field_sw_top_reasons',
                'label' => 'Why is it TOP (Pros)',
                'name' => 'top_reasons',
                'type' => 'textarea',
                'rows' => 5,
                'instructions' => 'Every thought on a new line',
            ),
            array(
                'key' => 'field_sw_disadvantages',
                'label' => 'Nuances and Risks (Cons)',
                'name' => 'disadvantages',
                'type' => 'textarea',
                'rows' => 5,
                'instructions' => 'Every thought on a new line',
            ),
            array(
                'key' => 'field_sw_best_for',
                'label' => 'SUITABLE FOR YOU IF',
                'name' => 'best_for',
                'type' => 'textarea',
                'rows' => 5,
                'instructions' => 'Each condition on a new line',
            ),
            array(
                'key' => 'field_sw_bad_for',
                'label' => 'It\'s better NOT to take it if',
                'name' => 'bad_for',
                'type' => 'textarea',
                'rows' => 5,
                'instructions' => 'Each condition on a new line',
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
    ));

    // === VENDOR AND GROUP PURCHASING ===
    acf_add_local_field_group(array(
        'key' => 'group_software_vendor_buying',
        'title' => 'Group Purchasing and Contacts (Vendor)',
        'fields' => array(
            array(
                'key' => 'field_vendor_contact_email',
                'label' => 'Vendor email',
                'name' => 'vendor_contact_email',
                'type' => 'email',
                'instructions' => 'Negotiation representative email',
            ),
            array(
                'key' => 'field_vendor_contact_name',
                'label' => 'Representative name',
                'name' => 'vendor_contact_name',
                'type' => 'text',
            ),
            array(
                'key' => 'field_discount_available',
                'label' => 'Discount readiness',
                'name' => 'discount_available',
                'type' => 'radio',
                'choices' => array(
                    'yes' => 'Yes',
                    'no' => 'No',
                    'trial' => 'Trial fee (No contact)',
                ),
                'default_value' => 'trial',
                'layout' => 'horizontal',
                'return_format' => 'value',
            ),
            array(
                'key' => 'field_discount_amount',
                'label' => 'Discount amount',
                'name' => 'discount_amount',
                'type' => 'text',
                'instructions' => 'For example: 20% or $50',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_discount_available',
                            'operator' => '==',
                            'value' => 'yes',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_min_licenses',
                'label' => 'License threshold for discount',
                'name' => 'min_licenses_for_discount',
                'type' => 'number',
                'default_value' => 10,
                'min' => 1,
            ),
            array(
                'key' => 'field_promo_text',
                'label' => 'Hot Offer Text (Promo)',
                'name' => 'promo_text',
                'type' => 'text',
                'instructions' => 'Example: "Cyber Monday: 50% Off Lifetime"',
            ),
            array(
                'key' => 'field_promo_deadline',
                'label' => 'Hot Offer Deadline',
                'name' => 'promo_deadline',
                'type' => 'date_picker',
                'display_format' => 'd/m/Y',
                'return_format' => 'Y-m-d',
                'instructions' => 'When does the offer expire?',
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
        'menu_order' => 6,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ));

endif;
