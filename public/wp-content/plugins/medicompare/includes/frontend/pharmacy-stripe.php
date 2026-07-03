<?php

if (!defined('ABSPATH')) {
    exit;
}

use Stripe\Stripe;
use Stripe\Checkout\Session;

/**
 * Helper: get pharmacy ID for current user
 * Works for both new and old data models.
 */
function mc_get_pharmacy_id_by_user($user_id) {
    global $wpdb;

    // First try via wp_user_id meta (new model)
    $pharmacy_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = 'mc_pharmacy'
           AND m.meta_key = 'wp_user_id'
           AND m.meta_value = %d
         LIMIT 1",
        $user_id
    ));

    if ($pharmacy_id) {
        return (int) $pharmacy_id;
    }

    // Fallback via _mc_email (old model)
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return 0;
    }

    $pharmacy_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
         WHERE p.post_type = 'mc_pharmacy'
           AND m.meta_key = '_mc_email'
           AND m.meta_value = %s
         LIMIT 1",
        $user->user_email
    ));

    return $pharmacy_id ? (int) $pharmacy_id : 0;
}

/**
 * Add pretty URL: /pharmacy/subscribe/
 */
add_action('init', function () {
    add_rewrite_rule(
        '^pharmacy/subscribe/?$',
        'index.php?mc_pharmacy_subscribe=1',
        'top'
    );
});

/**
 * Add pretty URLs for success + cancel
 */
add_action('init', function () {
    add_rewrite_rule(
        '^pharmacy/subscription/success/?$',
        'index.php?mc_subscription_success=1',
        'top'
    );

    add_rewrite_rule(
        '^pharmacy/subscription/cancel/?$',
        'index.php?mc_subscription_cancel=1',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'mc_pharmacy_subscribe';
    $vars[] = 'mc_subscription_success';
    $vars[] = 'mc_subscription_cancel';
    return $vars;
});

/**
 * Handle /pharmacy/subscribe/ → create Stripe Checkout Session and redirect
 */
add_action('template_redirect', function () {

    if (get_query_var('mc_pharmacy_subscribe')) {

        if (!is_user_logged_in()) {
            wp_redirect(home_url('/pharmacy/login/'));
            exit;
        }

        $user_id     = get_current_user_id();
        $pharmacy_id = mc_get_pharmacy_id_by_user($user_id);

        if (!$pharmacy_id) {
            wp_die('Pharmacy not found for this user.');
        }

        // Load Stripe SDK (Composer autoloader)
        require_once ABSPATH . 'vendor/autoload.php';

        Stripe::setApiKey(mc_get_stripe_secret_key());

        $user = wp_get_current_user();

        try {
            $session = Session::create([
                'mode' => 'subscription',
                'customer_email' => $user->user_email,
                'line_items' => [[
                    'price'    => mc_get_stripe_price_id(),
                    'quantity' => 1,
                ]],
                'success_url' => home_url('/pharmacy/subscription/success/?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url'  => home_url('/pharmacy/subscription/cancel/'),
                'metadata'    => [
                    'pharmacy_id' => $pharmacy_id,
                    'wp_user_id'  => $user_id,
                ],
            ]);
        } catch (\Exception $e) {
            wp_die('Stripe error: ' . esc_html($e->getMessage()));
        }

        wp_redirect($session->url);
        exit;
    }
});

/**
 * Route handler for success + cancel pages
 */
add_action('template_redirect', function () {

    if (get_query_var('mc_subscription_success')) {
        mc_subscription_success_page();
        exit;
    }

    if (get_query_var('mc_subscription_cancel')) {
        mc_subscription_cancel_page();
        exit;
    }
});

/**
 * SUCCESS PAGE — called after Stripe Checkout completes
 */
function mc_subscription_success_page() {

    if (!isset($_GET['session_id'])) {
        wp_die('Missing Stripe session ID.');
    }

    $session_id = sanitize_text_field($_GET['session_id']);

    require_once ABSPATH . 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey(mc_get_stripe_secret_key());

    try {
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        $subscription = \Stripe\Subscription::retrieve($session->subscription);
        $customer = \Stripe\Customer::retrieve($session->customer);

        // Extract subscription data
        $subscription_id = $subscription->id;
        $customer_id = $customer->id;
        $price_id = $subscription->items->data[0]->price->id;
        $renewal_date = date('Y-m-d H:i:s', $subscription->current_period_end);

        global $wpdb;
        $table = $wpdb->prefix . 'medi_subscriptions';

        $wpdb->insert($table, [
            'user_id'             => get_current_user_id(),
            'stripe_customer'     => $customer_id,
            'stripe_subscription' => $subscription_id,
            'stripe_price'        => $price_id,
            'renewal_date'        => $renewal_date,
            'status'              => 'active'
        ]);

        /**
         * ⭐ NEW REQUIRED LINE ⭐
         * Save Stripe customer ID into user meta so renewal/reactivation works
         */
        update_user_meta(get_current_user_id(), 'mc_stripe_customer_id', $customer_id);

        wp_redirect(home_url('/pharmacy/dashboard/'));
        exit;

    } catch (Exception $e) {
        wp_die('Stripe error: ' . esc_html($e->getMessage()));
    }
}


/**
 * CANCEL PAGE — user backed out of checkout
 */
function mc_subscription_cancel_page() {
    echo '<h2>Subscription cancelled</h2>';
    echo '<p>Your payment was not completed.</p>';
    echo '<a href="' . home_url('/pharmacy/subscribe/') . '">Try again</a>';
}

/**
 * AJAX: Create Stripe Checkout Session for subscription renewal
 */
add_action('wp_ajax_mc_create_checkout_session', 'mc_create_checkout_session');
add_action('wp_ajax_nopriv_mc_create_checkout_session', 'mc_create_checkout_session');

function mc_create_checkout_session() {

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    // Try to get existing Stripe customer ID
    $customer_id = get_user_meta($user_id, 'mc_stripe_customer_id', true);

    // Load Stripe SDK
    require_once ABSPATH . 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey(mc_get_stripe_secret_key());

    // Subscription price ID
    $price_id = mc_get_stripe_price_id();

    try {

        // CASE 1: Existing Stripe customer → use customer ID
        if ($customer_id) {

            $session = \Stripe\Checkout\Session::create([
                'mode' => 'subscription',
                'customer' => $customer_id,
                'line_items' => [[
                    'price' => $price_id,
                    'quantity' => 1,
                ]],
                'success_url' => home_url('/pharmacy/subscription/success/?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url'  => home_url('/pharmacy/subscription/cancel/'),
            ]);

        } else {

            // CASE 2: New customer → use customer_email
            $session = \Stripe\Checkout\Session::create([
                'mode' => 'subscription',
                'customer_email' => $user->user_email,
                'line_items' => [[
                    'price' => $price_id,
                    'quantity' => 1,
                ]],
                'success_url' => home_url('/pharmacy/subscription/success/?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url'  => home_url('/pharmacy/subscription/cancel/'),
            ]);
        }

        wp_send_json_success(['url' => $session->url]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}


/**
 * Load subscription JS
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'mc-subscription-js',
        plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/subscription.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('mc-subscription-js', 'mcSubscription', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});


