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
        add_action('admin_head', [$this, 'mc_pharmacy_admin_column_styles']);

        //will trigger on load or edit to make sure the next billing date is checked for the pharmacy
        add_action('load-edit.php',[$this, 'mc_maybe_refresh_visible_pharmacies']);

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

    public function mc_pharmacy_admin_column_styles()
    {
        global $typenow;

        if ($typenow !== 'mc_pharmacy') {
            return;
        }

        ?>
        <style>

            .column-supplier_accounts {
                width: 280px;
            }

        </style>
        <?php
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
            'account_id'      => '_mc_account_id',
            'subscription_amount' => '_mc_subscription_amount',
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

        global $wpdb;

        $account_table = $wpdb->prefix . 'medi_pharmacy_supplier_accounts';

        $existing_supplier_accounts = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT supplier_id, account_id
                FROM {$account_table}
                WHERE pharmacy_id = %d
                ",
                $post->ID
            ),
            ARRAY_A
        );

        $supplier_accounts = [];

        foreach ($existing_supplier_accounts as $row) {
            $supplier_accounts[(int)$row['supplier_id']] = $row['account_id'];
        }

        // Load existing restrictions
        $restriction_raw = get_post_meta($post->ID, '_mc_supplier_restrictions', true);
        $use_all = ($restriction_raw === '' || $restriction_raw === 'ALL');
        $restricted_list = $use_all ? [] : json_decode($restriction_raw, true);

        wp_nonce_field('mc_save_pharmacy_meta', 'mc_pharmacy_meta_nonce');
        $subscription_status = get_post_meta(
                                $post->ID,
                                '_mc_subscription_status',
                                true
                            );

                            $restricted = get_post_meta(
                                $post->ID,
                                '_mc_account_restricted',
                                true
                            );

                            if ($restricted === 'yes') {

                                echo '<div style="
                                    background:#fff3f3;
                                    border-left:4px solid #c62828;
                                    padding:15px;
                                    margin:0 0 20px;
                                ">';

                                echo '<strong>⚠ Attention Required</strong><br>';

                                echo 'This pharmacy account is currently restricted.<br>';

                                echo 'Subscription Status: <strong>' .
                                    esc_html(
                                        ucwords(
                                            str_replace(
                                                "_",
                                                " ",
                                                $subscription_status
                                            )
                                        )
                                    ) .
                                    '</strong>';

                                echo '</div>';
                            }
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
                <th><label>AccountId</label></th> 
                <td> <input type="text" name="mc_account_id" value="<?php echo esc_attr($values['account_id']); ?>" class="regular-text" > <p class="description">Optional account identifier supplied by supplier.</p> 
                </td> 
            </tr>
            <tr>
                <th>
                    <label>Supplier Account IDs</label>
                </th>

                <td>

                    <p class="description">
                        Optional supplier-specific account references.
                    </p>

                    <div style="
                        max-height:300px;
                        overflow:auto;
                        border:1px solid #ddd;
                        padding:12px;
                        background:#fff;
                    ">

                        <?php foreach ($suppliers as $supplier): ?>
                            
                            <?php
                            $current_account_id =
                                $supplier_accounts[$supplier->ID] ?? '';
                                error_log( 'ACCOINT ID linked to SUPPLIER is ' . $current_account_id ); 
                            ?>

                            <div style="
                                display:flex;
                                align-items:center;
                                margin-bottom:8px;
                                gap:12px;
                            ">

                                <div style="
                                    width:220px;
                                    font-weight:600;
                                ">
                                    <?php echo esc_html($supplier->post_title); ?>
                                </div>

                                <input
                                    type="text"
                                    name="mc_supplier_account_ids[<?php echo (int)$supplier->ID; ?>]"
                                    value="<?php echo esc_attr($current_account_id); ?>"
                                    class="regular-text"
                                >

                            </div>

                        <?php endforeach; ?>

                    </div>

                </td>
            </tr>
            <tr> 
                <th> <label>Monthly Subscription Amount (£)</label> </th> 
                <td> <input type="number" step="0.01" min="0" name="mc_subscription_amount" value="<?php echo esc_attr( $values['subscription_amount'] ? $values['subscription_amount'] : '30.00' ); ?>" class="regular-text" > <p class="description"> Monthly subscription amount once trial ends. </p> 
                </td> 
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

        $old_status = get_post_meta( $post_id, '_mc_status', true );

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
            'mc_account_id'     => '_mc_account_id',
            'mc_subscription_amount' => '_mc_subscription_amount',
            'mc_status'         => '_mc_status',
        ];

        foreach ($fields as $form_key => $meta_key) {
            if (isset($_POST[$form_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_key]));
            }
        }

        if ( !get_post_meta( $post_id, '_mc_subscription_amount', true ) ) { 
            update_post_meta( $post_id, '_mc_subscription_amount', '30.00' ); 
        }

        $is_new_pharmacy = !get_post_meta( $post_id, '_mc_admin_claim_sent', true ); 
        if ( $is_new_pharmacy && !empty($_POST['mc_email']) ) {
             update_post_meta( $post_id, '_mc_admin_claim_sent', 1 );
             error_log( 'MC ADMIN CREATED ACTION FIRING: ' . $post_id ); 
             do_action( 'mc_admin_pharmacy_created', $post_id ); 
        }


        $new_status = get_post_meta( $post_id, '_mc_status', true ); 
        /* |-------------------------------------------------------------------------- 
           | STATUS TRANSITIONS |-------------------------------------------------------------------------- |
            | pending_verification -> active | Start trial (if not already started) | Send activation email |
            | pending_verification -> suspended | Send suspension email |
            | active -> suspended | Send suspension email |
            | suspended -> active | Send activation email | Do NOT restart trial |
        ----------------------------------------------------------------------------- */
        if ( $old_status === 'pending_verification' && $new_status === 'active' ) {
             $subscription_status = get_post_meta( $post_id, '_mc_subscription_status', true ); 
             
             if (empty($subscription_status)) {
                 update_post_meta( $post_id, '_mc_subscription_status', 'trial' ); 
                 update_post_meta( $post_id, '_mc_trial_start', time() ); 
                 update_post_meta( $post_id, '_mc_trial_end', strtotime('+30 days') ); 
            } 
            $this->send_activation_email($post_id); 
        }

        if ( $old_status === 'pending_verification' && $new_status === 'suspended' ) {
             $this->send_suspension_email($post_id); 
        }

        if ( $old_status === 'active' && $new_status === 'suspended' ) {
             $this->send_suspension_email($post_id); 
        }
        
        if ( $old_status === 'suspended' && $new_status === 'active' ) {
             $this->send_activation_email($post_id);
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

        /* ---------------------------------------------------------
        SAVE SUPPLIER ACCOUNT IDS
        --------------------------------------------------------- */
        global $wpdb;

        $account_table =
            $wpdb->prefix .
            'medi_pharmacy_supplier_accounts';

        if (isset($_POST['mc_supplier_account_ids'])) {

            foreach ($_POST['mc_supplier_account_ids'] as $supplier_id => $account_id) {

                $supplier_id = (int) $supplier_id;

                $account_id = trim(
                    sanitize_text_field($account_id)
                );

                /*
                Blank account id
                = remove mapping
                */
                if (empty($account_id)) {

                    $wpdb->delete(
                        $account_table,
                        [
                            'pharmacy_id' => $post_id,
                            'supplier_id' => $supplier_id,
                        ]
                    );

                    continue;
                }

                /*
                Update existing
                */
                $existing = $wpdb->get_var(
                    $wpdb->prepare(
                        "
                        SELECT id
                        FROM {$account_table}
                        WHERE pharmacy_id = %d
                        AND supplier_id = %d
                        LIMIT 1
                        ",
                        $post_id,
                        $supplier_id
                    )
                );

                if ($existing) {

                    $wpdb->update(
                        $account_table,
                        [
                            'account_id' => $account_id,
                        ],
                        [
                            'id' => $existing,
                        ]
                    );

                } else {

                    $wpdb->insert(
                        $account_table,
                        [
                            'pharmacy_id' => $post_id,
                            'supplier_id' => $supplier_id,
                            'account_id' => $account_id,
                        ]
                    );
                }
            }
        }
    }

    /* ------------------------------------
    Send the activation email for pharmacy once verification is copmpleted by admin
    --------------------------------------*/
    private function send_activation_email($post_id) {
         $email = get_post_meta( $post_id, '_mc_email', true ); 
         
         if (!$email) {
             return; 
        } 
        $contact_name = get_post_meta( $post_id, '_mc_contact_name', true );
        $home = home_url();
        $greeting = $contact_name ? 'Hello ' . $contact_name . ',' : 'Hello,';
        $logo_url = plugins_url( 'assets/img/logo.png', dirname(dirname(__FILE__)) . '/medicompare.php' );
        error_log($logo_url);

        $subject = 'Your Source Med Pharma Account Is Now Active'; 
        $message = ' <!DOCTYPE html> 
            <html> 
            <body style="font-family:Arial,sans-serif;background:#f5f7f8;margin:0;padding:0;">
             <table width="100%" cellpadding="0" cellspacing="0" border="0"> 
             <tr>
              <td align="center" style="padding:40px 20px;">
               <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:700px;background:#ffffff;border:1px solid #dce8ea;">
                <tr>
                 <td align="center" style="padding:30px 30px 24px;border-bottom:1px solid #dce8ea;"> 
                    <img src="' . esc_url($logo_url) . '" alt="Source Med Pharma" width="360" style="display:block;width:100%;max-width:360px;height:auto;border:0;" >
                 </td>
                </tr>
                <tr>
                 <td style="padding:30px;">
                  <p>' . esc_html($greeting) . '</p>
                  <p> Your pharmacy account has now been verified and activated. </p>
                  <p> Your free trial has started and your account is now ready to use. </p>
                  <p> You can now access the platform using: </p>
                  <p> <a href="' . esc_url($home) . '"> Access Source Med Pharma Platform </a> </p>
                  <p> Regards,<br> Source Med Pharma Team </p>
                 </td>
                </tr>
               </table>
              </td>
             </tr>
            </table>
            </body>
            </html> '; 
        
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ]; 
        wp_mail( $email, $subject, $message, $headers ); 
    }

    private function send_suspension_email($post_id) {
         $email = get_post_meta($post_id, '_mc_email', true); 
         
         if (!$email) { return; } 
            $contact_name = get_post_meta($post_id, '_mc_contact_name', true); 
            $greeting = $contact_name ? 'Hello ' . $contact_name . ',' : 'Hello,'; 
            $logo_url = plugins_url( 'assets/img/logo.png', dirname(dirname(__FILE__)) . '/medicompare.php' );
            error_log($logo_url);

            $subject = 'Your Source Med Pharma Account Has Been Suspended'; 
            
            $message = ' <!DOCTYPE html> 
            <html> 
            <body style="font-family:Arial,sans-serif;background:#f5f7f8;margin:0;padding:0;">
             <table width="100%" cellpadding="0" cellspacing="0" border="0"> 
             <tr>
              <td align="center" style="padding:40px 20px;">
               <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:700px;background:#ffffff;border:1px solid #dce8ea;">
                <tr>
                 <td align="center" style="padding:30px 30px 24px;border-bottom:1px solid #dce8ea;"> 
                    <img src="' . esc_url($logo_url) . '" alt="Source Med Pharma" width="360" style="display:block;width:100%;max-width:360px;height:auto;border:0;" >
                 </td>
                </tr>
                <tr>
                 <td style="padding:30px;">
                  <p>' . esc_html($greeting) . '</p>
                  <p> Your Source Med Pharma account has been suspended. </p>
                  <p> Please contact support for further information regarding this decision. </p>
                  <p> <a href="mailto:support@sourcemedpharma.com" style="color:#006d7c;text-decoration:underline;">
                                    support@sourcemedpharma.com</a></p>
                  <p> Regards,<br> Source Med Pharma Team </p>
                 </td>
                </tr>
               </table>
              </td>
             </tr>
            </table>
            </body>
            </html> '; 

            $headers = [ 'Content-Type: text/html; charset=UTF-8' ]; 
            wp_mail( $email, $subject, $message, $headers );
    }

    public function mc_maybe_refresh_visible_pharmacies()
    {
        global $typenow;

        if ($typenow !== 'mc_pharmacy') {
            return;
        }

        error_log(
            'GC PHARMACY LIST REFRESH TRIGGERED'
        );

        $pharmacy_ids = get_posts([
            'post_type'      => 'mc_pharmacy',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($pharmacy_ids as $pharmacy_id) {

            $this->mc_refresh_gocardless_next_billing_date(
                $pharmacy_id
            );

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
        $new['account_id']    = 'AccountId';
        $new['supplier_accounts'] = 'Supplier Accounts';
        $new['status']        = 'Status';
        $new['subscription_amount'] = 'Monthly Sub fee';

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
            case 'account_id': 
                echo esc_html( get_post_meta( $post_id, '_mc_account_id', true ) ); 
                break;
            case 'supplier_accounts':

                global $wpdb;

                $table = $wpdb->prefix . 'medi_pharmacy_supplier_accounts';

                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "
                        SELECT supplier_id, account_id
                        FROM {$table}
                        WHERE pharmacy_id = %d
                        ORDER BY supplier_id
                        ",
                        $post_id
                    ),
                    ARRAY_A
                );

                if (empty($rows)) {

                    echo '<div style="
                        background:#ffebee;
                        border:1px solid #ef9a9a;
                        color:#c62828;
                        padding:8px;
                        border-radius:4px;
                        font-weight:600;
                    ">
                        Needs Adding Through Edit
                    </div>';

                    break;
                }

                echo '<div style="
                    background:#e8f5e9;
                    border:1px solid #81c784;
                    padding:8px;
                    border-radius:4px;
                ">';

                foreach ($rows as $row) {

                    $supplier_name = get_the_title(
                        (int) $row['supplier_id']
                    );

                    echo '<div style="margin-bottom:4px;">';

                    echo '<strong>' .
                        esc_html($supplier_name) .
                        ':</strong> ' .
                        esc_html($row['account_id']);

                    echo '</div>';
                }

                echo '</div>';

                break;
            case 'subscription_amount': 
                $amount = get_post_meta( $post_id, '_mc_subscription_amount', true ); 
                echo '£' . esc_html( $amount ? $amount : '30.00' ); 
                break;

            /* ---------------------------------------------------------
            ⭐ SUBSCRIPTION COLUMNS
            --------------------------------------------------------- */

            case 'sub_status':
            $status = get_post_meta(
                $post_id,
                '_mc_subscription_status',
                true
            );

            $restricted = get_post_meta(
                $post_id,
                '_mc_account_restricted',
                true
            );

            if ($restricted === 'yes') {

                echo '<span style="
                    color:#c62828;
                    font-weight:bold;
                ">⚠ Attention Required</span><br>';

                echo esc_html(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $status
                        )
                    )
                );

            } else {

                switch ($status) {

                    case 'active':
                        echo '<span style="
                            color:#2e7d32;
                            font-weight:bold;
                        ">✅ Active</span>';
                        break;

                    case 'trial':
                        echo '<span style="
                            color:#ef6c00;
                            font-weight:bold;
                        ">🟠 Trial</span>';
                        break;

                    default:
                        echo esc_html(
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $status ?: 'Unknown'
                                )
                            )
                        );
                }
            }
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

        /* ------------------------------
        GETTING NEXT BILLING DATE FROM GET SUBSCRIPTION API CALL

        RULE:
        Only call GoCardless when:

        1. Stored billing date has passed
        2. Sync cooldown has expired

        This avoids jumping to the next billing
        cycle too early whilst still ensuring
        the date advances after the billing date
        has passed.
        -------------------------------- */
        private function mc_refresh_gocardless_next_billing_date(
            $pharmacy_id
        ) {

            $subscription_id = get_post_meta(
                $pharmacy_id,
                '_mc_gocardless_subscription_id',
                true
            );

            if (!$subscription_id) {

                error_log(
                    'GC BILLING CHECK SKIPPED | Pharmacy: ' .
                    $pharmacy_id .
                    ' | No Subscription ID'
                );

                return;
            }

            /*
            * Current stored billing date
            */
            $current_billing_date = (int) get_post_meta(
                $pharmacy_id,
                '_mc_next_billing_date',
                true
            );

            /*
            * Today's date at midnight
            */
            $today_timestamp = strtotime(
                current_time('Y-m-d')
            );

            error_log(
                'GC BILLING CHECK | Pharmacy: ' .
                $pharmacy_id .
                ' | Stored Date: ' .
                (
                    $current_billing_date
                        ? date(
                            'Y-m-d',
                            $current_billing_date
                        )
                        : 'NONE'
                )
            );

            /*
            * If current billing date is still today
            * or in the future, keep it.
            */
            if (
                $current_billing_date &&
                $current_billing_date >= $today_timestamp
            ) {

                error_log(
                    'GC BILLING REFRESH SKIPPED | Pharmacy: ' .
                    $pharmacy_id .
                    ' | Stored Date Still Valid'
                );

                return;
            }

            /*
            * Safety throttle
            */
            $last_refresh = (int) get_post_meta(
                $pharmacy_id,
                '_mc_subscription_last_refresh',
                true
            );

            if (
                $last_refresh &&
                (time() - $last_refresh) < 3600
            ) {

                error_log(
                    'GC BILLING REFRESH SKIPPED | Pharmacy: ' .
                    $pharmacy_id .
                    ' | Cooldown Active'
                );

                return;
            }

            error_log(
                'GC BILLING REFRESH STARTING | Pharmacy: ' .
                $pharmacy_id
            );

            try {

                require_once ABSPATH . 'vendor/autoload.php';

                $client = new \GoCardlessPro\Client([
                    'access_token' => mc_get_gocardless_access_token(),
                    'environment'  => mc_get_gocardless_environment(),
                ]);

                $subscription =
                    $client
                        ->subscriptions()
                        ->get(
                            $subscription_id
                        );

                if (
                    !empty($subscription->upcoming_payments)
                    &&
                    !empty(
                        $subscription->upcoming_payments[0]->charge_date
                    )
                ) {

                    $next_billing_date = strtotime(
                        $subscription
                            ->upcoming_payments[0]
                            ->charge_date
                    );

                    error_log(
                        'GC NEXT BILLING DATE RAW | Pharmacy: ' .
                        $pharmacy_id .
                        ' | Date: ' .
                        $subscription
                            ->upcoming_payments[0]
                            ->charge_date
                    );

                    update_post_meta(
                        $pharmacy_id,
                        '_mc_next_billing_date',
                        $next_billing_date
                    );

                    update_post_meta(
                        $pharmacy_id,
                        '_mc_subscription_last_refresh',
                        current_time('timestamp')
                    );

                    error_log(
                        'GC BILLING REFRESH SUCCESS | Pharmacy: ' .
                        $pharmacy_id .
                        ' | New Date: ' .
                        date(
                            'Y-m-d',
                            $next_billing_date
                        )
                    );
                }

            } catch (\Exception $e) {

                error_log(
                    'GC BILLING REFRESH ERROR | Pharmacy: ' .
                    $pharmacy_id .
                    ' | ' .
                    $e->getMessage()
                );

            }
        }  
}

new MediCompare_Pharmacy_CPT();
