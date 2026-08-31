<?php
/**
 * SoftMir — Admin Settings Page
 * Adds Gemini & Firecrawl API key management under Settings → SoftMir AI
 */

// ========== Helper: Get Gemini API Key ==========
// Checks DB option first, then falls back to wp-config.php constant
function softmir_get_gemini_key()
{
    $db_key = get_option('softmir_gemini_api_key', '');
    if (!empty($db_key)) {
        return $db_key;
    }
    // Fallback to constant in wp-config.php
    if (defined('GOOGLE_GEMINI_KEY') && !empty(GOOGLE_GEMINI_KEY)) {
        return GOOGLE_GEMINI_KEY;
    }
    return '';
}

// ========== Helper: Get Firecrawl API Key ==========
// Checks DB option first, then falls back to wp-config.php constant
function softmir_get_firecrawl_key()
{
    $db_key = get_option('softmir_firecrawl_api_key', '');
    if (!empty($db_key)) {
        return $db_key;
    }
    // Fallback to constant in wp-config.php
    if (defined('FIRECRAWL_API_KEY') && !empty(FIRECRAWL_API_KEY)) {
        return FIRECRAWL_API_KEY;
    }
    return '';
}

// ========== Helper: Check if Firecrawl is Active ==========
function softmir_is_firecrawl_enabled()
{
    $enabled = get_option('softmir_firecrawl_enabled', '0');
    $key = softmir_get_firecrawl_key();
    return ($enabled === '1' && !empty($key));
}

// ========== Register Settings Page ==========
function softmir_admin_settings_menu()
{
    add_options_page(
        __('AI Hub Settings', 'softmir'),
        __('AI Hub', 'softmir'),
        'manage_options',
        'softmir-ai-settings',
        'softmir_admin_settings_page'              // Callback
    );
}
add_action('admin_menu', 'softmir_admin_settings_menu');

// ========== Register Settings ==========
function softmir_register_settings()
{
    // --- Gemini ---
    register_setting('softmir_ai_settings', 'softmir_gemini_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    add_settings_section(
        'softmir_ai_section',
        __('Google Gemini API', 'softmir'),
        'softmir_ai_section_description',
        'softmir-ai-settings'
    );

    add_settings_field(
        'softmir_gemini_api_key',
        __('API Key', 'softmir'),
        'softmir_gemini_api_key_field',
        'softmir-ai-settings',
        'softmir_ai_section'
    );

    // --- Automation Cron ---
    register_setting('softmir_ai_settings', 'softmir_cron_enabled', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1',
    ]);

    add_settings_field(
        'softmir_cron_enabled',
        __('Background auto-fill', 'softmir'),
        'softmir_cron_enabled_field',
        'softmir-ai-settings',
        'softmir_ai_section'
    );

    // --- Firecrawl ---
    register_setting('softmir_ai_settings', 'softmir_firecrawl_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    register_setting('softmir_ai_settings', 'softmir_firecrawl_enabled', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '0',
    ]);

    add_settings_section(
        'softmir_firecrawl_section',
        __('🔥 Firecrawl API (Scout)', 'softmir'),
        'softmir_firecrawl_section_description',
        'softmir-ai-settings'
    );

    add_settings_field(
        'softmir_firecrawl_enabled',
        __('Module status', 'softmir'),
        'softmir_firecrawl_enabled_field',
        'softmir-ai-settings',
        'softmir_firecrawl_section'
    );

    add_settings_field(
        'softmir_firecrawl_api_key',
        __('API Key', 'softmir'),
        'softmir_firecrawl_api_key_field',
        'softmir-ai-settings',
        'softmir_firecrawl_section'
    );

    // --- GEO & Localization ---
    register_setting('softmir_ai_settings', 'softmir_geo_city', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Kyiv']);
    register_setting('softmir_ai_settings', 'softmir_geo_address', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '']);
    register_setting('softmir_ai_settings', 'softmir_geo_email', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_bloginfo('admin_email')]);

    add_settings_section(
        'softmir_geo_section',
        __('🌍 GEO targeting and localization', 'softmir'),
        'softmir_geo_section_description',
        'softmir-ai-settings'
    );

    add_settings_field('softmir_geo_city', __('City (UA)', 'softmir'), 'softmir_geo_city_field', 'softmir-ai-settings', 'softmir_geo_section');
    add_settings_field('softmir_geo_address', __('Address/Street', 'softmir'), 'softmir_geo_address_field', 'softmir-ai-settings', 'softmir_geo_section');
    add_settings_field('softmir_geo_email', __('Contact Email', 'softmir'), 'softmir_geo_email_field', 'softmir-ai-settings', 'softmir_geo_section');
}
add_action('admin_init', 'softmir_register_settings');

