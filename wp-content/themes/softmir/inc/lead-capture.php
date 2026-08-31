<?php
/**
 * SoftZor — Lead Capture REST Endpoint
 * Handles quiz Lead Form submissions (Async Scout — Option 2)
 *
 * Flow:
 * 1. Accepts name, email, quiz context from frontend
 * 2. Auto-registers WP user if not exists (subscriber)
 * 3. Saves lead request to DB
 * 4. Sends verification email
 * 5. On verification → triggers background Scout via WP-Cron
 */

if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// 1. Register REST Route
// ==========================================
add_action('rest_api_init', function () {
    register_rest_route('softmir/v1', '/lead-capture', [
        'methods' => 'POST',
        'callback' => 'softmir_rest_lead_capture',
        'permission_callback' => '__return_true', // Public endpoint
    ]);
});

// ==========================================
// 2. Lead Capture Handler
// ==========================================
function softmir_rest_lead_capture(WP_REST_Request $request)
{
    $params = $request->get_json_params() ?: $request->get_body_params();

    // --- Anti-bot: Honeypot field (must be empty) ---
    $honeypot = $params['website_url_confirm'] ?? '';
    if (!empty($honeypot)) {
        // Bot detected — return fake success to avoid revealing the trap
        return rest_ensure_response([
            'status' => 'ok',
            'message' => __('Check your email to confirm.', 'softmir'),
        ]);
    }

    // --- Anti-bot: Referer validation ---
    $referer = $request->get_header('referer');
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
    if (!empty($referer)) {
        $referer_host = wp_parse_url($referer, PHP_URL_HOST);
        if ($referer_host && $referer_host !== $site_host) {
            return new WP_Error('invalid_referer', __('Invalid request source.', 'softmir'), ['status' => 403]);
        }
    }

    $email = sanitize_email($params['email'] ?? '');
    $name = sanitize_text_field($params['name'] ?? '');
    $category_id = intval($params['category_id'] ?? 0);
    $user_text = sanitize_textarea_field($params['user_text'] ?? '');
    $session_id = sanitize_text_field($params['session_id'] ?? '');
    $answers = rest_sanitize_array($params['answers'] ?? []);
    $region = sanitize_text_field($params['region'] ?? '');
    $lang_name = sanitize_text_field($params['lang_name'] ?? 'English');

    if (!is_email($email)) {
        return new WP_Error('invalid_email', __('Please enter a correct email.', 'softmir'), ['status' => 400]);
    }

    // --- IP Rate Limiting (soft: 20 requests/day) ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $is_localhost = in_array($ip, ['127.0.0.1', '::1']);

    if (!$is_localhost) {
        $ip_key = 'sz_lead_' . md5($ip);
        $ip_count = (int) get_transient($ip_key);
        if ($ip_count >= 20) {
            return new WP_Error('rate_limit', __('Too many requests. Please try again later.', 'softmir'), ['status' => 429]);
        }
        set_transient($ip_key, $ip_count + 1, DAY_IN_SECONDS);

        // --- Email send limit (5 unverified per IP) ---
        $email_key = 'sz_mail_' . md5($ip);
        $email_count = (int) get_transient($email_key);
        if ($email_count >= 5) {
            return rest_ensure_response([
                'status' => 'email_limit',
                'message' => __('You have already requested 5 reports. Please confirm your email by clicking on the link in the first email.', 'softmir'),
            ]);
        }
        set_transient($email_key, $email_count + 1, DAY_IN_SECONDS);
    }

    // --- Auto-register WP user ---
    $user_id = 0;
    if (!is_user_logged_in()) {
        $existing_user_id = email_exists($email);
        if ($existing_user_id) {
            $user_id = $existing_user_id;
        } else {
            $random_password = wp_generate_password(12, true, false);
            $user_login = sanitize_user(current(explode('@', $email)), true);
            if (username_exists($user_login)) {
                $user_login .= '_' . wp_rand(100, 999);
            }
            $new_id = wp_create_user($user_login, $random_password, $email);
            if (!is_wp_error($new_id)) {
                $user_id = $new_id;
                $user = new WP_User($user_id);
                $user->set_role('subscriber');
                if (!empty($name)) {
                    wp_update_user([
                        'ID' => $user_id,
                        'display_name' => $name,
                        'first_name' => $name,
                    ]);
                }
                wp_new_user_notification($user_id, null, 'user');
            }
        }
    } else {
        $user_id = get_current_user_id();
    }

    // --- Save lead request to options (lightweight, no custom table needed for MVP) ---
    $verify_token = wp_generate_uuid4();
    $is_logged_in = is_user_logged_in();
    $lead_data = [
        'token' => $verify_token,
        'email' => $email,
        'name' => $name,
        'user_id' => $user_id,
        'category_id' => $category_id,
        'user_text' => $user_text,
        'session_id' => $session_id,
        'answers' => $answers,
        'region' => $region,
        'lang_name' => $lang_name,
        'status' => $is_logged_in ? 'verified' : 'pending', // Auto-verify if logged in
        'created_at' => current_time('mysql'),
        'ip' => $ip,
    ];

    // Store in wp_options as a transient (auto-expires in 7 days)
    set_transient('sz_lead_' . $verify_token, $lead_data, 7 * DAY_IN_SECONDS);

    if ($is_logged_in) {
        // Skip email verification, immediately schedule background Scout
        wp_schedule_single_event(time() + 2, 'softzor_run_background_scout', [$verify_token]);
        return rest_ensure_response([
            'status' => 'auto_verified',
            'message' => __('Request accepted. The selection will be sent to your email.', 'softmir'),
        ]);
    }

    // --- Send verification email (for guests) ---
    $verify_url = add_query_arg([
        'sz_verify_lead' => $verify_token,
    ], home_url('/'));

    $term = $category_id ? get_term($category_id, 'software_category') : null;
    $cat_name = ($term && !is_wp_error($term)) ? $term->name : __('General software', 'softmir');
    $display_name = !empty($name) ? $name : __('Guest', 'softmir');

    $subject = sprintf(__('SoftZor: Confirm the request for software selection - %s', 'softmir'), $cat_name);

    $verify_body = sprintf(
        '<p style="font-size:15px;color:#555;line-height:1.7;">'
        . __('You have requested a personalized selection of software in the <strong>%s</strong>category.', 'softmir')
        . '</p>'
        . '<p style="font-size:15px;color:#555;line-height:1.7;">'
        . __('Your request: "<em>%s</em>"', 'softmir')
        . '</p>'
        . '<p style="font-size:15px;color:#555;line-height:1.7;">'
        . __('To have our AI analyst begin market research and prepare a report for you, confirm your request:', 'softmir')
        . '</p>'
        . '<p style="font-size:13px;color:#94a3b8;margin-top:16px;">'
        . __('The link is valid for 7 days.', 'softmir')
        . '</p>',
        esc_html($cat_name),
        esc_html($user_text)
    );

    $body = softzor_build_email_html(
        $display_name,
        '🔍 ' . __('Confirm your request for software selection', 'softmir'),
        $verify_body,
        esc_url($verify_url),
        __('Confirm request →', 'softmir')
    );

    wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);

    return rest_ensure_response([
        'status' => 'ok',
        'message' => __('Check your email to confirm.', 'softmir'),
    ]);
}

