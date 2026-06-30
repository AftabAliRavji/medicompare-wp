<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Frontend {

    public function __construct() {
        add_shortcode('mc_pharmacy_dashboard', [$this, 'render_dashboard']);
        add_shortcode('mc_pharmacy_edit_details', [$this, 'render_edit_details']);
        add_shortcode('mc_pharmacy_search', [$this, 'render_search']);
        add_shortcode('mc_pharmacy_orders', [$this, 'render_orders_page']);

        add_action('init', [$this, 'handle_edit_details_submit']);
        add_action('init', [$this, 'handle_support_form']);
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

    if (is_admin()) {
        return '<div class="mc-admin-preview">Pharmacy Dashboard Preview</div>';
    }

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

    // Meta fields
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

    /* ⭐ ADD HEADER + ASSET PATH HERE */
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
    /* ⭐ END HEADER */
    ?>

    <div class="mc-dashboard">

        <!-- Header Bar -->
        <div class="mc-dashboard-header">
            <span>Welcome, <?php echo esc_html($current_user->user_email); ?></span>
            <a class="mc-logout-btn" href="<?php echo site_url('/pharmacy/login/?mc_logout=1'); ?>">Logout</a>
        </div>

        <!-- DASHBOARD GRID -->
        <div class="mc-dashboard-grid">

            <!-- PHARMACY DETAILS -->
            <section class="mc-card-pro"
                     onclick="window.location='<?php echo esc_url(site_url('/pharmacy/edit-details/')); ?>';">
                <div class="mc-card-icon">🏥</div>
                <h2>Pharmacy Details</h2>
                <p>View your pharmacy information and manage your account password.</p>
            </section>

            <!-- SEARCH PRODUCTS -->
            <section class="mc-card-pro mc-card-blue"
                     onclick="window.location='<?php echo esc_url(site_url('/pharmacy/search/')); ?>';">
                <div class="mc-card-icon">🔍</div>
                <h2>Search Products</h2>
                <p>Search products and compare suppliers.</p>
            </section>

            <!-- SUBSCRIPTION COUNTDOWN -->
            <section class="mc-card-pro mc-card-green">
                <div class="mc-card-icon">⏳</div>
                <h2>Subscription</h2>
                <p>
                    <?php
                        $today = time();
                        $days_left = max(0, floor(($trial_end - $today) / 86400));
                        echo $days_left . " days remaining";
                    ?>
                </p>
            </section>

            <!-- TOTAL TRANSFERRED ORDERS -->
            <section class="mc-card-pro mc-card-orange"
                     onclick="window.location='<?php echo esc_url(site_url('/pharmacy/orders/')); ?>';">
                <div class="mc-card-icon">📦</div>
                <h2>Transferred Orders</h2>
                <p>
                    <?php
                        global $wpdb;
                        $orders_table = $wpdb->prefix . 'medi_orders';
                        $count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) 
                             FROM {$orders_table}
                             WHERE pharmacy_id = %d",
                            $pharmacy_id
                        ));
                        echo $count . ' total orders';
                    ?>
                </p>
            </section>

            <!-- SUPPORT CARD -->
            <section class="mc-card-pro mc-card-purple" onclick="mcOpenSupportModal();">
                <div class="mc-card-icon">💬</div>
                <h2>Support</h2>
                <p>Contact MediCompare support for help.</p>
            </section>

        </div><!-- end .mc-dashboard-grid -->

        <!-- SUPPORT MODAL -->
        <div id="mc-support-modal" class="mc-modal">
            <div class="mc-modal-content">
                <span class="mc-modal-close" onclick="mcCloseSupportModal();">&times;</span>

                <h2>Contact Support</h2>

                <form method="post">
                    <?php wp_nonce_field('mc_support_form', 'mc_support_form_nonce'); ?>

                    <p>
                        <label>Your Message</label><br>
                        <textarea name="mc_support_message" required></textarea>
                    </p>

                    <p>
                        <button type="submit" name="mc_support_submit">Send Message</button>
                    </p>
                </form>
            </div>
        </div>

    </div>

    <!-- SUCCESS TOAST -->
    <div id="mc-toast" class="mc-toast">Message sent successfully</div>

    <script>
        /* ------------------------------
           OPEN / CLOSE SUPPORT MODAL
        ------------------------------ */
        function mcOpenSupportModal() {
            document.getElementById('mc-support-modal').style.display = 'flex';
        }

        function mcCloseSupportModal() {
            document.getElementById('mc-support-modal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('mc-support-modal');
            if (event.target === modal) {
                mcCloseSupportModal();
            }
        }

        /* ------------------------------
           TOAST NOTIFICATION
        ------------------------------ */
        function mcShowToast() {
            const toast = document.getElementById('mc-toast');
            toast.classList.add('mc-toast-show');

            // 3 seconds visible + 0.8s fade-out
            setTimeout(() => {
                toast.classList.remove('mc-toast-show');
            }, 
            3800
            );
        }

        /* ------------------------------
           INTERCEPT SUPPORT FORM SUBMIT
           (close modal + show toast)
        ------------------------------ */
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('#mc-support-modal form');

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault(); // STOP page reload

                    mcCloseSupportModal();
                    mcShowToast();

                    // Now manually submit via AJAX
                    const formData = new FormData(form);

                    fetch("", {
                        method: "POST",
                        body: formData
                    }).then(() => {
                        console.log("Support message sent via AJAX");
                    });
                });
            }
        });
    </script>

    <?php
    return ob_get_clean();
 }


   /* ---------------------------------------------------------
       EDIT DETAILS
--------------------------------------------------------- */
public function render_edit_details() {

    if (is_admin()) {
        return '<div class="mc-admin-preview">Pharmacy Edit Details Preview</div>';
    }

    if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

    $pharmacy = $this->get_current_pharmacy();
    if (!$pharmacy) {
        return '<p>You must be logged in as a pharmacy user to edit details.</p>';
    }

    $pharmacy_id = $pharmacy->ID;

    // Pharmacy meta
    $gphc        = get_post_meta($pharmacy_id, '_mc_gphc_number', true);
    $email       = get_post_meta($pharmacy_id, '_mc_email', true);
    $city        = get_post_meta($pharmacy_id, '_mc_city', true);
    $status      = get_post_meta($pharmacy_id, '_mc_status', true);
    $trial_start = get_post_meta($pharmacy_id, '_mc_trial_start', true);
    $trial_end   = get_post_meta($pharmacy_id, '_mc_trial_end', true);

    $trial_start_readable = $trial_start ? date('d M Y', $trial_start) : 'N/A';
    $trial_end_readable   = $trial_end ? date('d M Y', $trial_end) : 'N/A';

    // Address/contact
    $address_1 = get_post_meta($pharmacy_id, '_mc_address_1', true);
    $address_2 = get_post_meta($pharmacy_id, '_mc_address_2', true);
    $postcode  = get_post_meta($pharmacy_id, '_mc_postcode', true);
    $phone     = get_post_meta($pharmacy_id, '_mc_phone', true);
    $contact   = get_post_meta($pharmacy_id, '_mc_contact_name', true);

    $password_updated = isset($_GET['password_updated']) && $_GET['password_updated'] === '1';
    $password_error   = isset($_GET['password_error']) && $_GET['password_error'] === '1';

    $current_user = wp_get_current_user();

    ob_start();

    /* ⭐ ADD HEADER + ASSET PATH HERE */
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
    /* ⭐ END HEADER */
    ?>

    <div class="mc-dashboard-header">
        <span>Welcome, <?php echo esc_html($current_user->user_email); ?></span>
        <a class="mc-logout-btn" href="<?php echo site_url('/pharmacy/login/?mc_logout=1'); ?>">Logout</a>
    </div>

    <h1>Pharmacy Details</h1>

    <?php if ($password_updated): ?>
        <div class="mc-success">Password updated and email sent.</div>
    <?php endif; ?>

    <?php if ($password_error): ?>
        <div class="mc-error">Old password is incorrect.</div>
    <?php endif; ?>

    <div class="mc-details-grid">

        <!-- LEFT COLUMN -->
        <div class="mc-card">
            <h2>Pharmacy Information</h2>

            <p><label>Name</label><br><input type="text" value="<?php echo esc_attr($pharmacy->post_title); ?>" disabled></p>
            <p><label>GPhC Number</label><br><input type="text" value="<?php echo esc_attr($gphc); ?>" disabled></p>
            <p><label>Email</label><br><input type="text" value="<?php echo esc_attr($email); ?>" disabled></p>
            <p><label>City</label><br><input type="text" value="<?php echo esc_attr($city); ?>" disabled></p>
            <p><label>Status</label><br><input type="text" value="<?php echo esc_attr(ucfirst($status)); ?>" disabled></p>
            <p><label>Trial Period</label><br>
                <input type="text" value="<?php echo esc_attr($trial_start_readable . ' → ' . $trial_end_readable); ?>" disabled>
            </p>

            <h3>Address</h3>
            <p><label>Address Line 1</label><br><input type="text" value="<?php echo esc_attr($address_1); ?>" disabled></p>
            <p><label>Address Line 2</label><br><input type="text" value="<?php echo esc_attr($address_2); ?>" disabled></p>
            <p><label>Postcode</label><br><input type="text" value="<?php echo esc_attr($postcode); ?>" disabled></p>
            <p><label>Phone</label><br><input type="text" value="<?php echo esc_attr($phone); ?>" disabled></p>
            <p><label>Contact Name</label><br><input type="text" value="<?php echo esc_attr($contact); ?>" disabled></p>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="mc-card">
            <h2>Reset Password</h2>

            <form method="post">
                <?php wp_nonce_field('mc_reset_password', 'mc_reset_password_nonce'); ?>

                <p>
                    <label>Old Password</label><br>
                    <input type="password" name="mc_old_password" required>
                </p>

                <p>
                    <label>New Password</label><br>
                    <input type="password" name="mc_new_password" required>
                </p>

                <p>
                    <button type="submit" name="mc_reset_password_submit">Save New Password</button>
                </p>

                <p><a href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>">Back to dashboard</a></p>
            </form>
        </div>

    </div>

    <?php
    return ob_get_clean();
 }

    /* ---------------------------------------------------------
       EDIT DETAILS HANDLER - will only allow password to be reset
    --------------------------------------------------------- */
   public function handle_edit_details_submit() {

    if (!isset($_POST['mc_reset_password_submit'])) {
        return;
    }

    if (
        !isset($_POST['mc_reset_password_nonce']) ||
        !wp_verify_nonce($_POST['mc_reset_password_nonce'], 'mc_reset_password')
    ) {
        return;
    }

    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();
    if (!in_array('pharmacy_user', $user->roles)) return;

    $old_password = $_POST['mc_old_password'] ?? '';
    $new_password = $_POST['mc_new_password'] ?? '';

    // Validate old password
    if (!wp_check_password($old_password, $user->user_pass, $user->ID)) {
        wp_redirect(add_query_arg('password_error', '1', site_url('/pharmacy/edit-details/')));
        exit;
    }

    // Update password
    wp_update_user([
        'ID'        => $user->ID,
        'user_pass' => $new_password,
    ]);

    // Send email
    wp_mail(
        $user->user_email,
        'Your MediCompare password has been changed',
        "Hello,\n\nYour password has been successfully updated.\n\nIf this wasn't you, contact support immediately.\n\nMediCompare"
    );

    wp_redirect(add_query_arg('password_updated', '1', site_url('/pharmacy/edit-details/')));
    exit;
 }

