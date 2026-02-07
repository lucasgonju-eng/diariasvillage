<?php
require_once __DIR__ . '/../src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

$extractAsaasError = static function (array $response): string {
    if (!empty($response['error'])) {
        return (string) $response['error'];
    }

    $data = $response['data'] ?? null;
    if (is_array($data)) {
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $messages = [];
            foreach ($data['errors'] as $error) {
                if (is_array($error)) {
                    $messages[] = $error['description'] ?? $error['message'] ?? null;
                }
            }
            $messages = array_filter($messages);
            if ($messages) {
                return implode(' ', $messages);
            }
        }

        if (!empty($data['message'])) {
            return (string) $data['message'];
        }
    }

    return 'Falha ao criar pagamento.';
};

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
header('X-Debug-Method: ' . $method);
header('X-Debug-Content-Type: ' . $contentType);

Helpers::requirePost();
$user = Helpers::requireAuth();
$payload = json_decode(file_get_contents('php://input'), true);

$date = $payload['date'] ?? '';
$billingType = $payload['billing_type'] ?? 'PIX';
$documentRaw = trim($payload['document'] ?? '');
if ($documentRaw === '') {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    $keys = is_array($payload) ? implode(',', array_keys($payload)) : 'payload_invalido';
    file_put_contents($logPath, 'create-payment sem documento (payload keys: ' . $keys . ')' . PHP_EOL, FILE_APPEND);
}

if ($date === '') {
    Helpers::json(['ok' => false, 'error' => 'Selecione a data.'], 422);
}

$today = date('Y-m-d');
$hour = (int) date('H');
$plannedAmount = 77.00;

if ($date === $today) {
    if ($hour >= 16) {
        Helpers::json([
            'ok' => false,
            'error' => 'Compras para hoje encerradas apos as 16h. Escolha uma data futura.',
        ], 422);
    }

    if ($hour < 10) {
        $dailyType = 'planejada';
        $amount = $plannedAmount;
    } else {
        $dailyType = 'emergencial';
        $amount = 97.00;
    }
} else {
    $dailyType = 'planejada';
    $amount = $plannedAmount;
}