// ==========================================
// 3. Email Verification Handler (runs on init)
// ==========================================
add_action('init', function () {
    if (empty($_GET['sz_verify_lead'])) {
        return;
    }

    $token = sanitize_text_field($_GET['sz_verify_lead']);
    $lead = get_transient('sz_lead_' . $token);

    if (!$lead || !is_array($lead)) {
        wp_die(__('The link is invalid or expired.', 'softmir'), __('Error', 'softmir'), ['response' => 404]);
    }

    if ($lead['status'] !== 'pending') {
        // Already verified — redirect to catalog
        wp_safe_redirect(home_url('/software/'));
        exit;
    }

    // Mark as verified
    $lead['status'] = 'verified';
    set_transient('sz_lead_' . $token, $lead, 7 * DAY_IN_SECONDS);

    // Reset email rate limit for this IP (reward for verification)
    $ip_key = 'sz_mail_' . md5($lead['ip']);
    delete_transient($ip_key);

    // Schedule background Scout
    wp_schedule_single_event(time() + 5, 'softzor_run_background_scout', [$token]);

    // Redirect to a thank you message or catalog
    $redirect_url = add_query_arg(['sw_cat' => $lead['category_id']], get_post_type_archive_link('software') ?: home_url('/software/'));
    wp_safe_redirect($redirect_url);
    exit;
});

