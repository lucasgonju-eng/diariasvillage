<?php
require_once __DIR__ . '/src/Bootstrap.php';

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    echo 'Token inválido.';
    exit;
}

$client = new SupabaseClient(new HttpClient());
$tokenResult = $client->select('pendencia_tokens', 'select=*&token=eq.' . urlencode($token));
if (!$tokenResult['ok'] || empty($tokenResult['data'])) {
    echo 'Token inválido.';
    exit;
}

$tokenRow = $tokenResult['data'][0];
$expiresAt = strtotime($tokenRow['expires_at'] ?? '');
if ($expiresAt && $expiresAt < time()) {
    echo 'Token expirado.';
    exit;
}

$pendenciaId = $tokenRow['pendencia_id'];
$pendenciaResult = $client->select('pendencia_de_cadastro', 'select=*&id=eq.' . urlencode($pendenciaId));
if (!$pendenciaResult['ok'] || empty($pendenciaResult['data'])) {
    echo 'Pendência não encontrada.';
    exit;
}

$pendencia = $pendenciaResult['data'][0];
if (empty($pendencia['verified_at'])) {
    $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
        'verified_at' => date('c'),
    ]);
}

$guardianEmail = $pendencia['guardian_email'] ?? '';
$guardianName = $pendencia['guardian_name'] ?? 'Responsável';
$guardianCpf = $pendencia['guardian_cpf'] ?? '';

$invoiceUrl = $pendencia['asaas_invoice_url'] ?? '';
if ($invoiceUrl === '') {
    $asaas = new AsaasClient(new HttpClient());
    $customerPayload = [
        'name' => $guardianName,
        'email' => $guardianEmail,
    ];
    $documentDigits = preg_replace('/\D+/', '', $guardianCpf);
    if ($documentDigits !== '') {
        $customerPayload['cpfCnpj'] = $documentDigits;
    }

    $customer = $asaas->createCustomer($customerPayload);
    $customerId = $customer['data']['id'] ?? null;
    if ($customerId) {
        $payment = $asaas->createPayment([
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => 77.00,
            'dueDate' => date('Y-m-d'),
            'description' => 'Diaria planejada - pendencia cadastro - Einstein Village',
        ]);
        $paymentData = $payment['data'] ?? [];
        $invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? '';
        $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), [
            'asaas_payment_id' => $paymentData['id'] ?? null,
            'asaas_invoice_url' => $invoiceUrl ?: null,
        ]);
    }
}

if ($guardianEmail) {
    $mailer = new Mailer();
    $body = '<p>Olá!</p>'
        . '<p>Seu cadastro foi recebido e está pendente. A secretaria irá corrigir e confirmar os dados do aluno por e-mail.</p>'
        . '<p>Para garantir sua diária planejada, siga com o pagamento no link abaixo:</p>'
        . '<p><a href="' . htmlspecialchars($invoiceUrl ?: Helpers::baseUrl(), ENT_QUOTES, 'UTF-8') . '">Pagar diária planejada</a></p>';
    $mailer->send(
        $guardianEmail,
        'Cadastro pendente - Diárias Village',
        $body
    );
}

echo 'E-mail confirmado. Enviamos as próximas instruções.';
