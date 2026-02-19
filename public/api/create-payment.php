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
$diariaId = isset($payload['diaria_id']) ? trim((string) $payload['diaria_id']) : '';
$checkoutToken = isset($payload['checkout_token']) ? trim((string) $payload['checkout_token']) : '';
$billingType = $payload['billing_type'] ?? 'PIX';
$documentRaw = trim($payload['document'] ?? '');
$orientadoraSlotsPayload = $payload['orientadora_slots'] ?? [];
if ($documentRaw === '') {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    $keys = is_array($payload) ? implode(',', array_keys($payload)) : 'payload_inválido';
    file_put_contents($logPath, 'create-payment sem documento (payload keys: ' . $keys . ')' . PHP_EOL, FILE_APPEND);
}

if ($diariaId === '') {
    Helpers::json(['ok' => false, 'error' => 'Diária não informada.', 'redirect_to' => '/dashboard.php'], 422);
}

$today = date('Y-m-d');
$hour = (int) date('H');
// Valor padrão da diária planejada.
$plannedAmount = 77.00;

if (!in_array($billingType, ['PIX', 'DEBIT_CARD'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Forma de pagamento inválida.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardian = $client->select('guardians', 'select=*&id=eq.' . $user['id']);
if (!$guardian['ok'] || empty($guardian['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Usuário não encontrado.'], 404);
}

$guardianData = $guardian['data'][0];
$diaria = $client->select('diaria', 'select=*&id=eq.' . rawurlencode($diariaId) . '&guardian_id=eq.' . rawurlencode((string) $guardianData['id']) . '&limit=1');
if (!$diaria['ok'] || empty($diaria['data'])) {
    Helpers::json([
        'ok' => false,
        'error' => 'Diária não encontrada para este responsável.',
        'redirect_to' => '/dashboard.php',
    ], 404);
}

$diariaRow = $diaria['data'][0];
if (!($diariaRow['grade_oficina_modular_ok'] ?? false)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Finalize a etapa da Grade de Oficina Modular antes do pagamento.',
        'redirect_to' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId),
    ], 422);
}
$gradeCheckoutReady = $_SESSION['grade_checkout_ready'] ?? [];
$gradeReadyAt = is_array($gradeCheckoutReady) ? (int) ($gradeCheckoutReady[$diariaId] ?? 0) : 0;
if ($gradeReadyAt <= 0 || (time() - $gradeReadyAt) > 1800) {
    Helpers::json([
        'ok' => false,
        'error' => 'Antes de pagar, conclua novamente a etapa da Grade de Oficina Modular.',
        'redirect_to' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId),
    ], 422);
}
$checkoutTokens = $_SESSION['grade_checkout_tokens'] ?? [];
$tokenMeta = is_array($checkoutTokens) ? ($checkoutTokens[$checkoutToken] ?? null) : null;
$tokenDiariaId = is_array($tokenMeta) ? (string) ($tokenMeta['diaria_id'] ?? '') : '';
$tokenExpiresAt = is_array($tokenMeta) ? (int) ($tokenMeta['expires_at'] ?? 0) : 0;
if ($checkoutToken === '' || $tokenDiariaId !== $diariaId || $tokenExpiresAt <= 0 || time() > $tokenExpiresAt) {
    Helpers::json([
        'ok' => false,
        'error' => 'Sessão de checkout inválida. Conclua novamente a Grade de Oficina Modular.',
        'redirect_to' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($diariaId),
    ], 422);
}
$statusPagamentoDiaria = (string) ($diariaRow['status_pagamento'] ?? 'PENDENTE');
if ($statusPagamentoDiaria === 'PAGO' || ($diariaRow['grade_travada'] ?? false)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Essa diária já foi paga e está travada.',
        'redirect_to' => '/dashboard.php',
    ], 422);
}

$date = (string) ($diariaRow['data_diaria'] ?? '');
if ($date === '') {
    Helpers::json(['ok' => false, 'error' => 'Data da diária inválida.'], 422);
}

