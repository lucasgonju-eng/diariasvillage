<?php
$bootstrapCandidates = [
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
    __DIR__ . '/../src/Bootstrap.php',
];
$bootstrapLoaded = false;
foreach ($bootstrapCandidates as $candidate) {
    if (is_file($candidate)) {
        require_once $candidate;
        $bootstrapLoaded = true;
        break;
    }
}
if (!$bootstrapLoaded) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Bootstrap não encontrado.']);
    exit;
}

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\AsaasClient;
use App\AsaasCustomerIdentity;
use App\Services\AsaasWebhookInbox;
use App\Services\OficinaModularGradeService;
use App\SupabaseClient;

Helpers::requirePost();

$expected = trim(App\Env::get('ASAAS_WEBHOOK_TOKEN', ''));
if ($expected === '') {
    error_log('[asaas-webhook] ASAAS_WEBHOOK_TOKEN não configurado.');
    Helpers::json(['ok' => false, 'error' => 'Webhook indisponível.'], 503);
}

$token = trim((string) ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? ''));
if ($token === '' && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, 'asaas-access-token') === 0) {
            $token = trim((string) $value);
            break;
        }
    }
}
if ($token === '' || !hash_equals($expected, $token)) {
    error_log('[asaas-webhook] Token ausente ou inválido.');
    Helpers::json(['ok' => false, 'error' => 'Token inválido.'], 401);
}

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if ($contentType !== '' && !str_starts_with($contentType, 'application/json')) {
    Helpers::json(['ok' => false, 'error' => 'Content-Type inválido.'], 415);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 262144) {
    Helpers::json(['ok' => false, 'error' => 'Payload excede o limite permitido.'], 413);
}

$rawPayload = file_get_contents('php://input');
if (!is_string($rawPayload) || $rawPayload === '' || strlen($rawPayload) > 262144) {
    Helpers::json(['ok' => false, 'error' => 'Payload inválido.'], 400);
}

try {
    $payload = json_decode($rawPayload, true, 64, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    Helpers::json(['ok' => false, 'error' => 'JSON inválido.'], 400);
}

if (!is_array($payload)) {
    Helpers::json(['ok' => false, 'error' => 'Payload inválido.'], 400);
}

$eventId = trim((string) ($payload['id'] ?? ''));
$event = trim((string) ($payload['event'] ?? ''));
$paymentId = trim((string) ($payload['payment']['id'] ?? ''));
if (
    !preg_match('/^evt_[A-Za-z0-9]+$/', $eventId)
    || !preg_match('/^[A-Z][A-Z0-9_]{1,79}$/', $event)
    || !preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)
) {
    Helpers::json(['ok' => false, 'error' => 'Identificadores do evento inválidos.'], 400);
}

$inboxClient = new SupabaseClient(new HttpClient());
$inbox = new AsaasWebhookInbox($inboxClient);
$claim = $inbox->claim($eventId, $event, $paymentId, $payload, $rawPayload);
if (!($claim['ok'] ?? false)) {
    error_log('[asaas-webhook] Falha ao registrar evento ' . $eventId . ': ' . ($claim['code'] ?? 'UNKNOWN'));
    if (($claim['code'] ?? '') === 'EVENT_PAYLOAD_CONFLICT') {
        Helpers::json(['ok' => true, 'blocked' => true]);
    }
    Helpers::json(['ok' => false, 'error' => 'Não foi possível registrar o evento.'], 503);
}
if (($claim['claimed'] ?? false) !== true) {
    if (($claim['status'] ?? '') === 'PROCESSING') {
        Helpers::json(['ok' => false, 'error' => 'Evento ainda em processamento.'], 409);
    }
    Helpers::json(['ok' => true, 'idempotent' => true]);
}

