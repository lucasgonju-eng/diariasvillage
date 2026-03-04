<?php
$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$user = Helpers::requireAuth();
$client = new SupabaseClient(new HttpClient());

$studentId = trim((string) ($user['student_id'] ?? ''));
if ($studentId === '') {
    $userId = trim((string) ($user['id'] ?? ''));
    if ($userId !== '') {
        $currentResult = $client->select(
            'guardians',
            'select=student_id&id=eq.' . urlencode($userId) . '&limit=1'
        );
        $current = $currentResult['data'][0] ?? null;
        $studentId = trim((string) ($current['student_id'] ?? ''));
    }
}
if ($studentId === '') {
    $userEmail = strtolower(trim((string) ($user['email'] ?? '')));
    if ($userEmail !== '') {
        $currentByEmail = $client->select(
            'guardians',
            'select=student_id&email=eq.' . urlencode($userEmail) . '&limit=1'
        );
        $current = $currentByEmail['data'][0] ?? null;
        $studentId = trim((string) ($current['student_id'] ?? ''));
    }
}
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno vinculado não encontrado para esta conta.'], 422);
}

$result = $client->select(
    'guardians',
    'select=id,parent_name,email,parent_phone,parent_document,created_at,verified_at&student_id=eq.' . urlencode($studentId) . '&order=created_at.desc'
);
if (!($result['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao buscar responsáveis.'], 500);
}

$guardians = is_array($result['data'] ?? null) ? $result['data'] : [];
Helpers::json(['ok' => true, 'guardians' => $guardians]);