if (!in_array($billingType, ['PIX', 'DEBIT_CARD'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Forma de pagamento invalida.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=*&id=eq.' . $user['id']);
if (!$guardian['ok'] || empty($guardian['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Usuario nao encontrado.'], 404);
}

$guardianData = $guardian['data'][0];
$asaas = new AsaasClient(new HttpClient());

$documentRaw = $documentRaw !== '' ? $documentRaw : (string) ($guardianData['parent_document'] ?? '');
if ($documentRaw === '') {
    Helpers::json([
        'ok' => false,
        'error' => 'Informe um CPF ou CNPJ valido para gerar o pagamento.',
    ], 422);
}

$document = preg_replace('/\D+/', '', $documentRaw);

if (empty($guardianData['asaas_customer_id'])) {
    $customerPayload = [
        'name' => $guardianData['parent_name'] ?: 'Responsavel ' . $guardianData['email'],
        'email' => $guardianData['email'],
    ];

    if ($document !== '') {
        $customerPayload['cpfCnpj'] = $document;
    }

    $customer = $asaas->createCustomer($customerPayload);

    if (!$customer['ok']) {
        $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
        file_put_contents($logPath, 'Asaas createCustomer error: ' . json_encode($customer) . PHP_EOL, FILE_APPEND);
        error_log('Asaas createCustomer error: ' . json_encode($customer));
        Helpers::json([
            'ok' => false,
            'error' => $extractAsaasError($customer),
        ], 500);
    }

    $guardianData['asaas_customer_id'] = $customer['data']['id'] ?? null;
    $client->update('guardians', 'id=eq.' . $guardianData['id'], [
        'asaas_customer_id' => $guardianData['asaas_customer_id'],
        'parent_document' => $document,
    ]);
} else {
    $asaas->updateCustomer($guardianData['asaas_customer_id'], [
        'cpfCnpj' => $document,
    ]);
    $client->update('guardians', 'id=eq.' . $guardianData['id'], [
        'parent_document' => $document,
    ]);
}

$payment = $asaas->createPayment([
    'customer' => $guardianData['asaas_customer_id'],
    'billingType' => $billingType,
    'value' => $amount,
    'dueDate' => $date,
    'description' => 'Diaria ' . $dailyType . ' - Einstein Village',
]);

if (!$payment['ok']) {
    $errorMessage = $extractAsaasError($payment);

    if (stripos($errorMessage, 'cliente removido') !== false) {
        $customerPayload = [
            'name' => $guardianData['parent_name'] ?: 'Responsavel ' . $guardianData['email'],
            'email' => $guardianData['email'],
        ];
        if ($document !== '') {
            $customerPayload['cpfCnpj'] = $document;
        }

        $customer = $asaas->createCustomer($customerPayload);
        if ($customer['ok']) {
            $guardianData['asaas_customer_id'] = $customer['data']['id'] ?? null;
            $client->update('guardians', 'id=eq.' . $guardianData['id'], [
                'asaas_customer_id' => $guardianData['asaas_customer_id'],
                'parent_document' => $document,
            ]);

            $payment = $asaas->createPayment([
                'customer' => $guardianData['asaas_customer_id'],
                'billingType' => $billingType,
                'value' => $amount,
                'dueDate' => $date,
                'description' => 'Diaria ' . $dailyType . ' - Einstein Village',
            ]);
            if ($payment['ok']) {
                goto payment_success;
            }
        }
    }

    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    file_put_contents($logPath, 'Asaas createPayment error: ' . json_encode($payment) . PHP_EOL, FILE_APPEND);
    error_log('Asaas createPayment error: ' . json_encode($payment));
    Helpers::json([
        'ok' => false,
        'error' => $errorMessage,
    ], 500);
}

payment_success:
$paymentData = $payment['data'];
$client->insert('payments', [[
    'guardian_id' => $guardianData['id'],
    'student_id' => $guardianData['student_id'],
    'payment_date' => $date,
    'daily_type' => $dailyType,
    'amount' => $amount,
    'status' => 'pending',
    'billing_type' => $billingType,
    'asaas_payment_id' => $paymentData['id'] ?? null,
]]);

$invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? '#';
$portalLink = Helpers::baseUrl() ?: 'https://village.einsteinhub.co';
$paymentDate = date('d/m/Y', strtotime($date));
$dailyLabel = $dailyType === 'emergencial' ? 'Emergencial' : 'Planejada';
$amountFormatted = number_format((float) $amount, 2, ',', '.');
$studentName = $guardianData['student_name']
    ?? ($guardianData['student'] ?? null)
    ?? 'Aluno';

$mailer = new Mailer();
$template = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ordem criada • Diárias Village</title>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Ordem de pagamento criada. Finalize agora via PIX para liberar automaticamente.
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#EEF2F7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
               style="width:600px;max-width:600px;background:#FFFFFF;border-radius:18px;overflow:hidden;
                      box-shadow:0 10px 30px rgba(11,16,32,.14);">
          <tr>
            <td style="
              padding:26px 28px;
              background: radial-gradient(1100px 380px at 25% 0%, #163A7A 0%, #0A1B4D 40%, #081636 100%);
              color:#FFFFFF;
            ">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td valign="middle" style="padding-right:12px;">
                    <span style="display:inline-block;width:34px;height:34px;border-radius:12px;background:#D6B25E;"></span>
                  </td>
                  <td valign="middle">
                    <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-weight:800;letter-spacing:.06em;font-size:14px;line-height:1;">
                      DIARIAS VILLAGE
                    </div>
                    <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;opacity:.90;margin-top:4px;">
                      Pagamento rápido do Day use Village
                    </div>
                  </td>
                </tr>
              </table>

              <div style="
                margin-top:14px;
                display:inline-block;
                padding:8px 12px;
                border-radius:999px;
                border:1px solid rgba(255,255,255,.18);
                background:rgba(8,22,54,.35);
                font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
                font-size:12px;
                color:#EAF0FF;
              ">
                Ordem de pagamento criada
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">
                  Quase lá — falta só o PIX 😊
                </div>

                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Sua ordem de pagamento foi criada com sucesso. Para concluir, é só finalizar o pagamento via <b>PIX</b> no botão abaixo.
                </div>

                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">
                    Resumo desta diária
                  </div>

                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Aluno: <b>{{nome_aluno}}</b><br>
                    Data da diária: <b>{{data_diaria}}</b><br>
                    Tipo: <b>{{tipo_diaria}}</b><br>
                    Valor: <b>R$ {{valor}}</b>
                  </div>
                </div>

                <div style="margin-top:18px;font-size:15px;line-height:1.7;color:#1B2333;">
                  Assim que o pagamento for confirmado, o acesso é <b>liberado automaticamente</b> e a <b>secretaria é avisada</b>.
                </div>

                <div style="margin-top:16px;">
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    PIX rápido e seguro
                  </span>
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    Liberação automática
                  </span>
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    Secretaria avisada
                  </span>
                </div>

                <div style="margin-top:22px;">
                  <a href="{{link_pagamento}}" style="
                    display:inline-block;
                    background:#D6B25E;
                    color:#0B1020;
                    text-decoration:none;
                    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
                    font-weight:800;
                    padding:12px 16px;
                    border-radius:14px;
                  ">
                    Pagar diária agora
                  </a>

                  <div style="margin-top:10px;font-size:12px;line-height:1.5;color:#556070;">
                    Se o botão não funcionar, copie e cole este link no navegador:<br>
                    <span style="color:#0A1B4D;">{{link_pagamento}}</span>
                  </div>
                </div>

                <div style="margin-top:16px;font-size:12px;line-height:1.6;color:#556070;">
                  Dica: o sistema aplica automaticamente a regra do valor conforme o horário do pagamento.
                </div>

              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 28px;background:#F3F6FB;border-top:1px solid #E6E9F2;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:12px;line-height:1.5;color:#556070;text-align:center;">
                Diárias Village • Sistema oficial de pagamento e controle de acesso<br>
                Em caso de dúvidas, entre em contato com a secretaria.
              </div>
            </td>
          </tr>
        </table>

        <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:11px;color:#8A94A6;margin-top:10px;text-align:center;">
          © Diárias Village
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$replace = [
    '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
    '{{data_diaria}}' => $paymentDate,
    '{{tipo_diaria}}' => $dailyLabel,
    '{{valor}}' => $amountFormatted,
    '{{link_pagamento}}' => htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8'),
    '{{link_portal}}' => htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8'),
];
$html = strtr($template, $replace);

$mailer->send(
    $guardianData['email'],
    'Pagamento criado - Diarias Village',
    $html
);

Helpers::json(['ok' => true, 'invoice_url' => $invoiceUrl]);
