<?php
error_log("Child theme functions.php loaded");

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'twentytwentyfive-child',
        get_stylesheet_uri(),
        [],
        '1.0'
    );
});