// ==========================================
// 4. Background Scout Worker (WP-Cron) — with Retry Pattern
// ==========================================
add_action('softzor_run_background_scout', function ($token) {
    $lead = get_transient('sz_lead_' . $token);
    if (!$lead || !in_array($lead['status'], ['verified', 'retrying'], true)) {
        return;
    }

    // Get existing software to avoid duplicates
    $existing_names = [];
    if ($lead['category_id'] > 0) {
        $existing_posts = get_posts([
            'post_type' => 'software',
            'posts_per_page' => 50,
            'tax_query' => [
                [
                    'taxonomy' => 'software_category',
                    'field' => 'term_id',
                    'terms' => $lead['category_id'],
                ]
            ],
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        foreach ($existing_posts as $p_id) {
            $existing_names[] = get_the_title($p_id);
        }
    }

    // Run the Scout (the expensive operation — now safely in background)
    $scout_result = softmir_run_scout(
        $lead['category_id'],
        $lead['region'],
        $lead['answers'],
        $lead['user_text'],
        $lead['lang_name'],
        $existing_names
    );

    // ============================================================
    // RETRY PATTERN: If Scout failed, re-schedule with exponential backoff
    // ============================================================
    if (is_wp_error($scout_result)) {
        $retry_count = intval($lead['retry_count'] ?? 0);
        $max_retries = 3;
        $delays = [300, 1800, 3600]; // 5 min, 30 min, 1 hour

        if ($retry_count < $max_retries) {
            $lead['retry_count'] = $retry_count + 1;
            $lead['status'] = 'retrying';
            set_transient('sz_lead_' . $token, $lead, 7 * DAY_IN_SECONDS);
            wp_schedule_single_event(time() + $delays[$retry_count], 'softzor_run_background_scout', [$token]);
            return; // Exit without sending email — will retry later
        }

        // All retries exhausted → mark as failed, send apology email
        $lead['status'] = 'failed';
        set_transient('sz_lead_' . $token, $lead, 7 * DAY_IN_SECONDS);

        $term = $lead['category_id'] ? get_term($lead['category_id'], 'software_category') : null;
        $category_name = ($term && !is_wp_error($term)) ? $term->name : __('General software', 'softmir');
        $display_name = !empty($lead['name']) ? $lead['name'] : __('Guest', 'softmir');
        $subject = __('SoftZor: Update according to your request', 'softmir');

        $apology_html = softzor_build_email_html(
            $display_name,
            '⚠️ ' . __('Your request requires manual processing', 'softmir'),
            sprintf(
                '<p style="font-size:15px;color:#555;line-height:1.7;">'
                . __('Unfortunately, for your problem "<strong>%s</strong>" in category "%s", our AI analyst was not able to automatically find solutions.', 'softmir')
                . '</p>'
                . '<p style="font-size:15px;color:#555;line-height:1.7;">'
                . __('Your request has been forwarded to our moderator, and we will contact you within 24 hours with a personal selection.', 'softmir')
                . '</p>',
                esc_html($lead['user_text']),
                esc_html($category_name)
            ),
            '' // No CTA button
        );

        wp_mail($lead['email'], $subject, $apology_html, ['Content-Type: text/html; charset=UTF-8']);
        return;
    }

    // ============================================================
    // SUCCESS PATH: Scout returned results
    // ============================================================

    // Generate vendor brief
    $scouted_items_json = '';
    if (is_array($scout_result)) {
        $scouted_items_json = wp_json_encode($scout_result);
    }

    $term = $lead['category_id'] ? get_term($lead['category_id'], 'software_category') : null;
    $category_name = ($term && !is_wp_error($term)) ? $term->name : __('Not defined', 'softmir');

    $brief = softmir_generate_vendor_brief(
        $lead['user_text'],
        $category_name,
        $lead['answers'],
        $scouted_items_json,
        $lead['lang_name']
    );

    // Update intent log with brief
    if (!empty($lead['session_id'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'softzor_intent_logs';
        $wpdb->update($table, ['generated_brief' => $brief ?: ''], ['session_id' => $lead['session_id']]);
    }

    // Mark lead as scouted
    $lead['status'] = 'scouted';
    set_transient('sz_lead_' . $token, $lead, 7 * DAY_IN_SECONDS);

    // ============================================================
    // SEND HTML RESULTS EMAIL
    // ============================================================
    $archive_url = get_post_type_archive_link('software') ?: home_url('/software/');
    $results_url = add_query_arg(['sw_cat' => $lead['category_id']], $archive_url);

    $display_name = !empty($lead['name']) ? $lead['name'] : __('Guest', 'softmir');
    $subject = sprintf(__('SoftZor: Your software selection is ready - %s', 'softmir'), $category_name);

    // Build programs list HTML
    $programs_html = '';
    if (is_array($scout_result) && !empty($scout_result)) {
        $programs_html .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;">';
        $medals = ['🥇', '🥈', '🥉'];
        foreach ($scout_result as $index => $name) {
            $medal = $medals[$index] ?? '🔹';
            $num = $index + 1;
            $bg = ($index % 2 === 0) ? '#f8fafc' : '#ffffff';
            $programs_html .= '<tr><td style="padding:12px 16px;background:' . $bg . ';border-radius:8px;">'
                . '<span style="font-size:20px;margin-right:8px;">' . $medal . '</span>'
                . '<strong style="font-size:15px;color:#1e3a5f;">' . esc_html($name) . '</strong>'
                . '</td></tr>';
        }
        $programs_html .= '</table>';
    }

    $body_content = sprintf(
        '<p style="font-size:15px;color:#555;line-height:1.7;">'
        . __('Our AI analyst has completed market research for your request "<strong>%s</strong>".', 'softmir')
        . '</p>',
        esc_html($lead['user_text'])
    );

    if (!empty($programs_html)) {
        $body_content .= '<p style="font-size:15px;color:#333;font-weight:600;margin-bottom:4px;">'
            . __('We have selected the best solutions to suit your criteria:', 'softmir')
            . '</p>'
            . $programs_html
            . '<p style="font-size:14px;color:#777;">'
            . __('A detailed comparison has been prepared for each product: prices, pros/cons, use cases.', 'softmir')
            . '</p>';
    } else {
        $body_content .= '<p style="font-size:15px;color:#555;">'
            . __('We have added the best solutions to the catalog.', 'softmir')
            . '</p>';
    }

    $email_html = softzor_build_email_html(
        $display_name,
        '📋 ' . __('Your personal selection of software is ready', 'softmir'),
        $body_content,
        esc_url($results_url),
        __('Open the selection on SoftZor →', 'softmir')
    );

    wp_mail($lead['email'], $subject, $email_html, ['Content-Type: text/html; charset=UTF-8']);
});

// ==========================================
// 5. HTML Email Template Builder
// ==========================================

/**
 * Builds a branded HTML email for SoftZor.
 *
 * @param string $recipient_name Display name of the recipient.
 * @param string $headline       Main headline text.
 * @param string $body_html      HTML content for the body.
 * @param string $cta_url        URL for the CTA button (empty to skip).
 * @param string $cta_text       Text for the CTA button.
 * @return string Complete HTML email.
 */
function softzor_build_email_html($recipient_name, $headline, $body_html, $cta_url = '', $cta_text = '')
{
    $greeting = sprintf(__('Hello %s!', 'softmir'), esc_html($recipient_name));
    $footer_text = __('Best regards, SoftZor Team', 'softmir');
    $year = date('Y');

    $cta_block = '';
    if (!empty($cta_url) && !empty($cta_text)) {
        $cta_block = '<div style="text-align:center;margin:24px 0;">'
            . '<a href="' . esc_url($cta_url) . '" style="display:inline-block;padding:14px 32px;'
            . 'background:linear-gradient(135deg,#4a6cf7,#6366f1);color:#ffffff;font-size:15px;'
            . 'font-weight:700;text-decoration:none;border-radius:8px;">'
            . esc_html($cta_text) . '</a></div>';
    }

    return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:32px 0;">'
        . '<tr><td align="center">'
        // Header
        . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">'
        . '<tr><td style="text-align:center;padding:24px 0;">'
        . '<span style="font-size:24px;font-weight:800;color:#1e3a5f;">Soft<span style="color:#4a6cf7;">Zor</span></span>'
        . '</td></tr>'
        // Card
        . '<tr><td style="background:#ffffff;border-radius:12px;padding:32px 28px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">'
        . '<h2 style="font-size:20px;color:#1e3a5f;margin:0 0 6px;">' . $headline . '</h2>'
        . '<p style="font-size:15px;color:#555;margin:0 0 20px;">' . $greeting . '</p>'
        . $body_html
        . $cta_block
        . '</td></tr>'
        // Footer
        . '<tr><td style="text-align:center;padding:20px 0;font-size:13px;color:#94a3b8;">'
        . $footer_text . '<br>'
        . '© ' . $year . ' SoftZor. ' . __('All rights reserved.', 'softmir')
        . '</td></tr>'
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
}