$finalized = false;
$completeWebhook = static function (string $status = 'PROCESSED', array $response = ['ok' => true]) use (
    $inbox,
    $eventId,
    &$finalized
): void {
    if (!$inbox->complete($eventId, $status)) {
        $inbox->fail($eventId, 'Falha ao finalizar evento na caixa de entrada.');
        $finalized = true;
        Helpers::json(['ok' => false, 'error' => 'Falha ao finalizar evento.'], 503);
    }
    $finalized = true;
    Helpers::json($response);
};
$failWebhook = static function (string $message, int $status = 500) use (
    $inbox,
    $eventId,
    &$finalized
): void {
    $inbox->fail($eventId, $message);
    $finalized = true;
    Helpers::json(['ok' => false, 'error' => 'Falha ao processar evento.'], $status);
};
$blockWebhook = static function (string $message) use (
    $inbox,
    $eventId,
    &$finalized
): void {
    error_log('[asaas-webhook] Evento bloqueado ' . $eventId . ': ' . $message);
    if (!$inbox->complete($eventId, 'BLOCKED')) {
        $inbox->fail($eventId, 'Falha ao registrar bloqueio: ' . $message);
        $finalized = true;
        Helpers::json(['ok' => false, 'error' => 'Falha ao bloquear evento.'], 503);
    }
    $finalized = true;
    Helpers::json(['ok' => true, 'blocked' => true]);
};
register_shutdown_function(static function () use ($inbox, $eventId, &$finalized): void {
    if (!$finalized) {
        $error = error_get_last();
        $message = is_array($error)
            ? 'Encerramento inesperado: ' . (string) ($error['message'] ?? 'erro fatal')
            : 'Processamento encerrado antes da finalização.';
        $inbox->fail($eventId, $message);
    }
});

if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
    $completeWebhook('IGNORED', ['ok' => true, 'ignored' => true]);
}

$asaasClient = new AsaasClient(new HttpClient());
$asaasResult = $asaasClient->getPayment($paymentId);
$payment = is_array($asaasResult['data'] ?? null) ? $asaasResult['data'] : [];
if (
    !($asaasResult['ok'] ?? false)
    || (string) ($payment['id'] ?? '') !== $paymentId
    || !in_array((string) ($payment['status'] ?? ''), ['CONFIRMED', 'RECEIVED'], true)
) {
    error_log('[asaas-webhook] Estado remoto não confirmou pagamento ' . $paymentId . '.');
    $failWebhook('Pagamento não confirmado pela API do Asaas.', 503);
}

$client = $inboxClient;
$paymentResult = $client->select('payments', 'select=*&asaas_payment_id=eq.' . urlencode($payment['id']));

if (!($paymentResult['ok'] ?? false)) {
    $failWebhook('Falha ao consultar pagamento local.', 503);
}