if ($date === $today) {
    if ($hour >= 16) {
        Helpers::json([
            'ok' => false,
            'error' => 'Compras para hoje encerradas após as 16h. Escolha uma data futura.',
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

$asaas = new AsaasClient(new HttpClient());
$portalLink = Helpers::baseUrl() ?: 'https://village.einsteinhub.co';
$successUrl = $portalLink . '/pagamento-retorno.php?diariaId=' . rawurlencode($diariaId);
$isCallbackUrlValida = (bool) filter_var($successUrl, FILTER_VALIDATE_URL)
    && str_starts_with(strtolower($successUrl), 'https://')
    && stripos($successUrl, 'localhost') === false
    && stripos($successUrl, '127.0.0.1') === false;
$montarPayloadPagamento = static function (string $customerId, string $billingType, float $amount, string $date, string $dailyType, bool $includeCallback) use ($diariaId, $successUrl, $isCallbackUrlValida): array {
    $payloadPagamento = [
        'customer' => $customerId,
        'billingType' => $billingType,
        'value' => $amount,
        'dueDate' => $date,
        'description' => 'Diária ' . $dailyType . ' - Einstein Village',
        'externalReference' => $diariaId,
    ];
    if ($includeCallback && $isCallbackUrlValida) {
        $payloadPagamento['callback'] = [
            'successUrl' => $successUrl,
            'autoRedirect' => true,
        ];
    }
    return $payloadPagamento;
};

$documentRaw = $documentRaw !== '' ? $documentRaw : (string) ($guardianData['parent_document'] ?? '');
if ($documentRaw === '') {
    Helpers::json([
        'ok' => false,
        'error' => 'Informe um CPF ou CNPJ válido para gerar o pagamento.',
    ], 422);
}

$document = preg_replace('/\D+/', '', $documentRaw);

if (empty($guardianData['asaas_customer_id'])) {
    $customerPayload = [
        'name' => $guardianData['parent_name'] ?: 'Responsável ' . $guardianData['email'],
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

$payment = $asaas->createPayment(
    $montarPayloadPagamento(
        $guardianData['asaas_customer_id'],
        (string) $billingType,
        (float) $amount,
        (string) $date,
        (string) $dailyType,
        true
    )
);

if (!$payment['ok']) {
    $errorMessage = $extractAsaasError($payment);
    $errorNorm = mb_strtolower($errorMessage, 'UTF-8');
    $callbackInvalido = str_contains($errorNorm, 'callback') && str_contains($errorNorm, 'inválida');
    if ($callbackInvalido) {
        $payment = $asaas->createPayment(
            $montarPayloadPagamento(
                $guardianData['asaas_customer_id'],
                (string) $billingType,
                (float) $amount,
                (string) $date,
                (string) $dailyType,
                false
            )
        );
        if ($payment['ok']) {
            goto payment_success;
        }
        $errorMessage = $extractAsaasError($payment);
    }

    if (stripos($errorMessage, 'cliente removido') !== false) {
        $customerPayload = [
            'name' => $guardianData['parent_name'] ?: 'Responsável ' . $guardianData['email'],
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

            $payment = $asaas->createPayment(
                $montarPayloadPagamento(
                    $guardianData['asaas_customer_id'],
                    (string) $billingType,
                    (float) $amount,
                    (string) $date,
                    (string) $dailyType,
                    true
                )
            );
            if ($payment['ok']) {
                goto payment_success;
            }
            $errorMessage = $extractAsaasError($payment);
            $errorNorm = mb_strtolower($errorMessage, 'UTF-8');
            $callbackInvalido = str_contains($errorNorm, 'callback') && str_contains($errorNorm, 'inválida');
            if ($callbackInvalido) {
                $payment = $asaas->createPayment(
                    $montarPayloadPagamento(
                        $guardianData['asaas_customer_id'],
                        (string) $billingType,
                        (float) $amount,
                        (string) $date,
                        (string) $dailyType,
                        false
                    )
                );
                if ($payment['ok']) {
                    goto payment_success;
                }
                $errorMessage = $extractAsaasError($payment);
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
$studentIdForPayment = $diariaRow['student_id'] ?? $guardianData['student_id'];
$client->update('diaria', 'id=eq.' . rawurlencode($diariaId), [
    'status_pagamento' => 'PENDENTE',
    'grade_travada' => false,
    'updated_at' => date('c'),
]);

$paymentInsert = $client->insert('payments', [[
    'guardian_id' => $guardianData['id'],
    'student_id' => $studentIdForPayment,
    'diaria_id' => $diariaId,
    'payment_date' => $date,
    'daily_type' => $dailyType,
    'amount' => $amount,
    'status' => 'pending',
    'billing_type' => $billingType,
    'asaas_payment_id' => $paymentData['id'] ?? null,
]]);

$invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? '#';
$paymentDate = date('d/m/Y', strtotime($date));
$dailyLabel = $dailyType === 'emergencial' ? 'Emergencial' : 'Planejada';
$amountFormatted = number_format((float) $amount, 2, ',', '.');
$studentName = $guardianData['student_name']
    ?? ($guardianData['student'] ?? null)
    ?? 'Aluno';
$dayNamesByPrefix = [
    'SEG' => 'Segunda-feira',
    'TER' => 'Terça-feira',
    'QUA' => 'Quarta-feira',
    'QUI' => 'Quinta-feira',
    'SEX' => 'Sexta-feira',
    'SAB' => 'Sábado',
    'DOM' => 'Domingo',
];
$selecoesHtml = '<li>Nenhuma Oficina Modular travada nesta diária.</li>';
$atrativoOficinas = 'As Oficinas Modulares foram planejadas para promover desenvolvimento emocional, autonomia e aprendizagem prática, com atividades dinâmicas e acompanhamento pedagógico contínuo.';
$formatarSlotLabel = static function (string $slotId) use ($dayNamesByPrefix): string {
    if ($slotId !== '' && preg_match('/^([A-Z]{3})_(\d{2}:\d{2})$/', strtoupper($slotId), $m)) {
        $diaNome = $dayNamesByPrefix[$m[1]] ?? $m[1];
        $horaIni = $m[2];
        $horaFim = $horaIni === '14:00' ? '15:00' : ($horaIni === '15:40' ? '16:40' : '');
        return $horaFim !== '' ? ($diaNome . ' • ' . $horaIni . '-' . $horaFim) : ($diaNome . ' • ' . $horaIni);
    }
    return '';
};
$selecoesResult = $client->select(
    'diaria_slots_travados',
    'select=slot_id,oficina_modular:oficina_modular_id(nome)'
    . '&diaria_id=eq.' . rawurlencode($diariaId)
    . '&order=slot_id.asc'
);
if (($selecoesResult['ok'] ?? false) && is_array($selecoesResult['data']) && !empty($selecoesResult['data'])) {
    $itens = [];
    $slotsComOficina = [];
    foreach ($selecoesResult['data'] as $row) {
        $slotId = (string) ($row['slot_id'] ?? '');
        if ($slotId !== '') {
            $slotsComOficina[strtoupper($slotId)] = true;
        }
        $oficinaJoin = is_array($row['oficina_modular'] ?? null) ? $row['oficina_modular'] : [];
        $nomeOficina = (string) (($oficinaJoin['nome'] ?? '') ?: 'Oficina Modular');
        $horarioLabel = $formatarSlotLabel($slotId);
        $linha = htmlspecialchars($nomeOficina, ENT_QUOTES, 'UTF-8');
        if ($horarioLabel !== '') {
            $linha .= ' <span style="color:#556070">(' . htmlspecialchars($horarioLabel, ENT_QUOTES, 'UTF-8') . ')</span>';
        }
        $itens[] = '<li>' . $linha . '</li>';
    }
    if (is_array($orientadoraSlotsPayload)) {
        $slotsOrientadora = [];
        foreach ($orientadoraSlotsPayload as $slotRaw) {
            $slotId = strtoupper(trim((string) $slotRaw));
            if ($slotId === '' || isset($slotsOrientadora[$slotId])) {
                continue;
            }
            $slotsOrientadora[$slotId] = true;
            if (isset($slotsComOficina[$slotId])) {
                continue;
            }
            $horarioLabel = $formatarSlotLabel($slotId);
            $linha = 'A Oficina Modular deve ser escolhida pela Orientadora';
            if ($horarioLabel !== '') {
                $linha .= ' <span style="color:#556070">(' . htmlspecialchars($horarioLabel, ENT_QUOTES, 'UTF-8') . ')</span>';
            }
            $itens[] = '<li>' . $linha . '</li>';
        }
    }
    if (!empty($itens)) {
        $selecoesHtml = implode('', $itens);
    }
}

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

                <div style="margin-top:14px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">
                    Oficinas Modulares escolhidas
                  </div>
                  <ul style="margin:0;padding-left:18px;font-size:14px;line-height:1.7;color:#1B2333;">
                    {{oficinas_escolhidas}}
                  </ul>
                  <div style="margin-top:10px;font-size:14px;line-height:1.6;color:#1B2333;">
                    {{descricao_oficinas}}
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
    '{{oficinas_escolhidas}}' => $selecoesHtml,
    '{{descricao_oficinas}}' => htmlspecialchars($atrativoOficinas, ENT_QUOTES, 'UTF-8'),
];
$html = strtr($template, $replace);

$mailer->send(
    $guardianData['email'],
    'Pagamento criado - Diárias Village',
    $html
);

$paymentRow = (($paymentInsert['ok'] ?? false) && !empty($paymentInsert['data'][0]) && is_array($paymentInsert['data'][0]))
    ? $paymentInsert['data'][0]
    : null;
$paymentId = (string) ($paymentRow['id'] ?? '');
if (isset($_SESSION['grade_checkout_ready']) && is_array($_SESSION['grade_checkout_ready'])) {
    unset($_SESSION['grade_checkout_ready'][$diariaId]);
}
if (isset($_SESSION['grade_checkout_tokens']) && is_array($_SESSION['grade_checkout_tokens'])) {
    unset($_SESSION['grade_checkout_tokens'][$checkoutToken]);
}

Helpers::json([
    'ok' => true,
    'invoice_url' => $invoiceUrl,
    'payment_id' => $paymentId,
    'success_url' => $paymentId !== '' ? ('/pagamento-sucesso.php?paymentId=' . rawurlencode($paymentId)) : null,
    'retorno_url' => '/pagamento-retorno.php?diariaId=' . rawurlencode($diariaId) . '&invoiceUrl=' . rawurlencode($invoiceUrl),
]);
