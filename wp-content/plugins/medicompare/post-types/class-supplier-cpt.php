<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Supplier_CPT {

    public function __construct() {
        $this->register();
    }

    public function register() {

        $labels = [
            'name' => 'Suppliers',
            'singular_name' => 'Supplier',
            'add_new' => 'Add Supplier',
            'add_new_item' => 'Add New Supplier',
            'edit_item' => 'Edit Supplier',
            'new_item' => 'New Supplier',
            'view_item' => 'View Supplier',
            'search_items' => 'Search Suppliers',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-store',
            'supports' => ['title'],
        ];

        register_post_type('mc_supplier', $args);
    }
}

new MediCompare_Supplier_CPT();
