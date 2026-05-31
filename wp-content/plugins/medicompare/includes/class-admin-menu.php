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

    public function upload_csv_page() {
        ?>
        <div class="wrap">
            <h1>Upload Supplier CSV</h1>
            <p>Upload a CSV file containing supplier product prices and stock levels.</p>

            <form method="post" enctype="multipart/form-data">
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
}

new MediCompare_Admin_Menu();
