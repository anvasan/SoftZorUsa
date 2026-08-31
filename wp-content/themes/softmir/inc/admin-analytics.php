<?php
/**
 * SoftZor Analytics Dashboard v2.0
 * Tabs: Overview / Clicks by Software / Group Buying
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Analytics SoftZor',
        'AI Analytics & Clicks',
        'manage_options',
        'softzor-analytics',
        'softzor_render_analytics_dashboard',
        'dashicons-chart-area',
        2
    );
});

// Enqueue Chart.js on our admin page
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_softzor-analytics') {
        return;
    }
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
});

/**
 * Main dashboard renderer
 */
function softzor_render_analytics_dashboard()
{
    global $wpdb;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    $base_url = admin_url('admin.php?page=softzor-analytics');

    ?>
    <div class="wrap sz-analytics-wrap">
        <h1 class="wp-heading-inline">📊 SoftZor: Analytics</h1>
        <hr class="wp-header-end">

        <h2 class="nav-tab-wrapper sz-tabs">
            <a href="<?php echo esc_url($base_url . '&tab=overview'); ?>"
                class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                📈 Overview
            </a>
            <a href="<?php echo esc_url($base_url . '&tab=clicks'); ?>"
                class="nav-tab <?php echo $active_tab === 'clicks' ? 'nav-tab-active' : ''; ?>">
                🔗 Клики по софту
            </a>
            <a href="<?php echo esc_url($base_url . '&tab=groupbuy'); ?>"
                class="nav-tab <?php echo $active_tab === 'groupbuy' ? 'nav-tab-active' : ''; ?>">
                🤝 Group Buying
            </a>
        </h2>

        <?php
        switch ($active_tab) {
            case 'clicks':
                softzor_tab_clicks();
                break;
            case 'groupbuy':
                softzor_tab_group_buying();
                break;
            default:
                softzor_tab_overview();
        }
        ?>
    </div>

    <style>
        .sz-analytics-wrap {
            max-width: 1200px;
        }

        .sz-tabs {
            margin-bottom: 20px;
        }

        /* KPI Cards Grid */
        .sz-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .sz-kpi-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            transition: box-shadow .2s;
        }

        .sz-kpi-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
        }

        .sz-kpi-card h4 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .sz-kpi-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
        }

        .sz-kpi-value.blue {
            color: #2271b1;
        }

        .sz-kpi-value.green {
            color: #00a32a;
        }

        .sz-kpi-value.orange {
            color: #dba617;
        }

        .sz-kpi-value.red {
            color: #d63638;
        }

        .sz-kpi-sub {
            font-size: 12px;
            color: #8c8f94;
            margin-top: 4px;
        }

        /* Chart containers */
        .sz-chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 960px) {
            .sz-chart-row {
                grid-template-columns: 1fr;
            }
        }

        .sz-chart-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            position: relative;
            height: 300px;
        }
        .sz-chart-box canvas {
            max-height: 240px !important;
        }

        .sz-chart-box h3 {
            margin: 0 0 15px;
            font-size: 14px;
            color: #1d2327;
        }

        /* Data tables */
        .sz-table-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            margin-bottom: 20px;
        }

        .sz-table-box h3 {
            margin: 0 0 15px;
            font-size: 14px;
        }

        .sz-data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sz-data-table th {
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #e0e0e0;
            font-size: 12px;
            text-transform: uppercase;
            color: #646970;
            letter-spacing: .3px;
        }

        .sz-data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f1;
            font-size: 13px;
        }

        .sz-data-table tr:hover td {
            background: #f6f7f7;
        }

        .sz-data-table .num {
            text-align: right;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .sz-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .sz-badge.pending {
            background: #fcf0e3;
            color: #996800;
        }

        .sz-badge.sent {
            background: #e1f5fe;
            color: #0277bd;
        }

        .sz-badge.done {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .sz-empty-state {
            text-align: center;
            padding: 40px;
            color: #8c8f94;
        }

        .sz-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
            color: #c3c4c7;
        }
    </style>
    <?php
}

