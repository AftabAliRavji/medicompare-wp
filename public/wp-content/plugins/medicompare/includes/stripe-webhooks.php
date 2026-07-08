<?php

if (!defined('ABSPATH')) exit;

/**
 * Pretty URL: /pharmacy/stripe/webhook/
 */
add_action('init', function () {
    add_rewrite_rule(
        '^pharmacy/stripe/webhook/?$',
        'index.php?mc_stripe_webhook=1',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'mc_stripe_webhook';
    return $vars;
});

add_action('template_redirect', function () {

    if (!get_query_var('mc_stripe_webhook')) {
        return;
    }

    mc_handle_stripe_webhook();
    exit;
});


/**
 * Store subscription status in user meta
 */
function mc_set_subscription_status($user_id, $status) {
    update_user_meta($user_id, 'mc_subscription_status', $status);
}


/**
 * MAIN WEBHOOK HANDLER
 */
function mc_handle_stripe_webhook() {

    // Stripe sends JSON
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    require_once ABSPATH . 'vendor/autoload.php';

    $endpoint_secret = mc_get_stripe_webhook_secret();

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $endpoint_secret
        );
    } catch (\Exception $e) {
        status_header(400);
        echo 'Webhook signature verification failed.';
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'medi_subscriptions';

    $type   = $event->type;
    $object = $event->data->object;

    /**
     * Identify WP user from Stripe customer ID
     */
    $customer_id = $object->customer ?? null;

    if ($customer_id) {

        $users = get_users([
            'meta_key'   => 'mc_stripe_customer_id',
            'meta_value' => $customer_id,
            'number'     => 1
        ]);

        if (!empty($users)) {
            $wp_user_id = $users[0]->ID;
        } else {
            $wp_user_id = null;
        }
    } else {
        $wp_user_id = null;
    }


    /**
     * HANDLE EVENTS
     */

    switch ($type) {

        /**
         * Subscription created (first payment succeeded)
         */
        case 'customer.subscription.created':
            $subscription = $object;

            // Update DB
            $wpdb->update(
                $table,
                ['status' => 'active'],
                ['stripe_subscription' => $subscription->id]
            );

            // Update WP user meta
            if ($wp_user_id) {
                mc_set_subscription_status($wp_user_id, 'active');
            }

            break;


        /**
         * Subscription renewed (recurring payment succeeded)
         */
        case 'invoice.payment_succeeded':

            $invoice = $object;

            if (!empty($invoice->subscription)) {

                /**
                 * Expand invoice to get full billing cycle details
                 */
                $expandedInvoice = \Stripe\Invoice::retrieve([
                    'id'     => $invoice->id,
                    'expand' => ['lines.data', 'payment_intent']
                ]);

                $line = $expandedInvoice->lines->data[0];

                $period_start = $line->period->start ?? null;
                $period_end   = $line->period->end ?? null;

                $next_billing = $period_end;

                $last_payment_date   = $expandedInvoice->status_transitions->paid_at ?? $expandedInvoice->created;
                $last_payment_amount = $expandedInvoice->amount_paid ?? null;
                $last_payment_ref    = $expandedInvoice->id ?? null;

                $renewal_date = date('Y-m-d H:i:s', $period_end);

                /**
                 * Update DB
                 */
                $wpdb->update(
                    $table,
                    [
                        'renewal_date' => $renewal_date,
                        'status'       => 'active'
                    ],
                    ['stripe_subscription' => $invoice->subscription]
                );

                /**
                 * Update WP user meta
                 */
                if ($wp_user_id) {
                    mc_set_subscription_status($wp_user_id, 'active');
                }

                /**
                 * Update pharmacy postmeta (overlay + subscription details page)
                 */
                $pharmacy_id = mc_get_pharmacy_id_by_user($wp_user_id);

                if ($pharmacy_id) {

                    update_post_meta($pharmacy_id, '_mc_subscription_status', 'active');
                    update_post_meta($pharmacy_id, '_mc_status', 'active');

                    update_post_meta($pharmacy_id, '_mc_subscription_period_start', $period_start);
                    update_post_meta($pharmacy_id, '_mc_subscription_period_end', $period_end);
                    update_post_meta($pharmacy_id, '_mc_next_billing_date', $next_billing);

                    update_post_meta($pharmacy_id, '_mc_last_payment_date', $last_payment_date);
                    update_post_meta($pharmacy_id, '_mc_last_payment_amount', $last_payment_amount);
                    update_post_meta($pharmacy_id, '_mc_last_payment_reference', $last_payment_ref);

                    update_post_meta($pharmacy_id, '_mc_sub_end', $period_end);
                }
            }

            break;


        /**
         * Payment failed (card declined, expired, insufficient funds)
         */
        case 'invoice.payment_failed':
            $invoice = $object;

            if (!empty($invoice->subscription)) {

                // Update DB
                $wpdb->update(
                    $table,
                    ['status' => 'past_due'],
                    ['stripe_subscription' => $invoice->subscription]
                );

                // Update WP user meta
                if ($wp_user_id) {
                    mc_set_subscription_status($wp_user_id, 'past_due');
                }

                // Update pharmacy postmeta
                $pharmacy_id = mc_get_pharmacy_id_by_user($wp_user_id);
                if ($pharmacy_id) {
                    update_post_meta($pharmacy_id, '_mc_subscription_status', 'past_due');
                    update_post_meta($pharmacy_id, '_mc_status', 'past_due');
                }
            }

            break;


        /**
         * Subscription cancelled
         */
        case 'customer.subscription.deleted':
            $subscription = $object;

            // Update DB
            $wpdb->update(
                $table,
                ['status' => 'cancelled'],
                ['stripe_subscription' => $subscription->id]
            );

            // Update WP user meta
            if ($wp_user_id) {
                mc_set_subscription_status($wp_user_id, 'cancelled');
            }

            // Update pharmacy postmeta
            $pharmacy_id = mc_get_pharmacy_id_by_user($wp_user_id);
            if ($pharmacy_id) {
                update_post_meta($pharmacy_id, '_mc_subscription_status', 'cancelled');
                update_post_meta($pharmacy_id, '_mc_status', 'cancelled');
            }

            break;


        /**
         * Subscription updated (status changes)
         */
        case 'customer.subscription.updated':
            $subscription = $object;

            $status = $subscription->status; // active, past_due, canceled, incomplete

            // Update DB
            $wpdb->update(
                $table,
                ['status' => $status],
                ['stripe_subscription' => $subscription->id]
            );

            // Update WP user meta
            if ($wp_user_id) {
                mc_set_subscription_status($wp_user_id, $status);
            }

            // Update pharmacy postmeta
            $pharmacy_id = mc_get_pharmacy_id_by_user($wp_user_id);
            if ($pharmacy_id) {
                update_post_meta($pharmacy_id, '_mc_subscription_status', $status);
                update_post_meta($pharmacy_id, '_mc_status', $status);
            }

            break;
    }

    status_header(200);
    echo 'OK';
}
