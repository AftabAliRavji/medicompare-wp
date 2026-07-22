<?php
if (!defined('ABSPATH')) exit;

class MediCompare_Supplier_CPT {

    /* ---------------------------------------------------------
       META KEYS
    --------------------------------------------------------- */
    const META_EMAIL     = 'mc_supplier_email';
    const META_PHONE     = 'mc_supplier_phone';
    const META_CODE      = 'mc_supplier_code';
    const META_STATUS    = 'mc_supplier_status';
    const META_MANAGER   = 'mc_supplier_manager';

    const META_ADDR1     = 'mc_supplier_address_1';
    const META_ADDR2     = 'mc_supplier_address_2';
    const META_CITY      = 'mc_supplier_city';
    const META_COUNTY    = 'mc_supplier_county';
    const META_POSTCODE  = 'mc_supplier_postcode';
    const META_COUNTRY   = 'mc_supplier_country';

    public function __construct() {

        add_action('init', [$this, 'register_cpt'], 20);

        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_mc_supplier', [$this, 'save_meta'], 10, 2);

        add_filter('manage_mc_supplier_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_mc_supplier_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);

        add_filter('manage_edit-mc_supplier_sortable_columns', [$this, 'sortable_columns']);
    }

    /* ---------------------------------------------------------
       REGISTER CPT
    --------------------------------------------------------- */
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
            'supports'           => ['title'],
            'menu_icon'          => 'dashicons-groups',
        ];

