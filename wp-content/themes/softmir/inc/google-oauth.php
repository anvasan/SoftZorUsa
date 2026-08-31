<?php
/**
 * SoftMir Google OAuth 2.0
 * Social login via Google without any SDK — pure HTTP requests
 */

// ========== Google OAuth URL ==========
function softmir_google_auth_url()
{
    $client_id = get_option('softmir_google_client_id', '');
    if (empty($client_id))
        return '';

    $redirect_uri = home_url('/?softmir_google_callback=1');

    // Generate state token for CSRF protection
    if (!session_id())
        session_start();
    $state = wp_generate_password(32, false);
    $_SESSION['softmir_google_state'] = $state;

    $params = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

// ========== Handle Google Callback ==========
function softmir_handle_google_callback()
{
    if (!isset($_GET['softmir_google_callback']))
        return;

    $code = sanitize_text_field($_GET['code'] ?? '');
    $state = sanitize_text_field($_GET['state'] ?? '');
    $error = sanitize_text_field($_GET['error'] ?? '');

    // Handle user cancellation
    if ($error) {
        softmir_flash_set('warning', __('Login via Google has been cancelled.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    // Verify state token
    if (!session_id())
        session_start();
    $stored_state = $_SESSION['softmir_google_state'] ?? '';
    unset($_SESSION['softmir_google_state']);

    if (empty($state) || $state !== $stored_state) {
        softmir_flash_set('error', __('OAuth security error. Try again.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    if (empty($code)) {
        softmir_flash_set('error', __('Google authorization error.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    $client_id = get_option('softmir_google_client_id', '');
    $client_secret = get_option('softmir_google_client_secret', '');
    $redirect_uri = home_url('/?softmir_google_callback=1');

    // Exchange code for tokens
    $token_response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($token_response)) {
        softmir_flash_set('error', __('Error communicating with Google. Please try again later.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    $token_data = json_decode(wp_remote_retrieve_body($token_response), true);

    if (empty($token_data['access_token'])) {
        softmir_flash_set('error', __('Error getting Google token.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    // Get user profile
    $profile_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token_data['access_token'],
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($profile_response)) {
        softmir_flash_set('error', __('Error retrieving Google profile.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    $profile = json_decode(wp_remote_retrieve_body($profile_response), true);

    if (empty($profile['email'])) {
        softmir_flash_set('error', __('Failed to receive email from Google.', 'softmir'));
        wp_redirect(home_url('/login/'));
        exit;
    }

    $google_id = sanitize_text_field($profile['id']);
    $google_email = sanitize_email($profile['email']);
    $google_name = sanitize_text_field($profile['name'] ?? '');
    $google_avatar = esc_url_raw($profile['picture'] ?? '');

    // Check if user exists by Google ID
    $users_by_google = get_users([
        'meta_key' => 'softmir_google_id',
        'meta_value' => $google_id,
        'number' => 1,
    ]);

    if (!empty($users_by_google)) {
        // Existing Google user — log in
        $user = $users_by_google[0];
        softmir_google_login_user($user, $google_avatar);
        return;
    }

    // Check if user exists by email
    $existing_user = get_user_by('email', $google_email);

    if ($existing_user) {
        // Link Google account to existing user
        update_user_meta($existing_user->ID, 'softmir_google_id', $google_id);
        update_user_meta($existing_user->ID, 'softmir_google_avatar', $google_avatar);
        update_user_meta($existing_user->ID, 'softmir_email_verified', true);
        softmir_google_login_user($existing_user, $google_avatar);
        return;
    }

    // Create new user
    $username = sanitize_user(strtolower(str_replace(' ', '', $google_name)));
    if (empty($username) || username_exists($username)) {
        $username = sanitize_user(strstr($google_email, '@', true));
    }
    if (username_exists($username)) {
        $username .= '_' . wp_rand(100, 999);
    }

    $random_password = wp_generate_password(24, true);
    $user_id = wp_create_user($username, $random_password, $google_email);

    if (is_wp_error($user_id)) {
        softmir_flash_set('error', sprintf(__('Error creating account: %s', 'softmir'), $user_id->get_error_message()));
        wp_redirect(home_url('/login/'));
        exit;
    }

    // Set metadata
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $google_name ?: $username,
    ]);
    update_user_meta($user_id, 'softmir_google_id', $google_id);
    update_user_meta($user_id, 'softmir_google_avatar', $google_avatar);
    update_user_meta($user_id, 'softmir_email_verified', true); // Google accounts are pre-verified

    $user = get_userdata($user_id);
    softmir_google_login_user($user, $google_avatar);
}
add_action('init', 'softmir_handle_google_callback');

// ========== Log in via Google ==========
function softmir_google_login_user($user, $avatar_url = '')
{
    // Update avatar on each login
    if ($avatar_url) {
        update_user_meta($user->ID, 'softmir_google_avatar', $avatar_url);
    }

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    softmir_flash_set('success', sprintf(__('Welcome, %s!', 'softmir'), esc_html($user->display_name)));
    wp_redirect(home_url('/profile/'));
    exit;
}
