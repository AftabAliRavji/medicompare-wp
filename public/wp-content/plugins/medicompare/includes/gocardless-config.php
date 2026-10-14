<?php
 if (!defined('ABSPATH')) {
     exit; 
 } 
 
 function mc_get_gocardless_access_token() {

    if (
        mc_get_gocardless_environment_name()
        ===
        'production'
    ) {

        return get_option(
            'mc_gocardless_production_access_token',
            ''
        );
    }

    return get_option(
        'mc_gocardless_sandbox_access_token',
        ''
    );
}
 
 function mc_get_gocardless_webhook_secret() {

    if (
        mc_get_gocardless_environment_name()
        ===
        'production'
    ) {

        return get_option(
            'mc_gocardless_production_webhook_secret',
            ''
        );
    }

    return get_option(
        'mc_gocardless_sandbox_webhook_secret',
        ''
    );
}

    /**
     * Creditor
     */
     function mc_get_gocardless_creditor_id() {

        if (mc_get_gocardless_environment_name() === 'production') {
            return get_option(
                'mc_gocardless_production_creditor_id',
                ''
            );
        }

        return get_option(
            'mc_gocardless_sandbox_creditor_id',
            ''
        );
    }

    /**
     * Environment helper
     */
    function mc_get_gocardless_environment_name() {

        return get_option(
            'mc_gocardless_environment',
            'sandbox'
        );
    }

    /**
     * SDK Environment Helper
     */
    function mc_get_gocardless_environment() {

    return mc_get_gocardless_environment_name() === 'production'
        ? \GoCardlessPro\Environment::LIVE
        : \GoCardlessPro\Environment::SANDBOX;
    }

