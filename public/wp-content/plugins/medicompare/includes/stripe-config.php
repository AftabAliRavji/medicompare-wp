<?php

//
// SECRET KEY
//
function mc_get_stripe_secret_key() {

    if (MC_ENV === 'production') {
        return MC_STRIPE_SECRET_KEY_PROD;
    }

    if (MC_ENV === 'test') {
        return MC_STRIPE_SECRET_KEY_TEST;
    }

    return MC_STRIPE_SECRET_KEY_LOCAL; // development
}

//
// PUBLISHABLE KEY
//
function mc_get_stripe_publishable_key() {

    if (MC_ENV === 'production') {
        return MC_STRIPE_PUBLISHABLE_KEY_PROD;
    }

    if (MC_ENV === 'test') {
        return MC_STRIPE_PUBLISHABLE_KEY_TEST;
    }

    return MC_STRIPE_PUBLISHABLE_KEY_LOCAL; // development
}

//
// PRICE ID
//
function mc_get_stripe_price_id() {

    if (MC_ENV === 'production') {
        return MC_STRIPE_PRICE_ID_PROD;
    }

    if (MC_ENV === 'test') {
        return MC_STRIPE_PRICE_ID_TEST;
    }

    return MC_STRIPE_PRICE_ID_LOCAL; // development
}

//
// WEBHOOK SECRET
//
function mc_get_stripe_webhook_secret() {

    if (MC_ENV === 'development') {
        return MC_STRIPE_WEBHOOK_SECRET_LOCAL;
    }

    if (MC_ENV === 'test') {
        return MC_STRIPE_WEBHOOK_SECRET_TEST;
    }

    if (MC_ENV === 'production') {
        return MC_STRIPE_WEBHOOK_SECRET_PROD;
    }

    return ''; // fallback
}
