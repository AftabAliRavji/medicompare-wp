<?php

// Add body class to MediCompare pages
add_filter('body_class', function($classes) {

    global $post;

    // Safety: ensure $post exists
    if ($post) {

        // Get the slug of the current page
        $slug = $post->post_name;

        // Get the parent slug if it exists
        $parent_slug = $post->post_parent ? get_post_field('post_name', $post->post_parent) : null;

        // Detect pharmacy pages by slug, not ID
        if ($slug === 'login' && $parent_slug === 'pharmacy') {
            $classes[] = 'medicompare-app';
        }

        if ($slug === 'register' && $parent_slug === 'pharmacy') {
            $classes[] = 'medicompare-app';
        }

        if ($slug === 'dashboard' && $parent_slug === 'pharmacy') {
            $classes[] = 'medicompare-app';
        }

        if ($slug === 'search' && $parent_slug === 'pharmacy') {
            $classes[] = 'medicompare-app';
        }
    }

    // Detect plugin-driven pages using ?page=
    if (isset($_GET['page']) && strpos($_GET['page'], 'medicompare') !== false) {
        $classes[] = 'medicompare-app';
    }

    return $classes;
});


// Load CSS for hiding theme UI + pharmacy search UI
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

});
