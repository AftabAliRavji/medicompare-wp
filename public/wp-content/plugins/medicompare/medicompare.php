<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.2.1
 * Author: Aftab
 * Deployment test - July 18
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

        // ⭐ Auto-create pharmacy pages
        register_activation_hook(__FILE__, [$this, 'create_pharmacy_pages']);

        // ⭐ NEW: Auto-create Welcome Signup page
        register_activation_hook(__FILE__, [$this, 'create_welcome_signup_page']);

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

        // ⭐ NEW: Welcome Signup page template loader
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/welcome-signup.php';

        // Hide theme header/footer for MediCompare pages
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/hide-theme-ui.php';

        // Stripe config + checkout
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-config.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-stripe.php';
        require_once plugin_dir_path(__FILE__) . 'includes/stripe-webhooks.php';

        // Requirements board
        require_once ABSPATH . 'project-req/requirements-board-endpoints.php';
    }

    /**
     * ⭐ Auto-create Welcome Signup Page
     */
    public function create_welcome_signup_page() {

        $existing = get_page_by_path('welcome-signup');
        if ($existing) return;

        wp_insert_post([
            'post_title'   => 'Welcome to MediCompare',
            'post_name'    => 'welcome-signup',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[mc_welcome_signup]'
        ]);
    }

    /**
     * ⭐ Auto-create pharmacy parent + child pages safely
     */
    public function create_pharmacy_pages() {

        $parent = get_page_by_path('pharmacy');

        if (!$parent) {
            $parent_id = wp_insert_post([
                'post_title'   => 'Pharmacy',
                'post_name'    => 'pharmacy',
                'post_content' => '[mc_pharmacy_portal]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        } else {
            $parent_id = $parent->ID;

            if (strpos($parent->post_content, '[mc_pharmacy_portal]') === false) {
                wp_update_post([
                    'ID'           => $parent_id,
                    'post_content' => '[mc_pharmacy_portal]'
                ]);
            }
        }

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

            $existing = get_page_by_path('pharmacy/' . $slug);

            if ($existing) continue;

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
     * Register the pharmacy_user role
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
