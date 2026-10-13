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

            if (
                !empty($event['links']['subscription'])
            ) {

                $pharmacy_id =
                    mc_get_pharmacy_by_gocardless_subscription(
                        $event['links']['subscription']
                    );

            }

            if (
                !$pharmacy_id &&
                !empty($event['links']['mandate'])
            ) {

               if ( !$pharmacy_id && !empty($event['links']['billing_request']) ) { 
                $pharmacy_id = 
                    mc_get_pharmacy_by_gocardless_billing_request( 
                        $event['links']['billing_request'] 
                    ); 
                }  

                $pharmacy_id =
                    mc_get_pharmacy_by_gocardless_mandate(
                        $event['links']['mandate']
                    );

            }

            mc_gocardless_log_event(
                $pharmacy_id,
                $event
            );

		if ($resource_type === 'payments') {

    		error_log(
        	'GC PAYMENT ACTION: ' .
        	$action
    		);

		}

        if ( $resource_type === 'payments' && $action === 'confirmed' ) 
            { 
                $payment_id = $event['links']['payment'] ?? ''; 
                error_log( 'GC PAYMENT CONFIRMED: ' . $payment_id ); 
                
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

                            error_log(
                                "GC ACTIVE PHARMACY {$pharmacy_id}"
                            );
                        }
            }
       }
   }

 }

    status_header(200);

    echo 'OK';
}

    function mc_gocardless_log_event(
    $pharmacy_id,
    $event
) {

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

        $resource_id =
            $event['links']['payment'];

    } elseif (!empty($event['links']['subscription'])) {

        $resource_id =
            $event['links']['subscription'];

    } elseif (!empty($event['links']['mandate'])) {

        $resource_id =
            $event['links']['mandate'];

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

            'raw_payload' =>
                wp_json_encode($event),

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