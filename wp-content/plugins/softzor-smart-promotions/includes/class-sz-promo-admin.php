<?php
if (!defined('ABSPATH')) {
    exit;
}

class SZ_Promo_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_sz_promo_reset_stats', [$this, 'handle_reset_stats']);
    }

    public function add_menu_pages() {
        add_submenu_page(
            'edit.php?post_type=sz_promo',
            '📊 Аналитика и CTR',
            '📊 Аналитика и CTR',
            'manage_options',
            'sz-promo-analytics',
            [$this, 'render_analytics_page']
        );

        add_submenu_page(
            'edit.php?post_type=sz_promo',
            '⚙️ Настройки показа',
            '⚙️ Настройки показа',
            'manage_options',
            'sz-promo-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('sz_promo_settings_group', 'sz_promo_options');
    }

    public function render_analytics_page() {
        $stats = SZ_Promo_DB::get_stats();

        // Подготавливаем сводку
        $total_views = 0;
        $total_clicks = 0;
        foreach ($stats as $row) {
            $total_views += absint($row['views']);
            $total_clicks += absint($row['clicks']);
        }
        $avg_ctr = $total_views > 0 ? round(($total_clicks / $total_views) * 100, 2) : 0;
        ?>
        <div class="wrap">
            <h1 style="display:flex; align-items:center; gap: 10px;">
                <span>📊 Аналитика промо-баннеров и офферов</span>
            </h1>

            <div style="display:flex; gap: 20px; margin: 20px 0;">
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; flex:1; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px; color:#64748b; font-weight:600;">Всего показов</div>
                    <div style="font-size:28px; font-weight:700; color:#0f172a; margin-top:4px;"><?php echo number_format_i18n($total_views); ?></div>
                </div>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; flex:1; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px; color:#64748b; font-weight:600;">Всего кликов по ссылкам</div>
                    <div style="font-size:28px; font-weight:700; color:#2563eb; margin-top:4px;"><?php echo number_format_i18n($total_clicks); ?></div>
                </div>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; flex:1; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px; color:#64748b; font-weight:600;">Средний CTR сайта</div>
                    <div style="font-size:28px; font-weight:700; color:#16a34a; margin-top:4px;"><?php echo $avg_ctr; ?>%</div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2 style="margin:0; font-size:18px;">Детализация по баннерам и плейсментам</h2>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Вы уверены, что хотите сбросить всю накопленную статистику?');">
                        <?php wp_nonce_field('sz_reset_stats_action', 'sz_reset_nonce'); ?>
                        <input type="hidden" name="action" value="sz_promo_reset_stats">
                        <button type="submit" class="button button-secondary" style="color:#dc2626;">Сбросить всю статистику</button>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:25%;">Оффер / Баннер</th>
                            <th>Место показа</th>
                            <th>Показы</th>
                            <th>Клики</th>
                            <th>CTR (%)</th>
                            <th>Текущий вес в ротации</th>
                            <th>Статус</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:20px; color:#64748b;">Данных пока нет. Добавьте первый баннер и активируйте ротацию!</td></tr>
                        <?php else: ?>
                            <?php foreach ($stats as $row): 
                                $promo_id = absint($row['promo_id']);
                                $placement = esc_html($row['placement']);
                                $views = absint($row['views']);
                                $clicks = absint($row['clicks']);
                                $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
                                $post_title = get_the_title($promo_id);
                                $is_active = get_post_meta($promo_id, '_sz_is_active', true) !== 'no';
                                $base_weight = absint(get_post_meta($promo_id, '_sz_base_weight', true)) ?: 5;
                                
                                // Сглаженный вес ротации
                                $score = (($clicks + ($base_weight * 0.5)) / ($views + 10)) * 100;
                                
                                $badge_bg = '#f1f5f9';
                                $badge_color = '#475569';
                                if ($ctr >= 3.0) { $badge_bg = '#dcfce7'; $badge_color = '#15803d'; }
                                elseif ($ctr >= 1.0) { $badge_bg = '#fef9c3'; $badge_color = '#a16207'; }
                            ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?php echo get_edit_post_link($promo_id); ?>"><?php echo esc_html($post_title ?: 'Баннер #' . $promo_id); ?></a></strong>
                                    </td>
                                    <td><code><?php echo $placement; ?></code></td>
                                    <td><?php echo number_format_i18n($views); ?></td>
                                    <td><strong><?php echo number_format_i18n($clicks); ?></strong></td>
                                    <td>
                                        <span style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:3px 8px; border-radius:12px; font-weight:700; font-size:12px;">
                                            <?php echo $ctr; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <div style="background:#e2e8f0; width:60px; height:8px; border-radius:4px; overflow:hidden;">
                                                <div style="background:#2563eb; width:<?php echo min(100, max(5, round($score * 5))); ?>%; height:100%;"></div>
                                            </div>
                                            <span style="font-size:12px; color:#64748b;"><?php echo round($score, 1); ?> pt</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $is_active ? '<span style="color:#16a34a; font-weight:600;">● Активен</span>' : '<span style="color:#94a3b8;">⏸ Пауза</span>'; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($promo_id); ?>" class="button button-small">Изменить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        $options = get_option('sz_promo_options', []);
        $auto_in_content = !empty($options['auto_in_content']);
        $in_content_para = !empty($options['in_content_para']) ? absint($options['in_content_para']) : 2;
        $enable_top_bar = !empty($options['enable_top_bar']);
        ?>
        <div class="wrap">
            <h1>⚙️ Настройки вывода промо-баннеров</h1>

            <form method="post" action="options.php" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; max-width:800px; margin-top:20px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <?php settings_fields('sz_promo_settings_group'); ?>

                <h2>1. Автоматическая вставка в контент (In-Content)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Авто-вставка в статьи</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sz_promo_options[auto_in_content]" value="1" <?php checked($auto_in_content); ?>>
                                Автоматически выводить промо-плашку внутри статей блога и карточек софта
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Позиция вставки</th>
                        <td>
                            Вставлять после <input type="number" min="1" max="10" name="sz_promo_options[in_content_para]" value="<?php echo esc_attr($in_content_para); ?>" style="width:70px;"> абзаца текста.
                            <p class="description">Оптимально: после 2-го или 3-го абзаца (в зоне максимального внимания читателя).</p>
                        </td>
                    </tr>
                </table>

                <hr style="border:0; border-top:1px solid #e2e8f0; margin:24px 0;">

                <h2>2. Сквозная промо-полоса в шапке (Top Bar)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Включить Top Bar</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sz_promo_options[enable_top_bar]" value="1" <?php checked($enable_top_bar); ?>>
                                Показывать тонкую промо-полосу над шапкой сайта
                            </label>
                            <p class="description">Показывает баннеры с включенным плейсментом <code>top_bar</code>.</p>
                        </td>
                    </tr>
                </table>

                <hr style="border:0; border-top:1px solid #e2e8f0; margin:24px 0;">

                <h2>3. Ручная вставка шорткодами</h2>
                <p>Вы можете вставить промо-блок в любое место сайта, сайдбар Elementor или виджет с помощью шорткодов:</p>
                <ul>
                    <li><code>[sz_promo placement="sidebar"]</code> — выведет баннер для сайдбара с автоматической умной ротацией.</li>
                    <li><code>[sz_promo placement="in_content"]</code> — выведет баннер для контента.</li>
                    <li><code>[sz_promo id="123"]</code> — принудительно выведет конкретный баннер по его ID.</li>
                </ul>

                <?php submit_button('Сохранить настройки'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_reset_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Доступ запрещен');
        }
        check_admin_referer('sz_reset_stats_action', 'sz_reset_nonce');

        SZ_Promo_DB::reset_stats();

        wp_redirect(admin_url('edit.php?post_type=sz_promo&page=sz-promo-analytics&reset=success'));
        exit;
    }
}