if (empty($paymentResult['data'])) {
    $invoiceUrl = $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? '';
    $pendenciaResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,paid_at,student_id,enrollment,student_name,guardian_name,guardian_cpf,guardian_email,payment_date'
            . '&asaas_payment_id=eq.' . urlencode($payment['id'])
    );
    if (!($pendenciaResult['ok'] ?? false)) {
        $failWebhook('Falha ao consultar pendência local.', 503);
    }
    if (!empty($pendenciaResult['data'])) {
        $pendenciaRow = $pendenciaResult['data'][0];
        if (empty($pendenciaRow['paid_at'])) {
            $studentId = trim((string) ($pendenciaRow['student_id'] ?? ''));
            $enrollment = $pendenciaRow['enrollment'] ?? null;
            if (
                !preg_match('/^[0-9a-f-]{36}$/i', $studentId)
                || trim((string) ($pendenciaRow['guardian_name'] ?? '')) === ''
                || trim((string) ($pendenciaRow['guardian_email'] ?? '')) === ''
                || trim((string) ($pendenciaRow['guardian_cpf'] ?? '')) === ''
            ) {
                $blockWebhook('Pendência sem identidade ou aluno explícito.');
            }

            $remoteCustomerId = trim((string) ($payment['customer'] ?? ''));
            if ($remoteCustomerId === '') {
                $blockWebhook('Pagamento da pendência sem cliente remoto.');
            }
            $remoteCustomerResult = $asaasClient->getCustomer($remoteCustomerId);
            if (!($remoteCustomerResult['ok'] ?? false)) {
                $failWebhook('Falha ao consultar cliente remoto da pendência.', 503);
            }
            $remoteCustomer = is_array($remoteCustomerResult['data'] ?? null)
                ? $remoteCustomerResult['data']
                : [];
            if (
                !AsaasCustomerIdentity::matchesRemoteCustomer(
                    $remoteCustomer,
                    (string) $pendenciaRow['guardian_name'],
                    (string) $pendenciaRow['guardian_email'],
                    (string) $pendenciaRow['guardian_cpf']
                )
            ) {
                $blockWebhook('Identidade remota diverge da pendência.');
            }

            $accessCode = Helpers::randomNumericCode(6);
            $paymentDateFromAsaas = $payment['dueDate'] ?? null;
            $dayUseDate = $pendenciaRow['payment_date'] ?? $paymentDateFromAsaas;

            if ($enrollment === null || $enrollment === '') {
                $studentRes = $client->select(
                    'students',
                    'select=enrollment&id=eq.' . rawurlencode($studentId) . '&limit=1'
                );
                if (!($studentRes['ok'] ?? false)) {
                    $failWebhook('Falha ao consultar aluno da pendência.', 503);
                }
                if (empty($studentRes['data'][0])) {
                    $blockWebhook('Aluno da pendência não encontrado.');
                }
                $enrollment = $studentRes['data'][0]['enrollment'] ?? null;
            }

            $updatePayload = [
                'paid_at' => date('c'),
                'asaas_payment_id' => $payment['id'],
                'asaas_invoice_url' => $invoiceUrl ?: null,
                'access_code' => $accessCode,
            ];
            if ($dayUseDate) {
                $updatePayload['payment_date'] = $dayUseDate;
            }
            $updatePayload['student_id'] = $studentId;
            if ($enrollment !== null) {
                $updatePayload['enrollment'] = $enrollment;
            }

            $update = $client->update('pendencia_de_cadastro', 'id=eq.' . $pendenciaRow['id'], $updatePayload);
            if (!$update['ok']) {
                error_log('[asaas-webhook] Falha ao atualizar pendência ' . ($pendenciaRow['id'] ?? '-') . '.');
                $failWebhook('Falha ao atualizar pendência.', 503);
            }

            $guardianEmail = $pendenciaRow['guardian_email'] ?? '';
            if ($guardianEmail) {
                $studentName = $pendenciaRow['student_name'] ?? 'Aluno';
                $amount = '77,00';
                $paymentDateFormatted = $dayUseDate ? date('d/m/Y', strtotime($dayUseDate)) : '-';
                $portalLink = Helpers::baseUrl() ?: 'https://diarias.village.einsteinhub.co';
                $paymentLink = $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? $portalLink;

                $pendenciaTemplate = <<<'HTML'
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
            <td style="padding:26px 28px;background: radial-gradient(1100px 380px at 25% 0%, #163A7A 0%, #0A1B4D 40%, #081636 100%); color:#FFFFFF;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td valign="middle"><div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-weight:800;letter-spacing:.06em;font-size:14px;">DIÁRIAS VILLAGE</div>
                    <div style="font-size:13px;opacity:.90;margin-top:4px;">Pagamento rápido do Day use Village</div></td>
                </tr>
              </table>
              <div style="margin-top:14px;display:inline-block;padding:8px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.18);background:rgba(8,22,54,.35);font-size:12px;color:#EAF0FF;">
                Pagamento confirmado
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">Pagamento confirmado <span style="font-size:22px;">✅</span></div>
                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Tudo certo! Recebemos o pagamento da diária e o acesso foi <b>liberado automaticamente</b>.
                </div>
                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">Resumo da sua compra</div>
                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Aluno: <b>{{nome_aluno}}</b><br>
                    Data da diária: <b>{{data_diaria}}</b><br>
                    Tipo: <b>Planejada</b><br>
                    Valor pago: <b>R$ {{valor}}</b><br>
                    Código de acesso: <b>{{codigo_acesso}}</b>
                  </div>
                </div>
                {{extra_dados}}
                <div style="margin-top:18px;font-size:15px;line-height:1.7;color:#1B2333;">
                  Você não precisa fazer mais nada.<br>
                  <b>A secretaria já foi avisada automaticamente.</b>
                </div>
                <div style="margin-top:22px;">
                  <a href="{{link_portal}}" style="display:inline-block;background:#D6B25E;color:#0B1020;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:14px;">Acessar Diárias Village</a>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;background:#F3F6FB;border-top:1px solid #E6E9F2;">
              <div style="font-size:12px;line-height:1.5;color:#556070;text-align:center;">
                Diárias Village • Sistema oficial de pagamento e controle de acesso
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

                $replace = [
                    '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
                    '{{data_diaria}}' => htmlspecialchars($paymentDateFormatted, ENT_QUOTES, 'UTF-8'),
                    '{{valor}}' => $amount,
                    '{{codigo_acesso}}' => htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8'),
                    '{{link_portal}}' => htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8'),
                    '{{extra_dados}}' => '',
                ];
                $html = strtr($pendenciaTemplate, $replace);

                $mailer = new Mailer();
                $mailer->send($guardianEmail, 'Pagamento confirmado • Diárias Village', $html);

                $secretaria = App\Env::get('EMAIL_SECRETARIA', '');
                $copia = App\Env::get('EMAIL_COPIA', '');
                if ($secretaria) {
                    $extraBlock = '<div style="margin-top:16px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:16px;">
                      <div style="font-size:14px;font-weight:800;margin-bottom:8px;color:#0B1020;">Dados do responsável</div>
                      <div style="font-size:13px;line-height:1.6;color:#1B2333;">
                        CPF/CNPJ: <b>' . htmlspecialchars($pendenciaRow['guardian_cpf'] ?? '', ENT_QUOTES, 'UTF-8') . '</b><br>
                        E-mail: <b>' . htmlspecialchars($guardianEmail, ENT_QUOTES, 'UTF-8') . '</b><br>
                        Código de acesso: <b>' . htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8') . '</b><br>
                        Matrícula: <b>' . htmlspecialchars($enrollment ?? '(CPF não vinculado ao aluno)', ENT_QUOTES, 'UTF-8') . '</b>
                      </div>
                    </div>';
                    $replace['{{extra_dados}}'] = $extraBlock;
                    $htmlSecretaria = strtr($pendenciaTemplate, $replace);
                    $mailer->send($secretaria, 'Pagamento confirmado - liberar estudante (pendência)', $htmlSecretaria, $copia ? [$copia] : []);
                }
            }
        }
        $completeWebhook();
    }
    error_log('[asaas-webhook] Pagamento local não encontrado: ' . $paymentId . '.');
    $completeWebhook('IGNORED', ['ok' => true, 'skipped' => true]);
}

