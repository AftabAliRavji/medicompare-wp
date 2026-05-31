<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.2.0
 * Author: Aftab
 */

if (!defined('ABSPATH')) exit;

class MediCompare {

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);

        // Load CPTs
        add_action('init', [$this, 'load_cpts']);

	// Load admin menu
    	require_once plugin_dir_path(__FILE__) . 'includes/class-admin-menu.php';
    }

    public function load_cpts() {
        error_log("MediCompare: load_cpts() fired");

        require_once plugin_dir_path(__FILE__) . 'post-types/class-supplier-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-product-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'post-types/class-pharmacy-cpt.php';
    }

    public function activate() {
        // DB table creation (already done in Step 3)
    }
}

new MediCompare();
