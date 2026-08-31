<?php
/**
 * AJAX Handler for sending software info to email.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_send_sw_info', 'softmir_send_software_info_ajax');
add_action('wp_ajax_nopriv_send_sw_info', 'softmir_send_software_info_ajax');

function softmir_send_software_info_ajax()
{
    check_ajax_referer('softmir_send_sw_info', 'nonce');

    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $user_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!is_email($email) || !$post_id) {
        wp_send_json_error(['message' => __('Некорректный email or ID записи.', 'softmir')]);
    }

    $sw_post = get_post($post_id);
    if (!$sw_post || $sw_post->post_type !== 'software') {
        wp_send_json_error(['message' => __('Product not found.', 'softmir')]);
    }

    $title = get_the_title($post_id);
    $short_desc = get_post_meta($post_id, 'short_description', true);

    // Affiliate Link
    $website = get_post_meta($post_id, 'website_url', true);
    $is_affiliate = get_post_meta($post_id, 'is_affiliate', true);
    $link = $website;
    if ($is_affiliate && $website) {
        $link = softmir_get_software_outbound_link($post_id);
    }

    $subject = sprintf(__('Detailed information about: %s', 'softmir'), $title);

    // Load template helpers (for list parsing etc)
    if (!function_exists('softmir_parse_text_list') && file_exists(get_template_directory() . '/inc/template-helpers.php')) {
        require_once get_template_directory() . '/inc/template-helpers.php';
    }

    $advantages = [];
    $disadvantages = [];
    $key_functions = [];
    $best_for = [];
    $bad_for = [];
    $scenarios = [];
    $content = $sw_post->post_content;

    if (function_exists('softmir_get_field_with_lang_fallback')) {
        $advantages = softmir_parse_text_list(softmir_get_field_with_lang_fallback('top_reasons', $post_id));
        $disadvantages = softmir_parse_text_list(softmir_get_field_with_lang_fallback('disadvantages', $post_id));
        $key_functions = softmir_parse_text_list(softmir_get_field_with_lang_fallback('key_functions', $post_id));

        $bf_raw = softmir_get_field_with_lang_fallback('best_for', $post_id);
        if (!$bf_raw)
            $bf_raw = get_post_meta($post_id, 'best_for', true);
        $best_for = softmir_parse_text_list($bf_raw);

        $bad_raw = softmir_get_field_with_lang_fallback('bad_for', $post_id);
        if (!$bad_raw)
            $bad_raw = get_post_meta($post_id, 'bad_for', true);
        $bad_for = softmir_parse_text_list($bad_raw);

        $scenarios_raw = get_field('scenarios_md', $post_id);
        if (!$scenarios_raw)
            $scenarios_raw = get_post_meta($post_id, 'scenarios_md', true);
        if (!empty($scenarios_raw)) {
            $parts = preg_split('/^###\s*/m', $scenarios_raw);
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part))
                    continue;
                $lines = explode("\n", $part, 2);
                $stitle = trim($lines[0], " :\t\r\n");
                $sdesc = isset($lines[1]) ? trim($lines[1]) : '';
                if (!empty($stitle)) {
                    $scenarios[] = ['title' => $stitle, 'desc' => $sdesc];
                }
            }
        }
    }

    $price = get_post_meta($post_id, 'price_summary', true);

    // Rating
    $rating = 0;
    if (function_exists('glsr_get_ratings')) {
        $ratings = glsr_get_ratings(['assigned_posts' => $post_id]);
        if ($ratings && isset($ratings->average)) {
            $rating = round($ratings->average, 1);
        }
    }

    // Category
    $terms = get_the_terms($post_id, 'software_category');
    $category_name = $terms && !is_wp_error($terms) ? $terms[0]->name : '';

    // Attributes
    $attrs_html = '';
    if (function_exists('softmir_get_attributes')) {
        $all_attrs = softmir_get_attributes();
        if ($all_attrs) {
            foreach ($all_attrs as $attr) {
                if (!softmir_attr_applies_to_software($attr->ID, $post_id))
                    continue;
                $meta = softmir_get_attr_meta($attr->ID);
                $val = softmir_get_software_attr_value($post_id, $attr->ID);

                if ($val !== '' && $val !== null && $val !== [] && $val !== false) {
                    if (is_array($val)) {
                        $val_str = implode(', ', $val);
                    } elseif ($meta['type'] === 'url') {
                        $val_str = '<a href="' . esc_url($val) . '">Learn more</a>';
                    } elseif ($meta['type'] === 'checkbox') {
                        $opts = softmir_parse_options($meta['options']);
                        if (empty($opts)) {
                            $val_str = $val ? 'Yes' : 'No';
                        } else {
                            $val_str = is_array($val) ? implode(', ', $val) : $val;
                        }
                    } else {
                        $val_str = $val;
                    }

                    if ($val_str !== 'No' && $val_str !== '') {
                        $icon = $meta['icon'] ? $meta['icon'] . ' ' : '';
                        $attrs_html .= '<li><strong>' . esc_html($icon . $attr->post_title) . ':</strong> ' . $val_str . '</li>';
                    }
                }
            }
        }
    }

    // Build Email HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f9fafb;
                margin: 0;
                padding: 20px;
                color: #1f2937;
            }

            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .logo-wrap {
                text-align: center;
                margin-bottom: 20px;
            }

            .logo-wrap img {
                max-height: 80px;
            }

            h1 {
                text-align: center;
                color: #1e3a8a;
                font-size: 26px;
                margin-top: 0;
                margin-bottom: 5px;
            }

            .sw-meta {
                text-align: center;
                font-size: 14px;
                color: #6b7280;
                margin-bottom: 20px;
            }

            .price-tag {
                display: inline-block;
                background: #e0f2fe;
                color: #0369a1;
                padding: 4px 10px;
                border-radius: 20px;
                font-weight: bold;
                font-size: 14px;
                margin-right: 10px;
            }

            .rating-tag {
                display: inline-block;
                background: #fef3c7;
                color: #b45309;
                padding: 4px 10px;
                border-radius: 20px;
                font-weight: bold;
                font-size: 14px;
            }

            .short-desc {
                font-size: 16px;
                line-height: 1.6;
                color: #4b5563;
                text-align: center;
                margin-bottom: 30px;
                font-style: italic;
            }

            .cta-wrap {
                text-align: center;
                margin: 30px 0;
            }

            .cta-btn {
                background-color: #2563eb;
                color: #ffffff;
                padding: 14px 28px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                display: inline-block;
                font-size: 16px;
                margin-bottom: 15px;
            }

            .gb-box {
                background: #f0fae6;
                border: 1px solid #c3e6cb;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                margin-bottom: 30px;
            }

            .gb-box h3 {
                margin-top: 0;
                color: #2e7d32;
                font-size: 18px;
            }

            .gb-box p {
                color: #555;
                font-size: 14px;
                margin-bottom: 15px;
            }

            .gb-btn {
                background-color: #10b981;
                color: #ffffff;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                display: inline-block;
                font-size: 15px;
            }

            .content-section {
                font-size: 15px;
                line-height: 1.6;
                color: #374151;
                margin-bottom: 30px;
            }

            .content-section h2,
            .content-section h3 {
                color: #111827;
                margin-top: 25px;
            }

            .list-box {
                margin-bottom: 25px;
                background: #f3f4f6;
                padding: 20px;
                border-radius: 6px;
            }

            .list-box h3 {
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 18px;
                color: #111827;
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 8px;
            }

            .list-box ul {
                padding-left: 20px;
                margin: 0;
                color: #374151;
                font-size: 15px;
                line-height: 1.5;
            }

            .list-box li {
                margin-bottom: 8px;
            }

            .attrs-box {
                margin-bottom: 25px;
                border: 1px solid #e5e7eb;
                padding: 20px;
                border-radius: 6px;
            }

            .attrs-box h3 {
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 18px;
                color: #111827;
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 8px;
            }

            .attrs-box ul {
                list-style: none;
                padding: 0;
                margin: 0;
                font-size: 14px;
                line-height: 1.6;
            }

            .attrs-box li {
                margin-bottom: 8px;
                border-bottom: 1px dashed #f3f4f6;
                padding-bottom: 6px;
            }

            .softmir-footer {
                text-align: center;
                margin-top: 40px;
                font-size: 12px;
                color: #9ca3af;
                border-top: 1px solid #e5e7eb;
                padding-top: 20px;
            }

            .softmir-footer a {
                color: #6b7280;
                text-decoration: underline;
            }
        </style>
    </head>

    <body>
        <div class="email-container">
            <!-- Platform Logo -->
            <?php
            $custom_logo_id = get_theme_mod('custom_logo');
            $softzor_logo = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
            if ($softzor_logo): ?>
                <div style="text-align:center; padding-bottom:20px; border-bottom:1px solid #e5e7eb; margin-bottom:20px;">
                    <a href="<?php echo esc_url(home_url('/')); ?>">
                        <img src="<?php echo esc_url($softzor_logo); ?>" alt="SoftZor" style="max-height:40px;">
                    </a>
                </div>
            <?php endif; ?>

            <!-- Product Logo -->
            <?php
            $product_logo = function_exists('softmir_get_company_logo') ? softmir_get_company_logo($post_id) : get_post_meta($post_id, 'company_logo', true);
            if (is_numeric($product_logo)) {
                $product_logo = wp_get_attachment_image_url($product_logo, 'full');
            }
            if ($product_logo && is_string($product_logo) && strpos($product_logo, 'http') === 0): ?>
                <div class="logo-wrap">
                    <img src="<?php echo esc_url($product_logo); ?>" alt="<?php echo esc_attr($title); ?>"
                        style="max-height:80px;">
                </div>
            <?php endif; ?>

            <h1><?php echo esc_html($title); ?></h1>
            <div class="sw-meta">
                <?php if ($category_name): ?>
                    <span>📁 <?php echo esc_html($category_name); ?></span> |
                <?php endif; ?>
                <?php if ($price): ?>
                    <span class="price-tag"><?php echo esc_html($price); ?></span>
                <?php endif; ?>
                <?php if ($rating > 0): ?>
                    <span class="rating-tag">⭐ <?php echo $rating; ?>/5</span>
                <?php endif; ?>
            </div>

            <?php if ($short_desc): ?>
                <p class="short-desc">
                    "<?php echo nl2br(esc_html($short_desc)); ?>"
                </p>
            <?php endif; ?>

            <?php if ($link): ?>
                <div class="cta-wrap">
                    <a href="<?php echo esc_url($link); ?>"
                        style="background-color: #1a56db; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px; margin-bottom: 15px;">
                        🌐 <?php esc_html_e('Visit the developer\'s site', 'softmir'); ?> &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <div class="gb-box">
                <h3>🤝 <?php esc_html_e('Joint purchase', 'softmir'); ?></h3>
                <p><?php esc_html_e('Мы выторгуем для вас эксклюзивную discount на этот продукт. Это freeо и ни к чему не обязывает.', 'softmir'); ?>
                </p>
                <a href="<?php echo esc_url(get_permalink($post_id) . '#group-buy'); ?>"
                    style="background-color: #10b981; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 15px;">
                    <?php esc_html_e('Submit request на discount', 'softmir'); ?>
                </a>
            </div>

            <?php if ($content): ?>
                <div class="content-section">
                    <?php
                    // strip shortcodes and embed tags to keep email clean
                    $clean_content = strip_shortcodes($content);
                    $clean_content = strip_tags($clean_content, '<p><br><ul><li><strong><em><h2><h3><h4>');
                    echo $clean_content;
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($scenarios)): ?>
                <div class="list-box" style="background: #f8fafc; border-left: 4px solid #64748b;">
                    <h3 style="color: #334155; margin-bottom:15px; border-bottom:0;">⚙️
                        <?php esc_html_e('Use Cases', 'softmir'); ?>
                    </h3>
                    <?php foreach ($scenarios as $scene): ?>
                        <div style="margin-bottom: 12px;">
                            <h4 style="margin: 0 0 4px 0; color: #0f172a; font-size: 15px;">↳
                                <?php echo esc_html($scene['title']); ?>
                            </h4>
                            <p style="margin: 0 0 0 15px; color: #475569; font-size: 14px; line-height:1.4;">
                                <?php echo esc_html($scene['desc']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($key_functions)): ?>
                <div class="list-box" style="background: #eff6ff; border-left: 4px solid #3b82f6;">
                    <h3 style="color: #1e3a8a;">✨ <?php esc_html_e('Key features', 'softmir'); ?></h3>
                    <ul>
                        <?php foreach ($key_functions as $kf): ?>
                            <li><?php echo esc_html(is_array($kf) ? ($kf['text'] ?? '') : $kf); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($advantages)): ?>
                <div class="list-box" style="background: #f0fdf4; border-left: 4px solid #22c55e;">
                    <h3 style="color: #166534;">✅ <?php esc_html_e('Pros (Why It\'s TOP)', 'softmir'); ?></h3>
                    <ul>
                        <?php foreach ($advantages as $adv): ?>
                            <li><?php echo esc_html(is_array($adv) ? ($adv['text'] ?? '') : $adv); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($disadvantages)): ?>
                <div class="list-box" style="background: #fef2f2; border-left: 4px solid #ef4444;">
                    <h3 style="color: #991b1b;">⚠️ <?php esc_html_e('Nuances & Risks', 'softmir'); ?></h3>
                    <ul>
                        <?php foreach ($disadvantages as $dis): ?>
                            <li><?php echo esc_html(is_array($dis) ? ($dis['text'] ?? '') : $dis); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($best_for)): ?>
                <div class="list-box" style="background: #fdf4ff; border-left: 4px solid #d946ef;">
                    <h3 style="color: #a21caf;">🚀 <?php esc_html_e('Вам SoftwareДОЙДЁТ, если:', 'softmir'); ?></h3>
                    <ul>
                        <?php foreach ($best_for as $bf): ?>
                            <li><?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($bad_for)): ?>
                <div class="list-box" style="background: #f1f5f9; border-left: 4px solid #94a3b8;">
                    <h3 style="color: #475569;">🥱 <?php esc_html_e('Better to avoid if:', 'softmir'); ?></h3>
                    <ul>
                        <?php foreach ($bad_for as $bf): ?>
                            <li><?php echo esc_html(is_array($bf) ? ($bf['text'] ?? '') : $bf); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($attrs_html)): ?>
                <div class="attrs-box">
                    <h3>📋 <?php esc_html_e('Properties and Features', 'softmir'); ?></h3>
                    <ul>
                        <?php echo $attrs_html; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="softmir-footer">
                <p><?php esc_html_e('You received this email because you requested information on the site', 'softmir'); ?>
                    SoftZor.</p>
                &copy; <?php echo date('Y'); ?> SoftZor. <a href="<?php echo home_url('/'); ?>">Visit site</a>
            </div>
        </div>
    </body>

    </html>
    <?php
    $body = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    if (wp_mail($email, $subject, $body, $headers)) {
        // --- Auto-registration: create WP user if not exists ---
        if (!is_user_logged_in() && !email_exists($email)) {
            $random_password = wp_generate_password(12, true, false);
            $user_login = sanitize_user(current(explode('@', $email)), true);

            // Ensure unique login
            if (username_exists($user_login)) {
                $user_login .= '_' . wp_rand(100, 999);
            }

            $user_id = wp_create_user($user_login, $random_password, $email);

            if (!is_wp_error($user_id)) {
                // Set role to subscriber
                $user = new WP_User($user_id);
                $user->set_role('subscriber');

                // Set display name from form
                if (!empty($user_name)) {
                    wp_update_user([
                        'ID' => $user_id,
                        'display_name' => $user_name,
                        'first_name' => $user_name,
                    ]);
                }

                // Send WP welcome email with credentials
                wp_new_user_notification($user_id, null, 'user');
            }
        }

        wp_send_json_success(['message' => __('Information successfully sent to your email.', 'softmir')]);
    } else {
        wp_send_json_error(['message' => __('Error sending email. Try again later.', 'softmir')]);
    }
}
