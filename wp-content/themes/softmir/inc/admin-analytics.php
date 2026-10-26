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
        'Аналитика SoftZor',
        'Аналитика ИИ & Клики',
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
        <h1 class="wp-heading-inline">📊 SoftZor: Аналитика</h1>
        <hr class="wp-header-end">

        <h2 class="nav-tab-wrapper sz-tabs">
            <a href="<?php echo esc_url($base_url . '&tab=overview'); ?>"
                class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                📈 Обзор
            </a>
            <a href="<?php echo esc_url($base_url . '&tab=clicks'); ?>"
                class="nav-tab <?php echo $active_tab === 'clicks' ? 'nav-tab-active' : ''; ?>">
                🔗 Клики по софту
            </a>
            <a href="<?php echo esc_url($base_url . '&tab=groupbuy'); ?>"
                class="nav-tab <?php echo $active_tab === 'groupbuy' ? 'nav-tab-active' : ''; ?>">
                🤝 Совместные закупки
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
            overflow-x: auto;
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
            overflow-x: auto;
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

    // Инициализация таблиц Pretty Links
    $table_links  = $wpdb->prefix . 'prli_links';
    $table_clicks = $wpdb->prefix . 'prli_clicks';
    $clicks_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_clicks}'") === $table_clicks;
    $clicks_30d   = 0;
    $clicks_7d    = 0;
    $clicks_today = 0;
    $click_chart_data = [];
    if ($clicks_table_exists) {
        // Клики без ботов (robot = 0)
        $clicks_30d = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table_clicks} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND robot = 0
        ");
        
        $clicks_7d = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table_clicks} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND robot = 0
        ");
        
        $clicks_today = (int) $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table_clicks} 
            WHERE DATE(created_at) = CURDATE() AND robot = 0
        ");
        // Данные для графика Chart.js по дням (за последние 30 дней)
        $click_rows = $wpdb->get_results("
            SELECT DATE(created_at) as day, COUNT(*) as cnt
            FROM {$table_clicks}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND robot = 0
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ");
        foreach ($click_rows as $r) {
            $click_chart_data[$r->day] = (int) $r->cnt;
        }
    }

    // Group buying stats
    $gb_table = $wpdb->prefix . 'softzor_group_buying';
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
            <h4>Каталог ПО</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($total_software); ?></div>
            <div class="sz-kpi-sub">Опубликовано</div>
        </div>
        <div class="sz-kpi-card">
            <h4>Клики (30 дней)</h4>
            <div class="sz-kpi-value green"><?php echo esc_html($clicks_30d); ?></div>
            <div class="sz-kpi-sub">Сегодня: <?php echo esc_html($clicks_today); ?> · За 7д:
                <?php echo esc_html($clicks_7d); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Заявки (30 дней)</h4>
            <div class="sz-kpi-value orange"><?php echo esc_html($gb_30d); ?></div>
            <div class="sz-kpi-sub">Всего: <?php echo esc_html($gb_total); ?> · Активных:
                <?php echo esc_html($gb_pending); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Конверсия</h4>
            <div class="sz-kpi-value <?php echo $conversion > 5 ? 'green' : 'red'; ?>"><?php echo esc_html($conversion); ?>%
            </div>
            <div class="sz-kpi-sub">Заявки / Клики · QA: <?php echo esc_html($avg_qa); ?>/10</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="sz-chart-row">
        <div class="sz-chart-box">
            <h3>🔗 Outbound-клики (30 дней)</h3>
            <canvas id="sz-clicks-chart" height="200"></canvas>
        </div>
        <div class="sz-chart-box">
            <h3>🤝 Заявки на совместные закупки (30 дней)</h3>
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
    $table_links  = $wpdb->prefix . 'prli_links';
    $table_clicks = $wpdb->prefix . 'prli_clicks';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_links}'") === $table_links;
    if (!$table_exists) {
        echo '<div class="sz-empty-state"><span class="dashicons dashicons-info-outline"></span>';
        echo '<p>Плагин Pretty Links не активирован или таблицы не найдены.</p></div>';
        return;
    }
    // Запрос статистики по каждой ссылке
    $rows = $wpdb->get_results("
        SELECT 
            pl.id as link_id,
            pl.name as link_name,
            pl.slug,
            pl.url as target_url,
            pl.clicks as total_clicks,
            pl.uniques as unique_clicks,
            COUNT(CASE WHEN pc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND pc.robot = 0 THEN 1 END) as clicks_7d,
            COUNT(CASE WHEN pc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND pc.robot = 0 THEN 1 END) as clicks_30d,
            MAX(pc.created_at) as last_click
        FROM {$table_links} pl
        LEFT JOIN {$table_clicks} pc ON pl.id = pc.link_id
        GROUP BY pl.id
        HAVING total_clicks > 0
        ORDER BY total_clicks DESC
        LIMIT 100
    ");
    if (empty($rows)) {
        echo '<div class="sz-empty-state"><span class="dashicons dashicons-chart-line"></span>';
        echo '<p>Пока нет зафиксированных переходов по ссылкам.</p></div>';
        return;
    }
    ?>
    <div class="sz-table-box">
        <h3>🔗 Исходящие переходы по программам (Pretty Links ТОП-100)</h3>
        <table class="sz-data-table">
            <thead>
                <tr>
                    <th style="min-width:240px;max-width:340px;">Программа / Ссылка</th>
                    <th style="width:140px;white-space:nowrap;">Короткая ссылка</th>
                    <th style="width:180px;max-width:200px;">Партнёрский кабинет</th>
                    <th class="num">Всего кликов</th>
                    <th class="num">Уникальных</th>
                    <th class="num">За 7 дней</th>
                    <th class="num">За 30 дней</th>
                    <th>Последний переход</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): 
                    // Поиск карточки софта по слагу
                    // Поиск карточки софта: 1) по _prli_id, 2) по pretty_link, 3) по слагу
                    $sw_post_id = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'software' AND p.post_status IN ('publish','draft','pending','private') AND pm.meta_key = '_prli_id' AND pm.meta_value = %s LIMIT 1",
                        (string) $row->link_id
                    ));
                    if (!$sw_post_id) {
                        $like_slug = '%/' . $wpdb->esc_like($row->slug) . '/%';
                        $sw_post_id = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'software' AND p.post_status IN ('publish','draft','pending','private') AND pm.meta_key = 'pretty_link' AND pm.meta_value LIKE %s LIMIT 1",
                            $like_slug
                        ));
                    }
                    $software_post = $sw_post_id ? get_post($sw_post_id) : get_page_by_path($row->slug, OBJECT, 'software');
                    if (!$software_post) {
                        $software_post = get_page_by_path($row->slug . '-review', OBJECT, 'software') ?: get_page_by_path($row->slug . '-obzor', OBJECT, 'software');
                    }
                    $partner_cabinet = '';
                    if ($software_post) {
                        $partner_cabinet = function_exists('softmir_get_field_with_lang_fallback')
                            ? trim((string) softmir_get_field_with_lang_fallback('partner_cabinet', $software_post->ID))
                            : trim((string) get_post_meta($software_post->ID, 'partner_cabinet', true));
                    }
                    $card_link = $software_post ? get_permalink($software_post->ID) : '';
                    $clean_name = str_replace('Партнерка: ', '', $row->link_name);
                    $last = $row->last_click ? date('d.m.Y H:i', strtotime($row->last_click)) : '—';
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($clean_name); ?></strong>
                            <?php if ($card_link): ?>
                                <br><a href="<?php echo esc_url($card_link); ?>" target="_blank" style="font-size:11px;color:#2271b1;">Карточка на сайте ↗</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code>/<?php echo esc_html($row->slug); ?></code>
                            <br><a href="<?php echo esc_url($row->target_url); ?>" target="_blank" rel="nofollow" style="font-size:11px;color:#8c8f94;">Партнерский URL ↗</a>
                        </td>
                        <td style="width:180px;max-width:200px;word-break:break-word;">
                            <?php
                            if ($partner_cabinet !== '') {
                                $cabinet_urls = [];
                                $cabinet_note = '';
                                if (preg_match_all('~(https?://[^\s<>"]+)~i', $partner_cabinet, $m)) {
                                    $cabinet_urls = array_values(array_unique($m[1]));
                                    $cabinet_note = trim(preg_replace('~https?://[^\s<>"]+~i', '', $partner_cabinet), " \t\n\r\0\x0B-|—:");
                                } elseif (preg_match('~^([a-z0-9.-]+\.[a-z]{2,}(?:/[^\s<>"]*)?)$~i', $partner_cabinet, $m)) {
                                    $cabinet_urls = ['https://' . $m[1]];
                                }
                                if (!empty($cabinet_urls)) {
                                    echo '<div style="display:flex;flex-direction:column;align-items:flex-start;gap:4px;">';
                                    foreach ($cabinet_urls as $c_url) {
                                        $host = preg_replace('/^www\./i', '', (string) parse_url($c_url, PHP_URL_HOST));
                                        echo '<a href="' . esc_url($c_url) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($c_url) . '" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;color:#1d4ed8;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;">🔑 ' . esc_html($host ?: 'Кабинет') . ' ↗</a>';
                                    }
                                    if ($cabinet_note !== '') {
                                        echo '<div style="font-size:11px;color:#646970;">' . esc_html($cabinet_note) . '</div>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<span style="display:inline-block;padding:3px 8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;color:#475569;font-size:12px;">ℹ️ ' . esc_html($partner_cabinet) . '</span>';
                                }
                            } else {
                                echo '<span style="color:#c3c4c7;">—</span>';
                            }
                            ?>
                        </td>
                        <td class="num"><strong><?php echo esc_html($row->total_clicks); ?></strong></td>
                        <td class="num"><?php echo esc_html($row->unique_clicks); ?></td>
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
            <h4>Всего заявок</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($total); ?></div>
            <div class="sz-kpi-sub">За всё время</div>
        </div>
        <div class="sz-kpi-card">
            <h4>Активных</h4>
            <div class="sz-kpi-value orange"><?php echo esc_html($pending); ?></div>
            <div class="sz-kpi-sub">Ожидают формирования пула</div>
        </div>
        <div class="sz-kpi-card">
            <h4>В пулах</h4>
            <div class="sz-kpi-value green"><?php echo esc_html($in_pools); ?></div>
            <div class="sz-kpi-sub">Сформировано пулов: <?php echo esc_html($total_pools); ?></div>
        </div>
        <div class="sz-kpi-card">
            <h4>Динамика</h4>
            <div class="sz-kpi-value blue"><?php echo esc_html($last_30d); ?></div>
            <div class="sz-kpi-sub">За 30д · За 7д: <?php echo esc_html($last_7d); ?></div>
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
        echo '<p>Пока нет заявок на совместные закупки.</p></div>';
        return;
    }

    ?>
    <div class="sz-table-box">
        <h3>🤝 ТОП-софт по заявкам на совместные закупки</h3>
        <table class="sz-data-table">
            <thead>
                <tr>
                    <th>Программа</th>
                    <th class="num">Всего</th>
                    <th class="num">Активных</th>
                    <th class="num">За 30д</th>
                    <th>Последняя заявка</th>
                    <th>Действия</th>
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
                                    href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($title ?: '(Удалён #' . $row->software_id . ')'); ?></a></strong>
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
                        <td><a href="<?php echo esc_url($details_link); ?>" class="button button-small">Смотреть →</a></td>
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
            <h3>📋 Последние 10 заявок</h3>
            <table class="sz-data-table">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Организация</th>
                        <th>Email</th>
                        <th>ПО</th>
                        <th>Статус</th>
                        <th>Дата</th>
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
