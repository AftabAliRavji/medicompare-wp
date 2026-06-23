<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Frontend {

    public function __construct() {
        add_shortcode('mc_pharmacy_dashboard', [$this, 'render_dashboard']);
        add_shortcode('mc_pharmacy_edit_details', [$this, 'render_edit_details']);
        add_shortcode('mc_pharmacy_search', [$this, 'render_search']);

        add_action('init', [$this, 'handle_edit_details_submit']);
    }

    private function get_current_pharmacy() {

        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();

        if (!in_array('pharmacy_user', $user->roles)) {
            return null;
        }

        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);
        if (!$pharmacy_id) return null;

        $pharmacy = get_post($pharmacy_id);
        if (!$pharmacy || $pharmacy->post_type !== 'mc_pharmacy') return null;

        return $pharmacy;
    }

    /* ---------------------------------------------------------
       DASHBOARD
    --------------------------------------------------------- */
    public function render_dashboard() {

        // Protect dashboard
        if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
            wp_redirect(site_url('/pharmacy/login/'));
            exit;
        }

        $pharmacy = $this->get_current_pharmacy();
        if (!$pharmacy) {
            return '<p>You must be logged in as a pharmacy user to view the dashboard.</p>';
        }

        $pharmacy_id = $pharmacy->ID;

        $gphc   = get_post_meta($pharmacy_id, '_mc_gphc_number', true);
        $email  = get_post_meta($pharmacy_id, '_mc_email', true);
        $city   = get_post_meta($pharmacy_id, '_mc_city', true);
        $status = get_post_meta($pharmacy_id, '_mc_status', true);

        $trial_start = get_post_meta($pharmacy_id, '_mc_trial_start', true);
        $trial_end   = get_post_meta($pharmacy_id, '_mc_trial_end', true);

        $trial_start_readable = $trial_start ? date('d M Y', $trial_start) : 'N/A';
        $trial_end_readable   = $trial_end ? date('d M Y', $trial_end) : 'N/A';

        $current_user = wp_get_current_user();

        ob_start();
        ?>

        <div class="mc-dashboard">

            <!-- Header Bar -->
            <div class="mc-dashboard-header">
                <span>Welcome, <?php echo esc_html($current_user->user_email); ?></span>
                <a class="mc-logout-btn" href="<?php echo site_url('/pharmacy/login/?mc_logout=1'); ?>">Logout</a>
            </div>

            <h1>Pharmacy Dashboard</h1>

            <section class="mc-card">
                <h2>Pharmacy Summary</h2>
                <p><strong>Name:</strong> <?php echo esc_html($pharmacy->post_title); ?></p>
                <p><strong>GPhC:</strong> <?php echo esc_html($gphc); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($email); ?></p>
                <p><strong>City:</strong> <?php echo esc_html($city); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html(ucfirst($status)); ?></p>
                <p><strong>Trial:</strong> <?php echo esc_html($trial_start_readable); ?> → <?php echo esc_html($trial_end_readable); ?></p>
            </section>

            <section class="mc-card">
                <h2>Quick Actions</h2>
                <ul>
                    <li><a href="<?php echo esc_url(site_url('/pharmacy/edit-details/')); ?>">Edit pharmacy details</a></li>
                    <li><a href="<?php echo esc_url(site_url('/pharmacy/search/')); ?>">Search products & compare suppliers</a></li>
                    <li><span class="mc-muted">View orders (coming soon)</span></li>
                </ul>
            </section>

            <section class="mc-card">
                <h2>Support</h2>
                <p>Need help? Contact MediCompare support at <a href="mailto:support@medicompare.local">support@medicompare.local</a></p>
            </section>

        </div>

        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       EDIT DETAILS
    --------------------------------------------------------- */
    public function render_edit_details() {

        if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
            wp_redirect(site_url('/pharmacy/login/'));
            exit;
        }

        $pharmacy = $this->get_current_pharmacy();
        if (!$pharmacy) {
            return '<p>You must be logged in as a pharmacy user to edit details.</p>';
        }

        $pharmacy_id = $pharmacy->ID;

        $address_1 = get_post_meta($pharmacy_id, '_mc_address_1', true);
        $address_2 = get_post_meta($pharmacy_id, '_mc_address_2', true);
        $city      = get_post_meta($pharmacy_id, '_mc_city', true);
        $postcode  = get_post_meta($pharmacy_id, '_mc_postcode', true);
        $phone     = get_post_meta($pharmacy_id, '_mc_phone', true);
        $contact   = get_post_meta($pharmacy_id, '_mc_contact_name', true);

        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';

        $current_user = wp_get_current_user();

        ob_start();
        ?>

        <div class="mc-dashboard-header">
            <span>Welcome, <?php echo esc_html($current_user->user_email); ?></span>
            <a class="mc-logout-btn" href="<?php echo site_url('/pharmacy/login/?mc_logout=1'); ?>">Logout</a>
        </div>

        <div class="mc-edit-details">
            <h1>Edit Pharmacy Details</h1>

            <?php if ($updated): ?>
                <div class="mc-success">Details updated successfully.</div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('mc_edit_pharmacy_details', 'mc_edit_pharmacy_details_nonce'); ?>

                <p>
                    <label>Address Line 1</label><br>
                    <input type="text" name="mc_address_1" value="<?php echo esc_attr($address_1); ?>" required>
                </p>

                <p>
                    <label>Address Line 2</label><br>
                    <input type="text" name="mc_address_2" value="<?php echo esc_attr($address_2); ?>">
                </p>

                <p>
                    <label>City</label><br>
                    <input type="text" name="mc_city" value="<?php echo esc_attr($city); ?>" required>
                </p>

                <p>
                    <label>Postcode</label><br>
                    <input type="text" name="mc_postcode" value="<?php echo esc_attr($postcode); ?>" required>
                </p>

                <p>
                    <label>Phone</label><br>
                    <input type="text" name="mc_phone" value="<?php echo esc_attr($phone); ?>">
                </p>

                <p>
                    <label>Contact Name</label><br>
                    <input type="text" name="mc_contact_name" value="<?php echo esc_attr($contact); ?>">
                </p>

                <p>
                    <button type="submit" name="mc_edit_details_submit">Save changes</button>
                    <a href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>">Back to dashboard</a>
                </p>

            </form>
        </div>

        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       EDIT DETAILS HANDLER
    --------------------------------------------------------- */
    public function handle_edit_details_submit() {

        if (!isset($_POST['mc_edit_details_submit'])) {
            return;
        }

        if (
            !isset($_POST['mc_edit_pharmacy_details_nonce']) ||
            !wp_verify_nonce($_POST['mc_edit_pharmacy_details_nonce'], 'mc_edit_pharmacy_details')
        ) {
            return;
        }

        if (!is_user_logged_in()) return;

        $user = wp_get_current_user();
        if (!in_array('pharmacy_user', $user->roles)) return;

        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);
        if (!$pharmacy_id) return;

        $fields = [
            '_mc_address_1'    => sanitize_text_field($_POST['mc_address_1'] ?? ''),
            '_mc_address_2'    => sanitize_text_field($_POST['mc_address_2'] ?? ''),
            '_mc_city'         => sanitize_text_field($_POST['mc_city'] ?? ''),
            '_mc_postcode'     => sanitize_text_field($_POST['mc_postcode'] ?? ''),
            '_mc_phone'        => sanitize_text_field($_POST['mc_phone'] ?? ''),
            '_mc_contact_name' => sanitize_text_field($_POST['mc_contact_name'] ?? ''),
        ];

        foreach ($fields as $key => $value) {
            update_post_meta($pharmacy_id, $key, $value);
        }

        wp_redirect(add_query_arg('updated', '1', site_url('/pharmacy/edit-details/')));
        exit;
    }

    
 /* ---------------------------------------------------------
   SEARCH / COMPARISON
--------------------------------------------------------- */
/* ---------------------------------------------------------
   SEARCH / COMPARISON
--------------------------------------------------------- */
public function render_search() {

    if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

    $pharmacy = $this->get_current_pharmacy();
    if (!$pharmacy) {
        return '<p>You must be logged in as a pharmacy user to search products.</p>';
    }

    $current_user = wp_get_current_user();

    // Enqueue JS
    wp_enqueue_script(
        'mc-pharmacy-comparison',
        plugin_dir_url(__DIR__) . '../assets/js/pharmacy-comparison.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('mc-pharmacy-comparison', 'mcComparison', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('mc_comparison_nonce'),
    ]);

    ob_start();
    ?>

    <!-- FORCE FULL-WIDTH WRAPPER TO BREAK OUT OF BLOCK THEME CONSTRAINTS -->
    <div class="wp-block-group alignfull" style="padding:0;margin:0;">

        <!-- SHARED PAGE CONTAINER -->
        <div class="mc-page-container">

            <!-- TOP BAR -->
            <div class="mc-topbar-inner">

                <a href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>" 
                class="mc-topbar-btn mc-back-btn">
                    ← Back to Dashboard
                </a>

                <div class="mc-topbar-right">
                    <span class="mc-topbar-badge mc-welcome-badge">
                        Welcome, <?php echo esc_html($current_user->user_email); ?>
                    </span>

                    <a href="<?php echo wp_logout_url(site_url('/pharmacy/login/')); ?>" 
                    class="mc-topbar-btn mc-logout-btn">
                        Logout
                    </a>
                </div>

            </div>

            <!-- SEARCH + RIGHT PANEL -->
            <div class="mc-search-layout">

                <!-- LEFT SIDE -->
                <div class="mc-search-left">

                    <h2 class="mc-section-title">Search Products & Compare Suppliers</h2>

                    <div class="mc-search-bar">
                        <label for="mc-search-input">Product name or code</label><br>
                        <input type="text" id="mc-search-input" placeholder="Start typing product name or code...">
                    </div>

                    <div id="mc-search-results" class="mc-search-results"></div>

                    <div id="mc-selected-item" class="mc-selected-item"></div>

                </div>

                <!-- RIGHT SIDE -->
                <div class="mc-search-right">

                    <div class="mc-order-tabs">
                        <button type="button" class="mc-order-tab mc-order-tab-active" data-tab="pending">
                            Pending Order
                        </button>
                        <button type="button" class="mc-order-tab" data-tab="transferred">
                            Transferred Orders
                        </button>
                    </div>

                    <div id="mc-pending-order" class="mc-order-panel mc-order-panel-active"></div>

                    <div id="mc-transferred-orders" class="mc-order-panel"></div>

                    <div class="mc-order-actions">
                        <button type="button" id="mc-transfer-order-btn" class="mc-transfer-btn">
                            Transfer Pending Order
                        </button>
                    </div>

                </div>

            </div><!-- end .mc-search-layout -->

        </div><!-- end .mc-page-container -->

    </div><!-- end alignfull wrapper -->

    <?php
    return ob_get_clean();
 }

}

new MediCompare_Pharmacy_Frontend();
