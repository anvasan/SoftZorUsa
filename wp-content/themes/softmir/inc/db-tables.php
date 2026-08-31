<?php
/**
 * SoftZor Custom Database Tables
 * Phase 1 Upgrade: External Scout + Intent Logs
 *
 * Creates two custom SQL tables:
 * - wp_softzor_external_scout: Software found by AI Scout
 * - wp_softzor_intent_logs: User intent/session logs for analytics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Current DB schema version.
 * Bump this when you change the table structure to trigger a re-migration.
 */
define('SOFTZOR_DB_VERSION', '1.0.6');

/**
 * Create or update custom tables using dbDelta.
 * Called on theme activation and checked on admin_init.
 */
function softmir_create_custom_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Table 1: External Scout — software found by AI in the web
    $table_scout = $wpdb->prefix . 'softzor_external_scout';
    $sql_scout = "CREATE TABLE {$table_scout} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        software_name VARCHAR(255) NOT NULL,
        website_url VARCHAR(500) DEFAULT '',
        category_slug VARCHAR(100) DEFAULT '',
        hit_count INT UNSIGNED DEFAULT 1,
        last_query TEXT,
        ai_summary LONGTEXT,
        status VARCHAR(50) DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_category_slug (category_slug),
        KEY idx_status (status),
        KEY idx_hit_count (hit_count)
    ) {$charset_collate};";

    // Table 2: Intent Logs — user search sessions for analytics
    $table_intent = $wpdb->prefix . 'softzor_intent_logs';
    $sql_intent = "CREATE TABLE {$table_intent} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(100) DEFAULT '',
        user_intent TEXT NOT NULL,
        offered_partners TEXT,
        selected_external_id BIGINT UNSIGNED DEFAULT NULL,
        is_expert_mode TINYINT(1) DEFAULT 0,
        generated_brief LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_session_id (session_id),
        KEY idx_created_at (created_at),
        KEY idx_is_expert_mode (is_expert_mode)
    ) {$charset_collate};";

    // Table 3: Group Buying Leads
    $table_group_buy = $wpdb->prefix . 'softzor_group_buying';
    $sql_group_buy = "CREATE TABLE {$table_group_buy} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        software_id BIGINT UNSIGNED NOT NULL,
        pool_id VARCHAR(50) DEFAULT '',
        contact_name VARCHAR(255) DEFAULT '',
        contact_email VARCHAR(255) NOT NULL,
        contact_phone VARCHAR(50) DEFAULT '',
        organization VARCHAR(255) DEFAULT '',
        seats_needed INT UNSIGNED DEFAULT 1,
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_software_id (software_id),
        KEY idx_pool_id (pool_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // Table 4: Group Buying Pools
    $table_pools = $wpdb->prefix . 'softzor_group_buying_pools';
    $sql_pools = "CREATE TABLE {$table_pools} (
        pool_id VARCHAR(50) NOT NULL,
        software_id BIGINT UNSIGNED NOT NULL,
        promo_code VARCHAR(100) DEFAULT '',
        status VARCHAR(50) DEFAULT 'negotiating',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (pool_id),
        KEY idx_software_id (software_id),
        KEY idx_status (status)
    ) {$charset_collate};";

    // Table 5: Click Log — outbound click tracking with timestamps
    $table_clicks = $wpdb->prefix . 'softzor_click_log';
    $sql_clicks = "CREATE TABLE {$table_clicks} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        software_id BIGINT UNSIGNED NOT NULL,
        referrer_url VARCHAR(500) DEFAULT '',
        user_ip VARCHAR(45) DEFAULT '',
        user_agent VARCHAR(500) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_software_id (software_id),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta($sql_scout);
    dbDelta($sql_intent);
    dbDelta($sql_group_buy);
    dbDelta($sql_pools);
    dbDelta($sql_clicks);

    // Table 6: Partner Requests — vendor onboarding applications
    $table_partners = $wpdb->prefix . 'softzor_partner_requests';
    $sql_partners = "CREATE TABLE {$table_partners} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_url VARCHAR(500) DEFAULT '',
        category VARCHAR(255) DEFAULT '',
        contact_name VARCHAR(255) DEFAULT '',
        contact_email VARCHAR(255) NOT NULL,
        contact_phone VARCHAR(50) DEFAULT '',
        message TEXT,
        status VARCHAR(50) DEFAULT 'new',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY idx_status (status)
    ) {$charset_collate};";
    dbDelta($sql_partners);

    // Save version to prevent running on every page load
    update_option('softzor_db_version', SOFTZOR_DB_VERSION);
}

