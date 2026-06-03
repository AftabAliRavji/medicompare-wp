<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);

        // AJAX for supplier auto-detect
        add_action('wp_ajax_medicompare_detect_supplier', [$this, 'ajax_detect_supplier']);
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

        // NEW: Upload Supplier CSV
        add_submenu_page(
            'medicompare',
            'Upload Supplier CSV',
            'Upload Supplier CSV',
            'manage_options',
            'upload-supplier-csv',
            [$this, 'upload_supplier_csv_page']
        );

        // NEW: Supplier Products ALL
        add_submenu_page(
            'medicompare',
            'Supplier Products ALL',
            'Supplier Products ALL',
            'manage_options',
            'supplier-products-all',
            [$this, 'supplier_products_all_page']
        );

        // Existing: Upload Supplier Product CSV
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

    /* ---------------------------------------------------------
       DASHBOARD
    --------------------------------------------------------- */
    public function dashboard_page() {
        echo '<div class="wrap"><h1>MediCompare Dashboard</h1><p>Welcome to the MediCompare admin panel.</p></div>';
    }

    /* ---------------------------------------------------------
       REPORTS (placeholder)
    --------------------------------------------------------- */
    public function reports_page() {
        echo '<div class="wrap"><h1>Reports</h1><p>Reports module coming soon.</p></div>';
    }

    /* ---------------------------------------------------------
       IMPORT LOGS (placeholder)
    --------------------------------------------------------- */
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
       INSERT OR UPDATE A SINGLE SUPPLIER PRODUCT ROW
    --------------------------------------------------------- */
    public function insert_supplier_product_row($supplier_id, $row) {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_supplier_products';

        // 1. Find or create product by product_code
        $product = get_page_by_title($row['product_code'], OBJECT, 'mc_product');

        if (!$product) {
            $product_id = wp_insert_post([
                'post_title'  => $row['product_code'],
                'post_type'   => 'mc_product',
                'post_status' => 'publish'
            ]);
        } else {
            $product_id = $product->ID;
        }

        // 2. Check if supplier/product mapping already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE supplier_id = %d AND product_id = %d",
            $supplier_id,
            $product_id
        ));

        // 3. Update existing row
        if ($existing) {

            $wpdb->update(
                $table,
                [
                    'price'        => $row['price'],
                    'stock'        => $row['stock'],
                    'last_updated' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%f', '%d', '%s'],
                ['%d']
            );

            return 'updated';
        }

        // 4. Insert new row
        $wpdb->insert(
            $table,
            [
                'supplier_id'  => $supplier_id,
                'product_id'   => $product_id,
                'price'        => $row['price'],
                'stock'        => $row['stock'],
                'last_updated' => current_time('mysql')
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

        $existing = get_posts([
            'post_type'      => 'mc_product',
            'post_status'    => 'any',
            'meta_key'       => '_mc_product_code',
            'meta_value'     => $product_code,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            $product_id = $existing[0];

            wp_update_post([
                'ID'         => $product_id,
                'post_title' => $product_name,
                'post_name'  => sanitize_title($product_code),
            ]);

            update_post_meta($product_id, '_mc_product_code', $product_code);
            update_post_meta($product_id, '_mc_category', $category);
            update_post_meta($product_id, '_mc_strength', $strength);
            update_post_meta($product_id, '_mc_pack_size', $pack_size);
            update_post_meta($product_id, '_mc_description', $description);

            return 'updated';

        } else {

            $product_id = wp_insert_post([
                'post_title'  => $product_name,
                'post_name'   => sanitize_title($product_code),
                'post_type'   => 'mc_product',
                'post_status' => 'publish'
            ]);

            if (is_wp_error($product_id) || !$product_id) {
                return 'skipped';
            }

            update_post_meta($product_id, '_mc_product_code', $product_code);
            update_post_meta($product_id, '_mc_category', $category);
            update_post_meta($product_id, '_mc_strength', $strength);
            update_post_meta($product_id, '_mc_pack_size', $pack_size);
            update_post_meta($product_id, '_mc_description', $description);

            return 'inserted';
        }
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
        $suppliers = $this->get_suppliers(); // ID → name

        foreach ($suppliers as $id => $name) {

            $slug = sanitize_title($name);

            if (stripos($filename, $slug) !== false) {
                wp_send_json_success(['supplier_id' => $id]);
            }
        }

        wp_send_json_success(['supplier_id' => null]);
    }

    /* ---------------------------------------------------------
       AUTO-DETECT SUPPLIER FROM FILENAME (legacy helper)
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
       (Two‑Step + Session)
    --------------------------------------------------------- */
    public function upload_supplier_product_csv_page() {

        $result = null;
        $db_result = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode = null;

        $suppliers = $this->get_suppliers();

        // STEP 1 — PREVIEW
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_supplier_product_csv'])) {

            $mode = 'preview';
            $result = $this->process_csv_upload();

            if ($result && isset($result['success'])) {

                $_SESSION['supplier_product_csv_preview'] = $result['data'];
                $_SESSION['supplier_product_csv_supplier_id'] = intval($_POST['supplier_id']);
            }
        }

        // STEP 2 — IMPORT
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

        // PREVIEW MODE
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_product_csv'])) {

            $mode = 'preview';
            $result = $this->process_product_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['product_csv_preview'] = $result['data'];
            }
        }

        // IMPORT MODE
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

        // STEP 1 — PREVIEW
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_pharmacy_csv'])) {

            $mode = 'preview';
            $result = $this->process_pharmacy_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['pharmacy_csv_preview'] = $result['data'];
            }
        }

        // STEP 2 — IMPORT
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
       SUPPLIER CSV PARSER (NEW)
    --------------------------------------------------------- */
    public function process_supplier_csv_upload() {

        if (!isset($_FILES['supplier_csv_file'])) {
            return null;
        }

        $file = $_FILES['supplier_csv_file'];

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
       INSERT / UPDATE SUPPLIER FROM CSV ROW (NEW)
    --------------------------------------------------------- */
    public function insert_or_update_supplier_from_csv_row($row) {

        $name = trim($row['supplier_name']);
        if ($name === '') {
            return 'skipped';
        }

        // Find existing supplier by title
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

        // Meta fields
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
       SUPPLIER CSV UPLOAD PAGE (NEW)
       Two‑Step: Preview → Import
    --------------------------------------------------------- */
    public function upload_supplier_csv_page() {

        $result  = null;
        $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $mode    = null;

        // STEP 1 — PREVIEW
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_supplier_csv'])) {

            $mode   = 'preview';
            $result = $this->process_supplier_csv_upload();

            if ($result && isset($result['success'])) {
                $_SESSION['supplier_csv_preview'] = $result['data'];
            }
        }

        // STEP 2 — IMPORT
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

        include __DIR__ . '/admin-pages/upload-supplier-csv.php';
    }

    /* ---------------------------------------------------------
       SUPPLIER PRODUCTS ALL PAGE (NEW)
    --------------------------------------------------------- */
    public function supplier_products_all_page() {
        $suppliers = $this->get_suppliers();
        $selected_supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

        $products = [];
        if ($selected_supplier_id) {
            $products = $this->get_supplier_products($selected_supplier_id);
        }

        include __DIR__ . '/admin-pages/supplier-products-all.php';
    }

    /* ---------------------------------------------------------
       GET SUPPLIER PRODUCTS FOR A SUPPLIER (NEW)
    --------------------------------------------------------- */
    public function get_supplier_products($supplier_id) {
        global $wpdb;

        $supplier_id = intval($supplier_id);
        if (!$supplier_id) {
            return [];
        }

        $table = $wpdb->prefix . 'medi_supplier_products';

        $sql = $wpdb->prepare("
            SELECT sp.id,
                   sp.price,
                   sp.stock,
                   sp.last_updated,
                   p.ID   AS product_id,
                   p.post_title AS product_title
            FROM {$table} sp
            LEFT JOIN {$wpdb->posts} p
                ON sp.product_id = p.ID
            WHERE sp.supplier_id = %d
            ORDER BY p.post_title ASC
        ", $supplier_id);

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return $rows ? $rows : [];
    }
}

new MediCompare_Admin_Menu();
