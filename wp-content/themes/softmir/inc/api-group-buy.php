<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API Endpoint for Group Buying Leads
 */
function softmir_register_group_buy_endpoint()
{
    register_rest_route('softmir/v1', '/group-buy', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_group_buy_submit',
        'permission_callback' => '__return_true', // Open to public
        'args' => [
            'software_id' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param);
                }
            ],
            'contact_name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'contact_email' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_email($param);
                }
            ],
            'contact_phone' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'organization' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'seats_needed' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && intval($param) > 0;
                }
            ],
        ]
    ]);
}
add_action('rest_api_init', 'softmir_register_group_buy_endpoint');

function softmir_rest_group_buy_submit(WP_REST_Request $request)
{
    // Verify WP Nonce for security
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Security check failed. Please reload the page.', ['status' => 403]);
    }

    $software_id = intval($request->get_param('software_id'));
    $name = sanitize_text_field($request->get_param('contact_name'));
    $email = sanitize_email($request->get_param('contact_email'));
    $phone = sanitize_text_field($request->get_param('contact_phone'));
    $organization = sanitize_text_field($request->get_param('organization'));
    $seats = intval($request->get_param('seats_needed'));

    // Check if software exists
    if (get_post_type($software_id) !== 'software') {
        return new WP_Error('invalid_software', 'Software not found', ['status' => 404]);
    }

    // Rate limiting: prevent duplicate submissions (same email + software within 5 min)
    global $wpdb;
    $recent = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$wpdb->prefix}softzor_group_buying WHERE software_id = %d AND contact_email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        $software_id,
        $email
    ));
    if ($recent > 0) {
        return new WP_Error('duplicate', 'You have already submitted a request for this product. Wait a few minutes.', ['status' => 429]);
    }

    // Auto-create WordPress user if requested
    $user_id = email_exists($email);
    if (!$user_id) {
        $userdata = [
            'user_login' => $email,
            'user_email' => $email,
            'user_pass' => wp_generate_password(12, false),
            'first_name' => $name,
            'role' => 'subscriber'
        ];
        wp_insert_user($userdata);
    }

    // Use our DB helper function
    if (function_exists('softmir_log_group_buying_lead')) {
        $insert_id = softmir_log_group_buying_lead([
            'software_id' => $software_id,
            'contact_name' => $name,
            'contact_email' => $email,
            'contact_phone' => $phone,
            'organization' => $organization,
            'seats_needed' => $seats
        ]);

        if ($insert_id) {
            wp_cache_delete('gb_count_' . $software_id, 'softmir_gb');
            return rest_ensure_response([
                'success' => true,
                'message' => 'Your application has been accepted! We will contact you as soon as we have found the required pool for the discount.'
            ]);
        }
    }

    return new WP_Error('db_error', 'Failed to save lead', ['status' => 500]);
}

/**
 * Gets the number of pending group buying leads for a software with caching.
 */
function softmir_get_gb_count($software_id)
{
    global $wpdb;
    $cache_key = 'gb_count_' . $software_id;
    $count = wp_cache_get($cache_key, 'softmir_gb');
    if ($count === false) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}softzor_group_buying WHERE software_id = %d AND status = 'pending'",
            $software_id
        ));
        wp_cache_set($cache_key, $count, 'softmir_gb', 3600);
    }
    return $count;
}