/**
 * Check DB version on admin_init and run migration if needed.
 */
function softmir_check_db_version()
{
    if (get_option('softzor_db_version') !== SOFTZOR_DB_VERSION) {
        softmir_create_custom_tables();
    }
}
add_action('admin_init', 'softmir_check_db_version');

/**
 * Also run on theme activation.
 */
add_action('after_switch_theme', 'softmir_create_custom_tables');

// ========== Helper Functions for Custom Tables ==========

/**
 * Insert or update an External Scout record.
 *
 * @param array $data {
 *     @type string $software_name  Required. Name of the software.
 *     @type string $website_url    Website URL.
 *     @type string $category_slug  Category slug.
 *     @type string $last_query     The user query that triggered the search.
 *     @type string $ai_summary     JSON tech passport from AI.
 *     @type string $status         Status: draft, published, rejected.
 * }
 * @return int|false Insert ID on success, false on failure.
 */
function softmir_insert_scout_record($data)
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_external_scout';

    // Check if record with same name already exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, hit_count FROM {$table} WHERE software_name = %s",
        $data['software_name']
    ));

    if ($existing) {
        // Increment hit_count and update query
        $wpdb->update(
            $table,
            [
                'hit_count' => $existing->hit_count + 1,
                'last_query' => $data['last_query'] ?? '',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $existing->id],
            ['%d', '%s', '%s'],
            ['%d']
        );
        return $existing->id;
    }

    // Insert new record
    $result = $wpdb->insert($table, [
        'software_name' => $data['software_name'],
        'website_url' => $data['website_url'] ?? '',
        'category_slug' => $data['category_slug'] ?? '',
        'hit_count' => 1,
        'last_query' => $data['last_query'] ?? '',
        'ai_summary' => $data['ai_summary'] ?? '',
        'status' => $data['status'] ?? 'draft',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);

    return $result ? $wpdb->insert_id : false;
}

/**
 * Log a user intent/session.
 *
 * @param array $data {
 *     @type string $session_id           Session identifier.
 *     @type string $user_intent          Full text of user's query.
 *     @type string $offered_partners     JSON array of offered partner IDs.
 *     @type int    $selected_external_id ID of selected external software (if any).
 *     @type bool   $is_expert_mode       Whether the query was in Deep Tech mode.
 *     @type string $generated_brief      AI generated brief for vendors.
 * }
 * @return int|false Insert ID on success, false on failure.
 */
function softmir_log_intent($data)
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_intent_logs';

    $result = $wpdb->insert($table, [
        'session_id' => $data['session_id'] ?? '',
        'user_intent' => $data['user_intent'],
        'offered_partners' => $data['offered_partners'] ?? '',
        'selected_external_id' => $data['selected_external_id'] ?? null,
        'is_expert_mode' => !empty($data['is_expert_mode']) ? 1 : 0,
        'generated_brief' => $data['generated_brief'] ?? '',
        'created_at' => current_time('mysql'),
    ], ['%s', '%s', '%s', '%d', '%d', '%s', '%s']);

    return $result ? $wpdb->insert_id : false;
}

/**
 * Log a group buying lead.
 *
 * @param array $data {
 *     @type int    $software_id   ID of the software post.
 *     @type string $contact_email User's contact email.
 *     @type int    $seats_needed  Number of licenses required.
 * }
 * @return int|false Insert ID on success, false on failure.
 */
function softmir_log_group_buying_lead($data)
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_group_buying';

    $result = $wpdb->insert($table, [
        'software_id' => intval($data['software_id']),
        'contact_name' => sanitize_text_field($data['contact_name'] ?? ''),
        'contact_email' => sanitize_email($data['contact_email']),
        'contact_phone' => sanitize_text_field($data['contact_phone'] ?? ''),
        'organization' => sanitize_text_field($data['organization'] ?? ''),
        'seats_needed' => intval($data['seats_needed'] ?? 1),
        'status' => 'pending',
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);

    return $result ? $wpdb->insert_id : false;
}

/**
 * Log an outbound click with full context.
 *
 * @param int $software_id Post ID of the software.
 * @return int|false Insert ID on success, false on failure.
 */
function softmir_log_click($software_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_click_log';

    $result = $wpdb->insert($table, [
        'software_id' => intval($software_id),
        'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        'user_ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 500) : '',
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%s', '%s']);

    return $result ? $wpdb->insert_id : false;
}
