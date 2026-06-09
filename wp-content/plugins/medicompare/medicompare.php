<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.2.0
 * Author: Aftab
 */

if (!session_id()) {
    session_start();
}

if (!defined('ABSPATH')) exit;

class MediCompare {

    public function __construct() {

        // Plugin activation
        register_activation_hook(__FILE__, [$this, 'activate']);

        // Load CPTs early
        add_action('init', [$this, 'load_cpts'], 1);

        // Register custom roles
        add_action('init', [$this, 'register_roles'], 2);

        // Load admin menu
        require_once plugin_dir_path(__FILE__) . 'includes/class-admin-menu.php';

        // Front-end registration + claim flows
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-registration.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-claim.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-login.php';
        require_once plugin_dir_path(__FILE__) . 'includes/frontend/pharmacy-frontend.php';
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
}

new MediCompare();