// ========== Gemini Section Description ==========
function softmir_ai_section_description()
{
    echo '<p>' . esc_html__('Enter your Google Gemini API key to use the AI ​​translation and content generation functions.', 'softmir') . '</p>';

    // Show current key source
    $db_key = get_option('softmir_gemini_api_key', '');
    $const_key = defined('GOOGLE_GEMINI_KEY') && !empty(GOOGLE_GEMINI_KEY);

    if (!empty($db_key)) {
        echo '<p style="color:#00a32a;">✅ ' . esc_html__('The key was loaded from the database.', 'softmir') . '</p>';
    } elseif ($const_key) {
        echo '<p style="color:#dba617;">⚠️ ' . esc_html__('The key is set via wp-config.php (GOOGLE_GEMINI_KEY). After saving in the settings, it will be given priority.', 'softmir') . '</p>';
    } else {
        echo '<p style="color:#d63638;">❌ ' . esc_html__('API key not set. Enter it below or add GOOGLE_GEMINI_KEY to wp-config.php.', 'softmir') . '</p>';
    }
}

// ========== Gemini API Key Field ==========
function softmir_gemini_api_key_field()
{
    $value = get_option('softmir_gemini_api_key', '');
    $masked = '';
    if (!empty($value)) {
        $masked = substr($value, 0, 6) . str_repeat('•', max(0, strlen($value) - 10)) . substr($value, -4);
    }
    ?>
    <input type="password" id="softmir_gemini_api_key" name="softmir_gemini_api_key" value="<?php echo esc_attr($value); ?>"
        class="regular-text" autocomplete="off" placeholder="AIza...">
    <button type="button" class="button"
        onclick="var f=document.getElementById('softmir_gemini_api_key');f.type=f.type==='password'?'text':'password';">
        👁
        <?php esc_html_e('Show/Hide', 'softmir'); ?>
    </button>
    <?php if (!empty($masked)): ?>
        <p class="description">
            <?php printf(esc_html__('Current key: %s', 'softmir'), '<code>' . esc_html($masked) . '</code>'); ?>
        </p>
    <?php endif; ?>
    <p class="description">
        <?php echo wp_kses(
            __('Get the key at <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>.', 'softmir'),
            ['a' => ['href' => [], 'target' => []]]
        ); ?>
    </p>
    <?php
}

// ========== Automation Cron Enabled Field ==========
function softmir_cron_enabled_field()
{
    $val = get_option('softmir_cron_enabled', '1');
    ?>
    <label class="softmir-switch">
        <input type="checkbox" name="softmir_cron_enabled" value="1" <?php checked($val, '1'); ?>>
        <strong><?php esc_html_e('Allow background cron to automatically generate software and category cards (Pause/Start)', 'softmir'); ?></strong>
    </label>
    <p class="description" style="color: #dba617;">
        <?php esc_html_e('Uncheck the box to completely pause automatic collection and generation of AI.', 'softmir'); ?>
    </p>
    <?php
}

// ========== Firecrawl Section Description ==========
function softmir_firecrawl_section_description()
{
    echo '<p>' . esc_html__('Firecrawl is used by the Scout mode to scrape real software sites. When the module is active, Scout makes real requests to the Internet (spends credits).', 'softmir') . '</p>';

    $key = softmir_get_firecrawl_key();
    $enabled = get_option('softmir_firecrawl_enabled', '0');

    if (empty($key)) {
        echo '<p style="color:#999;">ℹ️ ' . esc_html__('API key not set. The scout operates in Gemini-only mode.', 'softmir') . '</p>';
    } elseif ($enabled === '1') {
        echo '<p style="color:#00a32a;">🚀 ' . esc_html__('Firecrawl is active and will be used for scraping.', 'softmir') . '</p>';
    } else {
        echo '<p style="color:#dba617;">⏸️ ' . esc_html__('The module is turned off. The key is saved, but credits are not spent (Gemini-only mode).', 'softmir') . '</p>';
    }
}

