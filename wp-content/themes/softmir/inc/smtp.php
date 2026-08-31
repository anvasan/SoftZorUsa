<?php
/**
 * SoftMir Gmail SMTP Configuration
 * Configures WordPress PHPMailer to use Gmail SMTP
 */

// ========== Override From Address (runs before PHPMailer) ==========
function softmir_mail_from($from)
{
    $smtp_user = get_option('softmir_smtp_user', '');
    return !empty($smtp_user) ? $smtp_user : $from;
}
add_filter('wp_mail_from', 'softmir_mail_from');

function softmir_mail_from_name($name)
{
    $from_name = get_option('softmir_smtp_from_name', '');
    return !empty($from_name) ? $from_name : $name;
}
add_filter('wp_mail_from_name', 'softmir_mail_from_name');

// ========== Configure PHPMailer for Gmail SMTP ==========
function softmir_smtp_config($phpmailer)
{
    $smtp_user = get_option('softmir_smtp_user', '');
    $smtp_pass = get_option('softmir_smtp_pass', '');
    $from_name = get_option('softmir_smtp_from_name', get_bloginfo('name'));

    if (empty($smtp_user) || empty($smtp_pass))
        return;

    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587;
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->Username = $smtp_user;
    $phpmailer->Password = $smtp_pass;
    $phpmailer->From = $smtp_user;
    $phpmailer->FromName = $from_name;
}
add_action('phpmailer_init', 'softmir_smtp_config');

// ========== Admin Settings Page ==========
function softmir_auth_settings_menu()
{
    add_options_page(
        'SoftMir Auth',
        'SoftMir Auth',
        'manage_options',
        'softmir-auth',
        'softmir_auth_settings_page'
    );
}
add_action('admin_menu', 'softmir_auth_settings_menu');

