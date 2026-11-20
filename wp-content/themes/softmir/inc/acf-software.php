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

    // === PROMO FIELDS (HOT OFFERS) ===
    acf_add_local_field_group(array(
        'key' => 'group_software_promo',
        'title' => 'Hot Deals & Promotions',
        'fields' => array(
            array(
                'key' => 'field_promo_text',
                'label' => 'Promo Text (or Coupon Code)',
                'name' => 'promo_text',
                'type' => 'textarea',
                'instructions' => 'If filled, displays a highlighted promo banner on the software card and homepage.',
                'rows' => 3,
            ),
            array(
                'key' => 'field_promo_deadline',
                'label' => 'Offer Validity (Display Text)',
                'name' => 'promo_deadline',
                'type' => 'text',
                'instructions' => 'Human-readable text. Example: "Nov 30, 2026", "End of month", "Ongoing"',
            ),
            array(
                'key' => 'field_promo_deadline_date',
                'label' => 'Offer End Date (for Auto-Expiration)',
                'name' => 'promo_deadline_date',
                'type' => 'date_picker',
                'display_format' => 'm/d/Y',
                'return_format' => 'Ymd',
                'first_day' => 1,
                'instructions' => 'Machine-readable expiration date. Leave empty for permanent/ongoing deals.',
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
        'menu_order' => 7,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'show_in_rest' => true,
    ));

endif;
