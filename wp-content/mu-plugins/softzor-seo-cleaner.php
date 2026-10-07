<?php
/**
 * Plugin Name: SoftZor USA - Legacy 410 Guard & Smart Dev Indexing
 * Description: Permanently removes legacy /listing/ URLs (410 Gone) and prevents staging/coming-soon indexing automatically.
 * Version: 1.0.1
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

// =========================================================================
// 1. PERMANENT GUARD: Any legacy /listing/ URL returns 410 Gone immediately
// =========================================================================
$softzor_request_uri = $_SERVER['REQUEST_URI'] ?? '';
$softzor_path = parse_url($softzor_request_uri, PHP_URL_PATH);

if (!empty($softzor_path) && preg_match('#^/listings?(?:/|$)#i', $softzor_path)) {
    status_header(410);
    header('HTTP/1.1 410 Gone');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo "410 Gone: Legacy listing resource removed permanently.";
    exit;
}

// =========================================================================
// 2. SMART DEV MODE: Auto-detect Coming Soon / Staging mode
// =========================================================================
/**
 * Checks whether the site is in Coming Soon / Maintenance mode.
 * When SeedProd is disabled and blog_public is enabled, this automatically
 * returns false, allowing full Google indexing without code changes.
 */
function softzor_is_coming_soon_or_dev() {
    // Check WordPress native "Discourage search engines from indexing this site"
    if (get_option('blog_public') === '0') {
        return true;
    }

    // Check SeedProd Coming Soon / Maintenance mode settings (stored as JSON string)
    $seedprod_raw = get_option('seedprod_settings');
    $seedprod = is_string($seedprod_raw) ? json_decode($seedprod_raw, true) : (is_array($seedprod_raw) ? $seedprod_raw : []);

    if (!empty($seedprod['enable_coming_soon_mode']) || !empty($seedprod['enable_maintenance_mode'])) {
        return true;
    }

    return false;
}

// Intercept robots.txt when in coming-soon/dev mode before SeedProd or template loads
if ($softzor_path === '/robots.txt') {
    if (softzor_is_coming_soon_or_dev()) {
        status_header(200);
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-cache, must-revalidate');
        echo "User-agent: *\nDisallow: /\n";
        exit;
    }
}

// Send HTTP header X-Robots-Tag when in coming-soon/dev mode
add_action('send_headers', function () {
    if (softzor_is_coming_soon_or_dev()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
});

// Immediate header fallback in case send_headers is bypassed by early template exit (SeedProd)
if (!headers_sent() && softzor_is_coming_soon_or_dev()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

// Inject meta tag in wp_head
add_action('wp_head', function () {
    if (softzor_is_coming_soon_or_dev()) {
        echo '<meta name="robots" content="noindex, nofollow, noarchive">' . "\n";
    }
}, -9999);
