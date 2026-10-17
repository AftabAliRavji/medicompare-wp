<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pretty URL:
 * /pharmacy/gocardless/webhook/
 */
add_action('init', function () {

    add_rewrite_rule(
        '^gocardless/webhook/?$',
        'index.php?mc_gocardless_webhook=1',
        'top'
    );

});

/**
 * Query var
 */
add_filter('query_vars', function ($vars) {

    $vars[] = 'mc_gocardless_webhook';

    return $vars;

});

/**
 * Route webhook requests
 */
add_action('template_redirect', function () {

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    error_log('GC REQUEST: ' . $request_uri);

    if (
        strpos($request_uri, '/gocardless-webhook') !== false
    ) {

        error_log('GC WEBHOOK URL MATCHED');

        mc_handle_gocardless_webhook();

        exit;
    }

});

/**
  * helper function to get the subscription id for the pharmacy
  */
function mc_get_pharmacy_by_gocardless_subscription(
    $subscription_id
) {

    global $wpdb;

    $pharmacy_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_mc_gocardless_subscription_id'
            AND meta_value = %s
            LIMIT 1
            ",
            $subscription_id
        )
    );

    return $pharmacy_id
        ? (int) $pharmacy_id
        : 0;
}

 /**
  * helper function to get mandate_id for the pharmacy
  */

   function mc_get_pharmacy_by_gocardless_mandate(
    $mandate_id
) {

    global $wpdb;

    $pharmacy_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_mc_gocardless_mandate_id'
            AND meta_value = %s
            LIMIT 1
            ",
            $mandate_id
        )
    );

    return $pharmacy_id
        ? (int) $pharmacy_id
        : 0;

}

/**
 * helper function to get the billing request id for the pharmacy
 */
function mc_get_pharmacy_by_gocardless_billing_request( $billing_request_id ) { 
    global $wpdb; 
    
    $pharmacy_id = $wpdb->get_var( 
        $wpdb->prepare( 
            " SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mc_gocardless_billing_request_id' AND meta_value = %s LIMIT 1 ", 
            $billing_request_id 
        ) 
    ); 
    return $pharmacy_id ? (int) $pharmacy_id : 0;
}

/**
 * helper function to get the get pharmacy from the gocardless payment id
 */
function mc_get_pharmacy_by_gocardless_payment( $payment_id ) { 
    global $wpdb; 
    $pharmacy_id = $wpdb->get_var( $wpdb->prepare( 
        " SELECT pharmacy_id FROM {$wpdb->prefix}medi_gocardless_events WHERE resource_id = %s AND pharmacy_id > 0 LIMIT 1 ", $payment_id ) 
    ); 
    return $pharmacy_id ? (int) $pharmacy_id : 0; 
}

    /**
     * Update pharmacy subscription status
     */
    function mc_update_subscription_status(
        $pharmacy_id,
        $status,
        $restricted = 'no'
    ) {

        if (!$pharmacy_id) {
            return;
        }

        update_post_meta(
            $pharmacy_id,
            '_mc_subscription_status',
            $status
        );

        update_post_meta(
            $pharmacy_id,
            '_mc_account_restricted',
            $restricted
        );

        error_log(
            "GC STATUS UPDATED: Pharmacy {$pharmacy_id} => {$status}"
        );
    }

    /**
     * Send admin alert email
     */
    function mc_send_gocardless_alert_email(
    $pharmacy_id,
    $event_type,
    $message
    ) {

        if (!$pharmacy_id) {
            return;
        }

        $mailer = new MediCompare_Email_Engine();

        $mailer->send_gocardless_alert(
            $pharmacy_id,
            $event_type,
            $message
        );
    }

    /**
     * Write pharmacy subscription audit log
     */
    function mc_write_gocardless_audit(
        $pharmacy_id,
        $event_type,
        $message
    ) {

        if (!$pharmacy_id) {
            return;
        }

        $logs = get_post_meta(
            $pharmacy_id,
            '_mc_subscription_audit_log',
            true
        );

        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'timestamp' => time(),
            'message'   => '[GoCardless] ' . $message
        ];

        update_post_meta(
            $pharmacy_id,
            '_mc_subscription_audit_log',
            $logs
        );

        error_log(
            "GC AUDIT WRITTEN: {$event_type} | Pharmacy {$pharmacy_id}"
        );
    }


