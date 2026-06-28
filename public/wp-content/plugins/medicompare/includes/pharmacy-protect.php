<?php

if (!defined('ABSPATH')) exit;

/**
 * Protect all pharmacy pages so they cannot be accessed
 * unless the user is logged in AND has the pharmacy_user role.
 *
 * This runs BEFORE the page template loads, which prevents
 * the page from flashing or partially rendering.
 */

add_action('template_redirect', function () {

    // List of pharmacy page slugs to protect
    // Adjust these if your slugs differ
    $protected_slugs = [
        'dashboard',
        'edit-details',
        'search',
    ];

    // Only run on pages
    if (!is_page()) {
        return;
    }

    global $post;

    if (!$post) {
        return;
    }

    // Check if current page slug is protected
    if (!in_array($post->post_name, $protected_slugs, true)) {
        return;
    }

    // Must be logged in
    if (!is_user_logged_in()) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }

    // Must be a pharmacy user
    $user = wp_get_current_user();

    if (!in_array('pharmacy_user', (array) $user->roles, true)) {
        wp_redirect(site_url('/pharmacy/login/'));
        exit;
    }
});
