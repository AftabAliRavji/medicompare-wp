<?php
error_log("Supplier CPT file loaded successfully");

if (!defined('ABSPATH')) exit;

class MediCompare_Supplier_CPT {

    const META_EMAIL   = 'mc_supplier_email';
    const META_PHONE   = 'mc_supplier_phone';
    const META_CODE    = 'mc_supplier_code';
    const META_STATUS  = 'mc_supplier_status';
    const META_MANAGER = 'mc_supplier_manager';

    // NEW structured address fields
    const META_ADDR1   = 'mc_supplier_address_1';
    const META_ADDR2   = 'mc_supplier_address_2';
    const META_CITY    = 'mc_supplier_city';
    const META_COUNTY  = 'mc_supplier_county';
    const META_POSTCODE = 'mc_supplier_postcode';
    const META_COUNTRY  = 'mc_supplier_country';

    public function __construct() {
        add_action('init',                               [$this, 'register_cpt'], 20);
        add_action('add_meta_boxes',                     [$this, 'register_meta_boxes']);
        add_action('save_post_mc_supplier',              [$this, 'save_meta'], 10, 2);

        // Admin columns
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
            'show_in_admin_bar'  => true,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
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

        // NEW structured address meta box
        add_meta_box(
            'mc_supplier_address',
            'Supplier Address',
            [$this, 'render_address_meta_box'],
            'mc_supplier',
            'normal',
            'default'
        );
    }

    public function render_meta_box($post) {

        wp_nonce_field('mc_supplier_save_meta', 'mc_supplier_meta_nonce');

        $email   = get_post_meta($post->ID, self::META_EMAIL, true);
        $phone   = get_post_meta($post->ID, self::META_PHONE, true);
        $code    = get_post_meta($post->ID, self::META_CODE, true);
        $status  = get_post_meta($post->ID, self::META_STATUS, true) ?: 'active';
        $manager = get_post_meta($post->ID, self::META_MANAGER, true);

        ?>
        <table class="form-table">

            <tr>
                <th><label for="mc_supplier_email">Email</label></th>
                <td>
                    <input type="email" name="mc_supplier_email" id="mc_supplier_email"
                           value="<?php echo esc_attr($email); ?>" class="regular-text">
                    <p class="description">Used for order and invoice emails.</p>
                </td>
            </tr>

            <tr>
                <th><label for="mc_supplier_phone">Phone</label></th>
                <td>
                    <input type="text" name="mc_supplier_phone" id="mc_supplier_phone"
                           value="<?php echo esc_attr($phone); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th><label for="mc_supplier_manager">Account Manager</label></th>
                <td>
                    <input type="text" name="mc_supplier_manager" id="mc_supplier_manager"
                           value="<?php echo esc_attr($manager); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th><label for="mc_supplier_code">Supplier Code</label></th>
                <td>
                    <input type="text" name="mc_supplier_code" id="mc_supplier_code"
                           value="<?php echo esc_attr($code); ?>" class="regular-text">
                    <p class="description">Internal unique code (used for imports, reporting, future APIs).</p>
                </td>
            </tr>

            <tr>
                <th><label for="mc_supplier_status">Status</label></th>
                <td>
                    <select name="mc_supplier_status" id="mc_supplier_status">
                        <option value="active"    <?php selected($status, 'active'); ?>>Active</option>
                        <option value="suspended" <?php selected($status, 'suspended'); ?>>Suspended</option>
                        <option value="test"      <?php selected($status, 'test'); ?>>Test</option>
                    </select>
                </td>
            </tr>

        </table>
        <?php
    }

