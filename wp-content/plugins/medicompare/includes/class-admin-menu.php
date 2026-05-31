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


public function upload_csv_page() {

    $result = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
        $result = $this->process_csv_upload();
    }

    ?>
    <div class="wrap">
        <h1>Upload Supplier CSV</h1>

        <?php if ($result && isset($result['error'])): ?>
            <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
        <?php endif; ?>

        <?php if ($result && isset($result['success'])): ?>
            <div class="notice notice-success"><p>CSV uploaded successfully. Parsed <?php echo count($result['data']); ?> rows.</p></div>

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

            <p style="margin-top:20px;">
                <a href="#" class="button button-primary">Insert into Database (Step 7)</a>
            </p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="margin-top:30px;">
            <table class="form-table">
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

new MediCompare_Admin_Menu();
