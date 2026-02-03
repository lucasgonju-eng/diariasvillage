<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Env;

Env::load(dirname(__DIR__));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