$paymentRow = $paymentResult['data'][0];
$wasAlreadyPaid = (($paymentRow['status'] ?? '') === 'paid' && !empty($paymentRow['paid_at']));

$remoteAmount = (float) ($payment['value'] ?? 0);
$localAmount = (float) ($paymentRow['amount'] ?? 0);
if ($remoteAmount <= 0 || abs($remoteAmount - $localAmount) > 0.009) {
    $blockWebhook('Valor remoto diverge do pagamento local.');
}

$guardianResult = $client->select(
    'guardians',
    'select=id,email,parent_name,parent_document,asaas_customer_id'
        . '&id=eq.' . rawurlencode((string) ($paymentRow['guardian_id'] ?? ''))
        . '&student_id=eq.' . rawurlencode((string) ($paymentRow['student_id'] ?? ''))
        . '&limit=1'
);
if (!($guardianResult['ok'] ?? false)) {
    $failWebhook('Falha ao consultar responsável local.', 503);
}
$guardian = is_array($guardianResult['data'][0] ?? null)
    ? $guardianResult['data'][0]
    : null;
$remoteCustomerId = trim((string) ($payment['customer'] ?? ''));
if (
    !is_array($guardian)
    || $remoteCustomerId === ''
    || $remoteCustomerId !== trim((string) ($guardian['asaas_customer_id'] ?? ''))
) {
    $blockWebhook('Cliente remoto não corresponde ao responsável local.');
}
$remoteCustomerResult = $asaasClient->getCustomer($remoteCustomerId);
if (!($remoteCustomerResult['ok'] ?? false)) {
    $failWebhook('Falha ao consultar cliente remoto.', 503);
}
$remoteCustomer = is_array($remoteCustomerResult['data'] ?? null) ? $remoteCustomerResult['data'] : [];
if (
    !AsaasCustomerIdentity::matchesRemoteCustomer(
        $remoteCustomer,
        (string) ($guardian['parent_name'] ?? ''),
        (string) ($guardian['email'] ?? ''),
        (string) ($guardian['parent_document'] ?? '')
    )
) {
    $blockWebhook('Identidade remota diverge do responsável local.');
}

