<?php
if (!defined('ABSPATH')) {
    exit;
}

class SZ_Promo_DB {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sz_promo_stats';
    }

    public static function install() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            promo_id bigint(20) NOT NULL,
            placement varchar(50) NOT NULL,
            views bigint(20) NOT NULL DEFAULT 0,
            clicks bigint(20) NOT NULL DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY promo_placement (promo_id, placement),
            KEY promo_id_idx (promo_id),
            KEY placement_idx (placement)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function record_view($promo_id, $placement) {
        global $wpdb;
        $table_name = self::get_table_name();
        $promo_id = absint($promo_id);
        $placement = sanitize_key($placement);

        if (!$promo_id || empty($placement)) {
            return false;
        }

        return $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (promo_id, placement, views, clicks) 
             VALUES (%d, %s, 1, 0) 
             ON DUPLICATE KEY UPDATE views = views + 1",
            $promo_id,
            $placement
        ));
    }

    public static function record_click($promo_id, $placement) {
        global $wpdb;
        $table_name = self::get_table_name();
        $promo_id = absint($promo_id);
        $placement = sanitize_key($placement);

        if (!$promo_id || empty($placement)) {
            return false;
        }

        return $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (promo_id, placement, views, clicks) 
             VALUES (%d, %s, 0, 1) 
             ON DUPLICATE KEY UPDATE clicks = clicks + 1",
            $promo_id,
            $placement
        ));
    }

    public static function get_stats($promo_id = 0, $placement = '') {
        global $wpdb;
        $table_name = self::get_table_name();

        $where = [];
        $params = [];

        if (!empty($promo_id)) {
            $where[] = 'promo_id = %d';
            $params[] = absint($promo_id);
        }

        if (!empty($placement)) {
            $where[] = 'placement = %s';
            $params[] = sanitize_key($placement);
        }

        $sql = "SELECT * FROM $table_name";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public static function reset_stats($promo_id = 0, $placement = '') {
        global $wpdb;
        $table_name = self::get_table_name();

        if (!empty($promo_id) && !empty($placement)) {
            return $wpdb->delete($table_name, [
                'promo_id' => absint($promo_id),
                'placement' => sanitize_key($placement)
            ], ['%d', '%s']);
        } elseif (!empty($promo_id)) {
            return $wpdb->delete($table_name, ['promo_id' => absint($promo_id)], ['%d']);
        } elseif (!empty($placement)) {
            return $wpdb->delete($table_name, ['placement' => sanitize_key($placement)], ['%s']);
        } else {
            return $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }
}