        register_post_type('mc_supplier', $args);
    }

    /* ---------------------------------------------------------
       META BOXES
    --------------------------------------------------------- */
    public function register_meta_boxes() {

        add_meta_box(
            'mc_supplier_details',
            'Supplier Details',
            [$this, 'render_meta_box'],
            'mc_supplier',
            'normal',
            'high'
        );

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
                <th>Email</th>
                <td><input type="email" name="mc_supplier_email" class="regular-text"
                           value="<?php echo esc_attr($email); ?>"></td>
            </tr>

            <tr>
                <th>Phone</th>
                <td><input type="text" name="mc_supplier_phone" class="regular-text"
                           value="<?php echo esc_attr($phone); ?>"></td>
            </tr>

            <tr>
                <th>Account Manager</th>
                <td><input type="text" name="mc_supplier_manager" class="regular-text"
                           value="<?php echo esc_attr($manager); ?>"></td>
            </tr>

            <tr>
                <th>Supplier Code</th>
                <td><input type="text" name="mc_supplier_code" class="regular-text"
                           value="<?php echo esc_attr($code); ?>"></td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    <select name="mc_supplier_status">
                        <option value="active"    <?php selected($status, 'active'); ?>>Active</option>
                        <option value="suspended" <?php selected($status, 'suspended'); ?>>Suspended</option>
                        <option value="test"      <?php selected($status, 'test'); ?>>Test</option>
                    </select>
                </td>
            </tr>

        </table>
        <?php
    }

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
                <th>Address Line 1</th>
                <td><input type="text" name="mc_supplier_address_1" class="regular-text"
                           value="<?php echo esc_attr($addr1); ?>"></td>
            </tr>

            <tr>
                <th>Address Line 2</th>
                <td><input type="text" name="mc_supplier_address_2" class="regular-text"
                           value="<?php echo esc_attr($addr2); ?>"></td>
            </tr>

            <tr>
                <th>City</th>
                <td><input type="text" name="mc_supplier_city" class="regular-text"
                           value="<?php echo esc_attr($city); ?>"></td>
            </tr>

            <tr>
                <th>County</th>
                <td><input type="text" name="mc_supplier_county" class="regular-text"
                           value="<?php echo esc_attr($county); ?>"></td>
            </tr>

            <tr>
                <th>Postcode</th>
                <td><input type="text" name="mc_supplier_postcode" class="regular-text"
                           value="<?php echo esc_attr($postcode); ?>"></td>
            </tr>

            <tr>
                <th>Country</th>
                <td><input type="text" name="mc_supplier_country" class="regular-text"
                           value="<?php echo esc_attr($country); ?>"></td>
            </tr>

        </table>
        <?php
    }

    /* ---------------------------------------------------------
       SAVE META
    --------------------------------------------------------- */
    public function save_meta($post_id, $post) {

        if (!isset($_POST['mc_supplier_meta_nonce']) ||
            !wp_verify_nonce($_POST['mc_supplier_meta_nonce'], 'mc_supplier_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'mc_supplier') return;

        $fields = [
            self::META_EMAIL     => sanitize_email($_POST['mc_supplier_email'] ?? ''),
            self::META_PHONE     => sanitize_text_field($_POST['mc_supplier_phone'] ?? ''),
            self::META_CODE      => sanitize_text_field($_POST['mc_supplier_code'] ?? ''),
            self::META_STATUS    => sanitize_text_field($_POST['mc_supplier_status'] ?? 'active'),
            self::META_MANAGER   => sanitize_text_field($_POST['mc_supplier_manager'] ?? ''),

            self::META_ADDR1     => sanitize_text_field($_POST['mc_supplier_address_1'] ?? ''),
            self::META_ADDR2     => sanitize_text_field($_POST['mc_supplier_address_2'] ?? ''),
            self::META_CITY      => sanitize_text_field($_POST['mc_supplier_city'] ?? ''),
            self::META_COUNTY    => sanitize_text_field($_POST['mc_supplier_county'] ?? ''),
            self::META_POSTCODE  => sanitize_text_field($_POST['mc_supplier_postcode'] ?? ''),
            self::META_COUNTRY   => sanitize_text_field($_POST['mc_supplier_country'] ?? 'United Kingdom'),
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    /* ---------------------------------------------------------
       ADMIN COLUMNS
    --------------------------------------------------------- */
    public function add_admin_columns($columns) {

        $new = [];

        $new['cb']   = $columns['cb'];
        $new['title'] = 'Supplier';

        $new['email']     = 'Email';
        $new['phone']     = 'Phone';
        $new['code']      = 'Code';
        $new['status']    = 'Status';
        $new['manager']   = 'Account Manager';

        $new['addr1']     = 'Address 1';
        $new['addr2']     = 'Address 2';
        $new['city']      = 'City';
        $new['county']    = 'County';
        $new['postcode']  = 'Postcode';
        $new['country']   = 'Country';

        return $new;
    }

    public function render_admin_columns($column, $post_id) {

        switch ($column) {

            case 'email':
                echo esc_html(get_post_meta($post_id, self::META_EMAIL, true));
                break;

            case 'phone':
                echo esc_html(get_post_meta($post_id, self::META_PHONE, true));
                break;

            case 'code':
                echo esc_html(get_post_meta($post_id, self::META_CODE, true));
                break;

            case 'status':
                echo esc_html(ucfirst(get_post_meta($post_id, self::META_STATUS, true)));
                break;

            case 'manager':
                echo esc_html(get_post_meta($post_id, self::META_MANAGER, true));
                break;

            case 'addr1':
                echo esc_html(get_post_meta($post_id, self::META_ADDR1, true));
                break;

            case 'addr2':
                echo esc_html(get_post_meta($post_id, self::META_ADDR2, true));
                break;

            case 'city':
                echo esc_html(get_post_meta($post_id, self::META_CITY, true));
                break;

            case 'county':
                echo esc_html(get_post_meta($post_id, self::META_COUNTY, true));
                break;

            case 'postcode':
                echo esc_html(get_post_meta($post_id, self::META_POSTCODE, true));
                break;

            case 'country':
                echo esc_html(get_post_meta($post_id, self::META_COUNTRY, true));
                break;

            // ⭐ NEW — Commission Rule Column Renderer
            case 'commission_rule':

                $rule_type   = get_post_meta($post_id, 'mc_commission_rule_type', true);
                $custom_rate = get_post_meta($post_id, 'mc_commission_custom_rate', true);

                if (!$rule_type) {
                    echo '—';
                    break;
                }

                switch ($rule_type) {
                    case 'flat_5':
                        echo 'Flat 5%';
                        break;
                    case 'flat_3':
                        echo 'Flat 3%';
                        break;
                    case 'flat_25':
                        echo 'Flat 2.5%';
                        break;
                    case 'custom_flat':
                        echo 'Custom ' . (float)$custom_rate . '%';
                        break;
                    case 'default_tiers':
                    default:
                        echo 'Tiered 5% / 3% / 2.5%';
                        break;
                }
                break;
        }
    }

    /* ---------------------------------------------------------
       SORTABLE COLUMNS
    --------------------------------------------------------- */
    public function sortable_columns($columns) {

        $columns['code']     = 'code';
        $columns['city']     = 'city';
        $columns['postcode'] = 'postcode';
        $columns['status']   = 'status';

        // ⭐ NEW — Make commission rule sortable
        $columns['commission_rule'] = 'commission_rule';

        return $columns;
    }
}

new MediCompare_Supplier_CPT();