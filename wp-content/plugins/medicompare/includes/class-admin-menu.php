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
       AJAX — Detect Supplier From Filename
    --------------------------------------------------------- */
    public function ajax_detect_supplier() {

        if (!isset($_POST['filename'])) {
            wp_send_json_error(['message' => 'No filename provided']);
        }

        $filename = sanitize_text_field($_POST['filename']);
        $suppliers = $this->get_suppliers();

        $detected = $this->detect_supplier_from_filename($filename, $suppliers);

        wp_send_json_success([
            'supplier_id' => $detected
        ]);
    }

    /* ---------------------------------------------------------
       CSV PARSER (supplier product CSV)
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
       INSERT / UPDATE SUPPLIER PRODUCTS
    --------------------------------------------------------- */
    public function insert_supplier_products($supplier_id, $rows) {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_supplier_products';

        $inserted = 0;
        $updated = 0;

        foreach ($rows as $row) {

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

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE supplier_id = %d AND product_id = %d",
                $supplier_id,
                $product_id
            ));

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

                $updated++;

            } else {

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

                $inserted++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated'  => $updated
        ];
    }

    /* ---------------------------------------------------------
       GET SUPPLIERS
    --------------------------------------------------------- */
    public function get_suppliers() {
        return get_posts([
            'post_type'      => 'mc_supplier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);
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
       SUPPLIER PRODUCT CSV UPLOAD (AJAX auto-detect)
    --------------------------------------------------------- */
    public function upload_supplier_product_csv_page() {

        $result = null;
        $db_result = null;

        $suppliers = $this->get_suppliers();

        // Final upload
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['final_submit'])
        ) {

            $result = $this->process_csv_upload();

            if ($result && isset($result['success'])) {

                $supplier_id = intval($_POST['supplier_id']);

                $db_result = $this->insert_supplier_products(
                    $supplier_id,
                    $result['data']
                );
            }
        }

        ?>
        <div class="wrap">
            <h1>Upload Supplier Product CSV</h1>

            <?php if ($result && isset($result['error'])): ?>
                <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <?php if ($result && isset($result['success'])): ?>
                <div class="notice notice-success">
                    <p>CSV uploaded successfully. Parsed <?php echo count($result['data']); ?> rows.</p>
                </div>
            <?php endif; ?>

            <?php if ($db_result): ?>
                <div class="notice notice-success">
                    <p>
                        Inserted <?php echo $db_result['inserted']; ?> new products.<br>
                        Updated <?php echo $db_result['updated']; ?> existing products.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($result && isset($result['success'])): ?>
                <h2>Preview</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['data'] as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['product_code']); ?></td>
                                <td><?php echo esc_html($row['product_name']); ?></td>
                                <td><?php echo esc_html($row['price']); ?></td>
                                <td><?php echo esc_html($row['stock']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" style="margin-top:30px;">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="supplier_id">Supplier</label></th>
                        <td>
                            <select name="supplier_id" id="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier->ID; ?>">
                                        <?php echo esc_html($supplier->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Upload Supplier Product CSV', 'primary', 'final_submit'); ?>
            </form>

            <script>
            document.getElementById('csv_file').addEventListener('change', function () {

                const fileInput = this;
                if (!fileInput.files.length) return;

                const filename = fileInput.files[0].name;

                const data = new FormData();
                data.append('action', 'medicompare_detect_supplier');
                data.append('filename', filename);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(result => {

                    if (result.success && result.data.supplier_id) {
                        const dropdown = document.getElementById('supplier_id');
                        dropdown.value = result.data.supplier_id;
                    }
                });
            });
            </script>

        </div>
        <?php
    }

    /* ---------------------------------------------------------
       PRODUCT CSV UPLOAD (placeholder)
    --------------------------------------------------------- */
    public function upload_product_csv_page() {
        echo '<div class="wrap"><h1>Upload Product CSV</h1><p>Coming soon.</p></div>';
    }

    /* ---------------------------------------------------------
       PHARMACY CSV UPLOAD (placeholder)
    --------------------------------------------------------- */
    public function upload_pharmacy_csv_page() {
        echo '<div class="wrap"><h1>Upload Pharmacy CSV</h1><p>Coming soon.</p></div>';
    }
}

new MediCompare_Admin_Menu();
