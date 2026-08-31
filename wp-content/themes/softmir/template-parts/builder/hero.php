<?php
// Hero Module - uses ACF page fields (registered in acf-home-options.php)
$page_id = get_option('page_on_front');
$title = get_field('home_header_title', $page_id) ?: __('Proven software for business automation', 'softmir');
$text = get_field('home_header_subtitle', $page_id) ?: __('Your business can''t wait. Find effective CRM and ERP solutions ready for implementation today.', 'softmir');
$btn_text = get_field('home_header_btn_text', $page_id) ?: __('Go to catalog', 'softmir');
$btn_link = get_field('home_header_btn_url', $page_id) ?: get_post_type_archive_link('software');
$is_quiz_enabled = get_theme_mod('softmir_enable_quiz', false);
?>
<section class="hero">
    <div class="container">
        <div class="hero-row">
            <div class="hero-content">
                <h1><?php echo wp_kses_post($title); ?></h1>
                <p><?php echo esc_html($text); ?></p>
                <?php if ($btn_link): ?>
                    <a href="<?php echo esc_url($btn_link); ?>" class="btn btn-primary hero-btn-cta">
                        <?php echo esc_html($btn_text); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($is_quiz_enabled): ?>
                <div class="hero-quiz">
                    <?php echo do_shortcode('[softmir_quiz]'); ?>
                </div>
            <?php else: ?>
                <div class="hero-infographic">
                    <svg class="infographic-svg" viewBox="0 0 600 450" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Dashboard Background -->
                        <rect x="40" y="40" width="520" height="340" rx="16" fill="#ffffff" filter="drop-shadow(0 25px 50px rgba(0,0,0,0.08))"/>
                        
                        <!-- Sidebar -->
                        <rect x="40" y="40" width="140" height="340" rx="16" fill="#f8fafc"/>
                        <path d="M60 80h40M60 110h80M60 135h60M60 160h70" stroke="#cbd5e1" stroke-width="8" stroke-linecap="round"/>
                        
                        <!-- Main Content Area -->
                        <rect x="210" y="70" width="310" height="20" rx="10" fill="#f1f5f9"/>
                        
                        <!-- Graph -->
                        <rect x="210" y="110" width="310" height="150" rx="12" fill="#f8fafc"/>
                        <path d="M210 260 L240 210 L300 230 L380 150 L460 180 L520 120 L520 260 Z" fill="url(#gradGraph)"/>
                        <path d="M210 260 L240 210 L300 230 L380 150 L460 180 L520 120" stroke="#0ea5e9" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                        
                        <!-- Bottom Cards -->
                        <rect x="210" y="280" width="145" height="80" rx="12" fill="#f1f5f9"/>
                        <rect x="375" y="280" width="145" height="80" rx="12" fill="#f1f5f9"/>

                        <!-- Animated Floating Elements -->
                        <!-- Floating Element 1 (Top Right) -->
                        <g class="hero-float-1">
                            <rect x="420" y="20" width="160" height="64" rx="16" fill="#ffffff" filter="drop-shadow(0 15px 30px rgba(0,0,0,0.1))"/>
                            <circle cx="452" cy="52" r="16" fill="#22c55e" opacity="0.1"/>
                            <path d="M446 52l4 4 8-8" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="480" y="42" width="70" height="8" rx="4" fill="#94a3b8"/>
                            <rect x="480" y="56" width="40" height="6" rx="3" fill="#cbd5e1"/>
                        </g>
                        
                        <!-- Floating Element 2 (Bottom Left) -->
                        <g class="hero-float-2">
                            <rect x="10" y="200" width="140" height="140" rx="20" fill="#ffffff" filter="drop-shadow(0 20px 40px rgba(0,0,0,0.1))"/>
                            <circle cx="80" cy="270" r="40" stroke="#f1f5f9" stroke-width="12" fill="none"/>
                            <circle cx="80" cy="270" r="40" stroke="#8b5cf6" stroke-width="12" stroke-dasharray="160" stroke-dashoffset="40" stroke-linecap="round" fill="none"/>
                            <circle cx="80" cy="270" r="40" stroke="#0ea5e9" stroke-width="12" stroke-dasharray="160" stroke-dashoffset="120" stroke-linecap="round" fill="none"/>
                            <circle cx="80" cy="270" r="6" fill="#1e293b"/>
                        </g>

                        <!-- Floating Element 3 (Bottom Right) -->
                        <g class="hero-float-3">
                            <rect x="450" y="300" width="140" height="64" rx="32" fill="#ffffff" filter="drop-shadow(0 15px 30px rgba(0,0,0,0.1))"/>
                            <circle cx="482" cy="332" r="18" fill="#f1f5f9"/>
                            <path d="M482 325a5 5 0 100 10 5 5 0 000-10zm0 12c-3.33 0-10 1.67-10 5v2h20v-2c0-3.33-6.67-5-10-5z" fill="#94a3b8"/>
                            <rect x="510" y="322" width="50" height="8" rx="4" fill="#94a3b8"/>
                            <rect x="510" y="336" width="30" height="6" rx="3" fill="#cbd5e1"/>
                        </g>

                        <defs>
                            <linearGradient id="gradGraph" x1="210" y1="120" x2="210" y2="260" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#0ea5e9" stop-opacity="0.2"/>
                                <stop offset="1" stop-color="#0ea5e9" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
