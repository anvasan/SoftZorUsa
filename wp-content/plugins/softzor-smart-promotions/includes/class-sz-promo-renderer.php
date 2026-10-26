<?php
if (!defined('ABSPATH')) {
    exit;
}

class SZ_Promo_Renderer {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('sz_promo', [$this, 'render_shortcode']);
        add_filter('the_content', [$this, 'inject_in_content_promo']);
        add_action('wp_body_open', [$this, 'inject_top_bar']);

        // AJAX трекинг кликов и показов
        add_action('wp_ajax_sz_track_view', [$this, 'ajax_track_view']);
        add_action('wp_ajax_nopriv_sz_track_view', [$this, 'ajax_track_view']);
        add_action('wp_ajax_sz_track_click', [$this, 'ajax_track_click']);
        add_action('wp_ajax_nopriv_sz_track_click', [$this, 'ajax_track_click']);
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'sz-promo-frontend',
            SZ_PROMO_URL . 'assets/css/sz-promo-frontend.css',
            [],
            SZ_PROMO_VERSION
        );

        wp_enqueue_script(
            'sz-promo-tracker',
            SZ_PROMO_URL . 'assets/js/sz-promo-tracker.js',
            ['jquery'],
            SZ_PROMO_VERSION,
            true
        );

        wp_localize_script('sz-promo-tracker', 'szPromoData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sz_promo_track_nonce')
        ]);
    }

    /**
     * Шорткод: [sz_promo placement="sidebar"] или [sz_promo id="123"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'placement' => 'sidebar',
            'id'        => 0,
        ], $atts, 'sz_promo');

        $promo_id = absint($atts['id']);
        $placement = sanitize_key($atts['placement']);

        if (!$promo_id) {
            $promo_id = SZ_Promo_Rotator::pick_banner($placement);
        }

        if (!$promo_id) {
            return '';
        }

        return $this->get_promo_html($promo_id, $placement);
    }

    /**
     * Автоматическая вставка в контент записей блога и карточек ПО
     */
    public function inject_in_content_promo($content) {
        if (!is_singular(['post', 'software']) || is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $content;
        }

        $options = get_option('sz_promo_options', []);
        $auto_insert = !empty($options['auto_in_content']);
        if (!$auto_insert) {
            return $content;
        }

        $target_para = !empty($options['in_content_para']) ? absint($options['in_content_para']) : 2;

        $promo_id = SZ_Promo_Rotator::pick_banner('in_content');
        if (!$promo_id) {
            return $content;
        }

        $promo_html = $this->get_promo_html($promo_id, 'in_content');
        if (empty($promo_html)) {
            return $content;
        }

        $paragraphs = explode('</p>', $content);
        if (count($paragraphs) > $target_para) {
            $paragraphs[$target_para - 1] .= '</p>' . $promo_html;
            return implode('</p>', $paragraphs);
        }

        return $content . $promo_html;
    }

    /**
     * Сквозная полоса в шапке (Top Bar)
     */
    public function inject_top_bar() {
        if (is_admin()) {
            return;
        }

        $options = get_option('sz_promo_options', []);
        if (empty($options['enable_top_bar'])) {
            return;
        }

        $promo_id = SZ_Promo_Rotator::pick_banner('top_bar');
        if (!$promo_id) {
            return;
        }

        echo $this->get_promo_html($promo_id, 'top_bar');
    }

    /**
     * Генерация HTML-разметки баннера или нативной плашки под нужный размер
     */
    public function get_promo_html($promo_id, $placement) {
        $format      = get_post_meta($promo_id, '_sz_format', true) ?: 'native';
        $target_url  = get_post_meta($promo_id, '_sz_target_url', true);
        $new_tab     = get_post_meta($promo_id, '_sz_new_tab', true) !== 'no';
        $target_attr = $new_tab ? 'target="_blank" rel="nofollow sponsored"' : 'rel="nofollow sponsored"';

        if (empty($target_url) && $format !== 'html') {
            return '';
        }

        $wrapper_attrs = sprintf(
            'class="sz-rec-wrapper sz-rec-%s" data-promo-id="%d" data-placement="%s"',
            esc_attr($placement),
            esc_attr($promo_id),
            esc_attr($placement)
        );

        ob_start();

        if ($placement === 'top_bar') {
            $badge       = get_post_meta($promo_id, '_sz_badge_text', true) ?: 'Оффер SoftZor';
            $headline    = get_post_meta($promo_id, '_sz_headline', true) ?: get_the_title($promo_id);
            $btn_text    = get_post_meta($promo_id, '_sz_btn_text', true) ?: 'Узнать подробнее ↗';
            ?>
            <div <?php echo $wrapper_attrs; ?> style="background: #0f172a; color: #fff;">
                <div class="sz-rec-topbar-inner">
                    <span class="sz-rec-badge"><?php echo esc_html($badge); ?></span>
                    <span class="sz-rec-topbar-text"><?php echo esc_html($headline); ?></span>
                    <a href="<?php echo esc_url($target_url); ?>" <?php echo $target_attr; ?> class="sz-rec-topbar-btn sz-rec-track-link">
                        <?php echo esc_html($btn_text); ?>
                    </a>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // HTML-код партнера под нужный размер
        if ($format === 'html') {
            if ($placement === 'sidebar') {
                $raw_html = get_post_meta($promo_id, '_sz_sidebar_html', true) ?: get_post_meta($promo_id, '_sz_raw_html', true);
            } elseif ($placement === 'in_content') {
                $raw_html = get_post_meta($promo_id, '_sz_content_html', true) ?: get_post_meta($promo_id, '_sz_raw_html', true);
            } else {
                $raw_html = get_post_meta($promo_id, '_sz_raw_html', true) ?: get_post_meta($promo_id, '_sz_content_html', true) ?: get_post_meta($promo_id, '_sz_sidebar_html', true);
            }

            if (empty($raw_html)) {
                return '';
            }

            if (stripos($raw_html, 'class=') !== false) {
                $raw_html = preg_replace('/class=(["\'])/i', 'class=$1sz-rec-track-link ', $raw_html);
            } else {
                $raw_html = preg_replace('/<a\s/i', '<a class="sz-rec-track-link" ', $raw_html);
            }
            ?>
            <div <?php echo $wrapper_attrs; ?> style="display:flex; justify-content:center; max-width:100%; overflow:hidden;">
                <?php echo $raw_html; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        // Графический баннер под нужный размер
        if ($format === 'image') {
            if ($placement === 'sidebar') {
                $img_url = get_post_meta($promo_id, '_sz_sidebar_img', true) ?: get_post_meta($promo_id, '_sz_desktop_img', true);
            } elseif ($placement === 'in_content') {
                $img_url = get_post_meta($promo_id, '_sz_content_img', true) ?: get_post_meta($promo_id, '_sz_desktop_img', true);
            } else {
                $img_url = get_post_meta($promo_id, '_sz_desktop_img', true) ?: get_post_meta($promo_id, '_sz_content_img', true) ?: get_post_meta($promo_id, '_sz_sidebar_img', true);
            }

            if (empty($img_url)) {
                return '';
            }

            $mobile_img  = get_post_meta($promo_id, '_sz_mobile_img', true) ?: $img_url;
            $alt         = get_post_meta($promo_id, '_sz_img_alt', true) ?: get_the_title($promo_id);
            ?>
            <div <?php echo $wrapper_attrs; ?>>
                <a href="<?php echo esc_url($target_url); ?>" <?php echo $target_attr; ?> class="sz-rec-img-link sz-rec-track-link">
                    <picture>
                        <?php if ($mobile_img !== $img_url): ?>
                            <source media="(max-width: 640px)" srcset="<?php echo esc_url($mobile_img); ?>">
                        <?php endif; ?>
                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy" class="sz-rec-img">
                    </picture>
                </a>
            </div>
            <?php
            return ob_get_clean();
        }

        // Native Card Format (Default)
        $badge       = get_post_meta($promo_id, '_sz_badge_text', true) ?: '🔥 Рекомендуем';
        $headline    = get_post_meta($promo_id, '_sz_headline', true) ?: get_the_title($promo_id);
        $description = get_post_meta($promo_id, '_sz_description', true);
        $btn_text    = get_post_meta($promo_id, '_sz_btn_text', true) ?: 'Попробовать бесплатно ↗';
        $btn_color   = get_post_meta($promo_id, '_sz_btn_color', true) ?: '#2563eb';
        $logo_url    = get_post_meta($promo_id, '_sz_logo_url', true);
        ?>
        <div <?php echo $wrapper_attrs; ?>>
            <div class="sz-rec-card">
                <div class="sz-rec-header">
                    <div class="sz-rec-head-left">
                        <?php if (!empty($logo_url)): ?>
                            <div class="sz-rec-logo-box">
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($headline); ?>" class="sz-rec-logo">
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="sz-rec-badge"><?php echo esc_html($badge); ?></span>
                            <div class="sz-rec-title"><?php echo esc_html($headline); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($description)): ?>
                    <p class="sz-rec-desc"><?php echo esc_html($description); ?></p>
                <?php endif; ?>

                <div class="sz-rec-footer">
                    <a href="<?php echo esc_url($target_url); ?>" <?php echo $target_attr; ?> class="sz-rec-btn sz-rec-track-link" style="background-color: <?php echo esc_attr($btn_color); ?>;">
                        <?php echo esc_html($btn_text); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_track_view() {
        check_ajax_referer('sz_promo_track_nonce', 'nonce');
        $promo_id = isset($_POST['promo_id']) ? absint($_POST['promo_id']) : 0;
        $placement = isset($_POST['placement']) ? sanitize_key($_POST['placement']) : '';

        if ($promo_id && $placement) {
            SZ_Promo_DB::record_view($promo_id, $placement);
            wp_send_json_success(['status' => 'view_recorded']);
        }
        wp_send_json_error(['status' => 'invalid_data']);
    }

    public function ajax_track_click() {
        check_ajax_referer('sz_promo_track_nonce', 'nonce');
        $promo_id = isset($_POST['promo_id']) ? absint($_POST['promo_id']) : 0;
        $placement = isset($_POST['placement']) ? sanitize_key($_POST['placement']) : '';

        if ($promo_id && $placement) {
            SZ_Promo_DB::record_click($promo_id, $placement);
            wp_send_json_success(['status' => 'click_recorded']);
        }
        wp_send_json_error(['status' => 'invalid_data']);
    }
}
