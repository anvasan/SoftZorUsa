<?php
// Partnership CTA Module — "Have ready software?"
$title_line1 = __('Have ready software?', 'softmir');
$title_line2 = __('We will find clients for it.', 'softmir');
$subtitle = __('Place your product in our catalog and get access to thousands of potential clients who are already looking for solutions.', 'softmir');
$btn_text = __('Place product', 'softmir');
?>
<section class="section partnership-section">
    <div class="container">
        <div class="partnership-block">
            <!-- Left: decorative -->
            <div class="partnership-visual">
                <div class="partnership-bg-shape"></div>
                <!-- Floating cards -->
                <div class="partnership-float-card card-1">
                    <div class="pfc-logo">💼</div>
                    <div class="pfc-info">
                        <div class="pfc-name"><?php esc_html_e('Your product', 'softmir'); ?></div>
                        <div class="pfc-stars">★★★★★ <span>5.0</span></div>
                    </div>
                </div>
                <div class="partnership-float-card card-2">
                    <div class="pfc-logo">📊</div>
                    <div class="pfc-info">
                        <div class="pfc-name"><?php esc_html_e('CRM system', 'softmir'); ?></div>
                        <div class="pfc-stars">★★★★☆ <span>4.5</span></div>
                    </div>
                </div>
                <div class="partnership-float-card card-3">
                    <div class="pfc-logo">🚀</div>
                    <div class="pfc-info">
                        <div class="pfc-name"><?php esc_html_e('SaaS platform', 'softmir'); ?></div>
                        <div class="pfc-stars">★★★★★ <span>4.8</span></div>
                    </div>
                </div>
                <!-- Avatars -->
                <div class="partnership-avatars">
                    <span class="p-avatar">AK</span>
                    <span class="p-avatar">MP</span>
                    <span class="p-avatar">DS</span>
                </div>
            </div>
            <!-- Right: text + button -->
            <div class="partnership-content">
                <h2 class="partnership-title">
                    <?php echo esc_html($title_line1); ?><br>
                    <span><?php echo esc_html($title_line2); ?></span>
                </h2>
                <p class="partnership-desc"><?php echo esc_html($subtitle); ?></p>
                <button type="button" onclick="openPartnerModal()" class="btn btn-primary partnership-btn">
                    <?php echo esc_html($btn_text); ?> →
                </button>
            </div>
        </div>
    </div>
</section>