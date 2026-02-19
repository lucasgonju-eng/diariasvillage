<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;

$projectRoot = dirname(__DIR__);
$parentDir = dirname($projectRoot);
if (is_file($parentDir . DIRECTORY_SEPARATOR . '.env')) {
    Env::load($parentDir);
} else {
    Env::load($projectRoot);
}

ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'error_log_custom.txt');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
