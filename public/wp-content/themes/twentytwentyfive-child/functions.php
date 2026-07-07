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

add_action('init', function () {
    global $wp_rewrite;
    error_log("REWRITE RULES:");
    error_log(print_r($wp_rewrite->rules, true));
});

add_filter('rewrite_rules_array', function ($rules) {
    error_log("REWRITE RULES ARRAY FILTER HIT, COUNT = " . count($rules));
    error_log(print_r($rules, true));   // <-- ADD THIS LINE
    return $rules;
});



add_action('template_include', function ($template) {
    error_log('TEMPLATE USED: ' . $template);
    return $template;
});


