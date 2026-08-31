<?php
/**
 * Universal Platform Branding & White-Labeling (WP Customizer)
 */

function softmir_customize_register($wp_customize)
{
    // ========== Section: Branding ==========
    $wp_customize->add_section('softmir_branding_section', [
        'title' => __('Brand customization', 'softmir'),
        'priority' => 30,
    ]);

    // Brand Name Part 1
    $wp_customize->add_setting('softmir_brand_name', [
        'default' => 'Soft',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('softmir_brand_name', [
        'label' => __('Brand name (Part 1)', 'softmir'),
        'section' => 'softmir_branding_section',
        'type' => 'text',
    ]);

    // Brand Name Part 2 (Accent)
    $wp_customize->add_setting('softmir_brand_accent', [
        'default' => 'Mir',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control('softmir_brand_accent', [
        'label' => __('Brand name (Accent/Part 2)', 'softmir'),
        'section' => 'softmir_branding_section',
        'type' => 'text',
    ]);

    // Primary Brand Color
    $wp_customize->add_setting('softmir_brand_color', [
        'default' => '#0ea5e9',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'softmir_brand_color', [
        'label' => __('Main brand color', 'softmir'),
        'section' => 'softmir_branding_section',
    ]));

    // Accent Color (Secondary)
    $wp_customize->add_setting('softmir_accent_color', [
        'default' => '#8b5cf6',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport' => 'refresh',
    ]);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'softmir_accent_color', [
        'label' => __('Additional (accent) color', 'softmir'),
        'section' => 'softmir_branding_section',
    ]));
}
add_action('customize_register', 'softmir_customize_register');

// ========== Generate HSL for Gradients ==========
function softmir_hex2hsl($hex)
{
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max == $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        switch ($max) {
            case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
            case $g: $h = ($b - $r) / $d + 2; break;
            case $b: $h = ($r - $g) / $d + 4; break;
        }
        $h /= 6;
    }
    return [round($h * 360), round($s * 100), round($l * 100)];
}

// ========== Inject Branding CSS ==========
function softmir_inject_branding_css()
{
    $brand_hex = get_theme_mod('softmir_brand_color', '#0ea5e9');
    $accent_hex = get_theme_mod('softmir_accent_color', '#8b5cf6');
    
    // Generate HSL versions for transparency and gradients
    $brand_hsl = softmir_hex2hsl($brand_hex);
    $accent_hsl = softmir_hex2hsl($accent_hex);
    
    $brand_hsl_str = "{$brand_hsl[0]}, {$brand_hsl[1]}%, {$brand_hsl[2]}%";
    $accent_hsl_str = "{$accent_hsl[0]}, {$accent_hsl[1]}%, {$accent_hsl[2]}%";

    ?>
    <style id="softmir-branding-css">
        :root {
            /* Brand Colors Override */
            --brand: <?php echo esc_attr($brand_hex); ?>;
            --brand-rgb: <?php echo esc_attr(implode(',', sscanf($brand_hex, "#%02x%02x%02x"))); ?>;
            --brand-700: hsl(<?php echo $brand_hsl[0]; ?>, <?php echo $brand_hsl[1]; ?>%, <?php echo max(0, $brand_hsl[2] - 10); ?>%);
            --brand-dark: hsl(<?php echo $brand_hsl[0]; ?>, <?php echo $brand_hsl[1]; ?>%, <?php echo max(0, $brand_hsl[2] - 15); ?>%);
            --brand-50: hsl(<?php echo $brand_hsl[0]; ?>, <?php echo $brand_hsl[1]; ?>%, 97%);
            --brand-100: hsl(<?php echo $brand_hsl[0]; ?>, <?php echo $brand_hsl[1]; ?>%, 94%);
            
            --accent: <?php echo esc_attr($accent_hex); ?>;
            --accent-50: hsl(<?php echo $accent_hsl[0]; ?>, <?php echo $accent_hsl[1]; ?>%, 97%);
            --accent-100: hsl(<?php echo $accent_hsl[0]; ?>, <?php echo $accent_hsl[1]; ?>%, 94%);
        }

        /* Dynamic Hero Gradient */
        .hero {
            background: linear-gradient(135deg, 
                var(--brand-50) 0%, 
                var(--brand-100) 25%, 
                var(--accent-50) 50%, 
                var(--accent-100) 75%, 
                var(--brand-50) 100%
            ) !important;
        }

        /* Floating Glows in Hero */
        .hero::before {
            background: radial-gradient(circle, hsla(<?php echo $brand_hsl_str; ?>, 0.08) 0%, transparent 70%) !important;
        }
        .hero::after {
            background: radial-gradient(circle, hsla(<?php echo $accent_hsl_str; ?>, 0.06) 0%, transparent 70%) !important;
        }

        /* Hero Text Gradient */
        .hero h1 em {
            background: linear-gradient(135deg, var(--brand) 0%, var(--accent) 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'softmir_inject_branding_css', 15);

// ========== Helper: Get Brand Title ==========
function softmir_get_brand_html()
{
    $name = get_theme_mod('softmir_brand_name', 'Soft');
    $accent = get_theme_mod('softmir_brand_accent', 'Mir');
    
    // If name is empty, fallback to blog name
    if (empty($name) && empty($accent)) {
        return esc_html(get_bloginfo('name'));
    }
    
    return esc_html($name) . '<span>' . esc_html($accent) . '</span>';
}
