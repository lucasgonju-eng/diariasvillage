<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

$token = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN']
    ?? $_SERVER['HTTP_ACCESS_TOKEN']
    ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN']
    ?? $_SERVER['HTTP_AUTHORIZATION']
    ?? '';

if ($token === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, 'asaas-access-token') === 0 || strcasecmp($key, 'access_token') === 0) {
            $token = $value;
            break;
        }
    }
}
$expected = App\Env::get('ASAAS_WEBHOOK_TOKEN', '');

$token = trim($token);
$expected = trim($expected);
if (stripos($token, 'bearer ') === 0) {
    $token = trim(substr($token, 7));
}

if ($expected && $token !== $expected) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    $safeExpected = $expected !== '' ? ('***' . substr($expected, -4)) : '(vazio)';
    $safeToken = $token !== '' ? ('***' . substr($token, -4)) : '(vazio)';
    file_put_contents(
        $logPath,
        'Webhook token mismatch. Esperado ' . $safeExpected . ' recebido ' . $safeToken . PHP_EOL,
        FILE_APPEND
    );
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

$accessCode = $paymentRow['access_code'] ?: Helpers::randomNumericCode(6);
$client->update('payments', 'id=eq.' . $paymentRow['id'], [
    'status' => 'paid',
    'paid_at' => date('c'),
    'access_code' => $accessCode,
]);

$studentResult = $client->select('students', 'select=name,enrollment&' . 'id=eq.' . $paymentRow['student_id']);
$student = $studentResult['data'][0] ?? null;

$guardianResult = $client->select('guardians', 'select=*&id=eq.' . $paymentRow['guardian_id']);
$guardian = $guardianResult['data'][0] ?? null;

if ($guardian) {
    $studentName = $student['name'] ?? 'Aluno';
    $enrollment = $student['enrollment'] ?? '-';
    $amount = number_format((float) $paymentRow['amount'], 2, ',', '.');
    $paymentDate = date('d/m/Y', strtotime($paymentRow['payment_date']));
    $dailyLabel = $paymentRow['daily_type'] === 'emergencial' ? 'Emergencial' : 'Planejada';
    $portalLink = Helpers::baseUrl() ?: 'https://village.einsteinhub.co';
    $paymentLink = $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? $portalLink;
    $guardianDocument = $guardian['parent_document'] ?? '';

    $template = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pagamento confirmado • Diárias Village</title>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Pagamento confirmado. Liberação automática e secretaria avisada.
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
                Plataforma oficial do Day use Village
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">
                  Pagamento confirmado <span style="font-size:22px;">✅</span>
                </div>

                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Tudo certo! Recebemos o pagamento da diária e o acesso foi <b>liberado automaticamente</b>.
                </div>

                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">
                    Resumo da sua compra
                  </div>

                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Aluno: <b>{{nome_aluno}}</b><br>
                    Data da diária: <b>{{data_diaria}}</b><br>
                    Tipo: <b>{{tipo_diaria}}</b><br>
                    Valor pago: <b>R$ {{valor}}</b>
                  </div>
                </div>

                {{extra_dados}}

                <div style="margin-top:18px;font-size:15px;line-height:1.7;color:#1B2333;">
                  Você não precisa fazer mais nada.<br>
                  <b>A secretaria já foi avisada automaticamente.</b>
                </div>

                <div style="margin-top:16px;">
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    Confirmação por e-mail
                  </span>
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    Processo seguro
                  </span>
                  <span style="display:inline-block;margin:6px 8px 0 0;padding:8px 12px;border-radius:999px;background:#0A1B4D;color:#EAF0FF;font-size:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
                    Liberação automática
                  </span>
                </div>

                <div style="margin-top:22px;">
                  <a href="{{link_portal}}" style="
                    display:inline-block;
                    background:#D6B25E;
                    color:#0B1020;
                    text-decoration:none;
                    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
                    font-weight:800;
                    padding:12px 16px;
                    border-radius:14px;
                  ">
                    Acessar Diárias Village
                  </a>

                  <div style="margin-top:10px;font-size:12px;line-height:1.5;color:#556070;">
                    Se o botão não funcionar, copie e cole este link no navegador:<br>
                    <span style="color:#0A1B4D;">{{link_portal}}</span>
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

    $replace = [
        '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
        '{{data_diaria}}' => $paymentDate,
        '{{tipo_diaria}}' => $dailyLabel,
        '{{valor}}' => $amount,
        '{{link_portal}}' => htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8'),
        '{{link_pagamento}}' => htmlspecialchars($paymentLink, ENT_QUOTES, 'UTF-8'),
        '{{cpf_responsavel}}' => htmlspecialchars($guardianDocument, ENT_QUOTES, 'UTF-8'),
        '{{email_responsavel}}' => htmlspecialchars($guardian['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{codigo_acesso}}' => htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8'),
    ];
    $html = strtr($template, $replace + ['{{extra_dados}}' => '']);

    $mailer = new Mailer();
    $mailer->send(
        $guardian['email'],
        'Pagamento confirmado - Diarias Village',
        $html
    );

    $secretaria = App\Env::get('EMAIL_SECRETARIA', '');
    $copia = App\Env::get('EMAIL_COPIA', '');

    if ($secretaria) {
        $extraBlock = '
                <div style="margin-top:16px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:16px;">
                  <div style="font-size:14px;font-weight:800;margin-bottom:8px;color:#0B1020;">
                    Dados do responsável
                  </div>
                  <div style="font-size:13px;line-height:1.6;color:#1B2333;">
                    CPF/CNPJ: <b>{{cpf_responsavel}}</b><br>
                    E-mail: <b>{{email_responsavel}}</b><br>
                    Código de acesso: <b>{{codigo_acesso}}</b><br>
                    Link do pagamento: <b>{{link_pagamento}}</b>
                  </div>
                </div>';
        $htmlSecretaria = strtr($template, $replace + ['{{extra_dados}}' => $extraBlock]);
        $mailer->send(
            $secretaria,
            'Pagamento confirmado - liberar estudante',
            $htmlSecretaria,
            $copia ? [$copia] : []
        );
    }
}

Helpers::json(['ok' => true]);
