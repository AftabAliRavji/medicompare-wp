<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Registration {

    public function __construct() {
        add_shortcode('mc_pharmacy_register', [$this, 'render_registration_form']);
        add_action('init', [$this, 'handle_registration']);
    }

    /* ---------------------------------------------------------
       AUTO-GENERATE PHARMACY CODE
    --------------------------------------------------------- */
    private function generate_pharmacy_code() {
        $last = get_option('mc_last_pharmacy_code', 0);
        $next = $last + 1;

        update_option('mc_last_pharmacy_code', $next);

        return 'PHARM-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /* ---------------------------------------------------------
       RENDER FORM
    --------------------------------------------------------- */
    public function render_registration_form() {

        ob_start();

        if (!empty($_GET['registered'])) {
            echo '<div class="mc-success">Registration successful! Please wait for verification.</div>';
            return ob_get_clean();
        }

        ?>

        <form method="post" class="mc-register-form">

            <?php wp_nonce_field('mc_pharmacy_register', 'mc_pharmacy_register_nonce'); ?>

            <h2>Pharmacy Registration</h2>

            <label>Pharmacy Name</label>
            <input type="text" name="mc_pharmacy_name" required>

            <label>Email</label>
            <input type="email" name="mc_email" required>

            <label>Phone</label>
            <input type="text" name="mc_phone">

            <label>Address Line 1</label>
            <input type="text" name="mc_address_line_1" required>

            <label>Address Line 2</label>
            <input type="text" name="mc_address_line_2">

            <label>City</label>
            <input type="text" name="mc_city" required>

            <label>Postcode</label>
            <input type="text" name="mc_postcode" required>

            <label>GPhC Number</label>
            <input type="text" name="mc_gphc_number" required>

            <label>Contact Name</label>
            <input type="text" name="mc_contact_name" required>

            <label>Password</label>
            <input type="password" name="mc_password" required>

            <label>Confirm Password</label>
            <input type="password" name="mc_password_confirm" required>

            <!-- reCAPTCHA -->
            <div class="g-recaptcha" data-sitekey="6LeaBgstAAAAAKApstMhgvBj8zMYO9gxOv0cs7Yc"></div>

            <button type="submit" name="mc_register_submit">Register</button>

        </form>

        <?php

        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       HANDLE REGISTRATION
    --------------------------------------------------------- */
    public function handle_registration() {

        if (!isset($_POST['mc_register_submit'])) return;

        if (!isset($_POST['mc_pharmacy_register_nonce']) ||
            !wp_verify_nonce($_POST['mc_pharmacy_register_nonce'], 'mc_pharmacy_register')) {
            return;
        }

        // Validate passwords
        if ($_POST['mc_password'] !== $_POST['mc_password_confirm']) {
            wp_die('Passwords do not match.');
        }

        // Validate email uniqueness
        if (email_exists($_POST['mc_email'])) {
            wp_die('Email already registered.');
        }

        // Validate reCAPTCHA
        $response = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", [
            'body' => [
                'secret' => '6LeaBgstAAAAAJze9-SGJTKvqoHUDCkjlHEYbIyM',
                'response' => $_POST['g-recaptcha-response']
            ]
        ]);

        $result = json_decode($response['body']);

        if (empty($result->success)) {
            wp_die('reCAPTCHA failed.');
        }

        /* ---------------------------------------------------------
           CREATE USER
        --------------------------------------------------------- */
        $user_id = wp_create_user(
            sanitize_email($_POST['mc_email']),
            sanitize_text_field($_POST['mc_password']),
            sanitize_email($_POST['mc_email'])
        );

        wp_update_user([
            'ID' => $user_id,
            'role' => 'pharmacy_user'
        ]);

        /* ---------------------------------------------------------
           CREATE PHARMACY CPT ENTRY
        --------------------------------------------------------- */
        $pharmacy_code = $this->generate_pharmacy_code();

        $post_id = wp_insert_post([
            'post_title'  => sanitize_text_field($_POST['mc_pharmacy_name']),
            'post_type'   => 'mc_pharmacy',
            'post_status' => 'publish'
        ]);

        update_post_meta($post_id, '_mc_pharmacy_code', $pharmacy_code);
        update_post_meta($post_id, '_mc_email', sanitize_email($_POST['mc_email']));
        update_post_meta($post_id, '_mc_phone', sanitize_text_field($_POST['mc_phone']));
        update_post_meta($post_id, '_mc_address_line_1', sanitize_text_field($_POST['mc_address_line_1']));
        update_post_meta($post_id, '_mc_address_line_2', sanitize_text_field($_POST['mc_address_line_2']));
        update_post_meta($post_id, '_mc_city', sanitize_text_field($_POST['mc_city']));
        update_post_meta($post_id, '_mc_postcode', sanitize_text_field($_POST['mc_postcode']));
        update_post_meta($post_id, '_mc_gphc_number', sanitize_text_field($_POST['mc_gphc_number']));
        update_post_meta($post_id, '_mc_contact_name', sanitize_text_field($_POST['mc_contact_name']));
        update_post_meta($post_id, '_mc_status', 'pending_verification');

        // Trial
        update_post_meta($post_id, '_mc_trial_start', time());
        update_post_meta($post_id, '_mc_trial_end', strtotime('+30 days'));

        // Link user → pharmacy
        update_user_meta($user_id, '_mc_pharmacy_id', $post_id);

        /* ---------------------------------------------------------
           REDIRECT
        --------------------------------------------------------- */
        wp_redirect(add_query_arg('registered', '1', wp_get_referer()));
        exit;
    }
}

new MediCompare_Pharmacy_Registration();