/* -----------------------------------------------
  SUPPORT HANDLER TO SEND TO FORM AS AN EMAIL
-------------------------------------------------*/
  public function handle_support_form() {

    if (!isset($_POST['mc_support_submit'])) {
        return;
    }

    if (
        !isset($_POST['mc_support_form_nonce']) ||
        !wp_verify_nonce($_POST['mc_support_form_nonce'], 'mc_support_form')
    ) {
        return;
    }

    if (!is_user_logged_in()) return;

    $user = wp_get_current_user();
    $message = sanitize_textarea_field($_POST['mc_support_message']);

    // Send email to support
    wp_mail(
        'support@medicompare.local',
        'Support Request from ' . $user->user_email,
        "User: " . $user->user_email . "\n\nMessage:\n" . $message
    );

    // Redirect with success flag
    wp_redirect(add_query_arg('support_sent', '1', site_url('/pharmacy/dashboard/')));
    exit;
}


    /* ---------------------------------------------------------
       SEARCH / COMPARISON
--------------------------------------------------------- */
public function render_search() {

    // ⭐ Prevent shortcode logic from running inside the editor
    if (is_admin()) {
        return '<div class="mc-admin-preview">Pharmacy Search Preview</div>';
    }

    if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

    $pharmacy = $this->get_current_pharmacy();
    if (!$pharmacy) {
        return '<p>You must be logged in as a pharmacy user to search products.</p>';
    }

    // NEW: determine if a pending order exists for this pharmacy
    global $wpdb;
    $orders_table = $wpdb->prefix . 'medi_pending_orders';

    $pending_order_exists = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$orders_table} WHERE pharmacy_id = %d",
        $pharmacy->ID
    ));

    // initial active tab on first render
    $active_tab = 'pending';

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

    /* ⭐ ADD HEADER + ASSET PATH HERE */
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
    /* ⭐ END HEADER */
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

                        <button 
                            type="button"
                            id="mc-cancel-order-btn"
                            class="mc-cancel-order-btn <?php echo ($pending_order_exists ? '' : 'mc-disabled'); ?>"
                            <?php echo ($pending_order_exists ? '' : 'disabled'); ?>
                        >
                            Cancel Pending Order
                        </button>

                        <button 
                            type="button" 
                            id="mc-transfer-order-btn" 
                            class="mc-transfer-btn <?php echo ($pending_order_exists ? '' : 'mc-transfer-btn-disabled mc-disabled'); ?>"
                            <?php echo ($pending_order_exists ? '' : 'disabled'); ?>
                        >
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


    /* ---------------------------------------------------------
     ORDERS PAGE (Transferred Orders Only)
  --------------------------------------------------------- */
 public function render_orders_page() {

    if (is_admin()) {
        return '<div class="mc-admin-preview">Pharmacy Orders Preview</div>';
    }

    // Protect page
    if (!is_user_logged_in() || !in_array('pharmacy_user', wp_get_current_user()->roles)) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

    $pharmacy = $this->get_current_pharmacy();
    if (!$pharmacy) {
        return '<p>You must be logged in as a pharmacy user to view orders.</p>';
    }

    global $wpdb;
    $pharmacy_id   = $pharmacy->ID;
    $current_user  = wp_get_current_user();

    // Tables
    $orders_table           = $wpdb->prefix . 'medi_orders';
    $supplier_summary_table = $wpdb->prefix . 'medi_order_supplier_summary';
    $suborders_table        = $wpdb->prefix . 'medi_order_suborders';
    $order_items_table      = $wpdb->prefix . 'medi_order_items';
    $posts_table            = $wpdb->posts;

    // Filters
    $start_date   = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
    $end_date     = isset($_GET['end'])   ? sanitize_text_field($_GET['end'])   : '';
    $order_number = isset($_GET['order_number']) ? sanitize_text_field($_GET['order_number']) : '';
    $view_all     = isset($_GET['view']) && $_GET['view'] === 'all';

    // Build WHERE clause
    $where = $wpdb->prepare("WHERE pharmacy_id = %d", $pharmacy_id);

    if ($start_date) {
        $where .= $wpdb->prepare(" AND DATE(created_at) >= %s", $start_date);
    }
    if ($end_date) {
        $where .= $wpdb->prepare(" AND DATE(created_at) <= %s", $end_date);
    }
    if ($order_number) {
        $where .= $wpdb->prepare(" AND order_number = %s", $order_number);
    }

    // Load orders if:
    // - Date range used
    // - OR order number used
    // - OR View All clicked
    $should_load_orders = ($start_date || $end_date || $order_number || $view_all);

    $orders = [];
    if ($should_load_orders) {
        $orders = $wpdb->get_results("
            SELECT id, order_number, total_amount, created_at, status
            FROM {$orders_table}
            {$where}
            ORDER BY created_at DESC
        ", ARRAY_A);
    }

    ob_start();

    /* ⭐ ADD HEADER + ASSET PATH HERE */
    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';
    include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
    /* ⭐ END HEADER */
    ?>

    <div class="wp-block-group alignfull" style="padding:0;margin:0;">
        <div class="mc-page-container">

            <!-- TOP BAR -->
            <div class="mc-topbar-inner">
                <a href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>" 
                   class="mc-topbar-btn mc-back-btn">← Back to Dashboard</a>

                <div class="mc-topbar-right">
                    <span class="mc-topbar-badge mc-welcome-badge">
                        Welcome, <?php echo esc_html($current_user->user_email); ?>
                    </span>

                    <a href="<?php echo wp_logout_url(site_url('/pharmacy/login/')); ?>" 
                       class="mc-topbar-btn mc-logout-btn">Logout</a>
                </div>
            </div>

            <h2 class="mc-section-title">Transferred Orders</h2>

            <!-- FILTER BAR -->
            <form method="get" class="mc-filter-bar">

                <!-- ROW 1: DATE RANGE FILTERS -->
                <div class="mc-filter-row">
                    <label>Start Date</label>
                    <input type="date" name="start" value="<?php echo esc_attr($start_date); ?>">

                    <label>End Date</label>
                    <input type="date" name="end" value="<?php echo esc_attr($end_date); ?>">

                    <button type="submit" name="filter_dates" value="1" class="mc-btn mc-btn-primary">
                        Filter
                    </button>
                </div>

                <!-- ROW 2: ORDER NUMBER FILTER -->
                <div class="mc-filter-row mc-filter-row--order">
                    <label>Order Number</label>
                    <input type="number" name="order_number" value="<?php echo esc_attr($order_number); ?>">

                    <button type="submit" name="filter_order" value="1" class="mc-btn mc-btn-primary">
                        Filter
                    </button>
                </div>

            </form>

            <!-- ROW 3: SECONDARY ACTIONS -->
            <div class="mc-filter-actions">
                <a href="<?php echo esc_url(add_query_arg(['view' => 'all'], site_url('/pharmacy/orders/'))); ?>"
                   class="mc-btn mc-btn-secondary mc-btn-inline">
                    View All
                </a>

                <a href="<?php echo esc_url(site_url('/pharmacy/orders/')); ?>"
                   class="mc-btn mc-btn-tertiary mc-btn-inline">
                    Clear Filters
                </a>
            </div>

            <div class="mc-transferred-orders-wrapper">

                <?php if (!$should_load_orders): ?>

                    <p style="margin-top:20px;">Please enter a date range OR enter an Order Number.<br>
                    Alternatively, click View All to see all orders.</p>

                <?php elseif (!$orders): ?>

                    <p>No transferred orders found.</p>

                <?php else: ?>

                    <?php foreach ($orders as $order): ?>

                        <div class="mc-transferred-order-card mc-order-collapsed">

                            <div class="mc-order-collapse-header" data-order-toggle>
                                <span>Order #<?php echo esc_html($order['order_number']); ?></span>

                                <span class="mc-order-status-badge mc-status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo esc_html(ucfirst(strtolower($order['status']))); ?>
                                </span>

                                <span class="mc-order-collapse-arrow">▼</span>
                            </div>

                            <div class="mc-order-collapse-content" style="display:none;">

                                <p><strong>Date:</strong>
                                    <?php echo esc_html(date('d M Y H:i', strtotime($order['created_at']))); ?>
                                </p>

                                <p><strong>Total:</strong>
                                    £<?php echo number_format((float)$order['total_amount'], 2); ?>
                                </p>

                                <?php
                                // Supplier summary
                                $suppliers = $wpdb->get_results($wpdb->prepare(
                                    "SELECT supplier_id, suborder_number, supplier_total_amount
                                     FROM {$supplier_summary_table}
                                     WHERE order_id = %d",
                                    $order['id']
                                ), ARRAY_A);
                                ?>

                                <?php if ($suppliers): ?>
                                    <div class="mc-transferred-suppliers">

                                        <?php foreach ($suppliers as $s): ?>

                                            <?php
                                            $supplier_name = $wpdb->get_var($wpdb->prepare(
                                                "SELECT post_title FROM {$posts_table} WHERE ID = %d",
                                                $s['supplier_id']
                                            ));

                                            $suborder = $wpdb->get_row($wpdb->prepare(
                                                "SELECT supplier_order_status, email_sent, email_sent_at
                                                 FROM {$suborders_table}
                                                 WHERE suborder_number = %s
                                                 LIMIT 1",
                                                $s['suborder_number']
                                            ), ARRAY_A);

                                            $suborder_status = $suborder ? strtolower($suborder['supplier_order_status']) : 'pending';
                                            $email_sent      = $suborder ? (int)$suborder['email_sent'] : 0;
                                            $email_sent_at   = $suborder && $suborder['email_sent_at']
                                                ? date('d M Y H:i', strtotime($suborder['email_sent_at']))
                                                : null;

                                            $items = $wpdb->get_results($wpdb->prepare(
                                                "SELECT oi.product_id, oi.quantity, oi.unit_price, oi.line_total,
                                                        p.post_title AS product_name
                                                 FROM {$order_items_table} oi
                                                 INNER JOIN {$posts_table} p ON p.ID = oi.product_id
                                                 WHERE oi.order_id = %d
                                                   AND oi.supplier_id = %d
                                                 ORDER BY p.post_title ASC",
                                                $order['id'],
                                                $s['supplier_id']
                                            ), ARRAY_A);
                                            ?>

                                            <div class="mc-suborder-block">

                                                <div class="mc-suborder-header">
                                                    <div>
                                                        <strong><?php echo esc_html($supplier_name ?: 'Supplier #' . $s['supplier_id']); ?></strong><br>
                                                        <span class="mc-suborder-ref">
                                                            Sub-order: <?php echo esc_html($s['suborder_number']); ?>
                                                        </span>
                                                    </div>

                                                    <div class="mc-suborder-status">
                                                        <span class="mc-suborder-status-badge mc-status-<?php echo esc_attr($suborder_status); ?>">
                                                            <?php echo esc_html(ucfirst($suborder_status)); ?>
                                                        </span>

                                                        <span class="mc-suborder-total">
                                                            Supplier Total: £<?php echo number_format((float)$s['supplier_total_amount'], 2); ?>
                                                        </span>

                                                        <span class="mc-email-indicator">
                                                            Email:
                                                            <?php if ($email_sent): ?>
                                                                <strong>Sent</strong>
                                                                <?php if ($email_sent_at): ?>
                                                                    (<?php echo esc_html($email_sent_at); ?>)
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <strong>Not Sent</strong>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <?php if ($items): ?>
                                                    <table class="mc-suborder-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Product</th>
                                                                <th>Qty</th>
                                                                <th>Unit Price</th>
                                                                <th>Supplier Line Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($items as $item): ?>
                                                                <tr>
                                                                    <td><?php echo esc_html($item['product_name']); ?></td>
                                                                    <td><?php echo (int)$item['quantity']; ?></td>
                                                                    <td>£<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                                                                    <td>£<?php echo number_format((float)$item['line_total'], 2); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php else: ?>
                                                    <p>No items found for this supplier.</p>
                                                <?php endif; ?>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>
                                <?php else: ?>
                                    <p>No supplier breakdown available.</p>
                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.mc-transferred-order-card');

            cards.forEach(card => {
                const header = card.querySelector('.mc-order-collapse-header');
                const content = card.querySelector('.mc-order-collapse-content');

                if (!header || !content) return;

                header.addEventListener('click', () => {
                    const isOpen = content.style.display === 'block';

                    document.querySelectorAll('.mc-order-collapse-content').forEach(c => c.style.display = 'none');
                    document.querySelectorAll('.mc-transferred-order-card').forEach(c => c.classList.remove('mc-order-expanded'));

                    if (!isOpen) {
                        content.style.display = 'block';
                        card.classList.add('mc-order-expanded');
                    }
                });
            });
        });
    </script>

    <?php
    return ob_get_clean();
 }


}

new MediCompare_Pharmacy_Frontend();
