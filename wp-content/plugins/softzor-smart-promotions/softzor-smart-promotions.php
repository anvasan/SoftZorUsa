<?php
/**
 * Plugin Name: SoftZor Smart Promotions (Умный ротатор баннеров)
 * Plugin URI:  https://softzor.com.ua
 * Description: Собственная рекламная система для каталога: загрузка баннеров, таргетинг по категориям, нативные B2B-плашки, умная ротация на базе алгоритма Multi-Armed Bandit (сглаженный CTR) и аналитика показов/кликов. AdBlock-proof.
 * Version:     1.0.0
 * Author:      SoftZor Studio
 * Author URI:  https://softzor.com.ua
 * Text Domain: sz-smart-promo
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SZ_PROMO_VERSION', '1.0.0');
define('SZ_PROMO_DIR', plugin_dir_path(__FILE__));
define('SZ_PROMO_URL', plugin_dir_url(__FILE__));

// Подключаем модули
require_once SZ_PROMO_DIR . 'includes/class-sz-promo-db.php';
require_once SZ_PROMO_DIR . 'includes/class-sz-promo-cpt.php';
require_once SZ_PROMO_DIR . 'includes/class-sz-promo-rotator.php';
require_once SZ_PROMO_DIR . 'includes/class-sz-promo-renderer.php';
require_once SZ_PROMO_DIR . 'includes/class-sz-promo-admin.php';

// Хук активации: создание таблицы БД и дефолтных настроек
function sz_promo_activate() {
    SZ_Promo_DB::install();
    $existing = get_option('sz_promo_options');
    if (empty($existing)) {
        update_option('sz_promo_options', [
            'auto_in_content' => 1,
            'in_content_para' => 2,
            'enable_top_bar'  => 1
        ]);
    }
}
register_activation_hook(__FILE__, 'sz_promo_activate');

// Инициализация плагина
function sz_promo_init() {
    new SZ_Promo_CPT();
    new SZ_Promo_Renderer();
    if (is_admin()) {
        new SZ_Promo_Admin();
    }
}
add_action('plugins_loaded', 'sz_promo_init');
