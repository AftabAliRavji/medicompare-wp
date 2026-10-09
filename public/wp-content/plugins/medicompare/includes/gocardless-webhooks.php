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

            error_log(
                'GC EVENT TYPE: ' .
                ($event['action'] ?? 'unknown')
            );

        }

    }

    status_header(200);

    echo 'OK';
}