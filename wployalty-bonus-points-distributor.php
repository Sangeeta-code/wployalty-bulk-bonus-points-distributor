<?php
/**
 * Plugin Name: WPLoyalty Bonus Point Distributor (TechiEvolve)
 * Description: Distribute bonus points to customers - supports date-based and CSV upload methods
 * Version: 8.0 - WITH CSV UPLOAD
 * Author: TechiEvolve
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPLoyalty_Bonus_Distributor {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_distribute_bonus_points', array($this, 'distribute_bonus_points'));
        add_action('admin_post_distribute_csv_points', array($this, 'distribute_csv_points'));
        add_action('admin_post_test_single_customer', array($this, 'test_single_customer'));
        add_action('admin_post_download_sample_csv', array($this, 'download_sample_csv'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    // Add menu item in WordPress admin
    public function add_admin_menu() {
        add_menu_page(
            'Bonus Points Distributor',
            'Bonus Points',
            'manage_options',
            'bonus-points-distributor',
            array($this, 'admin_page'),
            'dashicons-awards',
            30
        );
    }
    
    // Get table columns
    private function get_table_columns($table_name) {
        global $wpdb;
        $columns = array();
        
        $results = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        foreach ($results as $column) {
            $columns[] = $column->Field;
        }
        
        return $columns;
    }
    
    // Get active campaign ID from WPLoyalty
    private function get_active_campaign_id() {
        global $wpdb;
        
        $campaign_table = $wpdb->prefix . 'wlr_earn_campaign';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$campaign_table'") === $campaign_table) {
            $campaign_id = $wpdb->get_var("SELECT id FROM $campaign_table WHERE campaign_type = 'point' LIMIT 1");
            return $campaign_id ? intval($campaign_id) : 0;
        }
        
        return 0;
    }
    
    // Download sample CSV
    public function download_sample_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        // Sample CSV data
        $csv_data = array(
            array('customer_email', 'points', 'note'),
            array('customer1@example.com', '1000', 'Year-End Bonus'),
            array('customer2@example.com', '1000', 'Year-End Bonus'),
            array('customer3@example.com', '500', 'Special Promotion'),
            array('customer4@example.com', '1500', 'VIP Customer Reward'),
        );
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="wployalty-points-sample.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    
    // Admin page HTML
    public function admin_page() {
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap"><h1>Error</h1><p>WooCommerce is not active. Please activate WooCommerce first.</p></div>';
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlr_earn_campaign_transaction';
        $user_table = $wpdb->prefix . 'wlr_users';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $user_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$user_table'") === $user_table;
        
        $campaign_id = $this->get_active_campaign_id();
        
        ?>
        <div class="wrap">
            <h1>Bonus Points Distribution</h1>
            
            <?php if (!$table_exists): ?>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> WPLoyalty earn transaction table not found. Please make sure WPLoyalty Pro plugin is activated.</p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <div class="notice notice-success" style="margin: 20px 0;">
                <p><strong>✅ System Ready!</strong></p>
                <p>Campaign ID: <strong><?php echo $campaign_id > 0 ? $campaign_id : 'Auto-detect'; ?></strong></p>
            </div>
            
            <!-- TESTING SECTION -->
            <div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin-bottom: 30px; border-left: 4px solid #00a0d2;">
                <h2 style="margin-top: 0;">🧪 Test Single Customer</h2>
                <p>Test the point distribution by awarding points to a specific customer email.</p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="test_single_customer">
                    <?php wp_nonce_field('test_single_customer_action', 'test_single_customer_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="customer_email">Customer Email</label>
                            </th>
                            <td>
                                <input type="email" 
                                       name="customer_email" 
                                       id="customer_email" 
                                       class="regular-text" 
                                       placeholder="customer@example.com" 
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test_points">Points to Award</label>
                            </th>
                            <td>
                                <input type="number" 
                                       name="test_points" 
                                       id="test_points" 
                                       value="1000" 
                                       min="1" 
                                       class="small-text" 
                                       required>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <input type="submit" 
                               name="submit" 
                               class="button button-secondary button-large" 
                               value="Test: Award Points to This Customer">
                    </p>
                </form>
            </div>
            
            <!-- CSV UPLOAD SECTION -->
            <div style="background: #fff; border: 1px solid #ccc; padding: 20px; margin-bottom: 30px; border-left: 4px solid #9b59b6;">
                <h2 style="margin-top: 0;">📁 CSV Upload - Award Points to Multiple Customers</h2>
                <p>Upload a CSV file with customer emails and points to distribute.</p>
                
                <div style="background: #e8f4f8; padding: 15px; margin: 15px 0; border-left: 4px solid #00a0d2;">
                    <h3 style="margin-top: 0;">CSV Format Requirements:</h3>
                    <!-- <p><strong>Required columns:</strong></p>
                    <ul>
                        <li><code>customer_email</code> - The customer's email address (must be registered in your store)</li>
                        <li><code>points</code> - Number of points to award (e.g., 1000)</li>
                        <li><code>note</code> - Description/reason for the points (e.g., "Year-End Bonus")</li>
                    </ul>
                    <p><strong>Example CSV content:</strong></p>
                    <pre style="background: #fff; padding: 10px; border: 1px solid #ddd;">customer_email,points,note
customer1@example.com,1000,Year-End Bonus
customer2@example.com,1000,Year-End Bonus
customer3@example.com,500,Special Promotion</pre> -->
                </div>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="download_sample_csv">
                    <p>
                        <input type="submit" 
                               name="submit" 
                               class="button button-secondary" 
                               value="📥 Download Sample CSV File">
                    </p>
                </form>
                
                <hr style="margin: 30px 0;">
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="distribute_csv_points">
                    <?php wp_nonce_field('distribute_csv_points_action', 'distribute_csv_points_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file">Upload CSV File</label>
                            </th>
                            <td>
                                <input type="file" 
                                       name="csv_file" 
                                       id="csv_file" 
                                       accept=".csv" 
                                       required>
                                <p class="description">Upload a CSV file with customer emails and points to award.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <input type="submit" 
                               name="submit" 
                               class="button button-primary button-large" 
                               value="🚀 Upload CSV and Distribute Points"
                               onclick="return confirm('Are you sure you want to process this CSV file and distribute points? This action cannot be undone.');">
                    </p>
                </form>
            </div>
            
        </div>
        <?php
    }
    
    // Process CSV upload and distribute points
    public function distribute_csv_points() {
        try {
            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }
            
            // Verify nonce
            if (!isset($_POST['distribute_csv_points_nonce']) || 
                !wp_verify_nonce($_POST['distribute_csv_points_nonce'], 'distribute_csv_points_action')) {
                wp_die('Security check failed');
            }
            
            // Check if file was uploaded
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'csv_error' => 'upload_failed'
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
            
            // Read CSV file
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            if (!$handle) {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'csv_error' => 'read_failed'
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
            
            $success_count = 0;
            $failed_count = 0;
            $skipped_count = 0;
            $row_number = 0;
            $errors = array();
            
            // Read header row
            $header = fgetcsv($handle);
            
            // Validate header
            if (!$header || !in_array('customer_email', $header) || !in_array('points', $header)) {
                fclose($handle);
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'csv_error' => 'invalid_format',
                        'error_msg' => urlencode('CSV must have customer_email and points columns')
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
            
            // Get column indexes
            $email_index = array_search('customer_email', $header);
            $points_index = array_search('points', $header);
            $note_index = array_search('note', $header);
            
            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                $row_number++;
                
                // Skip empty rows
                if (empty($row) || !isset($row[$email_index]) || !isset($row[$points_index])) {
                    $skipped_count++;
                    continue;
                }
                
                $customer_email = sanitize_email(trim($row[$email_index]));
                $points = intval(trim($row[$points_index]));
                $note = $note_index !== false && isset($row[$note_index]) ? sanitize_text_field(trim($row[$note_index])) : 'CSV Bulk Award';
                
                // Validate email
                if (!is_email($customer_email)) {
                    $failed_count++;
                    $errors[] = "Row $row_number: Invalid email - $customer_email";
                    continue;
                }
                
                // Validate points
                if ($points <= 0) {
                    $failed_count++;
                    $errors[] = "Row $row_number: Invalid points amount - $points";
                    continue;
                }
                
                // Get user by email
                $user = get_user_by('email', $customer_email);
                
                if (!$user) {
                    $failed_count++;
                    $errors[] = "Row $row_number: Customer not found - $customer_email";
                    continue;
                }
                
                // Award points
                $result = $this->award_points($user->ID, $points, $note);
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $failed_count++;
                    $errors[] = "Row $row_number ($customer_email): " . $result['error'];
                }
            }
            
            fclose($handle);
            
            // Store errors in transient for display
            if (!empty($errors)) {
                set_transient('wployalty_csv_errors', $errors, 300); // 5 minutes
            }
            
            // Redirect with results
            $redirect_url = add_query_arg(
                array(
                    'page' => 'bonus-points-distributor',
                    'csv_success' => $success_count,
                    'csv_failed' => $failed_count,
                    'csv_skipped' => $skipped_count
                ),
                admin_url('admin.php')
            );
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            error_log('WPLoyalty CSV Distribution Error: ' . $e->getMessage());
            wp_die('An error occurred during CSV processing: ' . esc_html($e->getMessage()));
        }
    }
    
    // Test single customer
    public function test_single_customer() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }
            
            if (!isset($_POST['test_single_customer_nonce']) || 
                !wp_verify_nonce($_POST['test_single_customer_nonce'], 'test_single_customer_action')) {
                wp_die('Security check failed');
            }
            
            $customer_email = sanitize_email($_POST['customer_email']);
            $points = intval($_POST['test_points']);
            
            $user = get_user_by('email', $customer_email);
            
            if (!$user) {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'test_error' => 'not_found',
                        'email' => urlencode($customer_email)
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
            
            $user_id = $user->ID;
            $points_before = $this->get_customer_points($user_id);
            
            $award_result = $this->award_points($user_id, $points, 'Test Bonus - Admin Manual Award');
            
            $points_after = $this->get_customer_points($user_id);
            
            if ($award_result['success']) {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'test_success' => '1',
                        'email' => urlencode($customer_email),
                        'user_id' => $user_id,
                        'points_awarded' => $points,
                        'points_before' => $points_before,
                        'points_after' => $points_after,
                        'method' => urlencode($award_result['method'])
                    ),
                    admin_url('admin.php')
                );
            } else {
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'bonus-points-distributor',
                        'test_error' => 'award_failed',
                        'email' => urlencode($customer_email),
                        'error_msg' => urlencode($award_result['error'])
                    ),
                    admin_url('admin.php')
                );
            }
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            error_log('WPLoyalty Bonus Distributor Error: ' . $e->getMessage());
            wp_die('An error occurred: ' . esc_html($e->getMessage()));
        }
    }
    
    // Get eligible customers
    private function get_eligible_customers() {
        global $wpdb;
        
        try {
            $end_date = '2025-12-31 23:59:59';
            
            $query = $wpdb->prepare("
                SELECT DISTINCT pm.meta_value as user_id, MAX(p.post_date) as order_date
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND pm.meta_key = '_customer_user'
                AND pm.meta_value > 0
                AND p.post_date <= %s
                GROUP BY pm.meta_value
                ORDER BY order_date DESC
                LIMIT 1000
            ", $end_date);
            
            $results = $wpdb->get_results($query, ARRAY_A);
            
            if ($wpdb->last_error) {
                throw new Exception('Database error: ' . $wpdb->last_error);
            }
            
            return $results ? $results : array();
            
        } catch (Exception $e) {
            error_log('WPLoyalty Bonus Distributor Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    // Get customer's current points
    private function get_customer_points($user_id) {
        global $wpdb;
        
        try {
            $user = get_userdata($user_id);
            if (!$user) {
                return 0;
            }
            
            $user_table = $wpdb->prefix . 'wlr_users';
            if ($wpdb->get_var("SHOW TABLES LIKE '$user_table'") === $user_table) {
                $points = $wpdb->get_var($wpdb->prepare(
                    "SELECT points FROM $user_table WHERE user_email = %s",
                    $user->user_email
                ));
                
                if ($points !== null) {
                    return intval($points);
                }
            }
            
            if (class_exists('\Wlr\App\Helpers\Woocommerce')) {
                $helper = \Wlr\App\Helpers\Woocommerce::getInstance();
                if (method_exists($helper, 'getPointBalance')) {
                    return intval($helper->getPointBalance($user_id));
                }
            }
        } catch (Exception $e) {
            error_log('Error getting points: ' . $e->getMessage());
        }
        return 0;
    }
    
    // Distribute bonus points (date-based)
    public function distribute_bonus_points() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }
            
            if (!isset($_POST['distribute_bonus_points_nonce']) || 
                !wp_verify_nonce($_POST['distribute_bonus_points_nonce'], 'distribute_bonus_points_action')) {
                wp_die('Security check failed');
            }
            
            $eligible_customers = $this->get_eligible_customers();
            $success_count = 0;
            $failed_count = 0;
            
            foreach ($eligible_customers as $customer) {
                $user_id = $customer['user_id'];
                
                $result = $this->award_points($user_id, 1000, 'Year-End Bonus - Last 1000 Customers');
                if ($result['success']) {
                    $success_count++;
                } else {
                    $failed_count++;
                    error_log("Failed to award points to user $user_id: " . $result['error']);
                }
            }
            
            $redirect_url = add_query_arg(
                array(
                    'page' => 'bonus-points-distributor',
                    'success' => $success_count,
                    'failed' => $failed_count
                ),
                admin_url('admin.php')
            );
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            error_log('WPLoyalty Bonus Distributor Error: ' . $e->getMessage());
            wp_die('An error occurred during distribution: ' . esc_html($e->getMessage()));
        }
    }
    
    // Award points - SMART COLUMN DETECTION
    private function award_points($user_id, $points, $note) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'error' => '',
            'method' => ''
        );
        
        try {
            $user = get_userdata($user_id);
            
            if (!$user) {
                $result['error'] = 'User not found';
                return $result;
            }
            
            $transaction_table = $wpdb->prefix . 'wlr_earn_campaign_transaction';
            $user_table = $wpdb->prefix . 'wlr_users';
            $points_ledger = $wpdb->prefix . 'wlr_points_ledger';
            
            $transaction_exists = $wpdb->get_var("SHOW TABLES LIKE '$transaction_table'") === $transaction_table;
            $user_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$user_table'") === $user_table;
            
            if (!$transaction_exists) {
                $result['error'] = 'Transaction table does not exist';
                return $result;
            }
            
            if (!$user_table_exists) {
                $result['error'] = 'User table does not exist';
                return $result;
            }
            
            $user_columns = $this->get_table_columns($user_table);
            
            $campaign_id = $this->get_active_campaign_id();
            if ($campaign_id == 0) {
                $result['error'] = 'No active campaign found';
                return $result;
            }
            
            $current_timestamp = time();
            
            // Step 1: Insert transaction
            $insert_data = array(
                'user_email' => $user->user_email,
                'action_type' => 'admin_add_points',
                'transaction_type' => 'credit',
                'campaign_type' => 'point',
                'referral_type' => '',
                'points' => $points,
                'display_name' => $note,
                'reward_id' => 0,
                'campaign_id' => $campaign_id,
                'order_id' => 0,
                'order_currency' => get_woocommerce_currency(),
                'order_total' => 0.0000,
                'product_id' => 0,
                'admin_user_id' => get_current_user_id(),
                'log_data' => json_encode(array('note' => $note)),
                'customer_command' => '',
                'action_sub_type' => '',
                'action_sub_value' => '',
                'created_at' => $current_timestamp,
                'modified_at' => $current_timestamp
            );
            
            $format = array(
                '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d',
                '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d'
            );
            
            $insert_result = $wpdb->insert($transaction_table, $insert_data, $format);
            
            if (!$insert_result) {
                $result['error'] = 'Transaction insert failed: ' . $wpdb->last_error;
                return $result;
            }
            
            // Step 2: Update wlr_users balance
            $user_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $user_table WHERE user_email = %s",
                $user->user_email
            ));
            
            if ($user_record) {
                $update_data = array();
                $update_format = array();
                
                if (in_array('points', $user_columns)) {
                    $update_data['points'] = intval($user_record->points) + $points;
                    $update_format[] = '%d';
                }
                
                if (in_array('earn_total_point', $user_columns)) {
                    $current_earn = isset($user_record->earn_total_point) ? intval($user_record->earn_total_point) : 0;
                    $update_data['earn_total_point'] = $current_earn + $points;
                    $update_format[] = '%d';
                }
                
                if (in_array('modified_at', $user_columns)) {
                    $update_data['modified_at'] = $current_timestamp;
                    $update_format[] = '%d';
                }
                
                if (in_array('updated_at', $user_columns)) {
                    $update_data['updated_at'] = $current_timestamp;
                    $update_format[] = '%d';
                }
                
                if (!empty($update_data)) {
                    $update_result = $wpdb->update(
                        $user_table,
                        $update_data,
                        array('user_email' => $user->user_email),
                        $update_format,
                        array('%s')
                    );
                    
                    if ($update_result !== false) {
                        $result['success'] = true;
                        $result['method'] = 'Updated wlr_users';
                    } else {
                        $result['error'] = 'User update failed: ' . $wpdb->last_error;
                        return $result;
                    }
                }
            } else {
                $insert_user_data = array(
                    'user_email' => $user->user_email,
                    'points' => $points
                );
                $insert_user_format = array('%s', '%d');
                
                if (in_array('earn_total_point', $user_columns)) {
                    $insert_user_data['earn_total_point'] = $points;
                    $insert_user_format[] = '%d';
                }
                
                if (in_array('used_total_point', $user_columns)) {
                    $insert_user_data['used_total_point'] = 0;
                    $insert_user_format[] = '%d';
                }
                
                if (in_array('created_at', $user_columns)) {
                    $insert_user_data['created_at'] = $current_timestamp;
                    $insert_user_format[] = '%d';
                }
                
                if (in_array('modified_at', $user_columns)) {
                    $insert_user_data['modified_at'] = $current_timestamp;
                    $insert_user_format[] = '%d';
                }
                
                $insert_result = $wpdb->insert($user_table, $insert_user_data, $insert_user_format);
                
                if ($insert_result) {
                    $result['success'] = true;
                    $result['method'] = 'Created wlr_users record';
                } else {
                    $result['error'] = 'User insert failed: ' . $wpdb->last_error;
                    return $result;
                }
            }
            
            // Step 3: Update points ledger if exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$points_ledger'") === $points_ledger) {
                $ledger_columns = $this->get_table_columns($points_ledger);
                $ledger_record = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $points_ledger WHERE user_email = %s",
                    $user->user_email
                ));
                
                if ($ledger_record) {
                    $ledger_update = array('points' => intval($ledger_record->points) + $points);
                    $ledger_format = array('%d');
                    
                    if (in_array('modified_at', $ledger_columns)) {
                        $ledger_update['modified_at'] = $current_timestamp;
                        $ledger_format[] = '%d';
                    }
                    
                    $wpdb->update($points_ledger, $ledger_update, array('user_email' => $user->user_email), $ledger_format, array('%s'));
                } else {
                    $ledger_insert = array('user_email' => $user->user_email, 'points' => $points);
                    $ledger_insert_format = array('%s', '%d');
                    
                    if (in_array('created_at', $ledger_columns)) {
                        $ledger_insert['created_at'] = $current_timestamp;
                        $ledger_insert_format[] = '%d';
                    }
                    
                    if (in_array('modified_at', $ledger_columns)) {
                        $ledger_insert['modified_at'] = $current_timestamp;
                        $ledger_insert_format[] = '%d';
                    }
                    
                    $wpdb->insert($points_ledger, $ledger_insert, $ledger_insert_format);
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log('Award points error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    // Show admin notices
    public function show_admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'bonus-points-distributor') {
            return;
        }
        
        // Test success
        if (isset($_GET['test_success'])) {
            $email = urldecode($_GET['email']);
            $points_awarded = intval($_GET['points_awarded']);
            $points_before = intval($_GET['points_before']);
            $points_after = intval($_GET['points_after']);
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Points Awarded Successfully!</strong></p>
                <p>Customer: <strong><?php echo esc_html($email); ?></strong></p>
                <p>Points: <strong><?php echo $points_before; ?></strong> → <strong><?php echo $points_after; ?></strong> (+<?php echo $points_awarded; ?>)</p>
            </div>
            <?php
        }
        
        // Test error
        if (isset($_GET['test_error'])) {
            $error_type = $_GET['test_error'];
            $email = isset($_GET['email']) ? urldecode($_GET['email']) : '';
            $error_msg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : '';
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>❌ Error:</strong> 
                <?php if ($error_type === 'not_found'): ?>
                    Customer not found: <?php echo esc_html($email); ?>
                <?php else: ?>
                    <?php echo esc_html($error_msg); ?>
                <?php endif; ?>
                </p>
            </div>
            <?php
        }
        
        // CSV success
        if (isset($_GET['csv_success'])) {
            $success = intval($_GET['csv_success']);
            $failed = intval($_GET['csv_failed']);
            $skipped = intval($_GET['csv_skipped']);
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>🎉 CSV Processing Complete!</strong></p>
                <p>✅ Success: <strong><?php echo $success; ?></strong> customers</p>
                <?php if ($failed > 0): ?>
                    <p>❌ Failed: <strong><?php echo $failed; ?></strong> customers</p>
                <?php endif; ?>
                <?php if ($skipped > 0): ?>
                    <p>⏭️ Skipped: <strong><?php echo $skipped; ?></strong> rows (empty or invalid)</p>
                <?php endif; ?>
            </div>
            <?php
            
            // Show errors if any
            $errors = get_transient('wployalty_csv_errors');
            if ($errors && is_array($errors)) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong>⚠️ Error Details:</strong></p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <?php foreach (array_slice($errors, 0, 10) as $error): ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($errors) > 10): ?>
                            <li><em>... and <?php echo count($errors) - 10; ?> more errors</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php
                delete_transient('wployalty_csv_errors');
            }
        }
        
        // CSV error
        if (isset($_GET['csv_error'])) {
            $error = $_GET['csv_error'];
            $error_msg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : '';
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>❌ CSV Upload Error:</strong> 
                <?php if ($error === 'upload_failed'): ?>
                    File upload failed. Please try again.
                <?php elseif ($error === 'read_failed'): ?>
                    Could not read CSV file.
                <?php elseif ($error === 'invalid_format'): ?>
                    <?php echo esc_html($error_msg); ?>
                <?php else: ?>
                    Unknown error occurred.
                <?php endif; ?>
                </p>
            </div>
            <?php
        }
        
        // Date-based bulk success
        if (isset($_GET['success'])) {
            $success = intval($_GET['success']);
            $failed = isset($_GET['failed']) ? intval($_GET['failed']) : 0;
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>🎉 Bulk Distribution Complete!</strong></p>
                <p>Successfully awarded 1000 points to <strong><?php echo $success; ?></strong> customers.</p>
                <?php if ($failed > 0): ?>
                    <p style="color: #d63638;">Failed: <?php echo $failed; ?> customers.</p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}

// Initialize the plugin
function wployalty_bonus_distributor_init() {
    new WPLoyalty_Bonus_Distributor();
}
add_action('plugins_loaded', 'wployalty_bonus_distributor_init');