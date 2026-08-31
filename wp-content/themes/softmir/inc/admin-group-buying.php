<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Add submenu page to Software post type for Group Buying Leads.
 */
function softmir_group_buying_admin_menu()
{
    add_submenu_page(
        'edit.php?post_type=software',
        'Аналитика закупок',
        'Совместные закупки',
        'manage_options',
        'softmir-group-buying',
        'softmir_group_buying_admin_page'
    );
}
add_action('admin_menu', 'softmir_group_buying_admin_menu');

function softmir_group_buying_admin_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'softzor_group_buying';
    $table_pools = $wpdb->prefix . 'softzor_group_buying_pools';

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';

    echo '<div class="wrap">';
    echo '<h1>B2B Group Buying Analytics</h1>';
    
    echo '<h2 class="nav-tab-wrapper">';
    echo '<a href="?post_type=software&page=softmir-group-buying&tab=active" class="nav-tab ' . ($active_tab == 'active' ? 'nav-tab-active' : '') . '">Active requests</a>';
    echo '<a href="?post_type=software&page=softmir-group-buying&tab=pools" class="nav-tab ' . ($active_tab == 'pools' ? 'nav-tab-active' : '') . '">Pool archive</a>';
    echo '</h2><br>';

    if ($active_tab == 'active') {
        // Fetch aggregates
        $query = "
            SELECT software_id, COUNT(id) as total_leads
            FROM {$table}
            WHERE status = 'pending'
            GROUP BY software_id
            ORDER BY total_leads DESC
        ";
        $results = $wpdb->get_results($query);

        if (empty($results)) {
            echo '<p>There are no active applications for joint procurement yet.</p>';
            echo '</div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Program (software)</th>';
        echo '<th>Vendor Terms</th>';
        echo '<th>Vendor Contacts</th>';
        echo '<th>Requests</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($results as $row) {
            $post_id = $row->software_id;
            $title = get_the_title($post_id);
            $edit_link = get_edit_post_link($post_id);

            $discount_available = get_field('discount_available', $post_id);
            $discount_amount = get_field('discount_amount', $post_id);
            $min_licenses = get_field('min_licenses_for_discount', $post_id) ?: 10;
            $vendor_email = get_field('vendor_contact_email', $post_id);
            $vendor_name = get_field('vendor_contact_name', $post_id);

            $status_label = '';
            if ($discount_available === 'yes') {
                $status_label = "<span style='color:green;font-weight:bold;'>Discount {$discount_amount} (от {$min_licenses} pcs)</span>";
            } elseif ($discount_available === 'trial') {
                $status_label = "<span style='color:orange;'>Trial collection</span>";
            } else {
                $status_label = "<span style='color:red;'>No discounts</span>";
            }

            $contact_info = ($vendor_name ? $vendor_name . '<br>' : '') . ($vendor_email ? "<a href='mailto:{$vendor_email}'>{$vendor_email}</a>" : '—');

            $is_ready = ($discount_available === 'yes' && $row->total_leads >= $min_licenses);
            $ready_style = $is_ready ? "background-color: #d4edda;" : "";

            echo "<tr style='{$ready_style}'>";
            echo "<td><strong><a href='{$edit_link}'>{$title}</a></strong></td>";
            echo "<td>{$status_label}</td>";
            echo "<td>{$contact_info}</td>";
            echo "<td><strong>{$row->total_leads} pcs</strong></td>";
            echo "<td><a href='" . admin_url("admin.php?page=softmir-group-buying-details&sw_id={$post_id}") . "' class='button action'>View applications</a></td>";
            echo "</tr>";
        }

        echo '</tbody></table>';
    } else {
        // Pools Tab
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_promo']) && isset($_POST['pool_id'])) {
            $pool_id = sanitize_text_field($_POST['pool_id']);
            $promo_code = sanitize_text_field($_POST['promo_code']);
            $wpdb->update($table_pools, ['promo_code' => $promo_code], ['pool_id' => $pool_id], ['%s'], ['%s']);
            echo '<div class="notice notice-success is-dismissible"><p>Promotional code saved for the pool ' . esc_html($pool_id) . '.</p></div>';
        }

        $pools = $wpdb->get_results("SELECT p.*, COUNT(l.id) as lead_count FROM {$table_pools} p LEFT JOIN {$table} l ON p.pool_id = l.pool_id GROUP BY p.pool_id ORDER BY p.created_at DESC");

        if (empty($pools)) {
            echo '<p>The pool archive is empty.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Pool ID</th><th>Software</th><th>Date of creation</th><th>Leads</th><th>Promo code</th><th>Actions</th></tr></thead><tbody>';
            foreach($pools as $pool) {
                $po_title = get_the_title($pool->software_id);
                $promo_val = esc_attr($pool->promo_code);
                $pool_enc = urlencode($pool->pool_id);
                
                echo "<tr>";
                echo "<td><strong>" . esc_html($pool->pool_id) . "</strong></td>";
                echo "<td>{$po_title}</td>";
                echo "<td>{$pool->created_at}</td>";
                echo "<td>{$pool->lead_count}</td>";
                echo "<td>
                    <form method='POST' style='display:flex;gap:5px;'>
                        <input type='hidden' name='pool_id' value='".esc_attr($pool->pool_id)."'>
                        <input type='text' name='promo_code' value='{$promo_val}' style='width:120px;' placeholder='PROMO123'>
                        <button type='submit' name='update_promo' class='button button-small'>Save</button>
                    </form>
                </td>";
                echo "<td>
                    <a href='" . admin_url("admin.php?page=softmir-group-buying-pool-details&pool_id={$pool_enc}") . "' class='button action'>Inside the pool</a>
                </td>";
                echo "</tr>";
            }
            echo '</tbody></table>';
        }
    }
    echo '</div>';
}

