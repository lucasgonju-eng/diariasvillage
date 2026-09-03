<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    return;
}

if ($requestPath === '/__e2e_health') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ok';
    return;
}

if (str_starts_with($requestPath, '/assets/')) {
    $assetsRoot = realpath($projectRoot . '/public/assets');
    $assetPath = realpath($projectRoot . '/public' . $requestPath);
    if (
        !is_string($assetsRoot)
        || !is_string($assetPath)
        || !str_starts_with($assetPath, $assetsRoot . DIRECTORY_SEPARATOR)
        || !is_file($assetPath)
    ) {
        http_response_code(404);
        return;
    }

    $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $contentTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'woff2' => 'font/woff2',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];
    header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
    readfile($assetPath);
    return;
}

if ($requestPath === '/api/admin-oficinas-current-month.php') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'offices' => []], JSON_THROW_ON_ERROR);
    return;
}

if ($requestPath === '/api/admin-attendance.php') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'items' => [], 'can_approve' => false], JSON_THROW_ON_ERROR);
    return;
}

if ($requestPath === '/admin/dashboard.php' || $requestPath === '/') {
    require __DIR__ . '/admin-dashboard-fixture.php';
    return;
}

http_response_code(404);
