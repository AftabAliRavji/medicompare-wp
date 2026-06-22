<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_medicompare_detect_supplier', [$this, 'ajax_detect_supplier']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

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
       - Filter by supplier, pharmacy, date range
       - Shows per-supplier totals + commission placeholder
    --------------------------------------------------------- */
    public function transferred_orders_page() {
    global $wpdb;

    $orders_table           = $wpdb->prefix . 'medi_orders';
    $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';
    $suborders_table        = $wpdb->prefix . 'medi_order_suborders';
    $order_items_table      = $wpdb->prefix . 'medi_order_items';
    $posts_table            = $wpdb->posts;

    // Suppliers
    $suppliers = $this->get_suppliers();

    // Pharmacies
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

    // Filters
    $selected_supplier = isset($_GET['supplier_id']) ? (int) $_GET['supplier_id'] : 0;
    $selected_pharmacy = isset($_GET['pharmacy_id']) ? (int) $_GET['pharmacy_id'] : 0;
    $date_from         = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to           = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $order_number      = isset($_GET['order_number']) ? (int) $_GET['order_number'] : 0;

    $where   = ["1=1"];
    $params  = [];

    if ($selected_pharmacy) {
        $where[]  = "o.pharmacy_id = %d";
        $params[] = $selected_pharmacy;
    }

    if ($selected_supplier) {
        $where[]  = "oss.supplier_id = %d";
        $params[] = $selected_supplier;
    }

    if ($date_from) {
        $where[]  = "o.created_at >= %s";
        $params[] = $date_from . ' 00:00:00';
    }

    if ($date_to) {
        $where[]  = "o.created_at <= %s";
        $params[] = $date_to . ' 23:59:59';
    }

    if ($order_number) {
        $where[]  = "o.order_number = %d";
        $params[] = $order_number;
    }

    // Only transferred/sent
    $where[] = "o.status IN ('TRANSFERRED','SENT')";

    $where_sql = implode(' AND ', $where);

    $sql = "
        SELECT
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
        LIMIT 100
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

        <?php if ($filters_applied && $rows): ?>
            <div class="mc-admin-results-count">
                Showing <?php echo count($rows); ?> transferred orders
            </div>
        <?php endif; ?>


        <?php if (!$filters_applied): ?>
            <p class="mc-muted">Use the filters above to view transferred orders.</p>

        <?php elseif ($filters_applied && !$rows): ?>
            <p>No transferred orders found for the selected filters.</p>

        <?php else: ?>

            <?php
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

                // Toggle only this card
                card.classList.toggle('mc-order-collapsed');
                card.classList.toggle('mc-order-expanded');
            });
            </script>

        <?php endif; ?>
    </div>
    <?php
  }

}

new MediCompare_Admin_Menu();
