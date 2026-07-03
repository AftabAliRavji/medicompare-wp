<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.2.0
 * Author: Aftab
 * Deployment test - June 28
 */

if (!session_id()) {
    session_start();
}

if (!defined('ABSPATH')) exit;

class MediCompare {

    public function __construct() {

        // Plugin activation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_activation_hook(__FILE__, [$this, 'run_migrations']);

        // ⭐ NEW: Auto-create pharmacy pages (safe, non-destructive)
        register_activation_hook(__FILE__, [$this, 'create_pharmacy_pages']);

        // Load CPTs early
        add_action('init', [$this, 'load_cpts'], 1);

        // Register custom roles
        add_action('init', [$this, 'register_roles'], 2);

        // Load admin menu
        require_once plugin_dir_path(__FILE__) . 'includes/class-admin-menu.php';

        // Front-end registration + claim flows
        require_once plugin_dir_path(__FILE__) . 'includes/pharmacy-protect.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-registration.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-claim.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-login.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-portal.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-frontend.php';
        require_once plugin_dir_path(__FILE__) . 'includes/pharmacy-comparison.php';

        // Hide theme header/footer for MediCompare pages
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/hide-theme-ui.php';

        // ⭐ NEW: Stripe config + checkout
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-config.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-stripe.php';
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-webhooks.php';


        // Requirements board
        require_once ABSPATH . 'project-req/requirements-board-endpoints.php';
    }

    /**
     * ⭐ Auto-create pharmacy parent + child pages safely
     * This will NOT override existing pages.
    */
    public function create_pharmacy_pages() {

        //
        // 1️⃣ Ensure parent /pharmacy/ page exists
        //
        $parent = get_page_by_path('pharmacy');

        if (!$parent) {

            // Create the parent page
            $parent_id = wp_insert_post([
                'post_title'   => 'Pharmacy',
                'post_name'    => 'pharmacy',
                'post_content' => '[mc_pharmacy_portal]',   // ⭐ main portal shortcode
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);

        } else {

            $parent_id = $parent->ID;

            // Ensure the parent page contains the portal shortcode
            if (strpos($parent->post_content, '[mc_pharmacy_portal]') === false) {
                wp_update_post([
                    'ID'           => $parent_id,
                    'post_content' => '[mc_pharmacy_portal]'
                ]);
            }
        }


        //
        // 2️⃣ Auto-create child pages under /pharmacy/
        //
        $pages = [
            'pharmacy-registration' => [
                'title'   => 'Pharmacy Registration',
                'content' => '[mc_pharmacy_registration]'
            ],
            'edit-details' => [
                'title'   => 'Edit Pharmacy Details',
                'content' => '[mc_pharmacy_edit_details]'
            ],
            'dashboard' => [
                'title'   => 'Pharmacy Dashboard',
                'content' => '[mc_pharmacy_dashboard]'
            ],
            'login' => [
                'title'   => 'Pharmacy Login',
                'content' => '[mc_pharmacy_login]'
            ],
            'search' => [
                'title'   => 'Search Products',
                'content' => '[mc_pharmacy_search]'
            ],
            'orders' => [
                'title'   => 'Pharmacy Orders',
                'content' => '[mc_pharmacy_orders]'
            ],
            'subscription' => [
                'title'   => 'Subscription History',
                'content' => '[mc_pharmacy_subscription]'
            ],

        ];

        foreach ($pages as $slug => $page) {

            // Check if the page already exists under ANY parent
            $existing = get_page_by_path('pharmacy/' . $slug);

            if ($existing) {
                continue; // SAFE — do not override or duplicate
            }

            // Create the missing page under /pharmacy/
            wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $slug,
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_parent'  => $parent_id
            ]);
        }
    }



    /**
     * Register the pharmacy_user role so WP does NOT strip it from users.
     */
    public function register_roles() {

        add_role(
            'pharmacy_user',
            'Pharmacy User',
            [
                'read' => true,
            ]
        );
    }

    public function load_cpts() {
        error_log("MediCompare: load_cpts() fired");

        require_once plugin_dir_path(__FILE__) . 'post-types/class-supplier-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-product-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-pharmacy-cpt.php';
    }

    public function activate() {
        global $wpdb;

        $table   = $wpdb->prefix . 'medi_import_logs';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id BIGINT UNSIGNED NOT NULL,
            supplier_id BIGINT UNSIGNED DEFAULT NULL,
            filename VARCHAR(255) NOT NULL,
            inserted INT DEFAULT 0,
            updated INT DEFAULT 0,
            errors LONGTEXT NULL,
            ip VARCHAR(100) NULL,
            ua VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function run_migrations() {
        global $wpdb;

        $migration_dir = plugin_dir_path(__FILE__) . 'migrations/';

        foreach (glob($migration_dir . '*.sql') as $file) {
            $sql = file_get_contents($file);
            $wpdb->query($sql);
        }
    }

}

new MediCompare();
