<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add pretty URL:
 * /gocardless-subscribe/
 */
add_action('init', function () {

    add_rewrite_rule(
    '^gocardless-subscribe/?$',
    'index.php?mc_gocardless_subscribe=1',
    'top'
    );

});

/**
 * Register query var
 */
add_filter('query_vars', function ($vars) {

    $vars[] = 'mc_gocardless_subscribe';

    return $vars;

});

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

/**
 * Placeholder route
 */
add_action('init', function () {

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    $path = strtok($request_uri, '?');

    if (substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

   if ($path === '/gocardless-subscribe') {

        require_once ABSPATH . 'vendor/autoload.php';

        try {

            $client = new \GoCardlessPro\Client([
                'access_token' => mc_get_gocardless_access_token(),
                'environment'  => \GoCardlessPro\Environment::SANDBOX,
            ]);

            $session_token = wp_generate_password(32, false);

                update_option(
                    'mc_gc_test_session_token',
                    $session_token
                );

                $redirectFlow = $client->redirectFlows()->create([
                    "params" => [
                        "description" => "SourceMed Subscription",
                        "session_token" => $session_token,
                        "success_redirect_url" => home_url('/gocardless-success/')
                    ]
                ]);

            wp_redirect(
                $redirectFlow->redirect_url
            );

            exit;

        } catch (\Exception $e) {

            echo '<pre>';
            echo esc_html($e->getMessage());
            echo '</pre>';

        }

        exit;
    }

    if ($path === '/gocardless-cancel') {

        wp_redirect(
            home_url(
                '/pharmacy/subscription/?subscription_failed=1'
            )
        );

        exit;
    }

    if ($path === '/gocardless-success') {

        require_once ABSPATH . 'vendor/autoload.php';

        try {

            $redirect_flow_id = sanitize_text_field(
                $_GET['redirect_flow_id'] ?? ''
            );

            if (!$redirect_flow_id) {
                wp_die('Missing redirect_flow_id');
            }

            $session_token = get_option(
                'mc_gc_test_session_token'
            );

            $client = new \GoCardlessPro\Client([
                'access_token' => mc_get_gocardless_access_token(),
                'environment'  => \GoCardlessPro\Environment::SANDBOX,
            ]);

            $completed = $client->redirectFlows()->complete(
                $redirect_flow_id,
                [
                    "params" => [
                        "session_token" => $session_token
                    ]
                ]
            );

            $customer_id = $completed->links->customer ?? '';

            $mandate_id = $completed->links->mandate ?? '';

            $bank_account_id = $completed->links->customer_bank_account ?? '';

            $billing_request_id = $completed->links->billing_request ?? '';

            $user_id = get_current_user_id();

            $pharmacy_id = mc_get_pharmacy_id_by_user($user_id);

            if ($pharmacy_id) {

                update_post_meta(
                    $pharmacy_id,
                    '_mc_gocardless_customer_id',
                    $customer_id
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_gocardless_mandate_id',
                    $mandate_id
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_gocardless_billing_request_id',
                    $billing_request_id
                );

            }

            $subscription_amount = get_post_meta(
                $pharmacy_id,
                '_mc_subscription_amount',
                true
            );

            error_log(
                'GC SUBSCRIPTION AMOUNT: ' .
                print_r($subscription_amount, true)
            );

            $amount_in_pence = intval(
                round($subscription_amount * 100)
            );

            $subscription = $client->subscriptions()->create([
                "params" => [
                    "amount" => $amount_in_pence,
                    "currency" => "GBP",
                    "name" => "SourceMed Monthly Subscription",
                    "interval_unit" => "monthly",
                    "interval" => 1,
                    "links" => [
                        "mandate" => $mandate_id
                    ]
                ]
            ]);

            error_log(
                'GC SUBSCRIPTION OBJECT: ' .
                print_r($subscription, true)
            );

            if ($pharmacy_id && !empty($subscription->id)) {

                update_post_meta(
                    $pharmacy_id,
                    '_mc_gocardless_subscription_id',
                    $subscription->id
                );

                $subscription_start = !empty($subscription->start_date)
                    ? strtotime($subscription->start_date)
                    : 0;

                $next_billing_date = 0;

                if (
                    !empty($subscription->upcoming_payments) &&
                    !empty($subscription->upcoming_payments[0]->charge_date)
                ) {
                    $next_billing_date = strtotime(
                        $subscription->upcoming_payments[0]->charge_date
                    );
                }

                update_post_meta(
                    $pharmacy_id,
                    '_mc_subscription_period_start',
                    current_time('timestamp')
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_subscription_period_end',
                    0
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_next_billing_date',
                    $next_billing_date
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_subscription_currency',
                    $subscription->currency
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_subscription_status',
                    'active'
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_status',
                    'active'
                );

            }

            wp_redirect(
                home_url(
                    '/pharmacy/subscription/?subscription_success=1'
                )
            );

            exit; 

        } catch (\Exception $e) {

            error_log(
                'GC SUBSCRIPTION ERROR: ' .
                $e->getMessage()
            );

            wp_redirect(
                home_url(
                    '/pharmacy/subscription/?subscription_failed=1'
                )
            );

            exit;

        }

        exit;
    }

});

