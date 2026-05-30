<?php
/**
 * Plugin Name: MediCompare
 * Description: Core functionality for the MediCompare platform.
 * Version: 0.1.0
 * Author: Aftab
 */

if (!defined('ABSPATH')) exit;

class MediCompare {

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $orders_table = $wpdb->prefix . 'medi_orders';
        $order_items_table = $wpdb->prefix . 'medi_order_items';
        $supplier_products_table = $wpdb->prefix . 'medi_supplier_products';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Orders table
        $sql_orders = "CREATE TABLE $orders_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pharmacy_id BIGINT UNSIGNED NOT NULL,
            supplier_id BIGINT UNSIGNED NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            platform_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            platform_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pharmacy_id (pharmacy_id),
            KEY supplier_id (supplier_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Order items table
        $sql_order_items = "CREATE TABLE $order_items_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            supplier_cost_price DECIMAL(10,2) NULL,
            supplier_profit DECIMAL(10,2) NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        // Supplier products table
        $sql_supplier_products = "CREATE TABLE $supplier_products_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock INT UNSIGNED NOT NULL DEFAULT 0,
            last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY supplier_product (supplier_id, product_id),
            KEY price (price),
            KEY stock (stock)
        ) $charset_collate;";

        dbDelta($sql_orders);
        dbDelta($sql_order_items);
        dbDelta($sql_supplier_products);
    }
}

new MediCompare();
