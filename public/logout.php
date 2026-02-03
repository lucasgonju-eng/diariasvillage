<?php
require_once __DIR__ . '/../src/Bootstrap.php';

$_SESSION = [];
session_destroy();
header('Location: /');
exit;
