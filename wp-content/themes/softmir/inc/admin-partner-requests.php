<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Admin page for Vendor Partnership Requests
 */
function softmir_partner_requests_admin_menu()
{
    /*
    add_submenu_page(
        'edit.php?post_type=software',
        'Заявки партнеров',
        'Заявки партнеров',
        'manage_options',
        'softmir-partner-requests',
        'softmir_partner_requests_admin_page'
    );
    */
}
add_action('admin_menu', 'softmir_partner_requests_admin_menu');

function softmir_partner_requests_admin_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_partner_requests';

    // Handle status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && isset($_POST['request_id'])) {
        $rid = intval($_POST['request_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $wpdb->update($table, ['status' => $new_status], ['id' => $rid], ['%s'], ['%d']);
        echo '<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>';
    }

    $requests = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");

    echo '<div class="wrap">';
    echo '<h1>Partnership applications from vendors</h1>';
    echo '<p>Requests from vendors who want to place their product in the SoftZor catalog are displayed here.</p>';

    if (empty($requests)) {
        echo '<p>No applications yet.</p></div>';
        return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>Company</th><th>Product</th><th>URL</th><th>Category</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th>Date</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    $statuses = ['new' => '🆕 New', 'reviewing' => '🔍 Under consideration', 'approved' => '✅ Approved', 'rejected' => '❌ Rejected'];

    foreach ($requests as $r) {
        $status_label = $statuses[$r->status] ?? $r->status;
        $row_style = '';
        if ($r->status === 'approved')
            $row_style = 'background-color:#d4edda;';
        if ($r->status === 'rejected')
            $row_style = 'background-color:#f8d7da;';

        echo "<tr style='{$row_style}'>";
        echo "<td><strong>" . esc_html($r->company_name) . "</strong></td>";
        echo "<td>" . esc_html($r->product_name) . "</td>";
        echo "<td><a href='" . esc_url($r->product_url) . "' target='_blank' rel='noopener'>↗ Website</a></td>";
        echo "<td>" . esc_html($r->category) . "</td>";
        echo "<td>" . esc_html($r->contact_name) . "</td>";
        echo "<td><a href='mailto:" . esc_attr($r->contact_email) . "'>" . esc_html($r->contact_email) . "</a></td>";
        echo "<td>" . esc_html($r->contact_phone) . "</td>";
        echo "<td>{$status_label}</td>";
        echo "<td>" . esc_html($r->created_at) . "</td>";
        echo "<td>
            <form method='POST' style='display:flex;gap:4px;'>
                <input type='hidden' name='request_id' value='{$r->id}'>
                <select name='new_status' style='font-size:12px;'>
                    <option value='new'>New</option>
                    <option value='reviewing'>Reviewing</option>
                    <option value='approved'>Approved</option>
                    <option value='rejected'>Rejected</option>
                </select>
                <button type='submit' name='update_status' class='button button-small'>OK</button>
            </form>
        </td>";
        echo "</tr>";

        if ($r->message) {
            echo "<tr style='{$row_style}'><td colspan='10' style='padding-left:20px;font-size:12px;color:#555;'><strong>Message:</strong> " . esc_html($r->message) . "</td></tr>";
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}