    /* ---------------------------------------------------------
       NEW STRUCTURED ADDRESS META BOX
    --------------------------------------------------------- */
    public function render_address_meta_box($post) {

        $addr1    = get_post_meta($post->ID, self::META_ADDR1, true);
        $addr2    = get_post_meta($post->ID, self::META_ADDR2, true);
        $city     = get_post_meta($post->ID, self::META_CITY, true);
        $county   = get_post_meta($post->ID, self::META_COUNTY, true);
        $postcode = get_post_meta($post->ID, self::META_POSTCODE, true);
        $country  = get_post_meta($post->ID, self::META_COUNTRY, true) ?: 'United Kingdom';

        ?>
        <table class="form-table">

            <tr>
                <th><label for="mc_supplier_address_1">Address Line 1</label></th>
                <td><input type="text" name="mc_supplier_address_1" id="mc_supplier_address_1"
                           class="regular-text" value="<?php echo esc_attr($addr1); ?>"></td>
            </tr>

            <tr>
                <th><label for="mc_supplier_address_2">Address Line 2</label></th>
                <td><input type="text" name="mc_supplier_address_2" id="mc_supplier_address_2"
                           class="regular-text" value="<?php echo esc_attr($addr2); ?>"></td>
            </tr>

            <tr>
                <th><label for="mc_supplier_city">City</label></th>
                <td><input type="text" name="mc_supplier_city" id="mc_supplier_city"
                           class="regular-text" value="<?php echo esc_attr($city); ?>"></td>
            </tr>

            <tr>
                <th><label for="mc_supplier_county">County / Region</label></th>
                <td><input type="text" name="mc_supplier_county" id="mc_supplier_county"
                           class="regular-text" value="<?php echo esc_attr($county); ?>"></td>
            </tr>

            <tr>
                <th><label for="mc_supplier_postcode">Postcode</label></th>
                <td><input type="text" name="mc_supplier_postcode" id="mc_supplier_postcode"
                           class="regular-text" value="<?php echo esc_attr($postcode); ?>"></td>
            </tr>

            <tr>
                <th><label for="mc_supplier_country">Country</label></th>
                <td><input type="text" name="mc_supplier_country" id="mc_supplier_country"
                           class="regular-text" value="<?php echo esc_attr($country); ?>"></td>
            </tr>

        </table>
        <?php
    }

    /* ---------------------------------------------------------
       SAVE ALL META FIELDS
    --------------------------------------------------------- */
    public function save_meta($post_id, $post) {

        if (!isset($_POST['mc_supplier_meta_nonce']) ||
            !wp_verify_nonce($_POST['mc_supplier_meta_nonce'], 'mc_supplier_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'mc_supplier') return;

        // Standard fields
        $fields = [
            self::META_EMAIL   => sanitize_email($_POST['mc_supplier_email'] ?? ''),
            self::META_PHONE   => sanitize_text_field($_POST['mc_supplier_phone'] ?? ''),
            self::META_CODE    => sanitize_text_field($_POST['mc_supplier_code'] ?? ''),
            self::META_STATUS  => sanitize_text_field($_POST['mc_supplier_status'] ?? 'active'),
            self::META_MANAGER => sanitize_text_field($_POST['mc_supplier_manager'] ?? ''),
        ];

        // NEW structured address fields
        $fields[self::META_ADDR1]    = sanitize_text_field($_POST['mc_supplier_address_1'] ?? '');
        $fields[self::META_ADDR2]    = sanitize_text_field($_POST['mc_supplier_address_2'] ?? '');
        $fields[self::META_CITY]     = sanitize_text_field($_POST['mc_supplier_city'] ?? '');
        $fields[self::META_COUNTY]   = sanitize_text_field($_POST['mc_supplier_county'] ?? '');
        $fields[self::META_POSTCODE] = sanitize_text_field($_POST['mc_supplier_postcode'] ?? '');
        $fields[self::META_COUNTRY]  = sanitize_text_field($_POST['mc_supplier_country'] ?? 'United Kingdom');

        foreach ($fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    /* ---------------------------------------------------------
       ADMIN COLUMNS
    --------------------------------------------------------- */
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

        // NEW address columns
        $new['mc_supplier_city']     = 'City';
        $new['mc_supplier_postcode'] = 'Postcode';

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
                echo esc_html(ucfirst(get_post_meta($post_id, self::META_STATUS, true) ?: 'active'));
                break;

            case 'mc_supplier_manager':
                echo esc_html(get_post_meta($post_id, self::META_MANAGER, true));
                break;

            case 'mc_supplier_city':
                echo esc_html(get_post_meta($post_id, self::META_CITY, true));
                break;

            case 'mc_supplier_postcode':
                echo esc_html(get_post_meta($post_id, self::META_POSTCODE, true));
                break;
        }
    }
}

new MediCompare_Supplier_CPT();