/**
 * Active Leads Details Subpage
 */
function softmir_group_buying_details_menu()
{
    add_submenu_page(
        null,
        'Purchase details',
        'Details',
        'manage_options',
        'softmir-group-buying-details',
        'softmir_group_buying_details_page'
    );
    add_submenu_page(
        null,
        'Application pool',
        'Pool',
        'manage_options',
        'softmir-group-buying-pool-details',
        'softmir_group_buying_pool_details_page'
    );
}
add_action('admin_menu', 'softmir_group_buying_details_menu');

function softmir_group_buying_details_page()
{
    if (!isset($_GET['sw_id'])) return;
    $software_id = intval($_GET['sw_id']);

    global $wpdb;
    $table = $wpdb->prefix . 'softzor_group_buying';
    $table_pools = $wpdb->prefix . 'softzor_group_buying_pools';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lead_action']) && !empty($_POST['lead_ids'])) {
        $lead_ids = array_map('intval', $_POST['lead_ids']);
        $ids_placeholder = implode(',', array_fill(0, count($lead_ids), '%d'));

        if ($_POST['lead_action'] === 'generate_pool') {
            $pool_id = 'POOL-' . strtoupper(wp_generate_password(8, false, false));
            
            $wpdb->insert($table_pools, [
                'pool_id' => $pool_id,
                'software_id' => $software_id,
                'status' => 'negotiating',
                'created_at' => current_time('mysql'),
            ]);

            $args = array_merge([$pool_id], $lead_ids);
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = 'sent', pool_id = %s WHERE id IN ($ids_placeholder)",
                ...$args
            ));
            
            echo '<div class="notice notice-success is-dismissible"><p>✅ Pool<strong>' . $pool_id . '</strong>successfully formed. Applications have been moved to <a href="'.admin_url('edit.php?post_type=software&page=softmir-group-buying&tab=pools').'">Pool archive</a>.</p></div>';
        }
    }

    $leads = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE software_id = %d AND status = 'pending' ORDER BY created_at DESC", $software_id));

    echo '<div class="wrap">';
    echo '<h1>Leads for group purchasing: ' . get_the_title($software_id) . '</h1>';
    echo '<a href="' . admin_url('edit.php?post_type=software&page=softmir-group-buying') . '" class="button">« Back to list</a>';
    echo '<br><br>';

    if (empty($leads)) {
        echo '<p>There are no active applications yet.</p></div>';
        return;
    }

    echo '<form method="post">';
    echo '<div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">';
    echo '<select name="lead_action"><option value="">-- Select action --</option><option value="generate_pool">Allocate to pool (Write off orders)</option></select>';
    echo '<button type="submit" class="button button-primary">Apply</button>';
    echo '</div>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th class="check-column"><input type="checkbox" onclick="document.querySelectorAll(\'.lead-check\').forEach(c=>c.checked=this.checked)"></th><th>Name</th><th>Organization</th><th>Email</th><th>Telephone</th><th>Date</th></tr></thead><tbody>';
    foreach ($leads as $lead) {
        echo "<tr>";
        echo "<th class='check-column'><input type='checkbox' name='lead_ids[]' value='{$lead->id}' class='lead-check'></th>";
        echo "<td><strong>" . esc_html($lead->contact_name) . "</strong></td>";
        echo "<td>" . esc_html($lead->organization) . "</td>";
        echo "<td><a href='mailto:" . esc_attr($lead->contact_email) . "'>{$lead->contact_email}</a></td>";
        echo "<td>" . esc_html($lead->contact_phone) . "</td>";
        echo "<td>{$lead->created_at}</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';
    echo '</form>';
    echo '</div>';
}

