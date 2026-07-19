<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_medicompare_detect_supplier', [$this, 'ajax_detect_supplier']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);


         //subscription actions
         add_action('admin_post_mc_save_subscription_meta', [$this, 'save_subscription_meta']);
         add_action('wp_ajax_mc_subscription_action', [$this, 'ajax_subscription_action']);
         add_action('wp_ajax_mc_subscription_audit_log', [$this, 'ajax_subscription_audit_log']);
         add_action('wp_ajax_mc_subscription_stripe_sync', [$this, 'ajax_subscription_stripe_sync']);
         add_action('wp_ajax_mc_billing_history_sync', [$this, 'ajax_billing_history_sync']);
         add_action('admin_enqueue_scripts', [$this, 'enqueue_subscription_js']);
         add_action('admin_enqueue_scripts', [$this, 'enqueue_subscription_css']);


        add_action('admin_enqueue_scripts', function($hook){
         error_log("HOOK: " . $hook);
     });



        // Load verification logic on all admin requests
        require_once plugin_dir_path(__FILE__) . 'admin-pages/pharmacy-verification.php';
        require_once plugin_dir_path(__FILE__) . 'admin-pages/admin-dashboard-widget.php';

    }

    public function enqueue_admin_assets($hook) {

        // Load CSS on all MediCompare admin pages
        if (strpos($hook, 'medicompare') !== false) {
            wp_enqueue_style(
                'medicompare-admin-css',
                plugin_dir_url(__FILE__) . '../assets/css/admin.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/admin.css')
            );
        }

        // Load JS ONLY on Transferred Orders page
        if ($hook === 'medicompare_page_medicompare-transferred-orders') {

            wp_enqueue_script(
                'mc-admin-order-transfer',
                plugin_dir_url(__FILE__) . '../assets/js/admin-order-transfer.js',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin-order-transfer.js'),
                true
            );
        }
    }


    public function register_menu() {

    add_menu_page(
        'MediCompare',
        'MediCompare',
        'manage_options',
        'medicompare',
        [$this, 'dashboard_page'],
        'dashicons-clipboard',
        2
    );

    add_submenu_page(
        'medicompare',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'medicompare',
        [$this, 'dashboard_page']
    );

    /* ---------------------------------------------------------
       SUPPLIERS
    --------------------------------------------------------- */

    add_submenu_page(
        'medicompare',
        'Suppliers',
        'Suppliers ALL',
        'manage_options',
        'edit.php?post_type=mc_supplier'
    );

    add_submenu_page(
        'medicompare',
        'Add New Supplier',
        'Suppliers Add New',
        'manage_options',
        'post-new.php?post_type=mc_supplier'
    );

    add_submenu_page(
        'medicompare',
        'Upload Supplier CSV',
        'Upload Supplier CSV',
        'manage_options',
        'upload-supplier-csv',
        [$this, 'upload_supplier_csv_page']
    );

    add_submenu_page(
        'medicompare',
        'Supplier Products ALL',
        'Supplier Products ALL',
        'manage_options',
        'supplier-products-all',
        [$this, 'supplier_products_all_page']
    );

    add_submenu_page(
        'medicompare',
        'Upload Supplier Product CSV',
        'Upload Supplier Product CSV',
        'manage_options',
        'medicompare-upload-supplier-product-csv',
        [$this, 'upload_supplier_product_csv_page']
    );

    /* ---------------------------------------------------------
       PRODUCTS
    --------------------------------------------------------- */

    add_submenu_page(
        'medicompare',
        'Products',
        'Products ALL',
        'manage_options',
        'edit.php?post_type=mc_product'
    );

    add_submenu_page(
        'medicompare',
        'Add New Product',
        'Products Add New',
        'manage_options',
        'post-new.php?post_type=mc_product'
    );

    add_submenu_page(
        'medicompare',
        'Upload Product CSV',
        'Upload Product CSV',
        'manage_options',
        'medicompare-upload-product-csv',
        [$this, 'upload_product_csv_page']
    );

    /* ---------------------------------------------------------
       PHARMACIES
    --------------------------------------------------------- */

    add_submenu_page(
        'medicompare',
        'Pharmacies',
        'Pharmacies ALL',
        'manage_options',
        'edit.php?post_type=mc_pharmacy'
    );

    add_submenu_page(
        'medicompare',
        'Add New Pharmacy',
        'Pharmacies Add New',
        'manage_options',
        'post-new.php?post_type=mc_pharmacy'
    );

    add_submenu_page(
        'medicompare',
        'Upload Pharmacy CSV',
        'Upload Pharmacy CSV',
        'manage_options',
        'medicompare-upload-pharmacy-csv',
        [$this, 'upload_pharmacy_csv_page']
    );

    add_submenu_page(
        'medicompare',
        'Pending Verification',
        'Pending Verification',
        'manage_options',
        'medicompare-pharmacy-verification',
        [$this, 'pharmacy_verification_page']
    );

    add_submenu_page(
        'medicompare',
        'Transferred Orders',
        'Transferred Orders',
        'manage_options',
        'medicompare-transferred-orders',
        [$this, 'transferred_orders_page']
    );

    /* ---------------------------------------------------------
       ⭐ NEW — SUBSCRIPTIONS CONTROL CENTRE
    --------------------------------------------------------- */
    add_submenu_page(
        'medicompare',
        'Subscriptions',
        'Subscriptions',
        'manage_options',
        'medicompare-subscriptions',
        [$this, 'subscription_control_page']
    );

    /* ---------------------------------------------------------
       REPORTS
    --------------------------------------------------------- */

    add_submenu_page(
        'medicompare',
        'Reports',
        'Reports',
        'manage_options',
        'medicompare-reports',
        [$this, 'reports_page']
    );

    /* ---------------------------------------------------------
       IMPORT LOGS
    --------------------------------------------------------- */

    add_submenu_page(
        'medicompare',
        'Import Logs',
        'Import Logs',
        'manage_options',
        'medicompare-import-logs',
        [$this, 'import_logs_page']
    );

    /* ---------------------------------------------------------
   ⭐ NEW — SIGNUP LEADS
    --------------------------------------------------------- */
    add_submenu_page(
        'medicompare',
        'Signup Leads',
        'Signup Leads',
        'manage_options',
        'medicompare-signup-leads',
        [$this, 'signup_leads_page']
    );

}


    public function dashboard_page() {
    echo '<div class="wrap">';
    echo '<h1>MediCompare Dashboard</h1>';
    echo '<p>Welcome to the MediCompare admin panel.</p>';

    // Show pending verification widget
    MediCompare_Admin_Dashboard_Widget::render_inline_widget();

    echo '</div>';
    }


    public function reports_page() {
        echo '<div class="wrap"><h1>Reports</h1><p>Reports module coming soon.</p></div>';
    }

    public function import_logs_page() {
        echo '<div class="wrap"><h1>Import Logs</h1><p>Import logs will appear here.</p></div>';
    }

    /* ---------------------------------------------------------
   ⭐ NEW — SIGNUP LEADS PAGE
    --------------------------------------------------------- */
    public function signup_leads_page() {
        include __DIR__ . '/admin-pages/signup-leads.php';
    }


    /* ---------------------------------------------------------
       SUPPLIER PRODUCT CSV PARSER
    --------------------------------------------------------- */
    public function process_csv_upload() {

        if (!isset($_FILES['csv_file'])) {
            return null;
        }

        $file = $_FILES['csv_file'];

        $allowed = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed)) {
            return ['error' => 'Invalid file type. Please upload a CSV file.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed.'];
        }

        $csv_path = $file['tmp_name'];
        $rows = array_map('str_getcsv', file($csv_path));

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        $header = array_map('trim', array_shift($rows));
        $header = array_map('strtolower', $header);

        $required = ['product_code', 'product_name', 'price', 'stock'];

        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                return ['error' => "Missing required column: $col"];
            }
        }

        $mapped = [];

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;

            $row = array_map('trim', $row);
            $data = array_combine($header, $row);

            $mapped[] = [
                'product_code' => $data['product_code'],
                'product_name' => $data['product_name'],
                'price'        => (float) $data['price'],
                'stock'        => (int) $data['stock'],
            ];
        }

        return [
            'success' => true,
            'data' => $mapped
        ];
    }

    /* ---------------------------------------------------------
       PRODUCT CSV PARSER
    --------------------------------------------------------- */
    public function process_product_csv_upload() {

        if (!isset($_FILES['product_csv_file'])) {
            return null;
        }

        $file = $_FILES['product_csv_file'];

        $allowed = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed)) {
            return ['error' => 'Invalid file type. Please upload a CSV file.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed.'];
        }

        $csv_path = $file['tmp_name'];
        $rows = array_map('str_getcsv', file($csv_path));

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        $header = array_map('trim', array_shift($rows));
        $header = array_map('strtolower', $header);

        $required = ['product_code', 'product_name', 'category', 'strength', 'pack_size', 'description'];

        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                return ['error' => "Missing required column: $col"];
            }
        }

        $mapped = [];

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;

            $row = array_map('trim', $row);
            $data = array_combine($header, $row);

            $mapped[] = [
                'product_code' => $data['product_code'],
                'product_name' => $data['product_name'],
                'category'     => $data['category'],
                'strength'     => $data['strength'],
                'pack_size'    => $data['pack_size'],
                'description'  => $data['description'],
            ];
        }

        return [
            'success' => true,
            'data'    => $mapped
        ];
    }

    /* ---------------------------------------------------------
       PHARMACY CSV PARSER
    --------------------------------------------------------- */
    public function process_pharmacy_csv_upload() {

        if (!isset($_FILES['pharmacy_csv_file'])) {
            return null;
        }

        $file = $_FILES['pharmacy_csv_file'];

        $allowed = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed)) {
            return ['error' => 'Invalid file type. Please upload a CSV file.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed.'];
        }

        $csv_path = $file['tmp_name'];
        $rows = array_map('str_getcsv', file($csv_path));

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        $header = array_map('trim', array_shift($rows));
        $header = array_map('strtolower', $header);

        $required = [
            'pharmacy_code','pharmacy_name','email','phone',
            'address_line_1','address_line_2','city','postcode',
            'gphc_number','contact_name','status'
        ];

        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                return ['error' => "Missing required column: $col"];
            }
        }

        $mapped = [];

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;

            $row = array_map('trim', $row);
            $data = array_combine($header, $row);

            $mapped[] = [
                'pharmacy_code'  => $data['pharmacy_code'],
                'pharmacy_name'  => $data['pharmacy_name'],
                'email'          => $data['email'],
                'phone'          => $data['phone'],
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $data['address_line_2'],
                'city'           => $data['city'],
                'postcode'       => $data['postcode'],
                'gphc_number'    => $data['gphc_number'],
                'contact_name'   => $data['contact_name'],
                'status'         => strtolower($data['status']),
            ];
        }

        return [
            'success' => true,
            'data'    => $mapped
        ];
    }

    /* ---------------------------------------------------------
       INSERT OR UPDATE SUPPLIER PRODUCT ROW
    --------------------------------------------------------- */
    public function insert_supplier_product_row($supplier_id, $row) {
    global $wpdb;

    $table = $wpdb->prefix . 'medi_supplier_products';

    $product_code = trim($row['product_code']);

    if ($product_code === '') {
        return 'skipped';
    }

    // Find existing product by mc_product_code (the canonical source of truth)
    $product_ids = get_posts([
        'post_type'      => 'mc_product',
        'post_status'    => 'any',
        'meta_key'       => 'mc_product_code',
        'meta_value'     => $product_code,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if (empty($product_ids)) {
        // Product does not exist – do NOT create it here
        return 'skipped';
    }

    $product_id = $product_ids[0];

    // Check if supplier/product row already exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table} WHERE supplier_id = %d AND product_id = %d",
        $supplier_id,
        $product_id
    ));

    if ($existing) {
        $wpdb->update(
            $table,
            [
                'price'        => (float) $row['price'],
                'stock'        => (int) $row['stock'],
                'last_updated' => current_time('mysql'),
            ],
            ['id' => $existing->id],
            ['%f', '%d', '%s'],
            ['%d']
        );

        return 'updated';
    }

    $wpdb->insert(
        $table,
        [
            'supplier_id'  => (int) $supplier_id,
            'product_id'   => (int) $product_id,
            'price'        => (float) $row['price'],
            'stock'        => (int) $row['stock'],
            'last_updated' => current_time('mysql'),
        ],
        ['%d', '%d', '%f', '%d', '%s']
    );

    return 'inserted';
}

    /* ---------------------------------------------------------
       INSERT / UPDATE PRODUCT
    --------------------------------------------------------- */
    public function insert_or_update_product_from_row($row) {

    $product_code = trim($row['product_code']);
    $product_name = trim($row['product_name']);
    $category     = trim($row['category']);
    $strength     = trim($row['strength']);
    $pack_size    = trim($row['pack_size']);
    $description  = trim($row['description']);

    if ($product_code === '' || $product_name === '') {
        return 'skipped';
    }

    // IMPORTANT: use correct meta key (no underscore)
    $existing = get_posts([
        'post_type'      => 'mc_product',
        'post_status'    => 'any',
        'meta_key'       => 'mc_product_code',
        'meta_value'     => $product_code,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    // UPDATE
    if (!empty($existing)) {

        $product_id = $existing[0];

        wp_update_post([
            'ID'          => $product_id,
            'post_title'  => $product_name,
            'post_name'   => sanitize_title($product_code),
            'post_status' => 'publish',   // ensure published
            'post_author' => 1,
        ]);

        update_post_meta($product_id, 'mc_product_code', $product_code);
        update_post_meta($product_id, 'mc_category', $category);
        update_post_meta($product_id, 'mc_strength', $strength);
        update_post_meta($product_id, 'mc_pack_size', $pack_size);
        update_post_meta($product_id, 'mc_description', $description);

        return 'updated';
    }

    // INSERT
    $product_id = wp_insert_post([
        'post_title'  => $product_name,
        'post_name'   => sanitize_title($product_code),
        'post_type'   => 'mc_product',
        'post_status' => 'publish',
        'post_author' => 1,
    ]);

    if (is_wp_error($product_id) || !$product_id) {
        return 'skipped';
    }

    update_post_meta($product_id, 'mc_product_code', $product_code);
    update_post_meta($product_id, 'mc_category', $category);
    update_post_meta($product_id, 'mc_strength', $strength);
    update_post_meta($product_id, 'mc_pack_size', $pack_size);
    update_post_meta($product_id, 'mc_description', $description);

    return 'inserted';
}


    /* ---------------------------------------------------------
       INSERT / UPDATE PHARMACY
    --------------------------------------------------------- */
    public function insert_or_update_pharmacy_from_row($row) {

        $code = trim($row['pharmacy_code']);
        $name = trim($row['pharmacy_name']);

        if ($code === '' || $name === '') {
            return 'skipped';
        }

        $existing = get_posts([
            'post_type'      => 'mc_pharmacy',
            'post_status'    => 'any',
            'meta_key'       => '_mc_pharmacy_code',
            'meta_value'     => $code,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            $pharmacy_id = $existing[0];

            wp_update_post([
                'ID'         => $pharmacy_id,
                'post_title' => $name,
                'post_name'  => sanitize_title($code),
            ]);

            $action = 'updated';

        } else {

            $pharmacy_id = wp_insert_post([
                'post_title'  => $name,
                'post_name'   => sanitize_title($code),
                'post_type'   => 'mc_pharmacy',
                'post_status' => 'publish'
            ]);

            if (is_wp_error($pharmacy_id) || !$pharmacy_id) {
                return 'skipped';
            }

            $action = 'inserted';
        }

        update_post_meta($pharmacy_id, '_mc_pharmacy_code', $code);
        update_post_meta($pharmacy_id, '_mc_email', $row['email']);
        update_post_meta($pharmacy_id, '_mc_phone', $row['phone']);
        update_post_meta($pharmacy_id, '_mc_address_line_1', $row['address_line_1']);
        update_post_meta($pharmacy_id, '_mc_address_line_2', $row['address_line_2']);
        update_post_meta($pharmacy_id, '_mc_city', $row['city']);
        update_post_meta($pharmacy_id, '_mc_postcode', $row['postcode']);
        update_post_meta($pharmacy_id, '_mc_gphc_number', $row['gphc_number']);
        update_post_meta($pharmacy_id, '_mc_contact_name', $row['contact_name']);
        update_post_meta($pharmacy_id, '_mc_status', $row['status']);

        return $action;
    }

    /* ---------------------------------------------------------
       GET SUPPLIERS (ID → Name)
    --------------------------------------------------------- */
    public function get_suppliers() {

        $posts = get_posts([
            'post_type'      => 'mc_supplier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);

        $suppliers = [];

        foreach ($posts as $post) {
            $suppliers[$post->ID] = $post->post_title;
        }

        return $suppliers;
    }

    /* ---------------------------------------------------------
       AJAX: Detect Supplier From Filename
    --------------------------------------------------------- */
    public function ajax_detect_supplier() {

        if (empty($_POST['filename'])) {
            wp_send_json_error(['message' => 'No filename provided']);
        }

        $filename = sanitize_text_field($_POST['filename']);
        $suppliers = $this->get_suppliers();

        foreach ($suppliers as $id => $name) {

            $slug = sanitize_title($name);

            if (stripos($filename, $slug) !== false) {
                wp_send_json_success(['supplier_id' => $id]);
            }
        }

        wp_send_json_success(['supplier_id' => null]);
    }

    /* ---------------------------------------------------------
       AUTO-DETECT SUPPLIER FROM FILENAME
    --------------------------------------------------------- */
    public function detect_supplier_from_filename($filename, $suppliers) {

        $filename = strtolower($filename);
        $filename_normalized = str_replace([' ', '-', '_'], '', $filename);

        foreach ($suppliers as $supplier) {

            $name = strtolower($supplier->post_title);

            $full = str_replace([' ', '-', '_'], '', $name);

            $parts = explode(' ', $name);
            $first = $parts[0];
            $last = end($parts);
            $initial = substr($last, 0, 1);

            $patterns = [$full, $name, $first, $last, $initial];

            foreach ($patterns as $pattern) {
                $pattern = strtolower($pattern);
                $pattern_normalized = str_replace([' ', '-', '_'], '', $pattern);

                if (
                    strpos($filename, $pattern) !== false ||
                    strpos($filename_normalized, $pattern_normalized) !== false
                ) {
                    return $supplier->ID;
                }
            }
        }

        return null;
    }

    /* ---------------------------------------------------------
       SUPPLIER PRODUCT CSV UPLOAD PAGE
    --------------------------------------------------------- */
    public function upload_supplier_product_csv_page() {

        $result = null;
        $db_result = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode = null;

        $suppliers = $this->get_suppliers();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_supplier_product_csv'])) {

            $mode = 'preview';
            $result = $this->process_csv_upload();

            if ($result && isset($result['success'])) {

                $_SESSION['supplier_product_csv_preview'] = $result['data'];
                $_SESSION['supplier_product_csv_supplier_id'] = intval($_POST['supplier_id']);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_supplier_product_csv'])) {

            $mode = 'import';

            if (!empty($_SESSION['supplier_product_csv_preview'])) {

                $rows = $_SESSION['supplier_product_csv_preview'];
                $supplier_id = $_SESSION['supplier_product_csv_supplier_id'];

                foreach ($rows as $row) {
                    $status = $this->insert_supplier_product_row($supplier_id, $row);
                    $db_result[$status]++;
                }

                unset($_SESSION['supplier_product_csv_preview']);
                unset($_SESSION['supplier_product_csv_supplier_id']);

                $result = ['success' => true];

            } else {
                $result = ['error' => 'No preview data found. Please upload the CSV again.'];
            }
        }

        include __DIR__ . '/admin-pages/upload-supplier-products.php';
    }

    /* ---------------------------------------------------------
       PRODUCT CSV UPLOAD PAGE
    --------------------------------------------------------- */
    public function upload_product_csv_page() {

        $result = null;
        $import_summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_product_csv'])) {

            $mode = 'preview';
            $result = $this->process_product_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['product_csv_preview'] = $result['data'];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_product_csv'])) {

            $mode = 'import';

            if (!empty($_SESSION['product_csv_preview'])) {

                $rows = $_SESSION['product_csv_preview'];

                foreach ($rows as $row) {
                    $status = $this->insert_or_update_product_from_row($row);
                    $import_summary[$status]++;
                }

                unset($_SESSION['product_csv_preview']);
                $result = ['success' => true];

            } else {
                $result = ['error' => 'No preview data found. Please upload the CSV again.'];
            }
        }

        include __DIR__ . '/admin-pages/upload-products.php';
    }

    /* ---------------------------------------------------------
       PHARMACY CSV UPLOAD PAGE
    --------------------------------------------------------- */
    public function upload_pharmacy_csv_page() {

        $result = null;
        $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_pharmacy_csv'])) {

            $mode = 'preview';
            $result = $this->process_pharmacy_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['pharmacy_csv_preview'] = $result['data'];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_pharmacy_csv'])) {

            $mode = 'import';

            if (!empty($_SESSION['pharmacy_csv_preview'])) {

                $rows = $_SESSION['pharmacy_csv_preview'];

                foreach ($rows as $row) {
                    $status = $this->insert_or_update_pharmacy_from_row($row);
                    $summary[$status]++;
                }

                unset($_SESSION['pharmacy_csv_preview']);
                $result = ['success' => true];

            } else {
                $result = ['error' => 'No preview data found. Please upload the CSV again.'];
            }
        }

        include __DIR__ . '/admin-pages/upload-pharmacies.php';
    }
    /* ---------------------------------------------------------
       SUPPLIER CSV PARSER (FIXED)
    --------------------------------------------------------- */
    public function process_supplier_csv_upload() {

        // Accept either name="supplier_csv_file" OR name="csv_file"
        $file = $_FILES['supplier_csv_file']
            ?? $_FILES['csv_file']
            ?? null;

        if (!$file) {
            return ['error' => 'No CSV file uploaded.'];
        }

        $allowed = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed)) {
            return ['error' => 'Invalid file type. Please upload a CSV file.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed.'];
        }

        $csv_path = $file['tmp_name'];
        $rows = array_map('str_getcsv', file($csv_path));

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        $header = array_map('trim', array_shift($rows));
        $header = array_map('strtolower', $header);

        $required = [
            'supplier_name','email','phone','address_1','address_2',
            'city','county','postcode','country','account_manager',
            'supplier_code','status'
        ];

        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                return ['error' => "Missing required column: $col"];
            }
        }

        $mapped = [];

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;

            $row = array_map('trim', $row);
            $data = array_combine($header, $row);

            $mapped[] = [
                'supplier_name'  => $data['supplier_name'],
                'email'          => $data['email'],
                'phone'          => $data['phone'],
                'address_1'      => $data['address_1'],
                'address_2'      => $data['address_2'],
                'city'           => $data['city'],
                'county'         => $data['county'],
                'postcode'       => $data['postcode'],
                'country'        => $data['country'],
                'account_manager'=> $data['account_manager'],
                'supplier_code'  => $data['supplier_code'],
                'status'         => $data['status'],
            ];
        }

        return [
            'success' => true,
            'data'    => $mapped
        ];
    }

    /* ---------------------------------------------------------
       INSERT / UPDATE SUPPLIER (NEW)
    --------------------------------------------------------- */
    public function insert_or_update_supplier_from_csv_row($row) {

        $name = trim($row['supplier_name']);
        if ($name === '') {
            return 'skipped';
        }

        $existing = get_page_by_title($name, OBJECT, 'mc_supplier');

        if ($existing) {
            $post_id = $existing->ID;
            $status  = 'updated';
        } else {
            $post_id = wp_insert_post([
                'post_title'  => $name,
                'post_type'   => 'mc_supplier',
                'post_status' => 'publish'
            ]);

            if (is_wp_error($post_id) || !$post_id) {
                return 'skipped';
            }

            $status = 'inserted';
        }

        $meta = [
            'mc_supplier_email'      => sanitize_email($row['email']),
            'mc_supplier_phone'      => sanitize_text_field($row['phone']),
            'mc_supplier_address_1'  => sanitize_text_field($row['address_1']),
            'mc_supplier_address_2'  => sanitize_text_field($row['address_2']),
            'mc_supplier_city'       => sanitize_text_field($row['city']),
            'mc_supplier_county'     => sanitize_text_field($row['county']),
            'mc_supplier_postcode'   => sanitize_text_field($row['postcode']),
            'mc_supplier_country'    => sanitize_text_field($row['country']),
            'mc_supplier_manager'    => sanitize_text_field($row['account_manager']),
            'mc_supplier_code'       => sanitize_text_field($row['supplier_code']),
            'mc_supplier_status'     => sanitize_text_field($row['status']),
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $status;
    }

    /* ---------------------------------------------------------
       SUPPLIER CSV UPLOAD PAGE (FIXED)
    --------------------------------------------------------- */
    public function upload_supplier_csv_page() {

        $result  = null;
        $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode    = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_supplier_csv'])) {

            $mode   = 'preview';
            $result = $this->process_supplier_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['supplier_csv_preview'] = $result['data'];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_supplier_csv'])) {

            $mode = 'import';

            if (!empty($_SESSION['supplier_csv_preview'])) {

                $rows = $_SESSION['supplier_csv_preview'];

                foreach ($rows as $row) {
                    $status = $this->insert_or_update_supplier_from_csv_row($row);
                    if (isset($summary[$status])) {
                        $summary[$status]++;
                    }
                }

                unset($_SESSION['supplier_csv_preview']);
                $result = ['success' => true];

            } else {
                $result = ['error' => 'No preview data found. Please upload the CSV again.'];
            }
        }

        // Provide variables expected by the view file
        $inserted = $summary['inserted'] ?? 0;
        $updated  = $summary['updated']  ?? 0;
        $skipped  = $summary['skipped']  ?? 0;

        include __DIR__ . '/admin-pages/upload-supplier-csv.php';
    }

    /* ---------------------------------------------------------
       SUPPLIER PRODUCTS ALL PAGE (FIXED)
    --------------------------------------------------------- */
    public function supplier_products_all_page() {

        $suppliers = $this->get_suppliers();
        $selected_supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

        // FIX: Provide variable expected by the view file
        $selected_supplier = $selected_supplier_id;

        $products = [];
        if ($selected_supplier_id) {
            $products = $this->get_supplier_products($selected_supplier_id);
        }

        include __DIR__ . '/admin-pages/supplier-products-all.php';
    }

    /* ---------------------------------------------------------
        GET SUPPLIER PRODUCTS (UPDATED WITH STRENGTH + PACK SIZE)
    --------------------------------------------------------- */
    public function get_supplier_products($supplier_id) {
        global $wpdb;

        $supplier_id = intval($supplier_id);
        if (!$supplier_id) return [];

        $table = $wpdb->prefix . 'medi_supplier_products';

        $sql = $wpdb->prepare("
            SELECT 
                sp.*,
                p.post_title AS product_title,
                pm_strength.meta_value AS strength,
                pm_pack.meta_value AS pack_size
            FROM {$table} sp
            LEFT JOIN {$wpdb->posts} p 
                   ON sp.product_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm_strength 
                   ON pm_strength.post_id = sp.product_id 
                  AND pm_strength.meta_key = 'mc_strength'
            LEFT JOIN {$wpdb->postmeta} pm_pack 
                   ON pm_pack.post_id = sp.product_id 
                  AND pm_pack.meta_key = 'mc_pack_size'
            WHERE sp.supplier_id = %d
            ORDER BY p.post_title ASC
        ", $supplier_id);

        return $wpdb->get_results($sql, ARRAY_A);
    }


    public function pharmacy_verification_page() {
     mc_render_pharmacy_verification_page();
    }

  /* ---------------------------------------------------------
   TRANSFERRED ORDERS ADMIN PAGE
   - Filter by supplier, pharmacy, date range, order number
   - Shows per-supplier totals + commission + email status
   - FIXED: No duplicates, correct filtering, matches pharmacy
--------------------------------------------------------- */
public function transferred_orders_page() {
    global $wpdb;

    $orders_table           = $wpdb->prefix . 'medi_orders';
    $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';
    $suborders_table        = $wpdb->prefix . 'medi_order_suborders';
    $order_items_table      = $wpdb->prefix . 'medi_order_items';
    $posts_table            = $wpdb->posts;

    /* ------------------------------
       LOAD SUPPLIERS
    ------------------------------ */
    $suppliers = $this->get_suppliers();

    /* ------------------------------
       LOAD PHARMACIES
    ------------------------------ */
    $pharmacies_posts = get_posts([
        'post_type'      => 'mc_pharmacy',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    $pharmacies = [];
    foreach ($pharmacies_posts as $pid) {
        $pharmacies[$pid] = get_the_title($pid);
    }

    /* ------------------------------
       FILTER INPUTS
    ------------------------------ */
    $selected_supplier = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
    $selected_pharmacy = isset($_GET['pharmacy_id']) ? (int) $_GET['pharmacy_id'] : 0;
    $date_from         = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to           = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $order_number      = isset($_GET['order_number']) ? (int) $_GET['order_number'] : 0;

    /* ------------------------------
       BUILD WHERE CLAUSE
    ------------------------------ */
    $where   = ["1=1"];
    $params  = [];

    // Pharmacy filter
    if ($selected_pharmacy) {
        $where[]  = "o.pharmacy_id = %d";
        $params[] = $selected_pharmacy;
    }

    // Supplier filter
    if ($selected_supplier) {
        $where[]  = "oss.supplier_id = %d";
        $params[] = $selected_supplier;
    }

    // Date range filter
    if ($date_from) {
        $where[]  = "DATE(o.created_at) >= %s";
        $params[] = $date_from;
    }

    if ($date_to) {
        $where[]  = "DATE(o.created_at) <= %s";
        $params[] = $date_to;
    }

    // Order number filter
    if ($order_number) {
        $where[]  = "o.order_number = %d";
        $params[] = $order_number;
    }

    // Only transferred/sent
    $where[] = "o.status IN ('TRANSFERRED','SENT')";

    $where_sql = implode(' AND ', $where);

    /* ------------------------------
       FIXED SQL — NO DUPLICATES
       One row per supplier per order
    ------------------------------ */
    $sql = "
        SELECT DISTINCT
            o.id AS order_id,
            o.order_number,
            o.pharmacy_id,
            o.total_amount,
            o.status,
            o.created_at,
            oss.supplier_id,
            oss.suborder_number,
            oss.supplier_total_amount,
            oss.platform_fee_percent,
            oss.platform_fee_amount
        FROM {$orders_table} o
        INNER JOIN {$supplier_summary_table} oss
                ON oss.order_id = o.id
        WHERE {$where_sql}
        ORDER BY o.created_at DESC, o.order_number DESC
        LIMIT 200
    ";

    $filters_applied = $selected_supplier || $selected_pharmacy || $date_from || $date_to || $order_number;

    $rows = [];
    if ($filters_applied) {
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows     = $wpdb->get_results($prepared, ARRAY_A);
    }
    ?>
    <div class="wrap">
        <h1>Transferred Orders</h1>

        <!-- FILTER FORM -->
        <form method="get" class="mc-admin-filters">
            <input type="hidden" name="page" value="medicompare-transferred-orders" />

            <label>
                <span>Supplier:</span>
                <select name="supplier_id">
                    <option value="0">All suppliers</option>
                    <?php foreach ($suppliers as $sid => $sname): ?>
                        <option value="<?php echo esc_attr($sid); ?>" <?php selected($selected_supplier, $sid); ?>>
                            <?php echo esc_html($sname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Pharmacy:</span>
                <select name="pharmacy_id">
                    <option value="0">All pharmacies</option>
                    <?php foreach ($pharmacies as $pid => $pname): ?>
                        <option value="<?php echo esc_attr($pid); ?>" <?php selected($selected_pharmacy, $pid); ?>>
                            <?php echo esc_html($pname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Date from:</span>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
            </label>

            <label>
                <span>Date to:</span>
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
            </label>

            <label>
                <span>Order Number:</span>
                <input type="number" name="order_number" value="<?php echo esc_attr($order_number); ?>" />
            </label>

            <button class="button button-primary" type="submit">Filter</button>
        </form>

        <?php if ($filters_applied): ?>
    <div class="mc-admin-results-count" style="margin: 15px 0; font-weight: 600;">
        <?php
            // Count unique orders
            $order_count = 0;
            if (!empty($rows)) {
                $unique_orders = [];
                foreach ($rows as $r) {
                    $unique_orders[$r['order_id']] = true;
                }
                $order_count = count($unique_orders);
            }
        ?>

        Showing <?php echo $order_count; ?> transferred order<?php echo ($order_count === 1 ? '' : 's'); ?>
    </div>
<?php endif; ?>


        <?php if (!$filters_applied): ?>
            <p class="mc-muted">Use the filters above to view transferred orders.</p>

        <?php elseif ($filters_applied && !$rows): ?>
            <p>No transferred orders found for the selected filters.</p>

        <?php else: ?>

            <?php
            // Group rows by order
            $grouped = [];
            foreach ($rows as $r) {
                $grouped[$r['order_id']][] = $r;
            }
            ?>

            <div class="mc-admin-transferred-orders-wrapper">
                <div class="mc-admin-transferred-orders">

                    <?php foreach ($grouped as $order_id => $subrows): ?>
                        <?php
                        $first         = $subrows[0];
                        $pharmacy_id   = (int) $first['pharmacy_id'];
                        $pharmacy_name = get_the_title($pharmacy_id);
                        $order_status  = strtolower($first['status']);
                        $order_date    = $first['created_at'];
                        $order_number  = $first['order_number'];
                        $order_total   = $first['total_amount'];
                        ?>

                        <div class="mc-transferred-order-card mc-order-collapsed">

                            <div class="mc-order-collapse-header" data-order-toggle>
                                <span>
                                    Order #<?php echo esc_html($order_number); ?>
                                    <span class="mc-order-status-badge mc-status-<?php echo esc_attr($order_status); ?>">
                                        <?php echo esc_html(ucfirst($order_status)); ?>
                                    </span>
                                </span>
                                <span>
                                    <span class="mc-admin-order-date">
                                        <?php echo esc_html(date('d M Y H:i', strtotime($order_date))); ?>
                                    </span>
                                    <span class="mc-admin-order-total">
                                        Total: £<?php echo number_format((float) $order_total, 2); ?>
                                    </span>
                                    <span class="mc-order-collapse-arrow">▼</span>
                                </span>
                            </div>

                            <div class="mc-order-collapse-content">
                                <p>
                                    <strong>Pharmacy:</strong>
                                    <?php echo esc_html($pharmacy_name ?: ('ID ' . $pharmacy_id)); ?>
                                </p>

                                <?php foreach ($subrows as $row): ?>
                                    <?php
                                    $supplier_id    = (int) $row['supplier_id'];
                                    $supplier_name  = get_the_title($supplier_id);
                                    $supplier_total = (float) $row['supplier_total_amount'];
                                    $fee_percent    = (float) $row['platform_fee_percent'];
                                    $fee_amount     = (float) $row['platform_fee_amount'];

                                    if ($fee_amount == 0 && $fee_percent > 0) {
                                        $fee_amount = round($supplier_total * $fee_percent / 100, 2);
                                    }

                                    $suborder = $wpdb->get_row($wpdb->prepare(
                                        "SELECT supplier_order_status, email_sent, email_sent_at
                                         FROM {$suborders_table}
                                         WHERE order_id = %d
                                           AND supplier_id = %d
                                           AND suborder_number = %s
                                         LIMIT 1",
                                        $order_id,
                                        $supplier_id,
                                        $row['suborder_number']
                                    ), ARRAY_A);

                                    $sub_status    = $suborder ? strtolower($suborder['supplier_order_status']) : 'pending';
                                    $email_sent    = $suborder ? (int) $suborder['email_sent'] : 0;
                                    $email_sent_at = ($suborder && $suborder['email_sent_at'])
                                        ? date('d M Y H:i', strtotime($suborder['email_sent_at']))
                                        : null;

                                    $items = $wpdb->get_results($wpdb->prepare(
                                        "SELECT oi.product_id, oi.quantity, oi.unit_price, oi.line_total
                                         FROM {$order_items_table} oi
                                         WHERE oi.order_id = %d
                                           AND oi.supplier_id = %d
                                         ORDER BY oi.product_id ASC",
                                        $order_id,
                                        $supplier_id
                                    ), ARRAY_A);
                                    ?>

                                    <div class="mc-suborder-block">
                                        <div class="mc-suborder-header">
                                            <div>
                                                <strong><?php echo esc_html($supplier_name ?: ('Supplier #' . $supplier_id)); ?></strong><br>
                                                <span class="mc-suborder-ref">
                                                    Sub-order: <?php echo esc_html($row['suborder_number']); ?>
                                                </span>
                                            </div>
                                            <div class="mc-suborder-status">
                                                <span class="mc-suborder-status-badge mc-status-<?php echo esc_attr($sub_status); ?>">
                                                    <?php echo esc_html(ucfirst($sub_status)); ?>
                                                </span><br>
                                                <span class="mc-suborder-total">
                                                    Supplier Total: £<?php echo number_format($supplier_total, 2); ?>
                                                </span><br>
                                                <span class="mc-suborder-commission">
                                                    Commission: £<?php echo number_format($fee_amount, 2); ?>
                                                    <?php if ($fee_percent > 0): ?>
                                                        (<?php echo number_format($fee_percent, 2); ?>%)
                                                    <?php endif; ?>
                                                </span><br>
                                                <span class="mc-email-indicator">
                                                    Email:
                                                    <?php if ($email_sent): ?>
                                                        <strong>Sent</strong>
                                                        <?php if ($email_sent_at): ?>
                                                            (<?php echo esc_html($email_sent_at); ?>)
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <strong>Not Sent</strong>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if ($items): ?>
                                            <table class="mc-suborder-table">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Qty</th>
                                                        <th>Unit Price</th>
                                                        <th>Line Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): ?>
                                                        <?php $product_label = get_the_title($item['product_id']); ?>
                                                        <tr>
                                                            <td><?php echo esc_html($product_label); ?></td>
                                                            <td><?php echo (int) $item['quantity']; ?></td>
                                                            <td>£<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                                            <td>£<?php echo number_format((float) $item['line_total'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p>No items found for this supplier.</p>
                                        <?php endif; ?>
                                    </div>

                                <?php endforeach; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <script>
            document.addEventListener('click', function (e) {
                const header = e.target.closest('[data-order-toggle]');
                if (!header) return;

                const card = header.closest('.mc-transferred-order-card');
                if (!card) return;

                card.classList.toggle('mc-order-collapsed');
                card.classList.toggle('mc-order-expanded');
            });
            </script>

        <?php endif; ?>
    </div>
    <?php
  }

      /* ---------------------------------------------------------
       SUBSCRIPTIONS CONTROL CENTRE (PAGE SKELETON)
       Hybrid Mode:
       - Dropdown reloads page
       - Actions use AJAX
    --------------------------------------------------------- */
    public function subscription_control_page() {

        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        // Load all pharmacies for dropdown
        $pharmacies = get_posts([
            'post_type'      => 'mc_pharmacy',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        $selected_id = isset($_GET['pharmacy_id']) ? intval($_GET['pharmacy_id']) : 0;

        ?>
        <div class="wrap mc-subscriptions-wrap">
            <h1>Pharmacy Subscriptions</h1>

            <!-- PHARMACY SELECTOR -->
            <form method="get" class="mc-subscription-selector">
                <input type="hidden" name="page" value="medicompare-subscriptions">

                <label>
                    <strong>Select Pharmacy:</strong>
                    <select name="pharmacy_id" onchange="this.form.submit()">
                        <option value="0">-- Select Pharmacy --</option>
                        <?php foreach ($pharmacies as $pid): ?>
                            <option value="<?php echo esc_attr($pid); ?>"
                                <?php selected($selected_id, $pid); ?>>
                                <?php echo esc_html(get_the_title($pid)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

            <?php if (!$selected_id): ?>
                <p class="mc-muted">Please select a pharmacy to view subscription details.</p>
                <?php return; ?>
            <?php endif; ?>

            <?php
            /* ---------------------------------------------------------
               LOAD SUBSCRIPTION META FOR SELECTED PHARMACY
            --------------------------------------------------------- */
            $status          = get_post_meta($selected_id, '_mc_subscription_status', true);
            $trial_start     = get_post_meta($selected_id, '_mc_trial_start', true);
            $trial_end       = get_post_meta($selected_id, '_mc_trial_end', true);
            $sub_start       = get_post_meta($selected_id, '_mc_subscription_start', true);
            $sub_end         = get_post_meta($selected_id, '_mc_subscription_end', true);
            $next_billing    = get_post_meta($selected_id, '_mc_next_billing_date', true);
            $stripe_customer = get_post_meta($selected_id, '_mc_stripe_customer_id', true);
            $stripe_sub      = get_post_meta($selected_id, '_mc_stripe_subscription_id', true);
            ?>

            <hr>

           <!-- SUBSCRIPTION DETAILS PANEL -->
            <h2>Subscription Details</h2>

            <div id="mc-subscription-details-table-wrapper">
                <table class="form-table mc-subscription-details-table">
                    <tr>
                        <th>Status</th>
                        <td><?php echo esc_html($status ?: 'unknown'); ?></td>
                    </tr>
                    <tr>
                        <th>Trial Start</th>
                        <td><?php echo $trial_start ? date('d M Y', $trial_start) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Trial End</th>
                        <td><?php echo $trial_end ? date('d M Y', $trial_end) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Subscription Start</th>
                        <td><?php echo $sub_start ? date('d M Y', $sub_start) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Subscription End</th>
                        <td><?php echo $sub_end ? date('d M Y', $sub_end) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Next Billing</th>
                        <td><?php echo $next_billing ? date('d M Y', $next_billing) : '—'; ?></td>
                    </tr>
                    <tr>
                        <th>Stripe Customer ID</th>
                        <td><?php echo esc_html($stripe_customer ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <th>Stripe Subscription ID</th>
                        <td><?php echo esc_html($stripe_sub ?: '—'); ?></td>
                    </tr>
                </table>
            </div>

            <!-- SUBSCRIPTION TIMELINE -->
            <h2>Subscription Timeline</h2>

            <div id="mc-subscription-timeline"
                 data-trial-start="<?php echo esc_attr($trial_start); ?>"
                 data-trial-end="<?php echo esc_attr($trial_end); ?>"
                 data-sub-start="<?php echo esc_attr($sub_start); ?>"
                 data-sub-end="<?php echo esc_attr($sub_end); ?>"
                 data-next-billing="<?php echo esc_attr($next_billing); ?>">
            </div>

            <hr>

            <!-- EDIT SUBSCRIPTION FIELDS -->
            <h2>Edit Subscription</h2>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="mc-subscription-edit-form">
                <?php wp_nonce_field('mc_save_subscription_meta', 'mc_subscription_meta_nonce'); ?>
                <input type="hidden" name="mc_pharmacy_id" value="<?php echo esc_attr($selected_id); ?>">

                <table class="form-table">
                    <tr>
                        <th>Trial Start</th>
                        <td><input type="date" name="trial_start"
                            value="<?php echo $trial_start ? date('Y-m-d', $trial_start) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th>Trial End</th>
                        <td><input type="date" name="trial_end"
                            value="<?php echo $trial_end ? date('Y-m-d', $trial_end) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th>Subscription Start</th>
                        <td><input type="date" name="sub_start"
                            value="<?php echo $sub_start ? date('Y-m-d', $sub_start) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th>Subscription End</th>
                        <td><input type="date" name="sub_end"
                            value="<?php echo $sub_end ? date('Y-m-d', $sub_end) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th>Next Billing</th>
                        <td><input type="date" name="next_billing"
                            value="<?php echo $next_billing ? date('Y-m-d', $next_billing) : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="trial"      <?php selected($status, 'trial'); ?>>Trial</option>
                                <option value="active"     <?php selected($status, 'active'); ?>>Active</option>
                                <option value="expired"    <?php selected($status, 'expired'); ?>>Expired</option>
                                <option value="past_due"   <?php selected($status, 'past_due'); ?>>Past Due</option>
                                <option value="canceled"   <?php selected($status, 'canceled'); ?>>Canceled</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p><button class="button button-primary">Save Changes</button></p>
            </form>

            <hr>

            <!-- ACTION BUTTONS (AJAX) -->
            <h2>Actions</h2>

            <div id="mc-subscription-actions"
                 data-pharmacy="<?php echo esc_attr($selected_id); ?>">

                <button class="button"
                    data-action="extend_trial_7">Extend Trial +7 Days</button>

                <button class="button"
                    data-action="extend_trial_30">Extend Trial +30 Days</button>

                <button class="button"
                    data-action="reset_trial">Reset Trial</button>

                <button class="button"
                    data-action="activate">Activate Subscription</button>

                <button class="button"
                    data-action="expire">Mark Expired</button>

                <button class="button"
                    data-action="past_due">Mark Past Due</button>

                <button class="button"
                    data-action="cancel">Cancel Subscription</button>

                <button class="button"
                    data-action="clear_billing">Clear Next Billing</button>

                <button class="button"
                    data-action="stripe_sync">Sync from Stripe</button>

                <button class="button" 
                   data-action="billing_history_sync">Sync Billing History</button>

            </div>

            <div id="mc-subscription-action-result"></div>

            <hr>

            <!-- AUDIT LOG -->
            <h2>Audit Log</h2>

            <div id="mc-subscription-audit-log">
                <p class="mc-muted">Audit log will load here.</p>
            </div>

        </div>
        <?php
    }

        /* ---------------------------------------------------------
       SAVE SUBSCRIPTION META (FORM SUBMISSION)
    --------------------------------------------------------- */
    public function save_subscription_meta() {

        if (!isset($_POST['mc_subscription_meta_nonce']) ||
            !wp_verify_nonce($_POST['mc_subscription_meta_nonce'], 'mc_save_subscription_meta')) {
            return;
        }

        if (!isset($_POST['mc_pharmacy_id'])) {
            return;
        }

        $pid = intval($_POST['mc_pharmacy_id']);

        // Convert dates to timestamps
        $fields = [
            'trial_start' => '_mc_trial_start',
            'trial_end'   => '_mc_trial_end',
            'sub_start'   => '_mc_subscription_start',
            'sub_end'     => '_mc_subscription_end',
            'next_billing'=> '_mc_next_billing_date',
        ];

        foreach ($fields as $form_key => $meta_key) {
            if (!empty($_POST[$form_key])) {
                $ts = strtotime($_POST[$form_key] . ' 00:00:00');
                update_post_meta($pid, $meta_key, $ts);
            }
        }

        // Status
        if (isset($_POST['status'])) {
            update_post_meta($pid, '_mc_subscription_status', sanitize_text_field($_POST['status']));
        }

        // Redirect back to page
        wp_redirect(add_query_arg([
            'page'        => 'medicompare-subscriptions',
            'pharmacy_id' => $pid,
            'updated'     => '1'
        ], admin_url('admin.php')));
        exit;
    }


    /* ---------------------------------------------------------
       AJAX: SUBSCRIPTION ACTIONS (extend trial, activate, etc.)
    --------------------------------------------------------- */
    public function ajax_subscription_action() {

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $pid    = intval($_POST['pharmacy_id'] ?? 0);
        $action = sanitize_text_field($_POST['action_type'] ?? '');

        if (!$pid || !$action) {
            wp_send_json_error(['message' => 'Invalid request']);
        }

        $now = time();

        switch ($action) {

            case 'extend_trial_7':
                $end = intval(get_post_meta($pid, '_mc_trial_end', true));
                $end = $end > $now ? $end : $now;
                update_post_meta($pid, '_mc_trial_end', $end + (7 * 86400));
                $this->log_subscription_event($pid, "Trial extended by 7 days");
                break;

            case 'extend_trial_30':
                $end = intval(get_post_meta($pid, '_mc_trial_end', true));
                $end = $end > $now ? $end : $now;
                update_post_meta($pid, '_mc_trial_end', $end + (30 * 86400));
                $this->log_subscription_event($pid, "Trial extended by 30 days");
                break;

            case 'reset_trial':
                update_post_meta($pid, '_mc_trial_start', $now);
                update_post_meta($pid, '_mc_trial_end', $now + (30 * 86400));
                update_post_meta($pid, '_mc_subscription_status', 'trial');
                $this->log_subscription_event($pid, "Trial reset to 30 days");
                break;

            case 'activate':
                update_post_meta($pid, '_mc_subscription_status', 'active');
                update_post_meta($pid, '_mc_subscription_start', $now);
                $this->log_subscription_event($pid, "Subscription activated");
                break;

            case 'expire':
                update_post_meta($pid, '_mc_subscription_status', 'expired');
                update_post_meta($pid, '_mc_subscription_end', $now);
                $this->log_subscription_event($pid, "Subscription marked expired");
                break;

            case 'past_due':
                update_post_meta($pid, '_mc_subscription_status', 'past_due');
                $this->log_subscription_event($pid, "Subscription marked past due");
                break;

            case 'cancel':
                update_post_meta($pid, '_mc_subscription_status', 'canceled');
                $this->log_subscription_event($pid, "Subscription canceled");
                break;

            case 'clear_billing':
                delete_post_meta($pid, '_mc_next_billing_date');
                $this->log_subscription_event($pid, "Next billing date cleared");
                break;

            default:
                wp_send_json_error(['message' => 'Unknown action']);
        }

        wp_send_json_success(['message' => 'Action completed']);
    }

        /* ---------------------------------------------------------
       AJAX: STRIPE SYNC
    --------------------------------------------------------- */
    public function ajax_subscription_stripe_sync() {

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $pid = intval($_POST['pharmacy_id'] ?? 0);
        if (!$pid) {
            wp_send_json_error(['message' => 'Invalid pharmacy']);
        }

        $stripe_customer = get_post_meta($pid, '_mc_stripe_customer_id', true);
        $stripe_sub_id   = get_post_meta($pid, '_mc_stripe_subscription_id', true);

        if (!$stripe_customer || !$stripe_sub_id) {
            wp_send_json_error(['message' => 'Missing Stripe IDs']);
        }

        // Load Stripe
        if (!class_exists('\Stripe\Stripe')) {
            require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
        }

        \Stripe\Stripe::setApiKey(get_option('mc_stripe_secret_key'));

        try {
            $subscription = \Stripe\Subscription::retrieve($stripe_sub_id);

            // Update WP meta
            update_post_meta($pid, '_mc_subscription_status', $subscription->status);

            update_post_meta($pid, '_mc_subscription_start', $subscription->current_period_start);
            update_post_meta($pid, '_mc_subscription_end',   $subscription->current_period_end);

            if (!empty($subscription->trial_start)) {
                update_post_meta($pid, '_mc_trial_start', $subscription->trial_start);
            }

            if (!empty($subscription->trial_end)) {
                update_post_meta($pid, '_mc_trial_end', $subscription->trial_end);
            }

            if (!empty($subscription->current_period_end)) {
                update_post_meta($pid, '_mc_next_billing_date', $subscription->current_period_end);
            }

            // Log event
            $this->log_subscription_event($pid, "Stripe sync completed (status: {$subscription->status})");

            wp_send_json_success([
                'message' => 'Stripe sync completed successfully'
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Stripe error: ' . $e->getMessage()
            ]);
        }
    }

        /* ---------------------------------------------------------
       SYNC BILLING HISTORY FROM STRIPE
    --------------------------------------------------------- */
    public function sync_billing_history_from_stripe($pharmacy_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_billing_history';

        $stripe_customer = get_post_meta($pharmacy_id, '_mc_stripe_customer_id', true);
        $stripe_subscription = get_post_meta($pharmacy_id, '_mc_stripe_subscription_id', true);

        if (!$stripe_customer || !$stripe_subscription) {
            return [
                'success' => false,
                'message' => 'Missing Stripe customer or subscription ID'
            ];
        }

        // Load Stripe
        if (!class_exists('\Stripe\Stripe')) {
            require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
        }

        \Stripe\Stripe::setApiKey(get_option('mc_stripe_secret_key'));

        try {
            // Fetch invoices for this subscription
            $invoices = \Stripe\Invoice::all([
                'subscription' => $stripe_subscription,
                'limit' => 100
            ]);

            foreach ($invoices->data as $invoice) {

                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE stripe_invoice_id = %s",
                    $invoice->id
                ));

                $data = [
                    'pharmacy_id'        => $pharmacy_id,
                    'subscription_id'    => $stripe_subscription,
                    'stripe_invoice_id'  => $invoice->id,
                    'stripe_payment_intent' => $invoice->payment_intent,
                    'stripe_charge_id'   => $invoice->charge,
                    'amount'             => $invoice->amount_paid / 100,
                    'currency'           => $invoice->currency,
                    'status'             => $invoice->status,
                    'invoice_url'        => isset($invoice->hosted_invoice_url) ? $invoice->hosted_invoice_url : '',
                    'created_at'         => date('Y-m-d H:i:s', $invoice->created),
                    'paid_at'            => $invoice->status === 'paid' ? date('Y-m-d H:i:s', $invoice->status_transitions->paid_at) : null,
                    'failed_at'          => $invoice->status === 'failed' ? date('Y-m-d H:i:s', $invoice->status_transitions->failed_at) : null,
                    'refunded_at'        => null,
                    'retry_count'        => $invoice->attempt_count
                ];

                if ($existing) {
                    // Update existing invoice
                    $wpdb->update(
                        $table,
                        $data,
                        ['id' => $existing]
                    );
                } else {
                    // Insert new invoice
                    $wpdb->insert($table, $data);
                }
            }

            // Log event
            $this->log_subscription_event($pharmacy_id, "Billing history synced from Stripe");

            return [
                'success' => true,
                'message' => 'Billing history synced successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Stripe error: ' . $e->getMessage()
            ];
        }
    }

     /* ---------------------------------------------------------
   AJAX: BILLING HISTORY SYNC
--------------------------------------------------------- */
    public function ajax_billing_history_sync()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $pharmacy_id = intval($_POST['pharmacy_id'] ?? 0);

        if (!$pharmacy_id) {
            wp_send_json_error(['message' => 'Invalid pharmacy ID']);
        }

        $result = $this->sync_billing_history_from_stripe($pharmacy_id);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }


    /* ---------------------------------------------------------
       AUDIT LOG: FETCH LOGS
    --------------------------------------------------------- */
    public function ajax_subscription_audit_log() {

        $pid = intval($_GET['pharmacy_id'] ?? 0);
        if (!$pid) {
            wp_send_json_error(['message' => 'Invalid pharmacy']);
        }

        $logs = get_post_meta($pid, '_mc_subscription_audit_log', true);
        if (!is_array($logs)) {
            $logs = [];
        }

        wp_send_json_success(['logs' => $logs]);
    }


    /* ---------------------------------------------------------
       AUDIT LOG: WRITE ENTRY
    --------------------------------------------------------- */
    private function log_subscription_event($pid, $message) {

        $logs = get_post_meta($pid, '_mc_subscription_audit_log', true);
        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'timestamp' => time(),
            'message'   => $message
        ];

        update_post_meta($pid, '_mc_subscription_audit_log', $logs);
    }

   /* ---------------------------------------------------------
   ENQUEUE JS FOR SUBSCRIPTION CONTROL PAGE
--------------------------------------------------------- */
    public function enqueue_subscription_js($hook) {

        if ($hook !== 'medicompare_page_medicompare-subscriptions') {
            return;
        }

        wp_enqueue_script(
            'mc-subscription-js',
            plugin_dir_url(__FILE__) . '../assets/js/subscription-control.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/subscription-control.js'),
            true
        );

        wp_localize_script('mc-subscription-js', 'MC_SUBSCRIPTIONS', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mc_subscription_actions'),
        ]);
    }

    /* ---------------------------------------------------------
   ENQUEUE CSS FOR SUBSCRIPTION CONTROL PAGE
--------------------------------------------------------- */
    public function enqueue_subscription_css($hook) {

        if ($hook !== 'medicompare_page_medicompare-subscriptions') {
            return;
        }

        wp_enqueue_style(
            'mc-subscription-css',
            plugin_dir_url(__FILE__) . '../assets/css/subscription-control.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/subscription-control.css')
        );
    }

}

new MediCompare_Admin_Menu();
