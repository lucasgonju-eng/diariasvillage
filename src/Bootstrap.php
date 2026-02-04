<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;

Env::load(dirname(__DIR__));

ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_log_custom.txt');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