// ===== TAB 1: OVERVIEW =====
function softzor_tab_overview()
{
    global $wpdb;

    $total_software = wp_count_posts('software')->publish;

    $click_table = $wpdb->prefix . 'softzor_click_log';
    $gb_table = $wpdb->prefix . 'softzor_group_buying';

    // Check if click_log table exists
    $click_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$click_table}'") === $click_table;

    // Bot exclusion pattern for SQL
    $bot_regex = 'bot|spider|crawl|slurp|mediapartners|google|yandex|bing|ia_archiver|headless|chrome-lighthouse|lighthouse|python|curl|wget|postman|insomnia|vercel|netlify|uptime|statuscake|pingdom|uptimerobot';
    $bot_filter = "AND user_agent NOT REGEXP '{$bot_regex}'";

    // Clicks last 30 days
    $clicks_30d = 0;
    $clicks_7d = 0;
    $clicks_today = 0;
    $click_chart_data = [];

    if ($click_table_exists) {
        $clicks_30d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$click_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) {$bot_filter}");
        $clicks_7d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$click_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) {$bot_filter}");
        $clicks_today = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$click_table} WHERE DATE(created_at) = CURDATE() {$bot_filter}");

        // Daily clicks for chart (last 30 days)
        $click_rows = $wpdb->get_results("
            SELECT DATE(created_at) as day, COUNT(*) as cnt
            FROM {$click_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) {$bot_filter}
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ");
        foreach ($click_rows as $r) {
            $click_chart_data[$r->day] = (int) $r->cnt;
        }
    } else {
        // Fallback: use legacy _sz_click_count
        $clicks_30d = (int) $wpdb->get_var("
            SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sz_click_count'
        ");
    }

    // Group buying stats
    $gb_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table}");
    $gb_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE status = 'pending'");
    $gb_30d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

    // Daily group-buy leads for chart (last 30 days)
    $gb_chart_data = [];
    $gb_rows = $wpdb->get_results("
        SELECT DATE(created_at) as day, COUNT(*) as cnt
        FROM {$gb_table}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    foreach ($gb_rows as $r) {
        $gb_chart_data[$r->day] = (int) $r->cnt;
    }

    // Build 30-day date range for charts
    $labels = [];
    $click_values = [];
    $gb_values = [];
    for ($i = 29; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d.m', strtotime($day));
        $click_values[] = $click_chart_data[$day] ?? 0;
        $gb_values[] = $gb_chart_data[$day] ?? 0;
    }

    // Conversion rate
    $conversion = $clicks_30d > 0 ? round(($gb_30d / $clicks_30d) * 100, 1) : 0;

    // QA Score
    $avg_qa = $wpdb->get_var("SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_qa_score'");
    $avg_qa = $avg_qa ? round($avg_qa, 1) : '—';

    ?>
    <!-- KPI Cards -->
    <div class="sz-kpi-grid">
        <div class="sz-kpi-card">
            <h4>Catalog Software</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($total_software); ?></div>
            <div class="sz-kpi-sub">Published</div>
        </div>
        <div class="sz-kpi-card">
            <h4>Клики (30 days)</h4>
            <div class="sz-kpi-value green"><?php echo esc_html($clicks_30d); ?></div>
            <div class="sz-kpi-sub">Today: <?php echo esc_html($clicks_today); ?> · За 7д:
                <?php echo esc_html($clicks_7d); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Заявки (30 days)</h4>
            <div class="sz-kpi-value orange"><?php echo esc_html($gb_30d); ?></div>
            <div class="sz-kpi-sub">Total: <?php echo esc_html($gb_total); ?> · Active:
                <?php echo esc_html($gb_pending); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Conversion</h4>
            <div class="sz-kpi-value <?php echo $conversion > 5 ? 'green' : 'red'; ?>"><?php echo esc_html($conversion); ?>%
            </div>
            <div class="sz-kpi-sub">Заявки / Клики · QA: <?php echo esc_html($avg_qa); ?>/10</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="sz-chart-row">
        <div class="sz-chart-box">
            <h3>🔗 Outbound-клики (30 days)</h3>
            <canvas id="sz-clicks-chart" height="200"></canvas>
        </div>
        <div class="sz-chart-box">
            <h3>🤝 Заявки на совместные закупки (30 days)</h3>
            <canvas id="sz-gb-chart" height="200"></canvas>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;

            var labels = <?php echo wp_json_encode($labels); ?>;
            var clickData = <?php echo wp_json_encode($click_values); ?>;
            var gbData = <?php echo wp_json_encode($gb_values); ?>;

            var sharedOptions = {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            };

            new Chart(document.getElementById('sz-clicks-chart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: clickData,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34,113,177,.1)',
                        fill: true,
                        tension: .3,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: sharedOptions
            });

            new Chart(document.getElementById('sz-gb-chart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: gbData,
                        borderColor: '#dba617',
                        backgroundColor: 'rgba(219,166,23,.1)',
                        fill: true,
                        tension: .3,
                        borderWidth: 2,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: sharedOptions
            });
        });
    </script>
    <?php
}

