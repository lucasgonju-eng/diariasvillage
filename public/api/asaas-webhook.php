<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

$token = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
$expected = App\Env::get('ASAAS_WEBHOOK_TOKEN', '');

if ($expected && $token !== $expected) {
    Helpers::json(['ok' => false, 'error' => 'Token invalido.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
$event = $payload['event'] ?? '';
$payment = $payload['payment'] ?? [];

if (!$event || empty($payment['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Payload invalido.'], 400);
}

if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
    Helpers::json(['ok' => true]);
}

$client = new SupabaseClient(new HttpClient());
$paymentResult = $client->select('payments', 'select=*&asaas_payment_id=eq.' . urlencode($payment['id']));

if (!$paymentResult['ok'] || empty($paymentResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pagamento nao encontrado.'], 404);
}

$paymentRow = $paymentResult['data'][0];

$accessCode = $paymentRow['access_code'] ?: Helpers::randomCode(8);
$client->update('payments', 'id=eq.' . $paymentRow['id'], [
    'status' => 'paid',
    'paid_at' => date('c'),
    'access_code' => $accessCode,
]);

$guardianResult = $client->select('guardians', 'select=*&id=eq.' . $paymentRow['guardian_id']);
$guardian = $guardianResult['data'][0] ?? null;

if ($guardian) {
    $mailer = new Mailer();
    $mailer->send(
        $guardian['email'],
        'Pagamento confirmado - Diarias Village',
        '<p>Pagamento confirmado!</p>'
        . '<p>Codigo de acesso ao Village: <strong>' . $accessCode . '</strong></p>'
        . '<p>Comprovante: consulte seu painel na Asaas.</p>'
    );

    $secretaria = App\Env::get('EMAIL_SECRETARIA', '');
    $copia = App\Env::get('EMAIL_COPIA', '');

    if ($secretaria) {
        $mailer->send(
            $secretaria,
            'Pagamento confirmado - liberar estudante',
            '<p>Pagamento aprovado para a diaria do estudante.</p>'
            . '<p>Codigo: <strong>' . $accessCode . '</strong></p>',
            $copia ? [$copia] : []
        );
    }
}

Helpers::json(['ok' => true]);
