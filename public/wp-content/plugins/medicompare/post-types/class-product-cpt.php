<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Product_CPT {

    public function __construct() {
        add_action('init', [$this, 'register'], 20);

        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'register_product_meta_boxes']);

        // Save meta box data
        add_action('save_post_mc_product', [$this, 'save_product_details']);

        // Admin list columns
        add_filter('manage_mc_product_posts_columns', [$this, 'add_product_columns']);
        add_action('manage_mc_product_posts_custom_column', [$this, 'render_product_columns'], 10, 2);

        // Make product_code sortable
        add_filter('manage_edit-mc_product_sortable_columns', [$this, 'make_columns_sortable']);
    }

    public function register() {

        $labels = [
            'name'          => 'Products',
            'singular_name' => 'Product',
            'add_new'       => 'Add Product',
            'add_new_item'  => 'Add New Product',
            'edit_item'     => 'Edit Product',
            'new_item'      => 'New Product',
            'view_item'     => 'View Product',
            'search_items'  => 'Search Products',
        ];

        $args = [
            'labels'      => $labels,
            'public'      => false,
            'show_ui'     => true,
            'show_in_menu'=> false,
            'show_in_admin_bar' => true,
            'menu_icon'   => 'dashicons-pressthis',
            'supports'    => ['title'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'has_archive'     => false,
            'hierarchical'    => false,
            'menu_position' => null,
        ];

        register_post_type('mc_product', $args);
    }

    /* ---------------------------------------------------------
       META BOX REGISTRATION
    --------------------------------------------------------- */
    public function register_product_meta_boxes() {
        add_meta_box(
            'mc_product_details',
            'Product Details',
            [$this, 'render_product_details_meta_box'],
            'mc_product',
            'normal',
            'high'
        );
    }

    /* ---------------------------------------------------------
       META BOX UI
    --------------------------------------------------------- */
    public function render_product_details_meta_box($post) {

        // Load correct meta keys (NO underscores)
        $product_code = get_post_meta($post->ID, 'mc_product_code', true);
        $category     = get_post_meta($post->ID, 'mc_category', true);
        $strength     = get_post_meta($post->ID, 'mc_strength', true);
        $pack_size    = get_post_meta($post->ID, 'mc_pack_size', true);
        $description  = get_post_meta($post->ID, 'mc_description', true);

        wp_nonce_field('mc_save_product_details', 'mc_product_details_nonce');
        ?>

        <table class="form-table">

            <tr>
                <th><label>Product Code</label></th>
                <td>
                    <input type="text"
                           name="mc_product_code"
                           value="<?php echo esc_attr($product_code); ?>"
                           class="regular-text"
                           required>

                    <?php if ($product_code): ?>
                        <p class="description" style="color:#d63638;">
                            Changing the product code may affect supplier product links.
                        </p>
                    <?php else: ?>
                        <p class="description">Enter a unique product code.</p>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th><label>Category</label></th>
                <td>
                    <input type="text"
                           name="mc_category"
                           value="<?php echo esc_attr($category); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th><label>Strength</label></th>
                <td>
                    <input type="text"
                           name="mc_strength"
                           value="<?php echo esc_attr($strength); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th><label>Pack Size</label></th>
                <td>
                    <input type="text"
                           name="mc_pack_size"
                           value="<?php echo esc_attr($pack_size); ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th><label>Description</label></th>
                <td>
                    <textarea name="mc_description"
                              rows="4"
                              class="large-text"><?php echo esc_textarea($description); ?></textarea>
                </td>
            </tr>

        </table>

        <?php
    }

    /* ---------------------------------------------------------
       SAVE HANDLER
    --------------------------------------------------------- */
    public function save_product_details($post_id) {

    // Prevent recursion
    if (defined('MC_SAVING_PRODUCT') && MC_SAVING_PRODUCT) {
        return;
    }

    // Nonce check
    if (!isset($_POST['mc_product_details_nonce']) ||
        !wp_verify_nonce($_POST['mc_product_details_nonce'], 'mc_save_product_details')) {
        return;
    }

    // Autosave / revisions / permissions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (wp_is_post_autosave($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Sanitize fields
    $product_code = isset($_POST['mc_product_code']) ? sanitize_text_field($_POST['mc_product_code']) : '';
    $category     = isset($_POST['mc_category']) ? sanitize_text_field($_POST['mc_category']) : '';
    $strength     = isset($_POST['mc_strength']) ? sanitize_text_field($_POST['mc_strength']) : '';
    $pack_size    = isset($_POST['mc_pack_size']) ? sanitize_text_field($_POST['mc_pack_size']) : '';
    $description  = isset($_POST['mc_description']) ? sanitize_textarea_field($_POST['mc_description']) : '';

    if ($product_code === '') {
        return; // product code required
    }

    // Prevent duplicate product codes
    $existing = get_posts([
        'post_type'      => 'mc_product',
        'post_status'    => 'any',
        'meta_key'       => 'mc_product_code',
        'meta_value'     => $product_code,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    if (!empty($existing) && $existing[0] != $post_id) {
        wp_die('A product with this product code already exists.');
    }

    // Prevent recursion during wp_update_post()
    define('MC_SAVING_PRODUCT', true);

    // Ensure product is published
    wp_update_post([
        'ID'          => $post_id,
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
        'post_name'   => sanitize_title($product_code), // slug = product_code
    ]);

    // Save meta using correct keys
    update_post_meta($post_id, 'mc_product_code', $product_code);
    update_post_meta($post_id, 'mc_category', $category);
    update_post_meta($post_id, 'mc_strength', $strength);
    update_post_meta($post_id, 'mc_pack_size', $pack_size);
    update_post_meta($post_id, 'mc_description', $description);

    // End recursion guard
    define('MC_SAVING_PRODUCT', false);
}


    /* ---------------------------------------------------------
       ADMIN LIST COLUMNS
    --------------------------------------------------------- */
    public function add_product_columns($columns) {

        $new = [];

        $new['cb']           = $columns['cb'];
        $new['title']        = 'Product Name';
        $new['product_code'] = 'Product Code';
        $new['category']     = 'Category';
        $new['strength']     = 'Strength';
        $new['pack_size']    = 'Pack Size';
        $new['description']  = 'Description';
        $new['date']         = $columns['date'];

        return $new;
    }

    public function render_product_columns($column, $post_id) {

        switch ($column) {

            case 'product_code':
                echo esc_html(get_post_meta($post_id, 'mc_product_code', true));
                break;

            case 'category':
                echo esc_html(get_post_meta($post_id, 'mc_category', true));
                break;

            case 'strength':
                echo esc_html(get_post_meta($post_id, 'mc_strength', true));
                break;

            case 'pack_size':
                echo esc_html(get_post_meta($post_id, 'mc_pack_size', true));
                break;

            case 'description':
                echo esc_html(get_post_meta($post_id, 'mc_description', true));
                break;
        }
    }

    public function make_columns_sortable($columns) {
        $columns['product_code'] = 'product_code';
        return $columns;
    }
}

new MediCompare_Product_CPT();
