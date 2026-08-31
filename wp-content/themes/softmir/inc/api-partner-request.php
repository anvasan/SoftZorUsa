<?php
if (!defined('ABSPATH'))
    exit;

/**
 * REST API Endpoint for Vendor Partnership Requests
 */
function softmir_register_partner_request_endpoint()
{
    register_rest_route('softmir/v1', '/partner-request', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_partner_request_submit',
        'permission_callback' => '__return_true',
        'args' => [
            'company_name' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'product_name' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'product_url' => ['required' => true, 'sanitize_callback' => 'esc_url_raw'],
            'category' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'contact_name' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'contact_email' => ['required' => true, 'validate_callback' => function ($p) {
                return is_email($p); }],
            'contact_phone' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'message' => ['required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
        ]
    ]);
}
add_action('rest_api_init', 'softmir_register_partner_request_endpoint');

function softmir_rest_partner_request_submit(WP_REST_Request $request)
{
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'Security check failed.', ['status' => 403]);
    }

    $email = sanitize_email($request->get_param('contact_email'));

    // Rate limiting
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_partner_requests';
    $recent = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM {$table} WHERE contact_email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $email
    ));
    if ($recent > 0) {
        return new WP_Error('duplicate', 'You have already submitted your application. Wait.', ['status' => 429]);
    }

    $result = $wpdb->insert($table, [
        'company_name' => sanitize_text_field($request->get_param('company_name')),
        'product_name' => sanitize_text_field($request->get_param('product_name')),
        'product_url' => esc_url_raw($request->get_param('product_url')),
        'category' => sanitize_text_field($request->get_param('category')),
        'contact_name' => sanitize_text_field($request->get_param('contact_name')),
        'contact_email' => $email,
        'contact_phone' => sanitize_text_field($request->get_param('contact_phone')),
        'message' => sanitize_textarea_field($request->get_param('message')),
        'status' => 'new',
        'created_at' => current_time('mysql'),
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

    if ($result) {
        return rest_ensure_response([
            'success' => true,
            'message' => 'Thank you! Your application has been accepted. We will contact you within 48 hours.',
        ]);
    }

    return new WP_Error('db_error', 'Failed to save request', ['status' => 500]);
}
