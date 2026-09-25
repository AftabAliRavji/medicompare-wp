<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue assets on the Welcome Signup page only.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_page('welcome-signup')) {
        return;
    }

    $css_path = plugin_dir_path(__FILE__) . '../../assets/css/welcome-signup.css';
    $css_url  = plugin_dir_url(__FILE__) . '../../assets/css/welcome-signup.css';

    wp_enqueue_style('dashicons');

    wp_enqueue_style(
        'mc-welcome-signup',
        $css_url,
        [],
        file_exists($css_path) ? filemtime($css_path) : null
    );
});

/**
 * Ensure the interest table contains the pharmacy postcode column.
 */
function mc_welcome_signup_ensure_postcode_column() {
    global $wpdb;

    $table = $wpdb->prefix . 'mc_interest';

    $table_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $table)
    );

    if ($table_exists !== $table) {
        return false;
    }

    $postcode_column = $wpdb->get_var(
        "SHOW COLUMNS FROM {$table} LIKE 'pharmacy_postcode'"
    );

    if (!$postcode_column) {
        $result = $wpdb->query(
            "ALTER TABLE {$table}
             ADD pharmacy_postcode VARCHAR(20) NOT NULL DEFAULT ''
             AFTER pharmacy_name"
        );

        if ($result === false) {
            return false;
        }
    }

    return true;
}

/**
 * Shortcode: [mc_welcome_signup]
 */
