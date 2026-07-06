<?php

/**
 * Add body class to MediCompare pages
 */
add_filter('body_class', function($classes) {

    global $post;

    if ($post) {

        $slug = $post->post_name;
        $parent_slug = $post->post_parent ? get_post_field('post_name', $post->post_parent) : null;

        // Detect pharmacy pages by slug
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
 * Hide theme header/footer on ALL /pharmacy/ pages
 */
add_action('wp', function () {

    if (is_admin()) return;

    $pharmacy_pages = [
        'pharmacy',
        'pharmacy/login',
        'pharmacy/search',
        'pharmacy/dashboard',
        'pharmacy/orders',
        'pharmacy/pharmacy-registration',
        'pharmacy/pharmacy-claim',
        'pharmacy/edit-details'
    ];

    foreach ($pharmacy_pages as $slug) {
        if (is_page($slug)) {

            // Hide admin bar
            add_filter('show_admin_bar', '__return_false');

            // Hide theme header/footer (but keep theme CSS)
            add_action('wp_head', function () {
                echo '<style>
                    header, .site-header, .main-header,
                    footer, .site-footer, .main-footer {
                        display: none !important;
                    }
                </style>';
            }, 999);

            break;
        }
    }
});



/**
 * Load CSS for hiding theme UI + pharmacy search UI + pharmacy portal UI
 */
add_action('wp_enqueue_scripts', function() {

    // 1. Hide theme header/footer (structural only)
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

    // 3. Pharmacy portal CSS
    wp_enqueue_style(
        'mc-pharmacy-portal',
        plugin_dir_url(dirname(__FILE__, 2)) . 'assets/css/pharmacy-portal.css',
        [],
        filemtime(plugin_dir_path(dirname(__FILE__, 2)) . 'assets/css/pharmacy-portal.css')
    );

    // ⭐ 4. Subscription modal JS (NEW)
    wp_enqueue_script(
        'mc-subscription-modal',
        plugin_dir_url(dirname(__FILE__, 2)) . 'assets/js/subscription-modal.js',
        ['jquery'],
        '1.0',
        true
    );

});



/**
 * Redirect pharmacy login → pharmacy portal
 */
add_action('template_redirect', function () {

    if (is_admin()) return;

    if (is_user_logged_in() && is_page('pharmacy/login')) {
        wp_redirect('/pharmacy/search/');
        exit;
    }
});



/**
 * Redirect logged-out users away from /pharmacy/login
 */
add_action('template_redirect', function () {

    if (is_admin()) return;

    if (is_page('pharmacy/login')) {
        wp_redirect('/pharmacy/');
        exit;
    }

    // Only redirect the homepage
    if (is_front_page() || is_home()) {
        wp_redirect(site_url('/pharmacy'));
        exit;
    }
});