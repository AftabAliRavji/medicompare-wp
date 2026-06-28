<?php

error_log("REQ ENDPOINT LOADED");


// SAVE BOARD STATE
add_action('wp_ajax_save_requirements_board', 'mc_save_requirements_board');
function mc_save_requirements_board() {
    global $wpdb;
    $table = $wpdb->prefix . 'medi_requirements_board';

    if (!isset($_POST['board_json'])) {
        wp_send_json_error(['message' => 'Missing board_json']);
    }

    // WordPress escapes JSON, so unescape it
    $json = wp_unslash($_POST['board_json']);

    if (json_decode($json) === null) {
        wp_send_json_error(['message' => 'Invalid JSON']);
    }

    // Keep only one row
    $wpdb->query("DELETE FROM {$table}");

    $wpdb->insert($table, [
        'board_json' => $json,
        'updated_at' => current_time('mysql')
    ]);

    wp_send_json_success(['message' => 'Board saved']);
}






// LOAD BOARD STATE
add_action('wp_ajax_load_requirements_board', 'mc_load_requirements_board');
function mc_load_requirements_board() {
    global $wpdb;
    $table = $wpdb->prefix . 'medi_requirements_board';

    $row = $wpdb->get_row("SELECT board_json FROM {$table} ORDER BY id DESC LIMIT 1");

    if ($row) {
        wp_send_json_success(['board_json' => $row->board_json]);
    } else {
        wp_send_json_success(['board_json' => null]);
    }
}
