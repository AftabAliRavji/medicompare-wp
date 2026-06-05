<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Pharmacy_Login {

    public function __construct() {
        add_shortcode('mc_pharmacy_login', [$this, 'render_login_form']);
        add_action('init', [$this, 'handle_login']);
    }

    public function render_login_form() {

    ob_start();

    if (isset($_GET['login']) && $_GET['login'] === 'failed') {
        echo '<div class="mc-error">Invalid email or password.</div>';
    }

    if (isset($_GET['status']) && $_GET['status'] === 'not_active') {
        echo '<div class="mc-error">Your account is not active yet.</div>';
    }

    ?>

    <form method="post" class="mc-login-form">
        <?php wp_nonce_field('mc_pharmacy_login', 'mc_pharmacy_login_nonce'); ?>

        <h2>Pharmacy Login</h2>

        <label>Email</label>
        <input type="email" name="mc_login_email" required>

        <label>Password</label>
        <input type="password" name="mc_login_password" required>

        <button type="submit" name="mc_login_submit">Login</button>

        <p>
            New pharmacy?
            <a href="<?php echo site_url('/pharmacy/register/'); ?>">Register here</a>
        </p>

    </form>

    <?php

    return ob_get_clean();
}


        public function handle_login() {

        // Only run on POST submit
        if (!isset($_POST['mc_login_submit'])) {
            return;
        }

        // Verify nonce
        if (
            !isset($_POST['mc_pharmacy_login_nonce']) ||
            !wp_verify_nonce($_POST['mc_pharmacy_login_nonce'], 'mc_pharmacy_login')
        ) {
            return;
        }

        // If already logged in, redirect to dashboard
        if (is_user_logged_in()) {
            wp_redirect(site_url('/pharmacy/dashboard/'));
            exit;
        }

        $email    = sanitize_email($_POST['mc_login_email']);
        $password = sanitize_text_field($_POST['mc_login_password']);

        $user = get_user_by('email', $email);

        // No user found
        if (!$user) {
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }

        // Must be pharmacy_user role
        if (!in_array('pharmacy_user', $user->roles)) {
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }

        // Check pharmacy status
        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);
        $status = get_post_meta($pharmacy_id, '_mc_status', true);

        if ($status !== 'active') {
            wp_redirect(add_query_arg('status', 'not_active', wp_get_referer()));
            exit;
        }

        // Authenticate
        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true
        ];

        $signon = wp_signon($creds);

        if (is_wp_error($signon)) {
            wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
            exit;
        }

        // Success → redirect to dashboard
        wp_redirect(site_url('/pharmacy/dashboard/'));
        exit;
    }

}

new MediCompare_Pharmacy_Login();
