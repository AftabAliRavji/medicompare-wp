<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {

    if (!current_user_can('manage_options')) {
        return;
    }

    try {

        require_once ABSPATH . 'vendor/autoload.php';

        $client = new \GoCardlessPro\Client([
            'access_token' => mc_get_gocardless_access_token(),
            'environment'  => \GoCardlessPro\Environment::SANDBOX,
        ]);

        error_log('GC SDK LOADED SUCCESSFULLY');

    } catch (Exception $e) {

        error_log(
            'GC SDK FAILED: ' .
            $e->getMessage()
        );

    }

});