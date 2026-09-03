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
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!empty($_SESSION['admin_authenticated'])) {
    $hasVersionedAdminSession = !empty($_SESSION['admin_id'])
        && !empty($_SESSION['admin_role'])
        && (int) ($_SESSION['admin_session_version'] ?? 0) > 0;
    $adminSessionExpired = (int) ($_SESSION['admin_expires_at'] ?? 0) <= time();
    if (!$hasVersionedAdminSession || $adminSessionExpired) {
        foreach ([
            'admin_id',
            'admin_role',
            'admin_session_version',
            'admin_issued_at',
            'admin_expires_at',
            'admin_authenticated',
            'admin_user',
        ] as $adminSessionKey) {
            unset($_SESSION[$adminSessionKey]);
        }
    }
}

if (isset($_SESSION['user'])) {
    $userSessionExpired = (
        (int) ($_SESSION['user_session_expires_at'] ?? PHP_INT_MAX) <= time()
        || (int) ($_SESSION['user_session_idle_expires_at'] ?? PHP_INT_MAX) <= time()
    );
    if ($userSessionExpired) {
        \App\Helpers::clearUserSession();
    }
}
