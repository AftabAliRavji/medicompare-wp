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
        add_action('wp_ajax_nopriv_mc_send_password_reset', [$this, 'ajax_send_password_reset']);
        add_action('wp_ajax_mc_send_password_reset', [$this, 'ajax_send_password_reset']);
    }

    public function render_login_form() {

        // Prevent shortcode logic from running inside the editor
        if (is_admin()) {
            return '<div class="mc-admin-preview">Pharmacy Login Preview</div>';
        }

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

           <div class="mc-login-form-row">
                <label>Email Address</label>

                <input
                    type="email"
                    name="mc_login_email"
                    placeholder="Enter your email address"
                    required
                >
            </div>

            <div class="mc-login-form-row">
                <label>Password</label>

                <input
                    type="password"
                    name="mc_login_password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <div class="mc-login-submit-row">
                <button
                    type="submit"
                    name="mc_login_submit">
                    Login
                </button>
            </div>

            <p class="mc-login-links">
                New pharmacy?
                <a href="<?php echo site_url('/pharmacy/pharmacy-registration/'); ?>">Register here</a>
            </p>

            <p class="mc-login-links">
                <a href="#" id="mc-forgot-password-link">Forgot your password?</a>
            </p>

        </form>

        <div id="mc-forgot-password-modal" class="mc-forgot-password-modal">

            <div class="mc-forgot-password-modal-content">

                <span
                    id="mc-forgot-password-close"
                    class="mc-forgot-password-close">
                    &times;
                </span>

                <h3>Reset Your Password</h3>

                <p>
                    Enter the email address associated with your pharmacy account.
                    If we find an active account, we'll send a secure password reset link.
                </p>

                <input
                    type="hidden"
                    id="mc-reset-password-nonce"
                    value="<?php echo esc_attr(
                    wp_create_nonce('mc_reset_password')); ?>">
                <input
                    type="email"
                    id="mc-reset-email"
                    placeholder="Enter your email address"
                >

                <button
                    type="button"
                    id="mc-send-reset-link">
                    Send Reset Link
                </button>

            </div>

        </div>

        <style>
            .mc-forgot-password-modal {
                display: none;
                position: fixed;
                z-index: 99999;
                inset: 0;
                background: rgba(15, 23, 42, 0.65);
                backdrop-filter: blur(3px);
            }

            .mc-forgot-password-modal-content {
                background: #ffffff;
                max-width: 520px;
                width: calc(100% - 40px);
                margin: 120px auto;
                padding: 35px;
                border-radius: 16px;
                position: relative;
                box-shadow: 0 25px 50px rgba(0,0,0,0.18);
            }

            .mc-forgot-password-modal-content h3 {
                margin: 0 0 15px;
                font-size: 28px;
                font-weight: 700;
                color: #0f2744;
            }

            .mc-forgot-password-modal-content p {
                margin: 0 0 25px;
                line-height: 1.6;
                color: #556274;
            }

            .mc-forgot-password-modal-content input[type="email"] {
                width: 100%;
                height: 50px;
                padding: 0 16px;
                border: 1px solid #d7e1ea;
                border-radius: 10px;
                font-size: 15px;
                box-sizing: border-box;
                transition: all 0.2s ease;
            }

            .mc-forgot-password-modal-content input[type="email"]:focus {
                outline: none;
                border-color: #008f78;
                box-shadow: 0 0 0 3px rgba(0,143,120,0.15);
            }

            #mc-send-reset-link {
                display: inline-block;
                margin-top: 18px;
                background: #008f78;
                color: #fff;
                border: none;
                border-radius: 10px;
                height: 48px;
                padding: 0 28px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            #mc-send-reset-link:hover {
                background: #007763;
            }

            .mc-forgot-password-close {
                position: absolute;
                top: 18px;
                right: 20px;
                cursor: pointer;
                font-size: 24px;
                color: #556274;
                font-weight: 700;
            }

            .mc-forgot-password-close:hover {
                color: #0f2744;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function() {

            const openBtn  = document.getElementById('mc-forgot-password-link');
            const modal    = document.getElementById('mc-forgot-password-modal');
            const closeBtn = document.getElementById('mc-forgot-password-close');
            const sendBtn  = document.getElementById('mc-send-reset-link');

            if (!openBtn || !modal || !closeBtn) {
                return;
            }

            openBtn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'block';
            });

            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            if (sendBtn) {

                sendBtn.addEventListener('click', function() {

                    const emailField =
                        document.getElementById('mc-reset-email');

                    if (!emailField) {
                        return;
                    }

                    const email = emailField.value.trim();

                    if (!email) {
                        alert('Please enter your email address.');
                        return;
                    }

                    sendBtn.disabled = true;
                    sendBtn.textContent = 'Sending...';

                    const data = new FormData();

                    data.append(
                        'action',
                        'mc_send_password_reset'
                    );

                    data.append(
                        'email',
                        email
                    );

                    fetch(
                        '<?php echo admin_url('admin-ajax.php'); ?>',
                        {
                            method: 'POST',
                            body: data
                        }
                    )
                    .then(response => response.json())
                    .then(result => {

                        alert(result.data.message);

                        emailField.value = '';

                        modal.style.display = 'none';
                    })
                    .catch(function() {

                        alert(
                            'Unable to send reset link. Please try again.'
                        );
                    })
                    .finally(function() {

                        sendBtn.disabled = false;
                        sendBtn.textContent = 'Send Reset Link';
                    });

                });

            }

        });
        </script>

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

        // ---------------------------------------------
         // UPDATE SUBSCRIPTION/TRIAL STATUS AT LOGIN
        // ---------------------------------------------
        $pharmacy_id = get_user_meta($user->ID, '_mc_pharmacy_id', true);

        if ($pharmacy_id) {

            $status    = get_post_meta($pharmacy_id, '_mc_subscription_status', true);
            $trial_end = (int) get_post_meta($pharmacy_id, '_mc_trial_end', true);
            $sub_end   = (int) get_post_meta($pharmacy_id, '_mc_subscription_period_end', true);

            $now = time();

            mc_debug("StateCheck: status=$status, trial_end=$trial_end, sub_end=$sub_end, now=$now");

            // Safety: If status is empty, treat as expired
            if (empty($status)) {
                update_post_meta($pharmacy_id, '_mc_subscription_status', 'expired');
                mc_debug("StateCheck: status was empty → set to expired");
            }

            // Trial expired?
            if ($status === 'trial' && $trial_end > 0 && $trial_end < $now) {
                update_post_meta($pharmacy_id, '_mc_subscription_status', 'expired');
                mc_debug("StateCheck: trial expired → set status=expired");
            }

            // Subscription expired?
            if ($status === 'active' && $sub_end > 0 && $sub_end < $now) {
                update_post_meta($pharmacy_id, '_mc_subscription_status', 'expired');
                mc_debug("StateCheck: subscription expired → set status=expired");
            }
        }

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

    /* ---------------------------------------------------------
    AJAX: SEND PASSWORD RESET EMAIL
    --------------------------------------------------------- */
    public function ajax_send_password_reset() {

        $email = sanitize_email(
            $_POST['email'] ?? ''
        );

        if (!$email) {

            wp_send_json_error([
                'message' => 'Please enter a valid email address.'
            ]);
        }

        $user = get_user_by(
            'email',
            $email
        );

        /*
        * Always return success.
        * Never reveal whether the email exists.
        */
        if (!$user) {

            wp_send_json_success([
                'message' =>
                    'If an account exists for that email address, a reset link has been sent.'
            ]);
        }

        if (
            !in_array(
                'pharmacy_user',
                $user->roles,
                true
            )
        ) {

            wp_send_json_success([
                'message' =>
                    'If an account exists for that email address, a reset link has been sent.'
            ]);
        }

        /*
        * Generate token
        */
        $token = wp_generate_password(
            32,
            false
        );

        update_user_meta(
            $user->ID,
            '_mc_password_reset_token',
            $token
        );

        update_user_meta(
            $user->ID,
            '_mc_password_reset_expiry',
            time() + HOUR_IN_SECONDS
        );

        $logo_url = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/logo.png';

        /*
        * Reset URL
        */
        $reset_url =
            site_url(
                '/pharmacy/reset-password/?token=' .
                urlencode($token));

        $subject = 'Reset Your SourceMed Pharma Password';

        $message = '
        <html>
        <head>
            <meta charset="utf-8">
        </head>
        <body style="
            margin:0;
            padding:40px;
            background:#f4f7fa;
            font-family:Arial,sans-serif;
        ">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                style="
                    max-width:700px;
                    margin:0 auto;
                    background:#ffffff;
                    border-radius:12px;
                    overflow:hidden;
                    box-shadow:0 4px 20px rgba(0,0,0,0.08);
                "
            >

                <tr>
                    <td>
                        <img
                            src="' . esc_url($logo_url) . '"
                            alt="Source Med Pharma"
                            width="360"
                            style="display:block;width:100%;max-width:360px;height:auto;border:0;"
                        >
                    </td>
                </tr>

                <tr>
                    <td style="padding:40px;">

                        <h2 style="
                            margin-top:0;
                            color:#0f2744;
                        ">
                            Password Reset Request
                        </h2>

                        <p style="
                            color:#556274;
                            line-height:1.7;
                        ">
                            A request has been made to reset the password for your
                            Source Med Pharma pharmacy account.
                        </p>

                        <p style="
                            color:#556274;
                            line-height:1.7;
                        ">
                            Click the button below to create a new password.
                        </p>

                        <p style="text-align:center;margin:35px 0;">
                        <a
                            href="' . esc_url($reset_url) . '"
                            style="
                                display:inline-block;
                                background:#008f78;
                                color:#ffffff;
                                text-decoration:none;
                                padding:14px 28px;
                                border-radius:8px;
                                font-weight:600;
                            "
                        >
                            Reset Password
                        </a>
                    </p>

                    <p style="
                        color:#556274;
                        font-size:13px;
                        line-height:1.7;
                        word-break:break-all;
                    ">
                        If the button does not work, copy and paste this link into your browser:
                        <br><br>
                        <a
                            href="' . esc_url($reset_url) . '"
                            style="
                                color:#008f78;
                                text-decoration:none;
                            "
                        >
                            ' . esc_url($reset_url) . '
                        </a>
                    </p>

                        <p style="
                            color:#556274;
                            line-height:1.7;
                            font-size:14px;
                        ">
                            This link will expire in 1 hour.
                        </p>

                        <p style="
                            color:#556274;
                            line-height:1.7;
                            font-size:14px;
                        ">
                            If you did not request this password reset, you can safely
                            ignore this email.
                        </p>

                    </td>
                </tr>

            </table>

        </body>
        </html>
        ';

        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];

        wp_mail(
            $email,
            $subject,
            $message,
            $headers
        );

        error_log(
            'PASSWORD RESET GENERATED FOR USER ' .
            $user->ID
        );

        wp_send_json_success([
            'message' =>
                'If an account exists for that email address, a reset link has been sent.'
        ]);
    }

}

new MediCompare_Pharmacy_Login();
