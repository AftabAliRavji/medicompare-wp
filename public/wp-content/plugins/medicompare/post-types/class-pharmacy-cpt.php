<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_CPT {

    public function __construct() {
        add_action('init', [$this, 'register'], 20);

        // Meta boxes
        add_action('add_meta_boxes', [$this, 'add_pharmacy_meta_boxes']);
        add_action('save_post_mc_pharmacy', [$this, 'save_pharmacy_meta'], 20, 2);

        // Admin columns
        add_filter('manage_mc_pharmacy_posts_columns', [$this, 'add_pharmacy_columns']);
        add_action('manage_mc_pharmacy_posts_custom_column', [$this, 'render_pharmacy_columns'], 10, 2);
        add_filter('manage_edit-mc_pharmacy_sortable_columns', [$this, 'make_pharmacy_columns_sortable']);
    }

    /* ---------------------------------------------------------
       REGISTER CPT
    --------------------------------------------------------- */
    public function register() {

        $labels = [
            'name'          => 'Pharmacies',
            'singular_name' => 'Pharmacy',
            'add_new'       => 'Add Pharmacy',
            'add_new_item'  => 'Add New Pharmacy',
            'edit_item'     => 'Edit Pharmacy',
            'new_item'      => 'New Pharmacy',
            'view_item'     => 'View Pharmacy',
            'search_items'  => 'Search Pharmacies',
        ];

        $args = [
            'labels'      => $labels,
            'public'      => false,
            'show_ui'     => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => true,
            'menu_icon'   => 'dashicons-admin-users',
            'supports'    => ['title'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'has_archive'     => false,
            'hierarchical'    => false,
        ];

        register_post_type('mc_pharmacy', $args);
    }

    /* ---------------------------------------------------------
       META BOXES
    --------------------------------------------------------- */
    public function add_pharmacy_meta_boxes() {
        add_meta_box(
            'mc_pharmacy_details',
            'Pharmacy Details',
            [$this, 'render_pharmacy_meta_box'],
            'mc_pharmacy',
            'normal',
            'default'
        );
    }

    public function render_pharmacy_meta_box($post) {

        $fields = [
            'pharmacy_code'   => '_mc_pharmacy_code',
            'email'           => '_mc_email',
            'phone'           => '_mc_phone',
            'address_line_1'  => '_mc_address_line_1',
            'address_line_2'  => '_mc_address_line_2',
            'city'            => '_mc_city',
            'postcode'        => '_mc_postcode',
            'gphc_number'     => '_mc_gphc_number',
            'contact_name'    => '_mc_contact_name',
            'status'          => '_mc_status',
        ];

        $values = [];
        foreach ($fields as $key => $meta_key) {
            $values[$key] = get_post_meta($post->ID, $meta_key, true);
        }

        wp_nonce_field('mc_save_pharmacy_meta', 'mc_pharmacy_meta_nonce');
        ?>

        <table class="form-table">

            <tr>
                <th><label>Pharmacy Code</label></th>
                <td><input type="text" name="mc_pharmacy_code" value="<?php echo esc_attr($values['pharmacy_code']); ?>" class="regular-text" required></td>
            </tr>

            <tr>
                <th><label>Email</label></th>
                <td><input type="email" name="mc_email" value="<?php echo esc_attr($values['email']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Phone</label></th>
                <td><input type="text" name="mc_phone" value="<?php echo esc_attr($values['phone']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Address Line 1</label></th>
                <td><input type="text" name="mc_address_line_1" value="<?php echo esc_attr($values['address_line_1']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Address Line 2</label></th>
                <td><input type="text" name="mc_address_line_2" value="<?php echo esc_attr($values['address_line_2']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>City</label></th>
                <td><input type="text" name="mc_city" value="<?php echo esc_attr($values['city']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Postcode</label></th>
                <td><input type="text" name="mc_postcode" value="<?php echo esc_attr($values['postcode']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>GPhC Number</label></th>
                <td><input type="text" name="mc_gphc_number" value="<?php echo esc_attr($values['gphc_number']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Contact Name</label></th>
                <td><input type="text" name="mc_contact_name" value="<?php echo esc_attr($values['contact_name']); ?>" class="regular-text"></td>
            </tr>

            <tr>
                <th><label>Status</label></th>
                <td>
                    <select name="mc_status">
                        <option value="pending_setup" <?php selected($values['status'], 'pending_setup'); ?>>Pending Setup</option>
                        <option value="pending_verification" <?php selected($values['status'], 'pending_verification'); ?>>Pending Verification</option>
                        <option value="suspended" <?php selected($values['status'], 'suspended'); ?>>Suspended</option>
                    </select>
                </td>
            </tr>

        </table>

        <?php
    }

    /* ---------------------------------------------------------
       SAVE META
    --------------------------------------------------------- */
    public function save_pharmacy_meta($post_id, $post) {

        if (!isset($_POST['mc_pharmacy_meta_nonce']) ||
            !wp_verify_nonce($_POST['mc_pharmacy_meta_nonce'], 'mc_save_pharmacy_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        // Set default status ONLY on new posts
        if ($post->post_status === 'auto-draft' || $post->post_date === $post->post_modified) {
            if (!get_post_meta($post_id, '_mc_status', true)) {
                update_post_meta($post_id, '_mc_status', 'pending_setup');
            }
        }

        $fields = [
            'mc_pharmacy_code'  => '_mc_pharmacy_code',
            'mc_email'          => '_mc_email',
            'mc_phone'          => '_mc_phone',
            'mc_address_line_1' => '_mc_address_line_1',
            'mc_address_line_2' => '_mc_address_line_2',
            'mc_city'           => '_mc_city',
            'mc_postcode'       => '_mc_postcode',
            'mc_gphc_number'    => '_mc_gphc_number',
            'mc_contact_name'   => '_mc_contact_name',
            'mc_status'         => '_mc_status',
        ];

        foreach ($fields as $form_key => $meta_key) {
            if (isset($_POST[$form_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_key]));
            }
        }
    }

    /* ---------------------------------------------------------
       ADMIN COLUMNS
    --------------------------------------------------------- */
    public function add_pharmacy_columns($columns) {

        $new = [];

        $new['cb']            = $columns['cb'];
        $new['title']         = 'Pharmacy Name';
        $new['pharmacy_code'] = 'Code';
        $new['email']         = 'Email';
        $new['phone']         = 'Phone';
        $new['addr1']         = 'Address 1';
        $new['addr2']         = 'Address 2';
        $new['city']          = 'City';
        $new['postcode']      = 'Postcode';
        $new['gphc']          = 'GPhC';
        $new['contact']       = 'Contact';
        $new['status']        = 'Status';
        $new['date']          = $columns['date'];

        return $new;
    }

    public function render_pharmacy_columns($column, $post_id) {

        switch ($column) {

            case 'pharmacy_code':
                echo esc_html(get_post_meta($post_id, '_mc_pharmacy_code', true));
                break;

            case 'email':
                echo esc_html(get_post_meta($post_id, '_mc_email', true));
                break;

            case 'phone':
                echo esc_html(get_post_meta($post_id, '_mc_phone', true));
                break;

            case 'addr1':
                echo esc_html(get_post_meta($post_id, '_mc_address_line_1', true));
                break;

            case 'addr2':
                echo esc_html(get_post_meta($post_id, '_mc_address_line_2', true));
                break;

            case 'city':
                echo esc_html(get_post_meta($post_id, '_mc_city', true));
                break;

            case 'postcode':
                echo esc_html(get_post_meta($post_id, '_mc_postcode', true));
                break;

            case 'gphc':
                echo esc_html(get_post_meta($post_id, '_mc_gphc_number', true));
                break;

            case 'contact':
                echo esc_html(get_post_meta($post_id, '_mc_contact_name', true));
                break;

            case 'status':
                $status = get_post_meta($post_id, '_mc_status', true);
                echo esc_html(ucwords(str_replace('_', ' ', $status)));
                break;
        }
    }

    public function make_pharmacy_columns_sortable($columns) {
        $columns['pharmacy_code'] = 'pharmacy_code';
        $columns['city']          = 'city';
        $columns['postcode']      = 'postcode';
        $columns['status']        = 'status';
        return $columns;
    }
}

new MediCompare_Pharmacy_CPT();
