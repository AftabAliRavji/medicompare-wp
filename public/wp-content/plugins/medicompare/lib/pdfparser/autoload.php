<?php

spl_autoload_register(function ($class) {

    // Only autoload Smalot PDF Parser classes
    if (strpos($class, 'Smalot\\PdfParser') !== 0) {
        return;
    }

    // Convert namespace to file path
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    // Build full path inside the plugin
    $file = plugin_dir_path(__FILE__) . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
