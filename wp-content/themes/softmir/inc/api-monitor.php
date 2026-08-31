<?php
/**
 * SoftZor — API Call Monitor
 * Tracks Gemini and Firecrawl API usage, errors, and spend.
 * Data stored in wp_options as daily aggregates.
 *
 * Usage: softmir_api_log('gemini', 200);  // success
 *        softmir_api_log('gemini', 429);  // rate limited
 *        softmir_api_log('firecrawl', 200);
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log an API call result.
 *
 * @param string $service  'gemini' or 'firecrawl'
 * @param int    $status   HTTP status code (200 = success, 0 = connection error)
 */
function softmir_api_log($service, $status = 200)
{
    $date = current_time('Y-m-d');
    $key = 'softmir_api_stats_' . $date;
    $stats = get_option($key, []);

    if (!isset($stats[$service])) {
        $stats[$service] = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'rate_limited' => 0,
            'last_error' => '',
            'last_call' => '',
        ];
    }

    $stats[$service]['total']++;
    $stats[$service]['last_call'] = current_time('H:i:s');

    if ($status === 200) {
        $stats[$service]['success']++;
    } elseif ($status === 429) {
        $stats[$service]['rate_limited']++;
        $stats[$service]['errors']++;
        $stats[$service]['last_error'] = '429 Rate Limited @ ' . current_time('H:i:s');
    } else {
        $stats[$service]['errors']++;
        $stats[$service]['last_error'] = $status . ' @ ' . current_time('H:i:s');
    }

    update_option($key, $stats, false); // autoload=false to avoid bloating
}

/**
 * Get API stats for a specific date.
 *
 * @param string|null $date  Date in Y-m-d format, or null for today.
 * @return array
 */
function softmir_api_get_stats($date = null)
{
    $date = $date ?: current_time('Y-m-d');
    return get_option('softmir_api_stats_' . $date, []);
}

/**
 * Check if error rate exceeds threshold and send admin alert.
 * Called after each API log — sends at most 1 email per day per service.
 *
 * @param string $service
 * @param float  $threshold  Error rate threshold (0.3 = 30%)
 */
function softmir_api_check_alert($service, $threshold = 0.3)
{
    $stats = softmir_api_get_stats();
    if (empty($stats[$service]) || $stats[$service]['total'] < 5) {
        return; // Not enough data points
    }

    $error_rate = $stats[$service]['errors'] / $stats[$service]['total'];
    if ($error_rate < $threshold) {
        return; // All good
    }

    // Check if we already sent an alert today for this service
    $alert_key = 'softmir_api_alert_' . $service . '_' . current_time('Y-m-d');
    if (get_transient($alert_key)) {
        return; // Already alerted today
    }

    // Send alert
    $admin_email = get_option('admin_email');
    $subject = sprintf('[SoftZor] ⚠️ API Alert: %s error rate %.0f%%', ucfirst($service), $error_rate * 100);
    $body = sprintf(
        "API monitoring SoftZor detected an increased percentage of errors.\n\n"
        . "Service: %s\n"
        . "Date: %s\n"
        . "Total calls: %d\n"
        . "Successful: %d\n"
        . "Errors: %d (%.0f%%)\n"
        . "Rate Limited (429): %d\n"
        . "Last error: %s\n\n"
        . "Check API limits and account balance.",
        ucfirst($service),
        current_time('Y-m-d H:i'),
        $stats[$service]['total'],
        $stats[$service]['success'],
        $stats[$service]['errors'],
        $error_rate * 100,
        $stats[$service]['rate_limited'],
        $stats[$service]['last_error']
    );

    wp_mail($admin_email, $subject, $body);
    set_transient($alert_key, true, DAY_IN_SECONDS);
}

/**
 * Cleanup old API stats (keep last 30 days).
 * Hooked to daily cron.
 */
function softmir_api_stats_cleanup()
{
    global $wpdb;
    $cutoff = date('Y-m-d', strtotime('-30 days'));
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s",
            'softmir_api_stats_%',
            'softmir_api_stats_' . $cutoff
        )
    );
}
add_action('softmir_daily_cleanup', 'softmir_api_stats_cleanup');

// Schedule daily cleanup if not already scheduled
if (!wp_next_scheduled('softmir_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'softmir_daily_cleanup');
}

/**
 * Admin page: API Monitor Dashboard
 */
function softmir_api_monitor_page()
{
    add_submenu_page(
        'softmir-settings',
        'API Monitor',
        '📊 API Monitor',
        'manage_options',
        'softmir-api-monitor',
        'softmir_api_monitor_render'
    );
}
add_action('admin_menu', 'softmir_api_monitor_page', 30);

function softmir_api_monitor_render()
{
    $days = 7;
    $all_stats = [];
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $all_stats[$date] = softmir_api_get_stats($date);
    }

    echo '<div class="wrap"><h1>📊 SoftZor API Monitor</h1>';
    echo '<style>
        .api-table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        .api-table th, .api-table td { padding: 10px 14px; border: 1px solid #ddd; text-align: center; }
        .api-table th { background: #f0f0f1; font-weight: 600; }
        .api-table .error-high { background: #fce4e4; color: #d63031; font-weight: 700; }
        .api-table .ok { color: #00b894; }
    </style>';

    foreach (['gemini', 'firecrawl'] as $service) {
        echo '<h2>' . ucfirst($service) . '</h2>';
        echo '<table class="api-table">';
        echo '<tr><th>Date</th><th>Total</th><th>✅ Success</th><th>❌ Errors</th><th>🚫 429</th><th>% errors</th><th>Last error</th></tr>';

        foreach ($all_stats as $date => $stats) {
            $s = $stats[$service] ?? ['total' => 0, 'success' => 0, 'errors' => 0, 'rate_limited' => 0, 'last_error' => '-'];
            $rate = $s['total'] > 0 ? round(($s['errors'] / $s['total']) * 100) : 0;
            $class = $rate > 30 ? 'error-high' : ($rate > 0 ? '' : 'ok');
            echo sprintf(
                '<tr><td>%s</td><td>%d</td><td>%d</td><td class="%s">%d</td><td>%d</td><td class="%s">%d%%</td><td>%s</td></tr>',
                $date,
                $s['total'],
                $s['success'],
                $class,
                $s['errors'],
                $s['rate_limited'],
                $class,
                $rate,
                esc_html($s['last_error'] ?: '-')
            );
        }
        echo '</table>';
    }
    echo '</div>';
}
