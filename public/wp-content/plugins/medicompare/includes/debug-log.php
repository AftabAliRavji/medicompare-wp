<?php
if (!defined('ABSPATH')) exit;

class MC_Debug_Log {

    private static $enabled = true; // toggle ON/OFF

    public static function log($message, $data = null) {
        if (!self::$enabled) return;

        $log_entry = "[" . date('Y-m-d H:i:s') . "] " . $message;

        if ($data !== null) {
            $log_entry .= " | " . print_r($data, true);
        }

        error_log($log_entry);
    }
}