// ========== Firecrawl Enabled Checkbox ==========
function softmir_firecrawl_enabled_field()
{
    $val = get_option('softmir_firecrawl_enabled', '0');
    ?>
    <label class="softmir-switch">
        <input type="checkbox" name="softmir_firecrawl_enabled" value="1" <?php checked($val, '1'); ?>>
        <strong><?php esc_html_e('Use Firecrawl to enrich Scout data', 'softmir'); ?></strong>
    </label>
    <p class="description">
        <?php esc_html_e('Uncheck this box if you want to save credits or temporarily disable real parsing.', 'softmir'); ?>
    </p>
    <?php
}

// ========== Firecrawl API Key Field ==========
function softmir_firecrawl_api_key_field()
{
    $value = get_option('softmir_firecrawl_api_key', '');
    $masked = '';
    if (!empty($value)) {
        $masked = substr($value, 0, 6) . str_repeat('•', max(0, strlen($value) - 10)) . substr($value, -4);
    }
    ?>
    <input type="password" id="softmir_firecrawl_api_key" name="softmir_firecrawl_api_key"
        value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="off" placeholder="fc-...">
    <button type="button" class="button"
        onclick="var f=document.getElementById('softmir_firecrawl_api_key');f.type=f.type==='password'?'text':'password';">
        👁
    </button>
    <?php if (!empty($masked)): ?>
        <p class="description">
            <?php printf(esc_html__('Current key: %s', 'softmir'), '<code>' . esc_html($masked) . '</code>'); ?>
        </p>
    <?php endif; ?>
    <p class="description">
        <?php echo wp_kses(
            __('Get the key from <a href="https://www.firecrawl.dev/app/api-keys" target="_blank">Firecrawl Dashboard</a>. Free tariff: 500 credits.', 'softmir'),
            ['a' => ['href' => [], 'target' => []]]
        ); ?>
    </p>
    <?php
}

// ========== GEO Section ==========
function softmir_geo_section_description()
{
    echo '<p>' . esc_html__('This data is used in Schema.org micro-markup to confirm the geographical location of the site to the Ukrainian market.', 'softmir') . '</p>';
}

function softmir_geo_city_field()
{
    $val = get_option('softmir_geo_city', 'Kyiv');
    echo '<input type="text" name="softmir_geo_city" value="' . esc_attr($val) . '" class="regular-text" placeholder="Kyiv">';
}

function softmir_geo_address_field()
{
    $val = get_option('softmir_geo_address', '');
    echo '<input type="text" name="softmir_geo_address" value="' . esc_attr($val) . '" class="regular-text" placeholder="st. Khreshchatyk, 1">';
}

function softmir_geo_email_field()
{
    $val = get_option('softmir_geo_email', get_bloginfo('admin_email'));
    echo '<input type="email" name="softmir_geo_email" value="' . esc_attr($val) . '" class="regular-text">';
}

// ========== Settings Page HTML ==========
function softmir_admin_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html(get_admin_page_title()); ?>
        </h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('softmir_ai_settings');
            do_settings_sections('softmir-ai-settings');

            // Autopost settings section
            if (function_exists('softmir_autopost_render_settings')) {
                softmir_autopost_render_settings();
            }

            submit_button(__('Save settings', 'softmir'));
            ?>
        </form>
    </div>
    <style>
        .softmir-switch {
            font-size: 14px;
            vertical-align: middle;
        }

        .softmir-switch input {
            margin-top: -3px;
            margin-right: 5px;
            width: 18px;
            height: 18px;
        }
    </style>
    <?php
}

// ========== AI Background Logger ==========
function softmir_log_ai_action($msg)
{
    $log_file = WP_CONTENT_DIR . '/softmir-ai-cron.log';
    // Use true WordPress time
    $date = current_time('mysql');
    $log_line = "[{$date}] " . $msg . "\n";
    file_put_contents($log_file, $log_line, FILE_APPEND);
}
