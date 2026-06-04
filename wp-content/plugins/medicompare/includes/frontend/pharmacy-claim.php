<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Claim {

    public function __construct() {
        // Generate tokens when pharmacies are created
        add_action('save_post_mc_pharmacy', [$this, 'generate_claim_token'], 99, 3);
        add_action('mc_csv_pharmacy_imported', [$this, 'generate_claim_token_after_csv'], 10, 1);

        // Front-end claim flow
        add_shortcode('mc_pharmacy_claim', [$this, 'render_claim_form']);
        add_action('init', [$this, 'handle_claim_submission']);
    }

    /* ---------------------------------------------------------
       SECURE TOKEN
    --------------------------------------------------------- */
    private function create_secure_token() {
        return bin2hex(random_bytes(32)); // 64-char secure token
    }

    private function get_pharmacy_by_token($token) {
        if (!$token) return null;

        $q = new WP_Query([
            'post_type'      => 'mc_pharmacy',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_mc_claim_token',
                    'value' => $token,
                ]
            ]
        ]);

        if (!$q->have_posts()) return null;

        $post = $q->posts[0];
        $expiry = (int) get_post_meta($post->ID, '_mc_claim_token_expiry', true);

        if (!$expiry || $expiry < time()) {
            return null;
        }

        return $post;
    }

    /* ---------------------------------------------------------
       WHEN PHARMACY CREATED IN ADMIN
    --------------------------------------------------------- */
    public function generate_claim_token($post_id, $post, $update) {

    error_log("MC CLAIM: generate_claim_token() fired for post_id={$post_id}, update=" . ($update ? 'true' : 'false') . ", post_type={$post->post_type}");

    if ($post->post_type !== 'mc_pharmacy') {
        error_log("MC CLAIM: exiting because post_type is {$post->post_type}, not mc_pharmacy");
        return;
    }

    // If token already exists, do nothing
    $existing = get_post_meta($post_id, '_mc_claim_token', true);
    if ($existing) {
        error_log("MC CLAIM: token already exists, skipping");
        return;
    }

    $email = get_post_meta($post_id, '_mc_email', true);
    error_log("MC CLAIM: email meta for post_id={$post_id} is: " . var_export($email, true));

    if (!$email) {
        error_log("MC CLAIM: exiting because email is empty");
        return;
    }

    $token  = $this->create_secure_token();
    $expiry = time() + (48 * 60 * 60); // 48 hours

    update_post_meta($post_id, '_mc_claim_token', $token);
    update_post_meta($post_id, '_mc_claim_token_expiry', $expiry);

    error_log("MC CLAIM: token generated for post_id={$post_id}: {$token}");

    $this->send_claim_email($email, $token);
}



    /* ---------------------------------------------------------
       WHEN PHARMACY IMPORTED VIA CSV
       (YOU ALREADY TRIGGER mc_csv_pharmacy_imported WITH $post_id)
    --------------------------------------------------------- */
    public function generate_claim_token_after_csv($post_id) {
        $email = get_post_meta($post_id, '_mc_email', true);
        if (!$email) return;

        $token  = $this->create_secure_token();
        $expiry = time() + (48 * 60 * 60);

        update_post_meta($post_id, '_mc_claim_token', $token);
        update_post_meta($post_id, '_mc_claim_token_expiry', $expiry);

        $this->send_claim_email($email, $token);
    }

    /* ---------------------------------------------------------
       SEND CLAIM EMAIL
    --------------------------------------------------------- */
    private function send_claim_email($email, $token) {
        $link = site_url('/pharmacy/complete-registration/?token=' . urlencode($token));

        $subject = "Complete Your MediCompare Registration";
        $message  = "Hello,\n\n";
        $message .= "Your pharmacy has been added to MediCompare.\n\n";
        $message .= "Please complete your registration using the secure link below:\n\n";
        $message .= $link . "\n\n";
        $message .= "This link will expire in 48 hours.\n\n";
        $message .= "Regards,\nMediCompare Team";

        wp_mail($email, $subject, $message);
    }

    /* ---------------------------------------------------------
       RENDER CLAIM FORM
    --------------------------------------------------------- */
    public function render_claim_form() {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        ob_start();

        if (isset($_GET['claimed']) && $_GET['claimed'] == '1') {
            echo '<div class="mc-success">Your account has been created. You can now log in.</div>';
            return ob_get_clean();
        }

        if (!$token) {
            echo '<div class="mc-error">Invalid or missing token.</div>';
            return ob_get_clean();
        }

        $pharmacy = $this->get_pharmacy_by_token($token);

        if (!$pharmacy) {
            echo '<div class="mc-error">This registration link is invalid or has expired.</div>';
            return ob_get_clean();
        }

        $post_id = $pharmacy->ID;

        $pharmacy_name  = get_the_title($post_id);
        $email          = get_post_meta($post_id, '_mc_email', true);
        $phone          = get_post_meta($post_id, '_mc_phone', true);
        $address1       = get_post_meta($post_id, '_mc_address_line_1', true);
        $address2       = get_post_meta($post_id, '_mc_address_line_2', true);
        $city           = get_post_meta($post_id, '_mc_city', true);
        $postcode       = get_post_meta($post_id, '_mc_postcode', true);
        $gphc           = get_post_meta($post_id, '_mc_gphc_number', true);
        $contact_name   = get_post_meta($post_id, '_mc_contact_name', true);

        ?>

        <form method="post" class="mc-register-form">
            <?php wp_nonce_field('mc_pharmacy_claim', 'mc_pharmacy_claim_nonce'); ?>

            <h2>Complete Pharmacy Registration</h2>

            <input type="hidden" name="mc_claim_token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="mc_pharmacy_id" value="<?php echo esc_attr($post_id); ?>">

            <label>Pharmacy Name</label>
            <input type="text" name="mc_pharmacy_name" value="<?php echo esc_attr($pharmacy_name); ?>" required>

            <label>Email</label>
            <input type="email" name="mc_email" value="<?php echo esc_attr($email); ?>" readonly>

            <label>Phone</label>
            <input type="text" name="mc_phone" value="<?php echo esc_attr($phone); ?>">

            <label>Address Line 1</label>
            <input type="text" name="mc_address_line_1" value="<?php echo esc_attr($address1); ?>" required>

            <label>Address Line 2</label>
            <input type="text" name="mc_address_line_2" value="<?php echo esc_attr($address2); ?>">

            <label>City</label>
            <input type="text" name="mc_city" value="<?php echo esc_attr($city); ?>" required>

            <label>Postcode</label>
            <input type="text" name="mc_postcode" value="<?php echo esc_attr($postcode); ?>" required>

            <label>GPhC Number</label>
            <input type="text" name="mc_gphc_number" value="<?php echo esc_attr($gphc); ?>" required>

            <label>Contact Name</label>
            <input type="text" name="mc_contact_name" value="<?php echo esc_attr($contact_name); ?>" required>

            <label>Password</label>
            <input type="password" name="mc_password" required>

            <label>Confirm Password</label>
            <input type="password" name="mc_password_confirm" required>

            <div class="g-recaptcha" data-sitekey="6LeaBgstAAAAAKApstMhgvBj8zMYO9gxOv0cs7Yc"></div>

            <button type="submit" name="mc_claim_submit">Complete Registration</button>
        </form>

        <?php

        return ob_get_clean();
    }

    /* ---------------------------------------------------------
       HANDLE CLAIM SUBMISSION
    --------------------------------------------------------- */
    public function handle_claim_submission() {
        if (!isset($_POST['mc_claim_submit'])) return;

        if (!isset($_POST['mc_pharmacy_claim_nonce']) ||
            !wp_verify_nonce($_POST['mc_pharmacy_claim_nonce'], 'mc_pharmacy_claim')) {
            return;
        }

        $token   = isset($_POST['mc_claim_token']) ? sanitize_text_field($_POST['mc_claim_token']) : '';
        $post_id = isset($_POST['mc_pharmacy_id']) ? (int) $_POST['mc_pharmacy_id'] : 0;

        if (!$token || !$post_id) {
            wp_die('Invalid request.');
        }

        $pharmacy = $this->get_pharmacy_by_token($token);
        if (!$pharmacy || $pharmacy->ID != $post_id) {
            wp_die('This registration link is invalid or has expired.');
        }

        // Validate passwords
        if ($_POST['mc_password'] !== $_POST['mc_password_confirm']) {
            wp_die('Passwords do not match.');
        }

        $email = sanitize_email($_POST['mc_email']);

        // Ensure no existing WP user with this email
        if (email_exists($email)) {
            wp_die('An account with this email already exists.');
        }

        // Validate reCAPTCHA
        $response = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", [
            'body' => [
                'secret'   => '6LeaBgstAAAAAJze9-SGJTKvqoHUDCkjlHEYbIyM',
                'response' => isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '',
            ]
        ]);

        if (is_wp_error($response)) {
            wp_die('reCAPTCHA request failed.');
        }

        $result = json_decode(wp_remote_retrieve_body($response));
        if (empty($result->success)) {
            wp_die('reCAPTCHA failed.');
        }

        // CREATE USER
        $user_id = wp_create_user(
            $email,
            sanitize_text_field($_POST['mc_password']),
            $email
        );

        if (is_wp_error($user_id)) {
            wp_die('Could not create user.');
        }

        wp_update_user([
            'ID'   => $user_id,
            'role' => 'pharmacy_user'
        ]);

        // UPDATE PHARMACY META FROM FORM
        update_post_meta($post_id, '_mc_email', $email);
        update_post_meta($post_id, '_mc_phone', sanitize_text_field($_POST['mc_phone']));
        update_post_meta($post_id, '_mc_address_line_1', sanitize_text_field($_POST['mc_address_line_1']));
        update_post_meta($post_id, '_mc_address_line_2', sanitize_text_field($_POST['mc_address_line_2']));
        update_post_meta($post_id, '_mc_city', sanitize_text_field($_POST['mc_city']));
        update_post_meta($post_id, '_mc_postcode', sanitize_text_field($_POST['mc_postcode']));
        update_post_meta($post_id, '_mc_gphc_number', sanitize_text_field($_POST['mc_gphc_number']));
        update_post_meta($post_id, '_mc_contact_name', sanitize_text_field($_POST['mc_contact_name']));

        // Status + trial (only if not already set)
        $status = get_post_meta($post_id, '_mc_status', true);
        if (!$status) {
            update_post_meta($post_id, '_mc_status', 'pending_verification');
        }

        $trial_start = get_post_meta($post_id, '_mc_trial_start', true);
        $trial_end   = get_post_meta($post_id, '_mc_trial_end', true);

        if (!$trial_start) {
            update_post_meta($post_id, '_mc_trial_start', time());
        }
        if (!$trial_end) {
            update_post_meta($post_id, '_mc_trial_end', strtotime('+30 days'));
        }

        // Link user → pharmacy
        update_user_meta($user_id, '_mc_pharmacy_id', $post_id);

        // Invalidate token
        delete_post_meta($post_id, '_mc_claim_token');
        delete_post_meta($post_id, '_mc_claim_token_expiry');

        // Redirect
        wp_redirect(add_query_arg('claimed', '1', remove_query_arg(['token'])));
        exit;
    }
}

new MediCompare_Pharmacy_Claim();
