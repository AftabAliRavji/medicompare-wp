<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_CPT {

    public function __construct() {
        add_action('init', [$this, 'register'], 20);

        // Meta boxes
        add_action('add_meta_boxes', [$this, 'add_pharmacy_meta_boxes']);
        add_action('save_post_mc_pharmacy', [$this, 'save_pharmacy_meta'], 20, 2);
        add_action('restrict_manage_posts', [$this, 'add_pharmacy_filters']);
        add_action('pre_get_posts', [$this, 'filter_pharmacy_query']);

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

    public function add_pharmacy_filters() {
        global $typenow;

        if ($typenow !== 'mc_pharmacy') {
            return;
        }

        $current = isset($_GET['supplier_access_filter']) ? $_GET['supplier_access_filter'] : '';

        ?>
        <select name="supplier_access_filter" id="supplier_access_filter">
            <option value="">All Supplier Access</option>
            <option value="restricted" <?php selected($current, 'restricted'); ?>>Restricted Only</option>
            <option value="unrestricted" <?php selected($current, 'unrestricted'); ?>>Unrestricted Only</option>
        </select>
        <?php
    }

    public function filter_pharmacy_query($query) {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }

        if ($query->get('post_type') !== 'mc_pharmacy') {
            return;
        }

        if (!isset($_GET['supplier_access_filter']) || $_GET['supplier_access_filter'] === '') {
            return;
        }

        $filter = $_GET['supplier_access_filter'];

        if ($filter === 'restricted') {
            $query->set('meta_query', [
                [
                    'key'     => '_mc_supplier_restrictions',
                    'value'   => 'ALL',
                    'compare' => '!='
                ]
            ]);
        }

        if ($filter === 'unrestricted') {
            $query->set('meta_query', [
                [
                    'key'     => '_mc_supplier_restrictions',
                    'value'   => 'ALL',
                    'compare' => '='
                ]
            ]);
        }
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

        // Load active suppliers
        $suppliers = get_posts([
            'post_type'      => 'mc_supplier',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => 'mc_supplier_status',
            'meta_value'     => 'active',
        ]);

        // Load existing restrictions
        $restriction_raw = get_post_meta($post->ID, '_mc_supplier_restrictions', true);
        $use_all = ($restriction_raw === '' || $restriction_raw === 'ALL');
        $restricted_list = $use_all ? [] : json_decode($restriction_raw, true);

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
                        <option value="pending_verification" <?php selected($values['status'], 'pending_verification'); ?>>
                            Pending Verification
                        </option>
                        <option value="active" <?php selected($values['status'], 'active'); ?>>
                            Active
                        </option>
                        <option value="suspended" <?php selected($values['status'], 'suspended'); ?>>
                            Suspended
                        </option>
                    </select>
                </td>
            </tr>


            <!-- ⭐ NEW SUPPLIER ACCESS CONTROL -->
            <tr>
                <th><label>Supplier Access</label></th>
                <td>

                    <label>
                        <input type="checkbox" name="mc_supplier_use_all" value="1" <?php checked($use_all); ?>>
                        Use ALL suppliers (default)
                    </label>

                    <p style="margin-top:10px; font-weight:bold;">If unchecked, select allowed suppliers:</p>

                    <div style="max-height:200px; overflow:auto; border:1px solid #ccc; padding:10px;">

                        <?php foreach ($suppliers as $s): ?>
                            <?php $checked = (!$use_all && in_array($s->ID, $restricted_list)); ?>
                            <label style="display:block; margin-bottom:5px;">
                                <input type="checkbox" name="mc_supplier_allowed[]" value="<?php echo $s->ID; ?>" <?php checked($checked); ?>>
                                <?php echo esc_html($s->post_title); ?> (ID: <?php echo $s->ID; ?>)
                            </label>
                        <?php endforeach; ?>

                    </div>

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
                update_post_meta($post_id, '_mc_status', 'pending_verification');
            }
        }

        /* ---------------------------------------------------------
        SAVE STANDARD FIELDS
        --------------------------------------------------------- */
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

        /* ---------------------------------------------------------
        ⭐ SAVE SUPPLIER RESTRICTIONS
        --------------------------------------------------------- */
        if (isset($_POST['mc_supplier_use_all'])) {

            // Use ALL suppliers
            update_post_meta($post_id, '_mc_supplier_restrictions', 'ALL');

        } else {

            // Restricted list
            $allowed = isset($_POST['mc_supplier_allowed'])
                ? array_map('intval', $_POST['mc_supplier_allowed'])
                : [];

            if (empty($allowed)) {
                // If none selected, default to ALL
                update_post_meta($post_id, '_mc_supplier_restrictions', 'ALL');
            } else {
                update_post_meta($post_id, '_mc_supplier_restrictions', json_encode($allowed));
            }
        }
    }


    /* ---------------------------------------------------------
       ADMIN COLUMNS (UPDATED)
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

        // ⭐ NEW SUBSCRIPTION COLUMNS
        $new['sub_status']      = 'Subscription';
        $new['trial_remaining'] = 'Trial Left';
        $new['next_billing']    = 'Next Billing';

        // ⭐ NEW SUPPLIER ACCESS COLUMN
        $new['supplier_access'] = 'Supplier Access';

        $new['date'] = $columns['date'];

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

            /* ---------------------------------------------------------
            ⭐ SUBSCRIPTION COLUMNS
            --------------------------------------------------------- */

            case 'sub_status':
                $status = get_post_meta($post_id, '_mc_subscription_status', true);
                echo esc_html(ucfirst($status ?: 'unknown'));
                break;

            case 'trial_remaining':
                $trial_end = (int) get_post_meta($post_id, '_mc_trial_end', true);
                if ($trial_end > time()) {
                    $days = floor(($trial_end - time()) / 86400);
                    echo esc_html($days . ' days');
                } else {
                    echo '—';
                }
                break;

            case 'next_billing':
                $next = (int) get_post_meta($post_id, '_mc_next_billing_date', true);
                echo $next ? esc_html(date('d M Y', $next)) : '—';
                break;

            /* ---------------------------------------------------------
            ⭐ NEW SUPPLIER ACCESS COLUMN
            --------------------------------------------------------- */

            case 'supplier_access':
                $raw = get_post_meta($post_id, '_mc_supplier_restrictions', true);

                if ($raw === 'ALL' || $raw === '') {
                    echo 'ALL';
                } else {
                    $arr = json_decode($raw, true);
                    echo 'Restricted (' . count($arr) . ')';
                }
                break;
        }
    }


    public function make_pharmacy_columns_sortable($columns) {
        $columns['pharmacy_code'] = 'pharmacy_code';
        $columns['city']          = 'city';
        $columns['postcode']      = 'postcode';
        $columns['status']        = 'status';

        // ⭐ NEW sortable subscription columns
        $columns['sub_status']    = 'sub_status';
        $columns['next_billing']  = 'next_billing';

        return $columns;
    }
}

new MediCompare_Pharmacy_CPT();
