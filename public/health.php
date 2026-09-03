<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$requiredFiles = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/src/Bootstrap.php',
    __DIR__ . '/admin/dashboard.php',
    __DIR__ . '/api/login.php',
];
foreach ($requiredFiles as $requiredFile) {
    if (!is_file($requiredFile) || !is_readable($requiredFile)) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'release_incomplete']);
        exit;
    }
}

require_once __DIR__ . '/vendor/autoload.php';

$manifestPath = __DIR__ . '/release-manifest.json';
$manifestRaw = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
$manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
if (!is_array($manifest) || empty($manifest['release'])) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'release_manifest_missing']);
    exit;
}

define('APP_HEALTH_CHECK', true);
require_once __DIR__ . '/src/Bootstrap.php';

foreach (['SUPABASE_URL', 'SUPABASE_SERVICE_ROLE_KEY', 'ASAAS_API_KEY'] as $requiredEnvironmentKey) {
    $value = \App\Env::get($requiredEnvironmentKey, '');
    if (!is_string($value) || trim($value) === '') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'environment_incomplete']);
        exit;
    }
}

echo json_encode([
    'ok' => true,
    'release' => (string) $manifest['release'],
], JSON_UNESCAPED_SLASHES);
