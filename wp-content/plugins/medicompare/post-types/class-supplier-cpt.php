<?php
error_log("Supplier CPT file loaded successfully");

if (!defined('ABSPATH')) exit;

class MediCompare_Supplier_CPT {

    const META_EMAIL   = 'mc_supplier_email';
    const META_PHONE   = 'mc_supplier_phone';
    const META_ADDRESS = 'mc_supplier_address';
    const META_CODE    = 'mc_supplier_code';
    const META_STATUS  = 'mc_supplier_status';
    const META_MANAGER = 'mc_supplier_manager';

    public function __construct() {
        add_action('init',                               [$this, 'register_cpt'], 20);
        add_action('add_meta_boxes',                     [$this, 'register_meta_boxes']);
        add_action('save_post_mc_supplier',              [$this, 'save_meta'], 10, 2);
        add_filter('manage_mc_supplier_posts_columns',   [$this, 'add_admin_columns']);
        add_action('manage_mc_supplier_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
    }

    public function register_cpt() {

        $labels = [
            'name'               => 'Suppliers',
            'singular_name'      => 'Supplier',
            'add_new'            => 'Add Supplier',
            'add_new_item'       => 'Add New Supplier',
            'edit_item'          => 'Edit Supplier',
            'new_item'           => 'New Supplier',
            'view_item'          => 'View Supplier',
            'search_items'       => 'Search Suppliers',
            'not_found'          => 'No suppliers found',
            'not_found_in_trash' => 'No suppliers found in Trash',
            'menu_name'          => 'Suppliers',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
	    'show_in_admin_bar' => true,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
	    'has_archive'     => false,
            'hierarchical'    => false,
            'menu_position' => null,

        ];

        register_post_type('mc_supplier', $args);
    }

    public function register_meta_boxes() {
        add_meta_box(
            'mc_supplier_details',
            'Supplier Details',
            [$this, 'render_meta_box'],
            'mc_supplier',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {

        wp_nonce_field('mc_supplier_save_meta', 'mc_supplier_meta_nonce');

        $email   = get_post_meta($post->ID, self::META_EMAIL, true);
        $phone   = get_post_meta($post->ID, self::META_PHONE, true);
        $address = get_post_meta($post->ID, self::META_ADDRESS, true);
        $code    = get_post_meta($post->ID, self::META_CODE, true);
        $status  = get_post_meta($post->ID, self::META_STATUS, true);
        $manager = get_post_meta($post->ID, self::META_MANAGER, true);

        if (!$status) {
            $status = 'active';
        }

        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="mc_supplier_email">Email</label></th>
                <td>
                    <input type="email" name="mc_supplier_email" id="mc_supplier_email"
                           value="<?php echo esc_attr($email); ?>" class="regular-text">
                    <p class="description">Used for order and invoice emails.</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_supplier_phone">Phone</label></th>
                <td>
                    <input type="text" name="mc_supplier_phone" id="mc_supplier_phone"
                           value="<?php echo esc_attr($phone); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_supplier_address">Address</label></th>
                <td>
                    <textarea name="mc_supplier_address" id="mc_supplier_address"
                              rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_supplier_manager">Account Manager</label></th>
                <td>
                    <input type="text" name="mc_supplier_manager" id="mc_supplier_manager"
                           value="<?php echo esc_attr($manager); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_supplier_code">Supplier Code</label></th>
                <td>
                    <input type="text" name="mc_supplier_code" id="mc_supplier_code"
                           value="<?php echo esc_attr($code); ?>" class="regular-text">
                    <p class="description">Internal unique code (used for imports, reporting, future APIs).</p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_supplier_status">Status</label></th>
                <td>
                    <select name="mc_supplier_status" id="mc_supplier_status">
                        <option value="active"    <?php selected($status, 'active'); ?>>Active</option>
                        <option value="suspended" <?php selected($status, 'suspended'); ?>>Suspended</option>
                        <option value="test"      <?php selected($status, 'test'); ?>>Test</option>
                    </select>
                    <p class="description">Suspended suppliers can be excluded from search/orders later.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta($post_id, $post) {

        if (!isset($_POST['mc_supplier_meta_nonce']) ||
            !wp_verify_nonce($_POST['mc_supplier_meta_nonce'], 'mc_supplier_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'mc_supplier') {
            return;
        }

        $fields = [
            self::META_EMAIL   => isset($_POST['mc_supplier_email'])   ? sanitize_email($_POST['mc_supplier_email']) : '',
            self::META_PHONE   => isset($_POST['mc_supplier_phone'])   ? sanitize_text_field($_POST['mc_supplier_phone']) : '',
            self::META_ADDRESS => isset($_POST['mc_supplier_address']) ? sanitize_textarea_field($_POST['mc_supplier_address']) : '',
            self::META_CODE    => isset($_POST['mc_supplier_code'])    ? sanitize_text_field($_POST['mc_supplier_code']) : '',
            self::META_STATUS  => isset($_POST['mc_supplier_status'])  ? sanitize_text_field($_POST['mc_supplier_status']) : 'active',
            self::META_MANAGER => isset($_POST['mc_supplier_manager']) ? sanitize_text_field($_POST['mc_supplier_manager']) : '',
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    public function add_admin_columns($columns) {

        $new = [];

        foreach ($columns as $key => $label) {
            if ($key === 'cb' || $key === 'title') {
                $new[$key] = $label;
            }
        }

        $new['mc_supplier_email']   = 'Email';
        $new['mc_supplier_phone']   = 'Phone';
        $new['mc_supplier_code']    = 'Code';
        $new['mc_supplier_status']  = 'Status';
        $new['mc_supplier_manager'] = 'Account Manager';

        return $new;
    }

    public function render_admin_columns($column, $post_id) {

        switch ($column) {
            case 'mc_supplier_email':
                echo esc_html(get_post_meta($post_id, self::META_EMAIL, true));
                break;

            case 'mc_supplier_phone':
                echo esc_html(get_post_meta($post_id, self::META_PHONE, true));
                break;

            case 'mc_supplier_code':
                echo esc_html(get_post_meta($post_id, self::META_CODE, true));
                break;

            case 'mc_supplier_status':
                $status = get_post_meta($post_id, self::META_STATUS, true) ?: 'active';
                echo esc_html(ucfirst($status));
                break;

            case 'mc_supplier_manager':
                echo esc_html(get_post_meta($post_id, self::META_MANAGER, true));
                break;
        }
    }
}

new MediCompare_Supplier_CPT();
