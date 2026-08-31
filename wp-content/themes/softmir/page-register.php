<?php
/**
 * Template Name: Register Page
 * Page for user registration
 */
get_header();
$google_url = function_exists('softmir_google_auth_url') ? softmir_google_auth_url() : '';
?>

<main class="auth-page">
    <div class="auth-card">
        <div class="auth-card-header">
            <div class="auth-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            </div>
            <h1><?php _e('Sign Up', 'softmir'); ?></h1>
            <p><?php _e('Create an account to access the platform', 'softmir'); ?></p>
        </div>

        <?php echo softmir_flash_html(); ?>

        <?php if ($google_url): ?>
        <a href="<?php echo esc_url($google_url); ?>" class="auth-social-btn auth-google-btn">
            <svg viewBox="0 0 24 24" width="20" height="20">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <?php _e('Sign Up with Google', 'softmir'); ?>
        </a>

        <div class="auth-divider">
            <span><?php _e('or', 'softmir'); ?></span>
        </div>
        <?php
endif; ?>

        <form method="post" class="auth-form" id="register-form">
            <?php wp_nonce_field('softmir_register', 'softmir_register_nonce'); ?>

            <div class="auth-field">
                <label for="username"><?php _e('Username', 'softmir'); ?></label>
                <div class="auth-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" id="username" name="username" placeholder="<?php esc_attr_e('Minimum 3 characters', 'softmir'); ?>" required minlength="3" autocomplete="username" value="<?php echo esc_attr($_POST['username'] ?? ''); ?>">
                </div>
            </div>

            <div class="auth-field">
                <label for="email">Email</label>
                <div class="auth-input-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email" value="<?php echo esc_attr($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="auth-field">
                <label for="password"><?php _e('Password', 'softmir'); ?></label>
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

            <div class="auth-field">
                <label for="role"><?php _e('Account type', 'softmir'); ?></label>
                <div class="auth-select-wrap">
                    <select id="role" name="role">
                        <option value="subscriber"><?php _e('👤 User', 'softmir'); ?></option>
                        <option value="vendor"><?php _e('🏢 Andнтегратор (Vendor)', 'softmir'); ?></option>
                    </select>
                </div>
            </div>

            <button type="submit" class="auth-submit-btn"><?php _e('Create account', 'softmir'); ?></button>

            <p class="auth-terms">
                <?php printf(__('Регистрируясь, вы принимаете %1$sTerms of Use%2$s и %3$sПолитику конфиденциальности%4$s.', 'softmir'), '<a href="' . home_url('/terms/') . '">', '</a>', '<a href="' . home_url('/privacy-policy/') . '">', '</a>'); ?>
            </p>
        </form>

        <p class="auth-footer-text">
            <?php _e('Already have an account?', 'softmir'); ?> <a href="<?php echo home_url('/login/'); ?>" class="auth-link-accent"><?php _e('Log In', 'softmir'); ?></a>
        </p>
    </div>
</main>

<?php get_footer(); ?>
