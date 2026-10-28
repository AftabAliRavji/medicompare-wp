<?php

if (!defined('ABSPATH')) {
    exit;
}

class MediCompare_Pharmacy_Reset_Password {

    public function __construct() {

        add_shortcode(
            'mc_pharmacy_reset_password',
            [$this, 'render_reset_form']
        );

        add_action(
            'init',
            [$this, 'handle_password_reset']
        );

    }

    public function render_reset_form() {

    $mc_assets = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/';

       if (
            isset($_GET['reset']) &&
            $_GET['reset'] === 'success'
        ) {

        ob_start();

        /* ⭐ ADD HEADER + ASSET PATH HERE */
            include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
            /* ⭐ END HEADER */

        mc_render_hero([
            'kicker'      => 'ACCOUNT SECURITY',
            'title'       => 'Password Updated',
            'description' => 'Your password has been updated successfully. You can now sign in using your new password.'
        ]);

        ?>

        <div class="mc-login-form">

            <div
                style="
                    text-align:center;
                    background:#f0fdf4;
                    border:1px solid #22c55e;
                    border-radius:12px;
                    padding:30px;
                    margin-block-start: 1rem;
                "
            >

                <h3 style="color:#166534;">
                    Password Updated Successfully
                </h3>

                <p>
                    Your password has been updated and the reset link has been invalidated.
                </p>

                <p>
                    You can now sign in using your new password.
                </p>

                <div class="mc-login-submit-row">

                    <a
                        href="<?php echo esc_url(site_url('/pharmacy/login/')); ?>"
                        class="mc-reset-login-button"
                    >
                        Go To Login
                    </a>

                </div>

            </div>

        </div>

        <?php

        return ob_get_clean();
    }

        $token = sanitize_text_field(
            $_GET['token'] ?? ''
        );

        if (!$token) {

            return '
                <div class="mc-portal-card">
                    <h2>Invalid Reset Link</h2>
                    <p>
                        The password reset link is missing or invalid.
                    </p>
                </div>
            ';
        }

        global $wpdb;

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = '_mc_password_reset_token'
                AND meta_value = %s
                LIMIT 1
                ",
                $token
            )
        );

        if (!$user_id) {

            return '
                <div class="mc-portal-card">
                    <h2>Invalid Reset Link</h2>
                    <p>
                        This password reset link is invalid.
                    </p>
                </div>
            ';
        }

        $expiry = (int) get_user_meta(
            $user_id,
            '_mc_password_reset_expiry',
            true
        );

        if (
            !$expiry ||
            $expiry < time()
        ) {

            return '
                <div class="mc-portal-card">
                    <h2>Link Expired</h2>
                    <p>
                        This password reset link has expired.
                    </p>
                </div>
            ';
        }

        ob_start();
        ?>

        <?php

            /* ⭐ ADD HEADER + ASSET PATH HERE */
            include dirname(__FILE__, 3) . '/templates/header-pharmacy.php';
            /* ⭐ END HEADER */

      
            mc_render_hero([
                'kicker'      => 'ACCOUNT SECURITY',
                'title'       => 'Reset Your Password',
                'description' => 'Create a new password for your pharmacy account. Once updated, you can sign in using your new credentials.',
            ]);
        ?>

        <div class="mc-portal-card mc-login-form" style="margin-block-start: 1rem;">

        <div class="mc-login-introduction">

            <h2 class="mc-card-title">
            Reset Password
            </h2>

            <p class="mc-login-eyebrow">
                Enter your new password below.
            </p>
            
        </div>

            <form method="post">

                <?php wp_nonce_field(
                    'mc_reset_password_submit',
                    'mc_reset_password_nonce'
                ); ?>
            
                <input
                    type="hidden"
                    name="mc_reset_token"
                    value="<?php echo esc_attr($token); ?>"
                >

                <div class="mc-login-form-row">

                    <label>
                        New Password
                    </label>

                    <input
                        type="password"
                        name="mc_new_password"
                        minlength="8"
                        required
                    >
                    <label>   Password must be at least 8 characters long.</label>
                </div>
               
                <div class="mc-login-form-row">

                    <label>
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        name="mc_confirm_password"
                        minlength="8"
                        required
                    >

                </div>

                <div class="mc-login-submit-row">

                    <button
                        type="submit"
                        name="mc_reset_password_submit">

                        Update Password

                    </button>

                </div>

            </form>

        </div>

        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------
    HANDLE PASSWORD RESET
    --------------------------------------------------------- */
    public function handle_password_reset() {

        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
        ) {
            return;
        }

        if (
            !isset($_POST['mc_reset_password_submit'])
        ) {
            return;
        }

        if (
            !isset($_POST['mc_reset_password_nonce'])
        ) {
            return;
        }

        if (
            !wp_verify_nonce(
                $_POST['mc_reset_password_nonce'],
                'mc_reset_password_submit'
            )
        ) {
            return;
        }

        $token = sanitize_text_field(
            $_POST['mc_reset_token'] ?? ''
        );

        $password = $_POST['mc_new_password'] ?? '';

        $confirm_password = $_POST['mc_confirm_password'] ?? '';

        if ($password !== $confirm_password) {

            wp_die(
                'Passwords do not match.'
            );
        }

        if (strlen($password) < 8) {

            wp_die(
                'Password must be at least 8 characters long.'
            );
        }

        global $wpdb;

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = '_mc_password_reset_token'
                AND meta_value = %s
                LIMIT 1
                ",
                $token
            )
        );

        if (!$user_id) {

            wp_die(
                'Invalid password reset token.'
            );
        }

        $expiry = (int) get_user_meta(
            $user_id,
            '_mc_password_reset_expiry',
            true
        );

        if (
            !$expiry ||
            $expiry < time()
        ) {

            wp_die(
                'Password reset link has expired.'
            );
        }

        wp_set_password(
            $password,
            $user_id
        );

        delete_user_meta(
            $user_id,
            '_mc_password_reset_token'
        );

        delete_user_meta(
            $user_id,
            '_mc_password_reset_expiry'
        );

        error_log(
            'PASSWORD RESET COMPLETED FOR USER ' .
            $user_id
        );

        wp_redirect(
            add_query_arg(
                'reset',
                'success',
                site_url('/pharmacy/reset-password/')
            )
        );

        exit;
    }
}

new MediCompare_Pharmacy_Reset_Password();