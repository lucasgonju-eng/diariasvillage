<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "health: ok\n";

$base = dirname(__DIR__);
$probe = $base . DIRECTORY_SEPARATOR . 'health_probe.txt';
file_put_contents($probe, 'ok ' . date('c') . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/src/Bootstrap.php';
echo "bootstrap: ok\n";
