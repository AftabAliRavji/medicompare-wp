<?php

/**
 * Add body class to MediCompare pages
 */
add_filter('body_class', function($classes) {

    global $post;

    // Safety: ensure $post exists
    if ($post) {

        // Get the slug of the current page
        $slug = $post->post_name;

        // Get the parent slug if it exists
        $parent_slug = $post->post_parent ? get_post_field('post_name', $post->post_parent) : null;

        // Detect pharmacy pages by slug, not ID
        if ($parent_slug === 'pharmacy') {

            $pharmacy_pages = [
                'login',
                'register',
                'dashboard',
                'search',
                'edit-details',
                'pharmacy-registration',
                'orders'
            ];

            if (in_array($slug, $pharmacy_pages)) {
                $classes[] = 'medicompare-app';
            }
        }
    }

    // Detect plugin-driven pages using ?page=
    if (isset($_GET['page']) && strpos($_GET['page'], 'medicompare') !== false) {
        $classes[] = 'medicompare-app';
    }

    return $classes;
});


/**
 * Load CSS for hiding theme UI + pharmacy search UI + pharmacy portal UI
 */
add_action('wp_enqueue_scripts', function() {

    // 1. Hide theme header/footer
    wp_enqueue_style(
        'medicompare-hide-theme',
        plugin_dir_url(__FILE__) . '../../assets/css/hide-theme-elements.css',
        [],
        '1.0'
    );

    // 2. Pharmacy search UI CSS
    wp_enqueue_style(
        'mc-pharmacy-search',
        plugin_dir_url(__FILE__) . '../../assets/css/pharmacy-search.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/css/pharmacy-search.css')
    );


    // 3. Pharmacy portal CSS (⭐ FINAL WORKING VERSION)
    wp_enqueue_style(
        'mc-pharmacy-portal',
        plugin_dir_url(dirname(__FILE__, 2)) . 'assets/css/pharmacy-portal.css',
        [],
        filemtime(plugin_dir_path(dirname(__FILE__, 2)) . 'assets/css/pharmacy-portal.css')
    );


});


/**
 * Redirect pharmacy login → pharmacy portal
 */
add_action('template_redirect', function () {

    // If user visits /pharmacy/login → redirect to /pharmacy/
    if (is_page('pharmacy/login')) {
        wp_redirect('/pharmacy/');
        exit;
    }
});


/**
 * Redirect logged-in users away from /pharmacy/login
 */
add_action('template_redirect', function () {

    if (is_user_logged_in() && is_page('pharmacy/login')) {
        wp_redirect('/pharmacy/dashboard/');
        exit;
    }
});
