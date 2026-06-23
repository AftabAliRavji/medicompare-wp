<?php

if (!defined('ABSPATH')) exit;

/**
 * Simple debug logger
 */
function mc_debug($msg) {
    error_log("MC_LOGIN_DEBUG: " . print_r($msg, true));
}

class MediCompare_Pharmacy_Login {

    public function __construct() {
        add_shortcode('mc_pharmacy_login', [$this, 'render_login_form']);
        add_action('init', [$this, 'handle_login']);
        add_action('init', [$this, 'handle_logout']);
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

        mc_debug("Login handler triggered");

        // FIX 1: Detect ANY POST submission (Enter key OR button)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            mc_debug("Not a POST request");
            return;
        }

        // FIX 2: Ensure this is the login form (avoid collisions with other POSTs)
        if (!isset($_POST['mc_pharmacy_login_nonce'])) {
            mc_debug("Login nonce missing → not our form");
            return;
        }

        // Verify nonce
        if (
            !wp_verify_nonce($_POST['mc_pharmacy_login_nonce'], 'mc_pharmacy_login')
        ) {
            mc_debug("Nonce failed");
            return;
        }

        mc_debug("Nonce OK");

        // If already logged in as pharmacy user, go to search
        if (is_user_logged_in()) {
            $current = wp_get_current_user();
            mc_debug("Already logged in as: " . print_r($current->roles, true));

            if (in_array('pharmacy_user', $current->roles)) {
                mc_debug("Already pharmacy user → redirecting");
                wp_redirect(site_url('/pharmacy/search/'));
                exit;
            }
        }

        // FIX 3: DO NOT SANITIZE PASSWORDS (sanitize_text_field breaks them)
        $email    = sanitize_email($_POST['mc_login_email']);
        $password = $_POST['mc_login_password'];

        mc_debug("Email received: $email");
        mc_debug("Password length: " . strlen($password));

        $user = get_user_by('email', $email);

        if (!$user) {
            mc_debug("User NOT found for email: $email");
            wp_redirect(add_query_arg('login', 'failed', site_url('/pharmacy/login/')));
            exit;
        }

        mc_debug("User found: ID {$user->ID}, login {$user->user_login}");

        // Must be pharmacy_user role
        if (!in_array('pharmacy_user', $user->roles)) {
            mc_debug("User does NOT have pharmacy_user role");
            wp_redirect(add_query_arg('login', 'failed', site_url('/pharmacy/login/')));
            exit;
        }

        mc_debug("User has pharmacy_user role");

        // Check pharmacy status
        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);
        $status = get_post_meta($pharmacy_id, '_mc_status', true);

        mc_debug("Pharmacy ID: $pharmacy_id, Status: $status");

        if ($status !== 'active') {
            mc_debug("Pharmacy NOT active");
            wp_redirect(add_query_arg('status', 'not_active', site_url('/pharmacy/login/')));
            exit;
        }

        mc_debug("Pharmacy active → attempting wp_signon");

        // Authenticate
        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true
        ];

        $signon = wp_signon($creds);

        if (is_wp_error($signon)) {
            mc_debug("wp_signon FAILED: " . $signon->get_error_message());
            wp_redirect(add_query_arg('login', 'failed', site_url('/pharmacy/login/')));
            exit;
        }

        mc_debug("wp_signon SUCCESS → redirecting to search");

        wp_redirect(site_url('/pharmacy/search/'));
        exit;
    }



    public function handle_logout() {

        if (!isset($_GET['mc_logout'])) {
            return;
        }

        mc_debug("Logout triggered");

        // Destroy WP session
        wp_logout();

        // Destroy PHP session
        if (session_id()) {
            session_destroy();
        }

        mc_debug("Logout complete → redirecting to login");

        // Redirect to login page
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

}

new MediCompare_Pharmacy_Login();
