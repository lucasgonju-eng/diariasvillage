<?php
require_once __DIR__ . '/../src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');

use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\Mailer;

function extractAsaasError(array $response): string
{
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

    return 'Falha ao processar cobrança.';
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Nao autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$charges = $payload['charges'] ?? [];

if (!is_array($charges) || !$charges) {
    Helpers::json(['ok' => false, 'error' => 'Nenhuma cobranca informada.'], 422);
}

$asaas = new AsaasClient(new HttpClient());
$mailer = new Mailer();
$results = [];
$today = date('Y-m-d');
$paymentDate = date('d/m/Y', strtotime($today));
$portalLink = Helpers::baseUrl() ?: 'https://village.einsteinhub.co';

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
                    Data do day-use: <b>{{data_diaria}}</b><br>
                    Tipo: <b>{{tipo_diaria}}</b><br>
                    Valor: <b>R$ {{valor}}</b>
                  </div>
                </div>

                <div style="margin-top:18px;font-size:15px;line-height:1.7;color:#1B2333;">
                  Assim que o pagamento for confirmado, o acesso é <b>liberado automaticamente</b> e a <b>secretaria é avisada</b>.
                </div>

                <div style="margin-top:12px;font-size:13px;line-height:1.6;color:#556070;">
                  Dica: o pagamento planejado tem desconto e sai por <b>R$ 77,00</b> quando feito antes das 10h.
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

foreach ($charges as $charge) {
    $studentName = trim($charge['student_name'] ?? '');
    $guardianName = trim($charge['guardian_name'] ?? '');
    $guardianEmail = trim($charge['guardian_email'] ?? '');
    $guardianWhatsapp = trim($charge['guardian_whatsapp'] ?? '');
    $dayUseDates = $charge['day_use_dates'] ?? [];
    if (!is_array($dayUseDates)) {
        $dayUseDates = [$dayUseDates];
    }
    $dayUseDates = array_filter(array_map('trim', $dayUseDates));

    if ($studentName === '' || $guardianName === '' || $guardianEmail === '') {
        $results[] = [
            'student_name' => $studentName ?: '(sem nome)',
            'ok' => false,
            'error' => 'Nome e e-mail do responsável são obrigatórios.',
        ];
        continue;
    }

    if (!$dayUseDates) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'Informe ao menos uma data de day-use.',
        ];
        continue;
    }

    if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'E-mail inválido.',
        ];
        continue;
    }

    $customerPayload = [
        'name' => $guardianName,
        'email' => $guardianEmail,
    ];

    $whatsappDigits = preg_replace('/\D+/', '', $guardianWhatsapp);
    if ($whatsappDigits !== '') {
        $customerPayload['mobilePhone'] = $whatsappDigits;
    }

    $customer = $asaas->createCustomer($customerPayload);
    if (!$customer['ok']) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => extractAsaasError($customer),
        ];
        continue;
    }

    $customerId = $customer['data']['id'] ?? null;
    if (!$customerId) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'Cliente Asaas inválido.',
        ];
        continue;
    }

    $daysCount = count($dayUseDates);
    $amount = 97.00 * $daysCount;
    $amountFormatted = number_format($amount, 2, ',', '.');

    $payment = $asaas->createPayment([
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => $amount,
        'dueDate' => $today,
        'description' => 'Diaria emergencial - cobranca manual - Einstein Village',
    ]);

    if (!$payment['ok']) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => extractAsaasError($payment),
        ];
        continue;
    }

    $paymentData = $payment['data'] ?? [];
    $invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? $portalLink;

    $dateLabel = implode(', ', $dayUseDates);

    $replace = [
        '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
        '{{data_diaria}}' => htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'),
        '{{tipo_diaria}}' => 'Emergencial',
        '{{valor}}' => $amountFormatted,
        '{{link_pagamento}}' => htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8'),
        '{{link_portal}}' => htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8'),
    ];
    $html = strtr($template, $replace);

    $mailResult = $mailer->send(
        $guardianEmail,
        'Ordem criada - Diarias Village',
        $html
    );

    $results[] = [
        'student_name' => $studentName,
        'ok' => $mailResult['ok'] ?? false,
        'invoice_url' => $invoiceUrl,
        'error' => $mailResult['ok'] ? null : ($mailResult['error'] ?? 'Falha ao enviar e-mail.'),
    ];
}

$failures = array_values(array_filter($results, static fn($item) => !$item['ok']));
$allOk = !$failures;
$error = $failures ? ($failures[0]['error'] ?? 'Falha ao enviar cobranças.') : null;
Helpers::json(['ok' => $allOk, 'error' => $error, 'results' => $results]);