$accessCode = $paymentRow['access_code'] ?: Helpers::randomNumericCode(6);
if (!$wasAlreadyPaid) {
    $paymentUpdate = $client->update('payments', 'id=eq.' . $paymentRow['id'], [
        'status' => 'paid',
        'paid_at' => date('c'),
        'access_code' => $accessCode,
    ]);
    if (!($paymentUpdate['ok'] ?? false)) {
        $failWebhook('Falha ao promover pagamento local.', 503);
    }
    error_log(
        '[asaas-webhook] Pagamento confirmado: payment_row='
        . ((string) ($paymentRow['id'] ?? '-'))
        . ' asaas_payment_id='
        . $paymentId
        . '.'
    );
}

$diariaId = (string) ($paymentRow['diaria_id'] ?? '');
if ($diariaId !== '') {
    $gradeService = new OficinaModularGradeService($client);
    $confirmacaoGrade = $gradeService->confirmarGradeNoPagamento($diariaId);
    if (!($confirmacaoGrade['ok'] ?? false)) {
        error_log(
            '[asaas-webhook] Falha ao confirmar grade: diaria_id='
            . $diariaId
            . ' payment_id='
            . ($paymentRow['id'] ?? '-')
            . '.'
        );
        $failWebhook('Falha ao confirmar grade vinculada ao pagamento.', 503);
    } elseif (!empty($confirmacaoGrade['user_alert'])) {
        $alertUpdate = $client->update('payments', 'id=eq.' . $paymentRow['id'], [
            'grade_alerta' => (string) $confirmacaoGrade['user_alert'],
        ]);
        if (!($alertUpdate['ok'] ?? false)) {
            $failWebhook('Falha ao registrar alerta da grade.', 503);
        }
    }
}

if ($wasAlreadyPaid) {
    $completeWebhook('PROCESSED', ['ok' => true, 'idempotent' => true]);
}

$studentResult = $client->select('students', 'select=name,enrollment&' . 'id=eq.' . $paymentRow['student_id']);
$student = $studentResult['data'][0] ?? null;

if ($guardian) {
    $studentName = $student['name'] ?? 'Aluno';
    $enrollment = $student['enrollment'] ?? '-';
    $amount = number_format((float) $paymentRow['amount'], 2, ',', '.');
    $dailyRaw = $paymentRow['daily_type'] ?? '';
    $dailyParts = explode('|', $dailyRaw, 2);
    $dailyBase = $dailyParts[0] ?? $dailyRaw;
    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($paymentRow['payment_date']));
    $paymentDate = $datesLabel;
    $dailyLabel = $dailyBase === 'emergencial' ? 'Emergencial' : 'Planejada';
    $portalLink = Helpers::baseUrl() ?: 'https://diarias.village.einsteinhub.co';
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
                      DIÁRIAS VILLAGE
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

    $thanksTemplate = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Obrigado pela regularização • Diárias Village</title>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Pagamento recebido. Obrigado por regularizar a diária.
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
                      DIÁRIAS VILLAGE
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
                Regularização concluída
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">
                  Obrigado por regularizar a diária <span style="font-size:22px;">💙</span>
                </div>

                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Recebemos o pagamento e a situação do aluno foi <b>regularizada automaticamente</b>.
                </div>

                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">
                    Resumo da diária utilizada
                  </div>

                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Aluno: <b>{{nome_aluno}}</b><br>
                    Data da diária: <b>{{data_diaria}}</b><br>
                    Tipo: <b>{{tipo_diaria}}</b><br>
                    Valor pago: <b>R$ {{valor}}</b>
                  </div>
                </div>

                <div style="margin-top:18px;font-size:15px;line-height:1.7;color:#1B2333;">
                  Se precisar de qualquer apoio, estamos à disposição. 😊
                </div>

                <div style="margin-top:12px;font-size:13px;line-height:1.6;color:#556070;">
                  Dica: o pagamento planejado tem desconto e sai por <b>R$ 77,00</b> quando feito antes das 10h.
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
    $isManual = ($paymentRow['billing_type'] ?? '') === 'PIX_MANUAL';
    $html = strtr($template, $replace + ['{{extra_dados}}' => '']);
    $guardianSubject = 'Pagamento confirmado • Diárias Village';
    if ($isManual) {
        $html = strtr($thanksTemplate, $replace);
        $guardianSubject = 'Obrigado pela regularização • Diárias Village';
    }

    $mailer = new Mailer();
    $mailer->send(
        $guardian['email'],
        $guardianSubject,
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

$completeWebhook();