function softmir_group_buying_pool_details_page() {
    if (!isset($_GET['pool_id'])) return;
    $pool_id = sanitize_text_field($_GET['pool_id']);

    global $wpdb;
    $table = $wpdb->prefix . 'softzor_group_buying';
    $table_pools = $wpdb->prefix . 'softzor_group_buying_pools';

    $pool = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_pools} WHERE pool_id = %s", $pool_id));
    if(!$pool) return;
    
    $leads = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE pool_id = %s ORDER BY created_at DESC", $pool_id));

    $software_title = get_the_title($pool->software_id);

    echo '<div class="wrap">';
    echo '<h1>Pool management: ' . esc_html($pool_id) . ' (' . $software_title . ')</h1>';
    echo '<a href="' . admin_url('edit.php?post_type=software&page=softmir-group-buying&tab=pools') . '" class="button">« Back to the pool archive</a>';
    echo '<br><br>';

    // Draft generation logic inside Pool Details
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draft_type'])) {
        $count = count($leads);
        $type = $_POST['draft_type'];
        $promo = $pool->promo_code;
        
        $draft_text = '';
        if ($type === 'pitch_ru') {
            $draft_text = "Добрый день!\n\nМы представляем B2B агрегатор Software SoftZor.\nМы собрали пул теплых корпоративных клиентов на ваш продукт: {$software_title}.\nПул: {$pool_id}.\nКоличество организаций: {$count}.\nЗапрашиваемые лицензии: {$count} (с потенциалом расширения).\n\nГотовы ли вы дать партнерскую/оптовую цену для этих клиентов, чтобы мы могли оформить их покупку?\n\nС уважением,\nКоманда SoftZor";
        } elseif ($type === 'pitch_en') {
            $draft_text = "Hello!\n\nWe represent SoftZor, a B2B software aggregator.\nWe've gathered a pool of warm corporate leads interested in your product: {$software_title}.\nPool ID: {$pool_id}.\nTotal interested organizations: {$count}.\nRequested licenses: {$count} (with expansion potential).\n\nWould you be open to providing a partner/bulk discount for these customers so we can help them finalize the purchase?\n\nBest regards,\nSoftZor Team";
        } elseif ($type === 'handover_ru') {
            $draft_text = "Здравствуйте!\nВ продолжение нашего партнерства по пулу {$pool_id}.\nВысылаем контакты заинтересованных B2B-клиентов для выставления счетов:\n\n";
            foreach($leads as $l) {
                $draft_text .= "- {$l->organization} | {$l->contact_name} | {$l->contact_email} | {$l->contact_phone}\n";
            }
            $draft_text .= "\nPlease issue them invoices with the agreed partner discount.";
        } elseif ($type === 'promo_ru') {
            $draft_text = "Здравствуйте!\n\nВы оставляли заявку на совместную закупку программы {$software_title} на platformе SoftZor.\n";
            if ($promo) {
                $draft_text .= "Мы успешно выторговали discount! Ваш эксклюзивный промоcode: {$promo}.\nВведите его при оформлении заказа на official website.";
            } else {
                $draft_text .= "We successfully negotiated a discount! (But the promotional code has not yet been saved in the system, please contact us).";
            }
        }
        
        echo "<div class='notice notice-info' style='padding:15px; margin-left:0;'><h3 style='margin-top:0'>📝 Generated draft (".esc_html($type).")</h3><textarea style='width:100%; height:200px;' onfocus='this.select()'>".esc_textarea($draft_text)."</textarea></div>";
    }

    echo '<form method="post" style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px; box-shadow:0 1px 1px rgba(0,0,0,.04);">';
    echo '<h3 style="margin-top:0;">Pool letter generator</h3>';
    echo '<div style="display:flex; gap:10px; align-items:center;">';
    echo '<select name="draft_type" style="max-width:400px; width:100%;">';
    echo '<option value="">-- Select draft type --</option>';
    echo '<option value="pitch_ru">Letter to vendor: Discount request (Pitch) - RU</option>';
    echo '<option value="pitch_en">Letter to vendor: Discount request (Pitch) - EN</option>';
    echo '<option value="handover_ru">Letter to vendor: Transfer of contacts (Handover) - RU</option>';
    echo '<option value="promo_ru">Letter to pool clients: Promo code distribution - RU</option>';
    echo '</select>';
    echo '<button type="submit" class="button button-primary">Generate</button>';
    echo '</div>';
    echo '</form>';

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Name</th><th>Organization</th><th>Email</th><th>Telephone</th><th>Date of application</th></tr></thead><tbody>';
    foreach ($leads as $lead) {
        echo "<tr>";
        echo "<td><strong>" . esc_html($lead->contact_name) . "</strong></td>";
        echo "<td>" . esc_html($lead->organization) . "</td>";
        echo "<td><a href='mailto:" . esc_attr($lead->contact_email) . "'>{$lead->contact_email}</a></td>";
        echo "<td>" . esc_html($lead->contact_phone) . "</td>";
        echo "<td>{$lead->created_at}</td>";
        echo "</tr>";
    }
    echo '</tbody></table>';
    echo '</div>';
}
