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

            if (
                $resource_type === 'subscriptions' &&
                $action === 'created'
            ) 
       {

        $subscription_id =
            $event['links']['subscription'] ?? '';

            if ($subscription_id) {

                $pharmacy_id =
                    mc_get_pharmacy_by_gocardless_subscription(
                        $subscription_id
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