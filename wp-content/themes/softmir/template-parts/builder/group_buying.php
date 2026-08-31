<?php
// Group Buying Promo Block
$title_line1 = softmir_quiz_t('gb_promo_kicker', 'Exclusive software discounts');
$title_line2 = softmir_quiz_t('gb_promo_title', 'Group Buying (B2B)');
$subtitle = softmir_quiz_t('gb_promo_desc', 'We unite buyers to get exclusive bulk discounts from developers. Leave a request — we\'ll negotiate the best terms for you!');
$btn_text = softmir_quiz_t('gb_promo_btn', 'Go to Software Catalog');
$btn_link = home_url('/software/');
?>
<section class="section" style="padding: 40px 0;">
    <div class="container">
        <div class="partnership-block" style="background-color: #f0fae6; border: 1px solid #c3e6cb;">
            <!-- Left content -->
            <div class="partnership-content" style="padding-right: 0;">
                <h2 class="partnership-title" style="color: #155724; font-size: 32px;">
                    <?php echo esc_html($title_line1); ?><br>
                    <span style="color: #28a745;"><?php echo esc_html($title_line2); ?></span>
                </h2>
                <p class="partnership-desc" style="color: #3d6a45;"><?php echo esc_html($subtitle); ?></p>
                <a href="<?php echo esc_url($btn_link); ?>" class="btn btn-primary partnership-btn" style="background-color: #28a745; border-color: #28a745; color:#fff;">
                    <?php echo esc_html($btn_text); ?> →
                </a>
            </div>
            
            <!-- Right decorative -->
            <div class="partnership-visual" style="display:flex; justify-content:center; align-items:center; position:relative;">
                <div style="font-size:120px; position:relative; z-index:2; text-shadow: 0 10px 30px rgba(40, 167, 69, 0.2);">🤝🛍️</div>
                <!-- Abstract glowing circle behind emojis -->
                <div style="position:absolute; width:200px; height:200px; background:radial-gradient(circle, rgba(40,167,69,0.15) 0%, rgba(40,167,69,0) 70%); top:50%; left:50%; transform:translate(-50%, -50%); border-radius:50%; z-index:1;"></div>
            </div>
        </div>
    </div>
</section>
