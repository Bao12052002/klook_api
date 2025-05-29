<?php
// Autoload classes

spl_autoload_register(function ($class) {
    $directories = [
        'core/',
        'controllers/',
        'models/',
        'middleware/',
        'helpers/'
    ];
    
    foreach ($directories as $directory) {
        $file = __DIR__ . '/' . $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $env = parse_ini_file(__DIR__ . '/../.env');
        if ($env === false) {
            throw new Exception('Error parsing .env file');
        }
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    } catch (Exception $e) {
        error_log('Environment file error: ' . $e->getMessage());
    }
}
// Load config files
require_once 'config/database.php';
require_once 'config/auth.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/api.log');

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');