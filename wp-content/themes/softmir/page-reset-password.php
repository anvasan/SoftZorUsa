<?php
/**
 * Template Name: Reset Password Page
 * Password reset request + new password form
 */
get_header();

// Check if we have a reset token (step 2)
$reset_token = sanitize_text_field($_GET['softmir_reset'] ?? '');
$reset_user_id = intval($_GET['user_id'] ?? 0);
$is_reset_step = !empty($reset_token) && $reset_user_id > 0;
?>

<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card-header">
            <div class="auth-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <?php if ($is_reset_step): ?>
                <h1><?php _e('New password', 'softmir'); ?></h1>
                <p><?php _e('Enter a new password for your account', 'softmir'); ?></p>
            <?php
else: ?>
                <h1><?php _e('Password reset', 'softmir'); ?></h1>
                <p><?php _e('Enter email to get reset link', 'softmir'); ?></p>
            <?php
endif; ?>
        </div>

        <?php echo softmir_flash_html(); ?>

        <?php if ($is_reset_step): ?>
            <!-- Step 2: Enter new password -->
            <form method="post" class="auth-form" id="reset-password-form">
                <?php wp_nonce_field('softmir_reset_password', 'softmir_reset_password_nonce'); ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($reset_user_id); ?>">
                <input type="hidden" name="token" value="<?php echo esc_attr($reset_token); ?>">

                <div class="auth-field">
                    <label for="password"><?php _e('New password', 'softmir'); ?></label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" placeholder="<?php esc_attr_e('Minimum 8 characters', 'softmir'); ?>" required minlength="8" autocomplete="new-password">
                        <button type="button" class="toggle-password" aria-label="<?php esc_attr_e('Show password', 'softmir'); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="password-strength" id="password-strength"></div>
                </div>

                <div class="auth-field">
                    <label for="password_confirm"><?php _e('Confirm password', 'softmir'); ?></label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password_confirm" name="password_confirm" placeholder="<?php esc_attr_e('Repeat password', 'softmir'); ?>" required minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn"><?php _e('Set a new password', 'softmir'); ?></button>
            </form>
        <?php
else: ?>
            <!-- Step 1: Request reset -->
            <form method="post" class="auth-form" id="reset-request-form">
                <?php wp_nonce_field('softmir_reset_request', 'softmir_reset_request_nonce'); ?>

                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn"><?php _e('Submit link', 'softmir'); ?></button>
            </form>
        <?php
endif; ?>

        <p class="auth-footer-text">
            <a href="<?php echo home_url('/login/'); ?>" class="auth-link-accent"><?php _e('← Back to login', 'softmir'); ?></a>
        </p>
    </div>
</main>

<?php get_footer(); ?>
