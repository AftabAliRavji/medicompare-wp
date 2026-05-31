<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {

        // Top-level menu
        add_menu_page(
            'MediCompare',
            'MediCompare',
            'manage_options',
            'medicompare',
            [$this, 'dashboard_page'],
            'dashicons-clipboard',
            2
        );

        // Dashboard submenu
        add_submenu_page(
            'medicompare',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'medicompare',
            [$this, 'dashboard_page']
        );

        // CSV Upload submenu
        add_submenu_page(
            'medicompare',
            'Upload CSV',
            'Upload CSV',
            'manage_options',
            'medicompare-upload-csv',
            [$this, 'upload_csv_page']
        );

        // Reports submenu
        add_submenu_page(
            'medicompare',
            'Reports',
            'Reports',
            'manage_options',
            'medicompare-reports',
            [$this, 'reports_page']
        );
    }

    public function dashboard_page() {
        echo '<div class="wrap"><h1>MediCompare Dashboard</h1><p>Welcome to the MediCompare admin panel.</p></div>';
    }

    public function reports_page() {
        echo '<div class="wrap"><h1>Reports</h1><p>Reports module coming soon.</p></div>';
    }

    public function process_csv_upload() {

        if (!isset($_FILES['csv_file'])) {
            return null;
        }

        $file = $_FILES['csv_file'];

        // Validate file type
        $allowed = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowed)) {
            return ['error' => 'Invalid file type. Please upload a CSV file.'];
        }

        // Validate upload success
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'File upload failed.'];
        }

        // Read file
        $csv_path = $file['tmp_name'];
        $rows = array_map('str_getcsv', file($csv_path));

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        // Extract header
        $header = array_map('trim', array_shift($rows));

        // Normalise header to lowercase
        $header = array_map('strtolower', $header);

        // Expected columns
        $required = ['product_code', 'product_name', 'price', 'stock'];

        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                return ['error' => "Missing required column: $col"];
            }
        }

        // Map rows into structured array
        $mapped = [];

        foreach ($rows as $row) {
            if (count($row) !== count($header)) continue;

            $row = array_map('trim', $row);
            $data = array_combine($header, $row);

            // Normalise values
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

    public function insert_supplier_products($supplier_id, $rows) {
        global $wpdb;

        $table = $wpdb->prefix . 'medi_supplier_products';

        $inserted = 0;
        $updated = 0;

        foreach ($rows as $row) {

            // Lookup product by product_code (stored as post_title)
            $product = get_page_by_title($row['product_code'], OBJECT, 'mc_product');

            if (!$product) {
                // Create product if not exists
                $product_id = wp_insert_post([
                    'post_title'  => $row['product_code'],
                    'post_type'   => 'mc_product',
                    'post_status' => 'publish'
                ]);
            } else {
                $product_id = $product->ID;
            }

            // Check if supplier/product combo already exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE supplier_id = %d AND product_id = %d",
                $supplier_id,
                $product_id
            ));

            if ($existing) {
                // Update existing row
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
                // Insert new row
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

    public function get_suppliers() {
        return get_posts([
            'post_type'      => 'mc_supplier',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ]);
    }

    public function upload_csv_page() {

        $result = null;
        $db_result = null;

        // Fetch suppliers for dropdown
        $suppliers = $this->get_suppliers();

        // Handle CSV upload + processing
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

            // Step 6: Parse CSV
            $result = $this->process_csv_upload();

            if ($result && isset($result['success'])) {

                // Supplier selected by admin
                $supplier_id = intval($_POST['supplier_id']);

                // Step 7: Insert/update DB rows
                $db_result = $this->insert_supplier_products($supplier_id, $result['data']);
            }
        }

        ?>
        <div class="wrap">
            <h1>Upload Supplier CSV</h1>

            <!-- Error Notice -->
            <?php if ($result && isset($result['error'])): ?>
                <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <!-- CSV Parsed Successfully -->
            <?php if ($result && isset($result['success'])): ?>
                <div class="notice notice-success">
                    <p>CSV uploaded successfully. Parsed <?php echo count($result['data']); ?> rows.</p>
                </div>
            <?php endif; ?>

            <!-- DB Insert/Update Results -->
            <?php if ($db_result): ?>
                <div class="notice notice-success">
                    <p>
                        Inserted <?php echo $db_result['inserted']; ?> new products.<br>
                        Updated <?php echo $db_result['updated']; ?> existing products.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Preview Table -->
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

            <!-- Upload Form -->
            <form method="post" enctype="multipart/form-data" style="margin-top:30px;">

                <table class="form-table">

                    <!-- Supplier Dropdown -->
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

                    <!-- CSV File -->
                    <tr>
                        <th scope="row"><label for="csv_file">CSV File</label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                    </tr>

                </table>

                <?php submit_button('Upload CSV'); ?>
            </form>
        </div>
        <?php
    }
}

// IMPORTANT: Instantiation must be OUTSIDE the class
new MediCompare_Admin_Menu();