function mc_welcome_signup_shortcode() {
    global $wpdb;

    $table       = $wpdb->prefix . 'mc_interest';
    $table_ready = mc_welcome_signup_ensure_postcode_column();

    $success_message = '';
    $error_message   = '';
    $terms_accepted  = false;

    $form_values = [
        'pharmacy_name'     => '',
        'pharmacy_postcode' => '',
        'contact_name'      => '',
        'contact_email'     => '',
        'contact_number'    => '',
    ];

    $request_method = isset($_SERVER['REQUEST_METHOD'])
        ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
        : '';

    if ($request_method === 'POST' && isset($_POST['mc_interest_submit'])) {
        $nonce = isset($_POST['mc_interest_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['mc_interest_nonce']))
            : '';

        if (!$nonce || !wp_verify_nonce($nonce, 'mc_submit_welcome_interest')) {
            $error_message = 'Your form session has expired. Please refresh the page and try again.';
        } else {
            $form_values['pharmacy_name'] = isset($_POST['pharmacy_name'])
                ? sanitize_text_field(wp_unslash($_POST['pharmacy_name']))
                : '';

            $form_values['pharmacy_postcode'] = isset($_POST['pharmacy_postcode'])
                ? strtoupper(sanitize_text_field(wp_unslash($_POST['pharmacy_postcode'])))
                : '';

            $form_values['contact_name'] = isset($_POST['contact_name'])
                ? sanitize_text_field(wp_unslash($_POST['contact_name']))
                : '';

            $form_values['contact_email'] = isset($_POST['contact_email'])
                ? sanitize_email(wp_unslash($_POST['contact_email']))
                : '';

            $form_values['contact_number'] = isset($_POST['contact_number'])
                ? sanitize_text_field(wp_unslash($_POST['contact_number']))
                : '';

            $terms_accepted = isset($_POST['mc_terms_accepted']);

            if (
                $form_values['pharmacy_name'] === '' ||
                $form_values['pharmacy_postcode'] === '' ||
                $form_values['contact_name'] === '' ||
                $form_values['contact_email'] === '' ||
                $form_values['contact_number'] === ''
            ) {
                $error_message = 'Please complete all required fields.';
            } elseif (!is_email($form_values['contact_email'])) {
                $error_message = 'Please enter a valid email address.';
            } elseif (!$terms_accepted) {
                $error_message = 'Please accept the Terms of Use, Privacy Policy and Cookie Policy.';
            } elseif (!$table_ready) {
                $error_message = 'The registration form is currently unavailable. Please contact the site administrator.';
            } else {
                $inserted = $wpdb->insert(
                    $table,
                    [
                        'pharmacy_name'     => $form_values['pharmacy_name'],
                        'pharmacy_postcode' => $form_values['pharmacy_postcode'],
                        'contact_name'      => $form_values['contact_name'],
                        'contact_number'    => $form_values['contact_number'],
                        'contact_email'     => $form_values['contact_email'],
                        'created_at'        => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($inserted === false) {
                    $error_message = 'We could not submit your registration at this time. Please try again.';
                } else {
                    if (!class_exists('MediCompare_Email_Engine')) {
                        require_once plugin_dir_path(__FILE__) . '../email-functions.php';
                    }

                if (class_exists('MediCompare_Email_Engine')) {

                    $engine = new MediCompare_Email_Engine();

                if (method_exists($engine, 'send_welcome_signup_notification')) {

                    $engine->send_welcome_signup_notification(
                        $form_values['pharmacy_name'],
                        $form_values['pharmacy_postcode'],
                        $form_values['contact_name'],
                        $form_values['contact_number'],
                        $form_values['contact_email']
                    );
                }

                if (
                    method_exists(
                        $engine,
                        'send_welcome_signup_pharmacy_notification'
                    )
                ) {

                    $engine->send_welcome_signup_pharmacy_notification(
                        $form_values['pharmacy_name'],
                        $form_values['pharmacy_postcode'],
                        $form_values['contact_name'],
                        $form_values['contact_number'],
                        $form_values['contact_email']
                    );
                }
            }

                    $success_message = 'Thank you. Our onboarding team will contact you shortly.';

                    $form_values = [
                        'pharmacy_name'     => '',
                        'pharmacy_postcode' => '',
                        'contact_name'      => '',
                        'contact_email'     => '',
                        'contact_number'    => '',
                    ];

                    $terms_accepted = false;
                }
            }
        }
    }

    $mc_assets   = plugin_dir_url(dirname(__FILE__, 2)) . 'assets/img/welcome-page/';
    $logo_url    = $mc_assets . 'logo.png';
    $terms_url   = home_url('/terms-of-use/');
    $privacy_url = home_url('/privacy-policy/');
    $cookie_url  = home_url('/cookie-policy/');

    ob_start();
    ?>

    <div class="mc-welcome-signup-page">
        <div class="mc-welcome-background-overlay"></div>

        <div class="mc-welcome-layout">
            <section class="mc-welcome-content">
                <div class="mc-welcome-logo">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Source Med Pharma">
                </div>

                <div class="mc-welcome-introduction">
                    <h1 class="mc-portal-title">
                        Smarter sourcing<br>
                        for <span>UK pharmacies.</span>
                    </h1>

                    <p class="mc-welcome-description">
                        Compare prices, check availability and order from multiple approved suppliers,
                        all in one place, with a focus on NHS concession medicines and more.
                    </p>
                </div>

                <div class="mc-welcome-benefits">
                    <div class="mc-welcome-benefit">
                        <div class="mc-benefit-icon">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        </div>
                        <div class="mc-benefit-content">
                            <h2 class="mc-card-title">Compare multiple suppliers</h2>
                            <p>See live prices and stock levels in seconds.</p>
                        </div>
                    </div>

                    <div class="mc-welcome-benefit">
                        <div class="mc-benefit-icon">
                            <svg
                                viewBox="0 0 24 24"
                                width="34"
                                height="34"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <g
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M8.2 19.8a4.8 4.8 0 0 1-6.8-6.8l8.8-8.8a4.8 4.8 0 1 1 6.8 6.8z" />
                                    <path d="M6 8.4l6.8 6.8" />
                                </g>
                            </svg>
                        </div>

                        <div class="mc-benefit-content">
                            <h2 class="mc-card-title">
                                Focus on concession medicines
                            </h2>

                            <p>Quickly find the best available prices.</p>
                        </div>
                    </div>

                    <div class="mc-welcome-benefit">
                        <div class="mc-benefit-icon">
                            <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                        </div>
                        <div class="mc-benefit-content">
                            <h2 class="mc-card-title">Save time and reduce costs</h2>
                            <p>Make more informed purchasing decisions.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mc-welcome-form-column">
                <div class="mc-welcome-form-card">
                    <div class="mc-form-introduction">
                        <p class="mc-form-eyebrow">Register your interest</p>
                        <h2 class="mc-card-title">Join Source <span>Med</span> Pharma</h2>
                        <p>
                            Be the first to receive updates about our platform and how it can support your pharmacy.
                        </p>
                    </div>

                    <?php if ($error_message !== '') : ?>
                        <div class="mc-form-message mc-form-error" role="alert">
                            <?php echo esc_html($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message !== '') : ?>
                        <div class="mc-form-message mc-form-success" role="status">
                            <?php echo esc_html($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="mc-welcome-interest-form">
                        <?php wp_nonce_field('mc_submit_welcome_interest', 'mc_interest_nonce'); ?>

                        <div class="mc-welcome-form-row">
                            <label for="mc-pharmacy-name">Pharmacy Name <span aria-hidden="true">*</span></label>
                            <input type="text" id="mc-pharmacy-name" name="pharmacy_name" value="<?php echo esc_attr($form_values['pharmacy_name']); ?>" placeholder="e.g. High Street Pharmacy" autocomplete="organization" required>
                        </div>

                        <div class="mc-welcome-form-row">
                            <label for="mc-contact-name">Contact Name <span aria-hidden="true">*</span></label>
                            <input type="text" id="mc-contact-name" name="contact_name" value="<?php echo esc_attr($form_values['contact_name']); ?>" placeholder="e.g. Jane Smith" autocomplete="name" required>
                        </div>

                        <div class="mc-welcome-form-row">
                            <label for="mc-contact-email">Email Address <span aria-hidden="true">*</span></label>
                            <input type="email" id="mc-contact-email" name="contact_email" value="<?php echo esc_attr($form_values['contact_email']); ?>" placeholder="e.g. jane@pharmacy.co.uk" autocomplete="email" required>
                        </div>

                        <div class="mc-welcome-form-row">
                            <label for="mc-contact-number">Contact Number <span aria-hidden="true">*</span></label>
                            <input type="tel" id="mc-contact-number" name="contact_number" value="<?php echo esc_attr($form_values['contact_number']); ?>" placeholder="e.g. 07123 456789" autocomplete="tel" required>
                        </div>

                        <div class="mc-welcome-form-row">
                            <label for="mc-pharmacy-postcode">Pharmacy Postcode <span aria-hidden="true">*</span></label>
                            <input type="text" id="mc-pharmacy-postcode" name="pharmacy_postcode" value="<?php echo esc_attr($form_values['pharmacy_postcode']); ?>" placeholder="e.g. SW1A 1AA" autocomplete="postal-code" maxlength="10" required>
                        </div>

                        <div class="mc-welcome-consent">
                            <label>
                                <input type="checkbox" name="mc_terms_accepted" value="1" <?php checked($terms_accepted, true); ?> required>
                                <span>
                                    I agree to the
                                    <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">Terms of Use</a>
                                    and acknowledge that I have read the
                                    <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                                    and
                                    <a href="<?php echo esc_url($cookie_url); ?>" target="_blank" rel="noopener noreferrer">Cookie Policy</a>.
                                </span>
                            </label>
                        </div>

                        <div class="mc-welcome-submit-row">
                            <button type="submit" name="mc_interest_submit" value="1">
                                <span>Register Your Interest</span>
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </button>
                        </div>

                        <div class="mc-welcome-security-message">
                            <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                            <p>Your information is secure and will only be used to contact you about Source Med Pharma.</p>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('mc_welcome_signup', 'mc_welcome_signup_shortcode');
