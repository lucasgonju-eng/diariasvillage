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

$paymentId = isset($_GET['paymentId']) ? trim((string) $_GET['paymentId']) : '';
$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
if ($paymentId === '' && $diariaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Pagamento não informado.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$query = 'select=id,status,paid_at,access_code,diaria_id,payment_date,daily_type,amount'
    . '&guardian_id=eq.' . rawurlencode((string) $user['id'])
    . '&limit=1';
if ($paymentId !== '') {
    $query .= '&id=eq.' . rawurlencode($paymentId);
} else {
    $query .= '&diaria_id=eq.' . rawurlencode($diariaId) . '&order=created_at.desc';
}
$result = $client->select('payments', $query);

if (!($result['ok'] ?? false) || empty($result['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pagamento não encontrado.'], 404);
}

$payment = $result['data'][0];
$status = strtolower((string) ($payment['status'] ?? 'pending'));
$isPaid = $status === 'paid' || !empty($payment['paid_at']);
$accessCode = trim((string) ($payment['access_code'] ?? ''));
$accessCodeValido = (bool) preg_match('/^\d{6}$/', $accessCode);

Helpers::json([
    'ok' => true,
    'payment' => [
        'id' => (string) ($payment['id'] ?? ''),
        'status' => $isPaid ? 'paid' : 'pending',
        'paid_at' => (string) ($payment['paid_at'] ?? ''),
        'access_code' => $accessCode,
        'access_code_valido' => $accessCodeValido,
        'diaria_id' => (string) ($payment['diaria_id'] ?? ''),
        'payment_date' => (string) ($payment['payment_date'] ?? ''),
        'daily_type' => (string) ($payment['daily_type'] ?? ''),
        'amount' => (float) ($payment['amount'] ?? 0),
    ],
    'redirect_to' => $isPaid ? ('/pagamento-sucesso.php?paymentId=' . rawurlencode((string) ($payment['id'] ?? ''))) : null,
]);
