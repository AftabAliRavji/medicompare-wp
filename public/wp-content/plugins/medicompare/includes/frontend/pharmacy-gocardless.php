<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add pretty URL:
 * /gocardless-test/
 */
add_action('init', function () {

    add_rewrite_rule(
        '^gocardless-test/?$',
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
                        "description" => "SourceMed Subscription Test",
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

                update_post_meta(
                    $pharmacy_id,
                    '_mc_gocardless_subscription_id',
                    $subscription->id
                );

                update_post_meta(
                    $pharmacy_id,
                    '_mc_subscription_status',
                    'active'
                );

            }

            $subscription_amount = get_post_meta(
                $pharmacy_id,
                '_mc_subscription_amount',
                true
            );

            $amount_in_pence = intval(
                round($subscription_amount * 100)
            );

            echo '</pre>';
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

            echo '<h1>Redirect Flow Completed</h1>';

            echo '<h2>Subscription Amount</h2>';

            echo '<pre>';
            var_dump($subscription_amount);
            echo '</pre>';

            echo '<h2>Subscription Created</h2>';

            echo '<pre>';

            print_r([
                'subscription_id' => $subscription->id,
            ]);

            echo '</pre>';

            echo '<h2>Saved Values</h2>';

            echo '<pre>';

            print_r([
                'pharmacy_id'          => $pharmacy_id,
                'customer_id'          => $customer_id,
                'mandate_id'           => $mandate_id,
                'bank_account_id'      => $bank_account_id,
                'billing_request_id'   => $billing_request_id,
                'subscription_amount'  => $subscription_amount,
                'subscription_id'      => $subscription->id ?? '',
            ]);

            echo '</pre>';

        } catch (\Exception $e) {

            echo '<pre>';
            echo esc_html($e->getMessage());
            echo '</pre>';

        }

        exit;
    }

});