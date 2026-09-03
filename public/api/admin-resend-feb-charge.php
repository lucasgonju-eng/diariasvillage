<?php
$bootstrapCandidates = array(
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
);
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
    echo json_encode(array('ok' => false, 'error' => 'Bootstrap não encontrado.'));
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

function parseDateToIso(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $raw)) {
        [$day, $month, $year] = explode('/', $raw);
        $yearInt = (int) $year;
        if ($yearInt < 100) {
            $yearInt += 2000;
        }
        if (!checkdate((int) $month, (int) $day, $yearInt)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $yearInt, (int) $month, (int) $day);
    }
    $time = strtotime($raw);
    if ($time === false) {
        return null;
    }
    return date('Y-m-d', $time);
}

function extractIsoDatesFromPaymentRow(array $paymentRow): array
{
    $dailyRaw = trim((string) ($paymentRow['daily_type'] ?? ''));
    $parts = explode('|', $dailyRaw, 2);
    $datesLabelRaw = trim((string) ($parts[1] ?? ''));

    $isoDates = array();
    if ($datesLabelRaw !== '') {
        foreach (explode(',', $datesLabelRaw) as $chunk) {
            $iso = parseDateToIso((string) $chunk);
            if ($iso !== null) {
                $isoDates[$iso] = true;
            }
        }
    }
    if (empty($isoDates)) {
        $fallback = parseDateToIso((string) ($paymentRow['payment_date'] ?? ''));
        if ($fallback !== null) {
            $isoDates[$fallback] = true;
        }
    }
    $keys = array_keys($isoDates);
    sort($keys);
    return $keys;
}

function asaasErrorMessage(array $response): string
{
    $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
    if (isset($data['errors']) && is_array($data['errors'])) {
        $messages = array();
        foreach ($data['errors'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $description = trim((string) ($entry['description'] ?? ''));
            if ($description !== '') {
                $messages[] = $description;
            }
        }
        if (!empty($messages)) {
            return implode(' | ', array_values(array_unique($messages)));
        }
    }
    if (!empty($response['error'])) {
        return (string) $response['error'];
    }
    if (!empty($data['message'])) {
        return (string) $data['message'];
    }
    return 'Falha ao processar cobrança no Asaas.';
}

\App\Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    \App\Helpers::json(array('ok' => false, 'error' => 'Método inválido.'), 405);
}

$logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = array();
    }
    $paymentId = trim((string) ($payload['payment_id'] ?? $payload['id'] ?? ''));
    if ($paymentId === '') {
        \App\Helpers::json(array('ok' => false, 'error' => 'ID da cobrança não informado.'), 422);
    }

    $client = new \App\SupabaseClient(new \App\HttpClient());
    $asaas = new \App\AsaasClient(new \App\HttpClient());
    $paymentLifecycle = new \App\Services\AsaasPaymentLifecycle($asaas);
    $mailer = new \App\Mailer();
    $today = date('Y-m-d');
    $portalLink = \App\Helpers::baseUrl() ?: 'https://diarias.village.einsteinhub.co';

    $paymentResult = $client->select(
        'payments',
        'select=id,guardian_id,student_id,payment_date,daily_type,amount,status,billing_type,asaas_payment_id'
        . '&id=eq.' . urlencode($paymentId) . '&limit=1'
    );
    $paymentRow = $paymentResult['data'][0] ?? null;
    if (!$paymentRow) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Cobrança não encontrada.'), 404);
    }

    $statusRaw = strtolower(trim((string) ($paymentRow['status'] ?? '')));
    if (in_array($statusRaw, array('paid', 'received', 'confirmed', 'canceled', 'cancelled', 'deleted', 'refunded'), true)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Cobrança já concluída/cancelada e não pode ser reenviada.'), 422);
    }
    if ($statusRaw === 'processing_asaas') {
        \App\Helpers::json(array('ok' => false, 'error' => 'Cobrança já está sendo processada.'), 409);
    }
    if (!in_array($statusRaw, array('queued', 'pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis'), true)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Estado local desconhecido. Reenvio bloqueado.'), 409);
    }

    $isoDates = extractIsoDatesFromPaymentRow((array) $paymentRow);
    if (empty($isoDates)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Não foi possível identificar as datas da cobrança.'), 422);
    }
    foreach ($isoDates as $isoDate) {
        if (substr((string) $isoDate, 5, 2) !== '02') {
            \App\Helpers::json(array('ok' => false, 'error' => 'Este botão é exclusivo para cobranças de fevereiro.'), 422);
        }
    }

    $guardianId = trim((string) ($paymentRow['guardian_id'] ?? ''));
    $studentId = trim((string) ($paymentRow['student_id'] ?? ''));
    if ($guardianId === '' || $studentId === '') {
        \App\Helpers::json(array('ok' => false, 'error' => 'Cobrança sem vínculo válido de responsável/aluno.'), 422);
    }
    if ((new \App\Services\MonthlyWorkshopService($client))->getActivePlan($studentId) !== null) {
        \App\Helpers::json(array(
            'ok' => false,
            'code' => 'MONTHLY_PLAN_NO_CHARGE',
            'error' => 'Aluno mensalista não gera PIX.',
        ), 409);
    }

    $guardianResult = $client->select(
        'guardians',
        'select=id,parent_name,parent_phone,parent_document,email,asaas_customer_id'
        . '&id=eq.' . urlencode($guardianId) . '&limit=1'
    );
    $guardian = $guardianResult['data'][0] ?? null;
    if (!$guardian) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Responsável não encontrado.'), 404);
    }
    $guardianEmail = trim((string) ($guardian['email'] ?? ''));
    if ($guardianEmail === '' || !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'E-mail do responsável inválido.'), 422);
    }

    $studentResult = $client->select(
        'students',
        'select=id,name&id=eq.' . urlencode($studentId) . '&limit=1'
    );
    $student = $studentResult['data'][0] ?? null;
    if (!$student) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Aluno não encontrado.'), 404);
    }

    $identity = (new \App\AsaasCustomerIdentity($asaas, $client))->resolve($guardian);
    if (!($identity['ok'] ?? false)) {
        \App\Helpers::json(array(
            'ok' => false,
            'error' => (string) ($identity['error'] ?? 'Identidade do responsável não validada.'),
            'code' => (string) ($identity['code'] ?? 'ASAAS_IDENTITY_VALIDATION_FAILED'),
        ), (int) ($identity['status'] ?? 422));
    }
    $customerId = (string) $identity['customer_id'];
    $amount = (float) ($paymentRow['amount'] ?? 0);
    if ($amount <= 0) {
        $amount = 77.00;
    }

    $existingAsaasPaymentId = trim((string) ($paymentRow['asaas_payment_id'] ?? ''));
    if ($existingAsaasPaymentId === '') {
        \App\Helpers::json(array(
            'ok' => false,
            'error' => 'Cobrança sem vínculo Asaas. Use o processamento da fila manual.',
        ), 422);
    }
    $mustCreateNewCharge = false;
    $invoiceUrl = '';
    $effectiveAsaasPaymentId = $existingAsaasPaymentId;
    $createdNewCharge = false;
    $existingPaymentResponse = null;

    $asaasPaymentResponse = $asaas->getPayment($existingAsaasPaymentId);
    if (!($asaasPaymentResponse['ok'] ?? false) && (int) ($asaasPaymentResponse['status'] ?? 0) === 404) {
        $asaasPaymentResponse = array(
            'ok' => true,
            'status' => 200,
            'data' => array('status' => 'DELETED', 'customer' => $customerId),
        );
    }
    if (!($asaasPaymentResponse['ok'] ?? false) || !is_array($asaasPaymentResponse['data'] ?? null)) {
        \App\Helpers::json(array(
            'ok' => false,
            'error' => 'Não foi possível confirmar a cobrança atual no Asaas. Tente novamente.',
        ), 503);
    }
    $existingPaymentResponse = $asaasPaymentResponse;
    $asaasPaymentData = $asaasPaymentResponse['data'];
    $asaasStatus = strtoupper(trim((string) ($asaasPaymentData['status'] ?? '')));
    if (in_array($asaasStatus, array('RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'), true)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Cobrança já consta como paga no Asaas.'), 422);
    }
    $remoteCustomerId = trim((string) ($asaasPaymentData['customer'] ?? ''));
    if ($remoteCustomerId === '' || $remoteCustomerId !== $customerId) {
        \App\Helpers::json(array(
            'ok' => false,
            'error' => 'A cobrança existente não pertence ao responsável validado.',
        ), 409);
    }
    $closedStatuses = array('CANCELED', 'CANCELLED', 'DELETED', 'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS');
    $reusableStatuses = array('PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS');
    if (in_array($asaasStatus, $closedStatuses, true)) {
        $mustCreateNewCharge = true;
    } elseif (!in_array($asaasStatus, $reusableStatuses, true)) {
        \App\Helpers::json(array(
            'ok' => false,
            'error' => 'Estado remoto desconhecido. Reenvio bloqueado para revisão.',
        ), 409);
    } else {
        $invoiceUrl = trim((string) ($asaasPaymentData['invoiceUrl'] ?? ($asaasPaymentData['bankSlipUrl'] ?? '')));
        $remoteValue = (float) ($asaasPaymentData['value'] ?? 0);
        $mustCreateNewCharge = $invoiceUrl === ''
            || $remoteValue <= 0
            || abs($remoteValue - $amount) > 0.009;
    }

    $dateLabels = array();
    foreach ($isoDates as $isoDate) {
        $dateLabels[] = date('d/m/Y', strtotime((string) $isoDate));
    }
    $dateLabel = implode(', ', $dateLabels);

    if ($mustCreateNewCharge || $invoiceUrl === '') {
        $operationToken = bin2hex(random_bytes(16));
        $claim = $client->update(
            'payments',
            'id=eq.' . urlencode($paymentId) . '&status=eq.' . urlencode($statusRaw),
            array(
                'status' => 'processing_asaas',
                'asaas_operation_token' => $operationToken,
            )
        );
        if (!($claim['ok'] ?? false) || empty($claim['data'][0])) {
            \App\Helpers::json(array(
                'ok' => false,
                'error' => 'Cobrança já está sendo processada por outra requisição.',
            ), 409);
        }
        $releaseClaim = static function () use ($client, $paymentId, $statusRaw, $operationToken): void {
            $client->update(
                'payments',
                'id=eq.' . urlencode($paymentId)
                    . '&status=eq.processing_asaas&asaas_operation_token=eq.' . urlencode($operationToken),
                array(
                    'status' => $statusRaw,
                    'asaas_operation_token' => null,
                )
            );
        };

        $cancelOld = $paymentLifecycle->cancelBeforeLocalMutation(
            $existingAsaasPaymentId,
            $existingPaymentResponse,
            $guardian
        );
        if (!($cancelOld['ok'] ?? false)) {
            $releaseClaim();
            \App\Helpers::json(array(
                'ok' => false,
                'error' => (string) ($cancelOld['error'] ?? 'Não foi possível cancelar a cobrança anterior.'),
            ), 409);
        }

        $createdNewCharge = true;
        $dailyBaseRaw = strtolower(trim((string) (explode('|', (string) ($paymentRow['daily_type'] ?? ''), 2)[0] ?? 'planejada')));
        $dailyBaseType = $dailyBaseRaw === 'emergencial' ? 'emergencial' : 'planejada';
        $createPaymentResponse = $asaas->createPayment(array(
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $amount,
            'dueDate' => $today,
            'description' => 'Diária ' . $dailyBaseType . ' - reenvio fevereiro - Einstein Village',
            'externalReference' => 'payment:' . $paymentId . ':' . $operationToken,
        ));
        if (!($createPaymentResponse['ok'] ?? false)) {
            if ($paymentLifecycle->isDefinitiveCreationRejection($createPaymentResponse)) {
                $releaseClaim();
                \App\Helpers::json(array(
                    'ok' => false,
                    'error' => asaasErrorMessage($createPaymentResponse),
                ), 422);
            }
            \App\Helpers::json(array(
                'ok' => false,
                'error' => 'Resultado remoto incerto. Cobrança travada para conciliação segura.',
            ), 503);
        }
        $newPaymentData = is_array($createPaymentResponse['data'] ?? null) ? $createPaymentResponse['data'] : array();
        $invoiceUrl = trim((string) ($newPaymentData['invoiceUrl'] ?? ($newPaymentData['bankSlipUrl'] ?? '')));
        $effectiveAsaasPaymentId = trim((string) ($newPaymentData['id'] ?? ''));
        if ($effectiveAsaasPaymentId === '' || $invoiceUrl === '') {
            $compensation = $paymentLifecycle->compensateCreatedPayment($effectiveAsaasPaymentId);
            if ($compensation['ok'] ?? false) {
                $releaseClaim();
            }
            \App\Helpers::json(array(
                'ok' => false,
                'error' => ($compensation['ok'] ?? false)
                    ? 'Cobrança inválida retornada pelo Asaas; ela foi cancelada.'
                    : 'Cobrança inválida e falha na compensação. Revisão manual obrigatória.',
            ), 502);
        }
        $updateResult = $client->update(
            'payments',
            'id=eq.' . urlencode($paymentId)
                . '&status=eq.processing_asaas&asaas_operation_token=eq.' . urlencode($operationToken),
            array(
            'billing_type' => 'PIX_MANUAL',
            'status' => 'pending_asaas',
            'asaas_payment_id' => $effectiveAsaasPaymentId,
            'asaas_operation_token' => null,
            )
        );
        if (!($updateResult['ok'] ?? false) || empty($updateResult['data'][0])) {
            $compensation = $paymentLifecycle->compensateCreatedPayment($effectiveAsaasPaymentId);
            if ($compensation['ok'] ?? false) {
                $releaseClaim();
            }
            \App\Helpers::json(array(
                'ok' => false,
                'error' => ($compensation['ok'] ?? false)
                    ? 'Falha local; a nova cobrança foi cancelada no Asaas.'
                    : 'Falha local e falha na compensação remota. Revisão manual obrigatória.',
            ), 500);
        }
    } else {
        if ($statusRaw !== 'pending' && $statusRaw !== 'pending_asaas' && $statusRaw !== 'awaiting_risk_analysis') {
            $statusUpdate = $client->update(
                'payments',
                'id=eq.' . urlencode($paymentId),
                array('status' => 'pending_asaas')
            );
            if (!($statusUpdate['ok'] ?? false) || empty($statusUpdate['data'][0])) {
                \App\Helpers::json(array(
                    'ok' => false,
                    'error' => 'Falha ao sincronizar o estado local da cobrança existente.',
                ), 500);
            }
        }
    }

    if ($invoiceUrl === '') {
        $invoiceUrl = $portalLink;
    }
    $amountFormatted = number_format($amount, 2, ',', '.');
    $studentName = htmlspecialchars((string) ($student['name'] ?? 'Aluno'), ENT_QUOTES, 'UTF-8');
    $safeDateLabel = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
    $safeInvoiceUrl = htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8');
    $mailHtml = '<p>Olá!</p>'
        . '<p>Reenviamos a cobrança de fevereiro referente ao aluno <strong>' . $studentName . '</strong>.</p>'
        . '<p>Datas: <strong>' . $safeDateLabel . '</strong><br>'
        . 'Valor: <strong>R$ ' . $amountFormatted . '</strong></p>'
        . '<p><a href="' . $safeInvoiceUrl . '">Clique aqui para pagar</a></p>';

    $mailResult = $mailer->send($guardianEmail, 'Reenvio de cobrança de fevereiro • Diárias Village', $mailHtml);
    if (!($mailResult['ok'] ?? false)) {
        \App\Helpers::json(array(
            'ok' => true,
            'created_new_charge' => $createdNewCharge,
            'reused_existing_charge' => !$createdNewCharge,
            'invoice_url' => $invoiceUrl,
            'asaas_payment_id' => $effectiveAsaasPaymentId !== '' ? $effectiveAsaasPaymentId : null,
            'email_sent' => false,
            'warning' => 'Cobrança preparada, mas houve falha no envio do e-mail.',
        ));
    }

    \App\Helpers::json(array(
        'ok' => true,
        'created_new_charge' => $createdNewCharge,
        'reused_existing_charge' => !$createdNewCharge,
        'invoice_url' => $invoiceUrl,
        'asaas_payment_id' => $effectiveAsaasPaymentId !== '' ? $effectiveAsaasPaymentId : null,
        'email_sent' => true,
    ));
} catch (Throwable $e) {
    @file_put_contents(
        $logPath,
        '[admin-resend-feb-charge] ' . $e->getMessage() . ' | file=' . $e->getFile() . ' | line=' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
    \App\Helpers::json(array(
        'ok' => false,
        'error' => 'Falha interna ao reenviar cobrança de fevereiro.',
        'details' => $e->getMessage(),
    ), 500);
}
