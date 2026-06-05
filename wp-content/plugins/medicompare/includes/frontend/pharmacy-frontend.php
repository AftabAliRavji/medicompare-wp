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
       DASHBOARD (NO REDIRECTS)
    --------------------------------------------------------- */
    public function render_dashboard() {

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

        ob_start();
        ?>

        <div class="mc-dashboard">

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
       EDIT DETAILS (FORM, NO REDIRECTS)
    --------------------------------------------------------- */
    public function render_edit_details() {

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

        ob_start();
        ?>

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
       EDIT DETAILS HANDLER (REDIRECT OK HERE)
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
       SEARCH / COMPARISON PLACEHOLDER (NO REDIRECTS)
    --------------------------------------------------------- */
    public function render_search() {

        $pharmacy = $this->get_current_pharmacy();
        if (!$pharmacy) {
            return '<p>You must be logged in as a pharmacy user to search products.</p>';
        }

        ob_start();
        ?>

        <div class="mc-search">
            <h1>Search Products & Compare Suppliers</h1>

            <form method="get" action="">
                <p>
                    <label>Product name or code</label><br>
                    <input type="text" name="q" value="<?php echo isset($_GET['q']) ? esc_attr($_GET['q']) : ''; ?>" required>
                    <button type="submit">Search</button>
                </p>
            </form>

            <?php if (!empty($_GET['q'])): ?>
                <h2>Results for "<?php echo esc_html($_GET['q']); ?>"</h2>
                <p>This is a placeholder. Here we will show:</p>
                <ul>
                    <li>All matching products</li>
                    <li>All suppliers with price, pack size, stock</li>
                    <li>Buttons to select supplier and add to basket</li>
                </ul>
                <p>Next step: implement the comparison engine and basket flow.</p>
            <?php endif; ?>

            <p><a href="<?php echo esc_url(site_url('/pharmacy/dashboard/')); ?>">Back to dashboard</a></p>
        </div>

        <?php
        return ob_get_clean();
    }
}

new MediCompare_Pharmacy_Frontend();