function softmir_auth_settings_init()
{
    // SMTP Section
    add_settings_section('softmir_smtp_section', 'Gmail SMTP', function () {
        echo '<p>' . esc_html__('Configure Gmail SMTP to send Email (verification, password reset).', 'softmir') . '</p>';
        echo '<p><small>' . sprintf(__('Andспользуйте %sApp Password%s (требуется 2FA).', 'softmir'), '<a href="https://myaccount.google.com/apppasswords" target="_blank">', '</a>') . '</small></p>';
    }, 'softmir-auth');

    // SMTP fields
    register_setting('softmir_auth_settings', 'softmir_smtp_user', ['sanitize_callback' => 'sanitize_email']);
    register_setting('softmir_auth_settings', 'softmir_smtp_pass', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('softmir_auth_settings', 'softmir_smtp_from_name', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_field('softmir_smtp_user', __('Gmail address', 'softmir'), function () {
        $val = get_option('softmir_smtp_user', '');
        echo "<input type='email' name='softmir_smtp_user' value='" . esc_attr($val) . "' class='regular-text' placeholder='your@gmail.com'>";
    }, 'softmir-auth', 'softmir_smtp_section');

    add_settings_field('softmir_smtp_pass', 'App Password', function () {
        $val = get_option('softmir_smtp_pass', '');
        echo "<input type='password' name='softmir_smtp_pass' value='" . esc_attr($val) . "' class='regular-text' placeholder='xxxx xxxx xxxx xxxx'>";
    }, 'softmir-auth', 'softmir_smtp_section');

    add_settings_field('softmir_smtp_from_name', __('Andмя отправителя', 'softmir'), function () {
        $val = get_option('softmir_smtp_from_name', get_bloginfo('name'));
        echo "<input type='text' name='softmir_smtp_from_name' value='" . esc_attr($val) . "' class='regular-text'>";
    }, 'softmir-auth', 'softmir_smtp_section');

    // Google OAuth Section
    add_settings_section('softmir_google_section', 'Google OAuth', function () {
        echo '<p>' . esc_html__('Configure Google OAuth for Google login.', 'softmir') . '</p>';
        echo '<p><small>' . sprintf(__('Create a project in %sGoogle Cloud Console%s.', 'softmir'), '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">', '</a>') . '</small></p>';
        echo '<p><strong>Redirect URI:</strong> <code>' . esc_html(home_url('/?softmir_google_callback=1')) . '</code></p>';
    }, 'softmir-auth');

    register_setting('softmir_auth_settings', 'softmir_google_client_id', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('softmir_auth_settings', 'softmir_google_client_secret', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_field('softmir_google_client_id', 'Client ID', function () {
        $val = get_option('softmir_google_client_id', '');
        echo "<input type='text' name='softmir_google_client_id' value='" . esc_attr($val) . "' class='regular-text' placeholder='xxxxx.apps.googleusercontent.com'>";
    }, 'softmir-auth', 'softmir_google_section');

    add_settings_field('softmir_google_client_secret', 'Client Secret', function () {
        $val = get_option('softmir_google_client_secret', '');
        echo "<input type='password' name='softmir_google_client_secret' value='" . esc_attr($val) . "' class='regular-text'>";
    }, 'softmir-auth', 'softmir_google_section');
}
add_action('admin_init', 'softmir_auth_settings_init');

// ========== Settings Page Render ==========
function softmir_auth_settings_page()
{
    if (!current_user_can('manage_options'))
        return;

    // Handle test email
    if (isset($_POST['softmir_test_email']) && check_admin_referer('softmir_test_email')) {
        $to = get_option('softmir_smtp_user', get_option('admin_email'));
        $smtp_user = get_option('softmir_smtp_user', '');
        $smtp_pass = get_option('softmir_smtp_pass', '');

        // Pre-flight checks
        if (empty($smtp_user) || empty($smtp_pass)) {
            echo '<div class="notice notice-error"><p>❌ ' . esc_html__('First enter Gmail address and App Password above and save settings.', 'softmir') . '</p></div>';
        }
        elseif (!extension_loaded('openssl')) {
            echo '<div class="notice notice-error"><p>❌ ' . esc_html__('PHP OpenSSL extension not enabled. Required for SMTP with TLS.', 'softmir') . '</p></div>';
        }
        else {
            // Capture PHPMailer errors
            $mail_error = '';
            $error_handler = function ($wp_error) use (&$mail_error) {
                $mail_error = $wp_error->get_error_message();
                $data = $wp_error->get_error_data();
                if (!empty($data['phpmailer_exception_code'])) {
                    $mail_error .= ' (' . __('code:', 'softmir') . ' ' . $data['phpmailer_exception_code'] . ')';
                }
            };
            add_action('wp_mail_failed', $error_handler);

            $subject = '[SoftMir] ' . __('SMTP Test Email', 'softmir');
            $message = '<html><body style="font-family:Inter,sans-serif;padding:40px;">
                <h2 style="color:#0ea5e9;">✅ ' . esc_html__('SMTP works!', 'softmir') . '</h2>
                <p style="color:#475569;">' . esc_html__('This is a test email from SoftMir. If you see this — SMTP settings are correct.', 'softmir') . '</p>
                <p style="color:#94a3b8;font-size:13px;">' . date('Y-m-d H:i:s') . '</p>
            </body></html>';
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($to, $subject, $message, $headers);

            remove_action('wp_mail_failed', $error_handler);

            if ($sent) {
                echo '<div class="notice notice-success"><p>✅ ' . sprintf(esc_html__('Test email sent to %s', 'softmir'), esc_html($to)) . '</p></div>';
            }
            else {
                echo '<div class="notice notice-error"><p>❌ ' . esc_html__('Send error.', 'softmir') . '</p>';
                if ($mail_error) {
                    echo '<p><strong>' . esc_html__('Reason:', 'softmir') . '</strong> ' . esc_html($mail_error) . '</p>';
                }
                echo '<p><strong>' . esc_html__('Check:', 'softmir') . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
                echo '<li>' . esc_html__('Is 2FA enabled in Google account', 'softmir') . '</li>';
                echo '<li>' . esc_html__('Is App Password correct (16 chars, no spaces)', 'softmir') . '</li>';
                echo '<li>' . esc_html__('Is SMTP port 587 blocked (firewall/antivirus)', 'softmir') . '</li>';
                echo '</ul>';
                echo '<p><small>SMTP ' . esc_html__('host:', 'softmir') . ' smtp.gmail.com:587 | ' . esc_html__('User:', 'softmir') . ' ' . esc_html($smtp_user) . ' | ' . esc_html__('Password:', 'softmir') . ' ' . str_repeat('•', min(strlen($smtp_pass), 16)) . '</small></p>';
                echo '</div>';
            }
        }
    }

?>
    <div class="wrap">
        <h1>SoftMir Auth — <?php esc_html_e('Settings', 'softmir'); ?></h1>
        <form method="post" action="options.php">
            <?php
    settings_fields('softmir_auth_settings');
    do_settings_sections('softmir-auth');
    submit_button(__('Save settings', 'softmir'));
?>
        </form>

        <hr>
        <h2><?php esc_html_e('Test email', 'softmir'); ?></h2>
        <p><?php echo sprintf(esc_html__('Submit test email на %s', 'softmir'), '<strong>' . esc_html(get_option('softmir_smtp_user', get_option('admin_email'))) . '</strong>'); ?></p>
        <form method="post">
            <?php wp_nonce_field('softmir_test_email'); ?>
            <button type="submit" name="softmir_test_email" class="button button-secondary">📧 <?php esc_html_e('Submit test email', 'softmir'); ?></button>
        </form>
    </div>
    <?php
}