/**
 * Main webhook handler
 */
function mc_handle_gocardless_webhook() {

    $payload = file_get_contents('php://input');

    $signature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';

    $expected_signature = hash_hmac(
        'sha256',
        $payload,
        mc_get_gocardless_webhook_secret()
    );

    if (
        !hash_equals(
            $expected_signature,
            $signature
        )
    ) {

        error_log(
            'GC WEBHOOK INVALID SIGNATURE'
        );

        status_header(498);

        echo 'Invalid signature';

        return;
    }

    $data = json_decode(
        $payload,
        true
    );

    error_log(
        'GC WEBHOOK RECEIVED: ' .
        print_r($data, true)
    );

    if (!empty($data['events'])) {

        foreach ($data['events'] as $event) {

            $resource_type = $event['resource_type'] ?? '';

            $action = $event['action'] ?? '';

            error_log(
                "GC EVENT: {$resource_type} {$action}"
            );

           $pharmacy_id = 0; 
           if ( !empty($event['links']['subscription']) ) { 
            $pharmacy_id = mc_get_pharmacy_by_gocardless_subscription( $event['links']['subscription'] ); 
            } 
            if ( !$pharmacy_id && !empty($event['links']['mandate']) ) { 
                $pharmacy_id = mc_get_pharmacy_by_gocardless_mandate( $event['links']['mandate'] ); 
            } 
            if ( !$pharmacy_id && !empty($event['links']['billing_request']) ) { 
                $pharmacy_id = mc_get_pharmacy_by_gocardless_billing_request( $event['links']['billing_request'] ); 
            } 
            if ( !$pharmacy_id && !empty($event['links']['payment']) ) { 
                $pharmacy_id = mc_get_pharmacy_by_gocardless_payment( $event['links']['payment'] ); 
            }

            mc_gocardless_log_event(
                $pharmacy_id,
                $event,
                $payload
            );

		if ($resource_type === 'payments') {

    		error_log(
        	'GC PAYMENT ACTION: ' .
        	$action
    		);

		}

        if ( $resource_type === 'payments' && $action === 'created' ) { 
            $payment_id = $event['links']['payment'] ?? ''; 
            if ( $pharmacy_id && $payment_id ) { 
                update_post_meta( $pharmacy_id, '_mc_gocardless_payment_id', $payment_id ); 
                error_log( 'GC PAYMENT STORED: ' . $payment_id ); 
            } 
        }

        if ( $resource_type === 'payments' && $action === 'confirmed' ) 
            { 
                $payment_id = $event['links']['payment'] ?? ''; 
                error_log( 'GC PAYMENT CONFIRMED: ' . $payment_id ); 
                error_log( 'GC PAYMENT CONFIRMED PHARMACY: ' . $pharmacy_id );

                if ($pharmacy_id) { 
                    update_post_meta( $pharmacy_id, '_mc_last_payment_id', $payment_id ); 
                    update_post_meta( $pharmacy_id, '_mc_last_payment_status', 'confirmed' ); 
                    update_post_meta( $pharmacy_id, '_mc_last_payment_date', current_time('timestamp') 
                    ); 
                } 
            }

            if ( $resource_type === 'payments' && $action === 'paid_out' ) 
                { 
                    $payment_id = $event['links']['payment'] ?? ''; 
                    error_log( 'GC PAYMENT PAID OUT: ' . $action );
                    error_log( 'GC PAYMENT PAID OUT PHARMACY: ' . $pharmacy_id );

                    if ($pharmacy_id) { 
                        update_post_meta( $pharmacy_id, '_mc_last_payout_id', $payment_id ); 
                        update_post_meta( $pharmacy_id, '_mc_last_payout_date', current_time('timestamp') 
                        ); 
                    } 
                }


            if (
                $resource_type === 'subscriptions' &&
                $action === 'created') 
            {

                error_log('GC SUBSCRIPTION CREATED BLOCK ENTERED');	

                    $subscription_id =
                        $event['links']['subscription'] ?? '';

                error_log(
                    'GC SUBSCRIPTION ID: ' .
                    $subscription_id
                );

                    if ($subscription_id) {

                        $pharmacy_id =
                            mc_get_pharmacy_by_gocardless_subscription(
                                $subscription_id
                        );

                        error_log(
                            'GC PHARMACY ID: ' .
                            $pharmacy_id
                        );

                        if ($pharmacy_id) {

                            mc_update_subscription_status(
                                $pharmacy_id,
                                'active',
                                'no'
                            );

                            update_post_meta(
                                $pharmacy_id,
                                '_mc_status',
                                'active'
                            );

                            mc_write_gocardless_audit(
                                $pharmacy_id,
                                'subscription_active',
                                'Subscription created and active'
                            );

                            error_log(
                                "GC ACTIVE PHARMACY {$pharmacy_id}"
                            );
                        }
                    }
            }

            if (
                $resource_type === 'subscriptions'
                &&
                $action === 'payment_created'
            ) {

                $subscription_id =
                    $event['links']['subscription'] ?? '';

                error_log(
                    'GC SUBSCRIPTION PAYMENT CREATED: ' .
                    $subscription_id
                );

                if (
                    $pharmacy_id
                    &&
                    $subscription_id
                ) {

                    try {

                        require_once ABSPATH . 'vendor/autoload.php';

                        $client = new \GoCardlessPro\Client([
                            'access_token' => mc_get_gocardless_access_token(),
                            'environment'  => mc_get_gocardless_environment(),
                        ]);

                        $subscription =
                            $client
                                ->subscriptions()
                                ->get(
                                    $subscription_id
                                );

                        if (
                            !empty(
                                $subscription->upcoming_payments
                            )
                            &&
                            !empty(
                                $subscription->upcoming_payments[0]->charge_date
                            )
                        ) {

                            $next_billing_date =
                                strtotime(
                                    $subscription
                                        ->upcoming_payments[0]
                                        ->charge_date
                                );

                            /*
                            Temporarily disabled.

                            The subscription API currently returns
                            the next future cycle (e.g. Nov) instead
                            of the currently pending payment (e.g. Oct).

                            Awaiting confirmation of renewal behaviour.

                            update_post_meta(
                                $pharmacy_id,
                                '_mc_subscription_last_refresh',
                                current_time('timestamp')
                            );

                            update_post_meta(
                                $pharmacy_id,
                                '_mc_next_billing_date',
                                $next_billing_date
                            );
                            */

                            error_log(
                                'GC NEXT BILLING DATE UPDATED: ' .
                                date(
                                    'Y-m-d',
                                    $next_billing_date
                                )
                            );

                        }

                    } catch (\Exception $e) {

                        error_log(
                            'GC SUBSCRIPTION REFRESH ERROR: ' .
                            $e->getMessage()
                        );

                    }

                }

            }

            if (
                $resource_type === 'payments'
                &&
                $action === 'failed'
            ) {

                error_log(
                    'GC PAYMENT FAILED: ' .
                    $pharmacy_id
                );

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'payment_failed',
                        'yes'
                    );

                    update_post_meta(
                        $pharmacy_id,
                        '_mc_last_payment_failure_date',
                        current_time('timestamp')
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'payment_failed',
                        'Recurring payment failed'
                    );

                    mc_send_gocardless_alert_email(
                        $pharmacy_id,
                        'Payment Failed',
                        'GoCardless recurring payment failed.'
                    );
                }
            }

            if (
                $resource_type === 'mandates'
                &&
                $action === 'cancelled'
            ) {

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'mandate_cancelled',
                        'yes'
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'mandate_cancelled',
                        'Mandate cancelled'
                    );

                    mc_send_gocardless_alert_email(
                        $pharmacy_id,
                        'Mandate Cancelled',
                        'Mandate was cancelled in GoCardless.'
                    );
                }
            }

            if (
                $resource_type === 'mandates'
                &&
                $action === 'failed'
            ) {

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'mandate_failed',
                        'yes'
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'mandate_failed',
                        'Mandate failed'
                    );

                    mc_send_gocardless_alert_email(
                        $pharmacy_id,
                        'Mandate Failed',
                        'Mandate failed in GoCardless.'
                    );
                }
            }

            if (
                $resource_type === 'subscriptions'
                &&
                $action === 'cancelled'
            ) {

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'subscription_cancelled',
                        'yes'
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'subscription_cancelled',
                        'Subscription cancelled'
                    );

                    mc_send_gocardless_alert_email(
                        $pharmacy_id,
                        'Subscription Cancelled',
                        'Subscription was cancelled.'
                    );
                }
            }

            if (
                $resource_type === 'subscriptions'
                &&
                $action === 'expired'
            ) {

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'expired',
                        'yes'
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'subscription_expired',
                        'Subscription expired'
                    );
                }
            }

            if (
                $resource_type === 'subscriptions'
                &&
                $action === 'active'
            ) {

                if ($pharmacy_id) {

                    mc_update_subscription_status(
                        $pharmacy_id,
                        'active',
                        'no'
                    );

                    mc_write_gocardless_audit(
                        $pharmacy_id,
                        'subscription_active',
                        'Subscription active'
                    );
                }
            }

        }

    }

    status_header(200);

    echo 'OK';
}

    function mc_gocardless_log_event(
    $pharmacy_id,
    $event,
    $payload = null) 
    {

        global $wpdb;

        $table =
            $wpdb->prefix .
            'medi_gocardless_events';

        error_log(
            'GC AUDIT FUNCTION HIT'
        );

        error_log(
            'GC AUDIT TABLE: ' .
            $table
        );

        $resource_id = '';

        if (!empty($event['links']['payment'])) {
            $resource_id = $event['links']['payment'];
        }
        elseif (!empty($event['links']['subscription'])) {
            $resource_id = $event['links']['subscription'];
        }
        elseif (!empty($event['links']['mandate'])) {
            $resource_id = $event['links']['mandate'];
        }
        elseif (!empty($event['links']['billing_request'])) {
            $resource_id = $event['links']['billing_request'];
        }
        elseif (!empty($event['links']['payout'])) {
            $resource_id = $event['links']['payout'];
        }
        elseif (!empty($event['links']['billing_request_flow'])) {
            $resource_id = $event['links']['billing_request_flow'];
        }

        $pretty_payload = null;

        if (!empty($payload)) {

            $decoded_payload = json_decode(
                $payload,
                true
            );

            if ($decoded_payload) {

                $pretty_payload = wp_json_encode(
                    $decoded_payload,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                );
            }
        }

        error_log(
            'GC AUDIT INSERT START'
        );

        $wpdb->insert(
            $table,
            [
                'pharmacy_id' =>
                    $pharmacy_id,

                'event_id' =>
                    $event['id'] ?? '',

                'resource_type' =>
                    $event['resource_type'] ?? '',

                'action_name' =>
                    $event['action'] ?? '',

                'resource_id' =>
                    $resource_id,

                'event_created_at' =>
                    !empty($event['created_at'])
                        ? gmdate(
                            'Y-m-d H:i:s',
                            strtotime($event['created_at'])
                        )
                        : null,

                'raw_payload' =>
                    wp_json_encode(
                        $event,
                        JSON_UNESCAPED_SLASHES |
                        JSON_UNESCAPED_UNICODE
                    ),

                'webhook_payload' =>
                    $pretty_payload,

                'created_at' =>
                    current_time('mysql')
            ]
        );

        error_log(
            'GC AUDIT INSERT ID: ' .
            $wpdb->insert_id
        );

        error_log(
            'GC AUDIT LAST ERROR: ' .
            $wpdb->last_error
        );
    }