<?php
/**
 * SoftZor — PHPUnit Bootstrap
 *
 * Basic unit tests for core functions that don't require WordPress.
 * Run: cd wp-content/themes/softmir && php vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/
 *
 * For tests that mock WordPress functions, we define stubs here.
 */

// Stub WordPress functions used in tested code
if (!function_exists('get_transient')) {
    $GLOBALS['_transients'] = [];
    function get_transient($key)
    {
        return $GLOBALS['_transients'][$key] ?? false;
    }
    function set_transient($key, $value, $expiry = 0)
    {
        $GLOBALS['_transients'][$key] = $value;
        return true;
    }
    function delete_transient($key)
    {
        unset($GLOBALS['_transients'][$key]);
        return true;
    }
}

if (!function_exists('get_option')) {
    $GLOBALS['_options'] = [];
    function get_option($key, $default = false)
    {
        return $GLOBALS['_options'][$key] ?? $default;
    }
    function update_option($key, $value, $autoload = null)
    {
        $GLOBALS['_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($format)
    {
        return date($format);
    }
}

if (!function_exists('wp_mail')) {
    $GLOBALS['_sent_emails'] = [];
    function wp_mail($to, $subject, $message, $headers = [], $attachments = [])
    {
        $GLOBALS['_sent_emails'][] = compact('to', 'subject', 'message');
        return true;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth')
    {
        return 'test-salt-' . $scheme;
    }
}
