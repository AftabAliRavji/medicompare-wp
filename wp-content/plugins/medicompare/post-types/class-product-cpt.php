<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Product_CPT {

    public function __construct() {
        $this->register();
    }

    public function register() {

        $labels = [
            'name' => 'Products',
            'singular_name' => 'Product',
            'add_new' => 'Add Product',
            'add_new_item' => 'Add New Product',
            'edit_item' => 'Edit Product',
            'new_item' => 'New Product',
            'view_item' => 'View Product',
            'search_items' => 'Search Products',
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-pressthis',
            'supports' => ['title'],
        ];

        register_post_type('mc_product', $args);
    }
}

new MediCompare_Product_CPT();
