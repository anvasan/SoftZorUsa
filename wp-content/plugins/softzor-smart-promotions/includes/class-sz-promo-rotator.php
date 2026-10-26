<?php
if (!defined('ABSPATH')) {
    exit;
}

class SZ_Promo_Rotator {

    /**
     * Выбирает оптимальный промо-баннер для заданного плейсмента
     * используя алгоритм Multi-Armed Bandit на основе сглаженного CTR.
     */
    public static function pick_banner($placement, $post_id = 0) {
        $placement = sanitize_key($placement);
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $promos = get_posts([
            'post_type'      => 'sz_promo',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (empty($promos)) {
            return null;
        }

        $current_categories = self::get_current_page_categories($post_id);

        $eligible_promos = [];

        foreach ($promos as $promo_id) {
            // Проверка активности
            $is_active = get_post_meta($promo_id, '_sz_is_active', true) !== 'no';
            if (!$is_active) {
                continue;
            }

            // Проверка плейсмента
            $placements = (array) get_post_meta($promo_id, '_sz_placements', true);
            if (!in_array($placement, $placements, true) && !in_array('custom', $placements, true)) {
                continue;
            }

            // Проверка наличия креатива нужного размера для плейсмента
            $fmt = get_post_meta($promo_id, '_sz_format', true) ?: 'native';
            if ($fmt === 'image') {
                if ($placement === 'sidebar') {
                    $has_img = get_post_meta($promo_id, '_sz_sidebar_img', true) ?: get_post_meta($promo_id, '_sz_desktop_img', true);
                    if (!$has_img) continue;
                } elseif ($placement === 'in_content') {
                    $has_img = get_post_meta($promo_id, '_sz_content_img', true) ?: get_post_meta($promo_id, '_sz_desktop_img', true);
                    if (!$has_img) continue;
                }
            } elseif ($fmt === 'html') {
                if ($placement === 'sidebar') {
                    $has_html = get_post_meta($promo_id, '_sz_sidebar_html', true) ?: get_post_meta($promo_id, '_sz_raw_html', true);
                    if (!$has_html) continue;
                } elseif ($placement === 'in_content') {
                    $has_html = get_post_meta($promo_id, '_sz_content_html', true) ?: get_post_meta($promo_id, '_sz_raw_html', true);
                    if (!$has_html) continue;
                }
            }

            // Проверка таргетинга по рубрикам
            $all_cats = get_post_meta($promo_id, '_sz_all_categories', true) !== 'no';
            if (!$all_cats) {
                $target_cats = (array) get_post_meta($promo_id, '_sz_categories', true);
                if (empty($current_categories) || empty(array_intersect($target_cats, $current_categories))) {
                    continue;
                }
            }

            $eligible_promos[] = $promo_id;
        }

        if (empty($eligible_promos)) {
            return null;
        }

        if (count($eligible_promos) === 1) {
            return $eligible_promos[0];
        }

        // Многорукий бандит (Multi-Armed Bandit / Bayesian Smoothed CTR)
        return self::weighted_bandit_selection($eligible_promos, $placement);
    }

    /**
     * Определяет категории текущей страницы
     */
    private static function get_current_page_categories($post_id) {
        $cats = [];
        if (!$post_id) {
            return $cats;
        }

        // Категории блога
        $blog_terms = get_the_terms($post_id, 'category');
        if (!empty($blog_terms) && !is_wp_error($blog_terms)) {
            foreach ($blog_terms as $t) {
                $cats[] = 'blog_' . $t->term_id;
            }
        }

        // Категории каталога программ
        $sw_terms = get_the_terms($post_id, 'software_category');
        if (!empty($sw_terms) && !is_wp_error($sw_terms)) {
            foreach ($sw_terms as $t) {
                $cats[] = 'sw_' . $t->term_id;
            }
        }

        return $cats;
    }

    /**
     * Взвешенный случайный выбор с адаптивным байесовским весом
     */
    private static function weighted_bandit_selection($promo_ids, $placement) {
        $weights = [];
        $total_weight = 0;

        foreach ($promo_ids as $pid) {
            $stats = SZ_Promo_DB::get_stats($pid, $placement);
            $views  = !empty($stats) ? absint($stats[0]['views']) : 0;
            $clicks = !empty($stats) ? absint($stats[0]['clicks']) : 0;

            $base_weight = absint(get_post_meta($pid, '_sz_base_weight', true)) ?: 5;

            $score = (($clicks + ($base_weight * 0.5)) / ($views + 10)) * 100;
            $weight = max(1, (int) round($score * 10));

            $weights[$pid] = $weight;
            $total_weight += $weight;
        }

        if ($total_weight <= 0) {
            return $promo_ids[array_rand($promo_ids)];
        }

        $rand = mt_rand(1, $total_weight);
        $cumulative = 0;

        foreach ($weights as $pid => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $pid;
            }
        }

        return $promo_ids[0];
    }
}
