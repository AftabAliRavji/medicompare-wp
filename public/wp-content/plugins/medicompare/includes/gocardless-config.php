<?php
 if (!defined('ABSPATH')) {
     exit; 
 } 
 
 function mc_get_gocardless_access_token() { 
    if (MC_ENV === 'test') { 
        return MC_GOCARDLESS_ACCESS_TOKEN_TEST; 
    } 
    
    return MC_GOCARDLESS_ACCESS_TOKEN_LOCAL; 
 } 
 
 function mc_get_gocardless_webhook_secret() { 
    if (MC_ENV === 'test') { 
        return MC_GOCARDLESS_WEBHOOK_SECRET_TEST; 
    } 
    
    return MC_GOCARDLESS_WEBHOOK_SECRET_LOCAL; 
 }

    /**
     * Creditor
     */
    function mc_get_gocardless_creditor_id() {

        if (MC_ENV === 'test') {
            return MC_GOCARDLESS_CREDITOR_ID_TEST;
        }

        return MC_GOCARDLESS_CREDITOR_ID_LOCAL;
    }

