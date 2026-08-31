<?php
/**
 * Template Name: Profile Page
 * User profile / dashboard
 */
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

get_header();
$user = wp_get_current_user();
$avatar_url = softmir_get_user_avatar_url($user->ID);
$role_label = softmir_get_role_label($user);
$verified = get_user_meta($user->ID, 'softmir_email_verified', true);
$google_linked = get_user_meta($user->ID, 'softmir_google_id', true);
?>

<main class="auth-page profile-page">
    <div class="profile-container">

        <?php echo softmir_flash_html(); ?>

        <!-- Profile Header Card -->
        <div class="profile-header-card">
            <div class="profile-avatar-wrap">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>"
                    class="profile-avatar">
                <div class="profile-status <?php echo $verified ? 'verified' : 'unverified'; ?>">
                    <?php if ($verified): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <?php
                    else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        <?php
                    endif; ?>
                </div>
            </div>

            <div class="profile-info">
                <h1><?php echo esc_html($user->display_name); ?></h1>
                <p class="profile-email"><?php echo esc_html($user->user_email); ?></p>
                <div class="profile-badges">
                    <span class="badge badge-role"><?php echo esc_html($role_label); ?></span>
                    <?php if ($verified): ?>
                        <span class="badge badge-verified"><?php _e('✓ Email confirmed', 'softmir'); ?></span>
                        <?php
                    else: ?>
                        <span class="badge badge-unverified"><?php _e('✗ Email not verified', 'softmir'); ?></span>
                        <?php
                    endif; ?>
                    <?php if ($google_linked): ?>
                        <span class="badge badge-google">
                            <svg viewBox="0 0 24 24" width="12" height="12">
                                <path
                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"
                                    fill="#4285F4" />
                                <path
                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                    fill="#34A853" />
                                <path
                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                    fill="#FBBC05" />
                                <path
                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                    fill="#EA4335" />
                            </svg>
                            Google
                        </span>
                        <?php
                    endif; ?>
                </div>
            </div>

            <a href="<?php echo esc_url(softmir_logout_url()); ?>" class="profile-logout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <?php _e('Log Out', 'softmir'); ?>
            </a>
        </div>

        <?php if (!$verified && !$google_linked): ?>
            <div class="profile-alert profile-alert-warning">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                <div>
                    <strong><?php _e('Email not verified', 'softmir'); ?></strong>
                    <p><?php _e('Confirm your email for full access.', 'softmir'); ?> <a
                            href="<?php echo esc_url(wp_nonce_url(home_url('/login/?resend_verification=' . $user->ID), 'softmir_resend_' . $user->ID)); ?>"><?php _e('Resubmit email', 'softmir'); ?></a>
                    </p>
                </div>
            </div>
            <?php
        endif; ?>

        <!-- Edit Profile -->
        <div class="profile-section">
            <h2>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                <?php _e('Edit profile', 'softmir'); ?>
            </h2>

            <form method="post" class="profile-form">
                <?php wp_nonce_field('softmir_profile_update', 'softmir_profile_nonce'); ?>

                <div class="auth-field">
                    <label for="display_name"><?php _e('Display Name', 'softmir'); ?></label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        <input type="text" id="display_name" name="display_name"
                            value="<?php echo esc_attr($user->display_name); ?>">
                    </div>
                </div>

                <div class="auth-field">
                    <label>Email</label>
                    <div class="auth-input-wrap disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg>
                        <input type="email" value="<?php echo esc_attr($user->user_email); ?>" disabled>
                    </div>
                </div>

                <hr class="profile-divider">

                <h3><?php _e('Andзменить пароль', 'softmir'); ?></h3>
                <p class="auth-field-hint"><?php _e('Leave blank if you do not want to change your password', 'softmir'); ?></p>

                <div class="auth-field">
                    <label for="new_password"><?php _e('New password', 'softmir'); ?></label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password" id="new_password" name="new_password"
                            placeholder="<?php esc_attr_e('Minimum 8 characters', 'softmir'); ?>" minlength="8"
                            autocomplete="new-password">
                        <button type="button" class="toggle-password"
                            aria-label="<?php esc_attr_e('Show password', 'softmir'); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="new_password_confirm"><?php _e('Confirm new password', 'softmir'); ?></label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password" id="new_password_confirm" name="new_password_confirm"
                            placeholder="<?php esc_attr_e('Repeat password', 'softmir'); ?>" minlength="8"
                            autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn"><?php _e('Save changes', 'softmir'); ?></button>
            </form>
        </div>

        <!-- Account Info -->
        <div class="profile-section profile-section-info">
            <h2>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="16" x2="12" y2="12" />
                    <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <?php _e('Account information', 'softmir'); ?>
            </h2>
            <div class="profile-info-grid">
                <div class="info-item">
                    <span class="info-label"><?php _e('Registration date', 'softmir'); ?></span>
                    <span class="info-value"><?php echo date_i18n('d.m.Y', strtotime($user->user_registered)); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php _e('Role', 'softmir'); ?></span>
                    <span class="info-value"><?php echo esc_html($role_label); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php _e('Login', 'softmir'); ?></span>
                    <span class="info-value"><?php echo esc_html($user->user_login); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php _e('Google Integration', 'softmir'); ?></span>
                    <span
                        class="info-value"><?php echo $google_linked ? __('Linked', 'softmir') : __('Not linked', 'softmir'); ?></span>
                </div>
            </div>
        </div>

    </div>
</main>

<?php get_footer(); ?>