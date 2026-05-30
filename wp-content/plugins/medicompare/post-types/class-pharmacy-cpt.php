<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_CPT {

    public function __construct() {
        $this->register();
    }

    public function register() {

        $labels = [
            'name' => 'Pharmacies',
            'singular_name' => 'Pharmacy',
            'add_new' => 'Add Pharmacy',
            'add_new_item' => 'Add New Pharmacy',
            'edit_item' => 'Edit Pharmacy',
            'new_item' => 'New Pharmacy',
            'view_item' => 'View Pharmacy',
            'search_items' => 'Search Pharmacies',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-admin-users',
            'supports' => ['title'],
        ];

        register_post_type('mc_pharmacy', $args);
    }
}

new MediCompare_Pharmacy_CPT();