// ===== TAB 2: CLICKS BY SOFTWARE =====
function softzor_tab_clicks()
{
    global $wpdb;
    $click_table = $wpdb->prefix . 'softzor_click_log';
    $click_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$click_table}'") === $click_table;

    if (!$click_table_exists) {
        echo '<div class="sz-empty-state"><span class="dashicons dashicons-info-outline"></span>';
        echo '<p>The click log table has not yet been created. It will appear automatically after the first transition through <code>/go/slug/</code>.</p></div>';
        return;
    }

    // Bot exclusion pattern for SQL
    $bot_regex = 'bot|spider|crawl|slurp|mediapartners|google|yandex|bing|ia_archiver|headless|chrome-lighthouse|lighthouse|python|curl|wget|postman|insomnia|vercel|netlify|uptime|statuscake|pingdom|uptimerobot';
    $bot_filter = "AND user_agent NOT REGEXP '{$bot_regex}'";

    // Software click stats — from click_log
    $rows = $wpdb->get_results("
        SELECT
            cl.software_id,
            COUNT(*) as total_clicks,
            SUM(CASE WHEN cl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as clicks_7d,
            SUM(CASE WHEN cl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as clicks_30d,
            MAX(cl.created_at) as last_click
        FROM {$click_table} cl
        WHERE 1=1 {$bot_filter}
        GROUP BY cl.software_id
        ORDER BY total_clicks DESC
        LIMIT 100
    ");

    if (empty($rows)) {
        echo '<div class="sz-empty-state"><span class="dashicons dashicons-chart-line"></span>';
        echo '<p>No click data available yet. Clicks will begin to be recorded when clicking through <code>/go/slug/</code>.</p></div>';
        return;
    }

    ?>
    <div class="sz-table-box">
        <h3>🔗 Program clicks (TOP-100)</h3>
        <table class="sz-data-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th class="num">Total</th>
                    <th class="num">7 days</th>
                    <th class="num">30 days</th>
                    <th>Last click</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $title = get_the_title($row->software_id);
                    $edit_link = get_edit_post_link($row->software_id);
                    $permalink = get_permalink($row->software_id);
                    $last = $row->last_click ? date('d.m.Y H:i', strtotime($row->last_click)) : '—';
                    ?>
                    <tr>
                        <td>
                            <strong><a
                                    href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($title ?: '(Deleted #' . $row->software_id . ')'); ?></a></strong>
                            <?php if ($permalink): ?>
                                <br><a href="<?php echo esc_url($permalink); ?>" target="_blank"
                                    style="font-size:11px;color:#8c8f94;">View ↗</a>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo esc_html($row->total_clicks); ?></td>
                        <td class="num"><?php echo esc_html($row->clicks_7d); ?></td>
                        <td class="num"><?php echo esc_html($row->clicks_30d); ?></td>
                        <td><?php echo esc_html($last); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ===== TAB 3: GROUP BUYING =====
function softzor_tab_group_buying()
{
    global $wpdb;
    $gb_table = $wpdb->prefix . 'softzor_group_buying';
    $pools_table = $wpdb->prefix . 'softzor_group_buying_pools';

    // KPI stats
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table}");
    $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE status = 'pending'");
    $in_pools = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE status = 'sent'");
    $total_pools = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$pools_table}");
    $last_30d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $last_7d = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$gb_table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

    ?>
    <div class="sz-kpi-grid">
        <div class="sz-kpi-card">
            <h4>Total заявок</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($total); ?></div>
            <div class="sz-kpi-sub">All time</div>
        </div>
        <div class="sz-kpi-card">
            <h4>Active</h4>
            <div class="sz-kpi-value orange"><?php echo esc_html($pending); ?></div>
            <div class="sz-kpi-sub">Waiting for pool formation</div>
        </div>
        <div class="sz-kpi-card">
            <h4>In pools</h4>
            <div class="sz-kpi-value green"><?php echo esc_html($in_pools); ?></div>
            <div class="sz-kpi-sub">Pools formed: <?php echo esc_html($total_pools); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Dynamics</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($last_30d); ?></div>
            <div class="sz-kpi-sub">For 30d · За 7д: <?php echo esc_html($last_7d); ?></div>
        </div>
    </div>
    <?php

    // Top software by leads
    $top_software = $wpdb->get_results("
        SELECT
            software_id,
            COUNT(*) as total_leads,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leads,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as leads_30d,
            MAX(created_at) as last_lead
        FROM {$gb_table}
        GROUP BY software_id
        ORDER BY total_leads DESC
        LIMIT 50
    ");

    if (empty($top_software)) {
        echo '<div class="sz-empty-state"><span class="dashicons dashicons-groups"></span>';
        echo '<p>There are no applications for joint purchases yet.</p></div>';
        return;
    }

    ?>
    <div class="sz-table-box">
        <h3>🤝 TOP software by joint purchase requests</h3>
        <table class="sz-data-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th class="num">Total</th>
                    <th class="num">Active</th>
                    <th class="num">For 30d</th>
                    <th>Last request</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_software as $row):
                    $title = get_the_title($row->software_id);
                    $edit_link = get_edit_post_link($row->software_id);
                    $last = $row->last_lead ? date('d.m.Y H:i', strtotime($row->last_lead)) : '—';
                    $details_link = admin_url("admin.php?page=softmir-group-buying-details&sw_id={$row->software_id}");
                    ?>
                    <tr>
                        <td><strong><a
                                    href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($title ?: '(Deleted #' . $row->software_id . ')'); ?></a></strong>
                        </td>
                        <td class="num"><?php echo esc_html($row->total_leads); ?></td>
                        <td class="num">
                            <?php if ($row->pending_leads > 0): ?>
                                <span class="sz-badge pending"><?php echo esc_html($row->pending_leads); ?></span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td class="num"><?php echo esc_html($row->leads_30d); ?></td>
                        <td><?php echo esc_html($last); ?></td>
                        <td><a href="<?php echo esc_url($details_link); ?>" class="button button-small">View →</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent leads -->
    <?php
    $recent = $wpdb->get_results("
        SELECT gb.*, p.post_title as software_name
        FROM {$gb_table} gb
        LEFT JOIN {$wpdb->posts} p ON gb.software_id = p.ID
        ORDER BY gb.created_at DESC
        LIMIT 10
    ");

    if (!empty($recent)):
        ?>
        <div class="sz-table-box">
            <h3>📋 Last 10 requests</h3>
            <table class="sz-data-table">
                <thead>
                    <tr>
                        <th>ФAndО</th>
                        <th>Organization</th>
                        <th>Email</th>
                        <th>Software</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $lead):
                        $date = date('d.m.Y H:i', strtotime($lead->created_at));
                        $status_class = $lead->status === 'pending' ? 'pending' : ($lead->status === 'sent' ? 'sent' : 'done');
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($lead->contact_name); ?></strong></td>
                            <td><?php echo esc_html($lead->organization); ?></td>
                            <td><a
                                    href="mailto:<?php echo esc_attr($lead->contact_email); ?>"><?php echo esc_html($lead->contact_email); ?></a>
                            </td>
                            <td><?php echo esc_html($lead->software_name ?: '#' . $lead->software_id); ?></td>
                            <td><span class="sz-badge <?php echo $status_class; ?>"><?php echo esc_html($lead->status); ?></span>
                            </td>
                            <td><?php echo esc_html($date); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php
}
