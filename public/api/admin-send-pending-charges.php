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
ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(180);
}

function resolveDayUseChargeLocal(string $dayUseDate): array
{
    $timestamp = strtotime($dayUseDate);
    if ($timestamp === false) {
        return ['amount' => 77.00, 'daily_type' => 'planejada'];
    }
    $dayUseIso = date('Y-m-d', $timestamp);
    $tz = new \DateTimeZone('America/Sao_Paulo');
    $now = new \DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $hour = (int) $now->format('H');
    $promoDeadline = '2026-03-16';

    if ($dayUseIso <= $promoDeadline) {
        return ['amount' => 77.00, 'daily_type' => 'planejada'];
    }

    if ($dayUseIso > $today) {
        return ['amount' => 77.00, 'daily_type' => 'planejada'];
    }

    if ($dayUseIso === $today && $hour < 10) {
        return ['amount' => 77.00, 'daily_type' => 'planejada'];
    }

    return ['amount' => 97.00, 'daily_type' => 'emergencial'];
}

function extractAsaasError(array $response): string
{
    $data = $response['data'] ?? null;
    if (is_array($data) && !empty($data['errors']) && is_array($data['errors'])) {
        $messages = [];
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
    if (is_array($data) && !empty($data['message'])) {
        return (string) $data['message'];
    }
    return 'Falha ao processar cobrança no Asaas.';
}

function parseBrDateToIso(string $raw): ?string
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

function isDeliverableGuardianEmailLocal(string $email): bool
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return !preg_match('/@(diariasvillage|placeholder)\.local$/i', $email);
}

/**
 * @return string[]
 */
function extractIsoDatesFromPaymentRow(array $paymentRow, string $today): array
{
    $dailyRaw = trim((string) ($paymentRow['daily_type'] ?? ''));
    $parts = explode('|', $dailyRaw, 2);
    $datesLabelRaw = trim((string) ($parts[1] ?? ''));

    $isoDates = [];
    if ($datesLabelRaw !== '') {
        foreach (explode(',', $datesLabelRaw) as $chunk) {
            $iso = parseBrDateToIso((string) $chunk);
            if ($iso !== null) {
                $isoDates[$iso] = true;
            }
        }
    }
    if (empty($isoDates)) {
        $fallback = parseBrDateToIso((string) ($paymentRow['payment_date'] ?? ''));
        if ($fallback !== null) {
            $isoDates[$fallback] = true;
        }
    }
    if (empty($isoDates)) {
        $isoDates[$today] = true;
    }

    $isoKeys = array_keys($isoDates);
    sort($isoKeys);
    return $isoKeys;
}

function buildChargeSignature(array $paymentRow, string $today): string
{
    $isoKeys = extractIsoDatesFromPaymentRow($paymentRow, $today);
    return implode(',', $isoKeys);
}

/**
 * @return array{amount: float, daily_base_type: string, date_label: string}
 */
function resolveQueuedChargeRule(array $paymentRow, string $today): array
{
    $isoKeys = extractIsoDatesFromPaymentRow($paymentRow, $today);

    $amount = 0.0;
    $hasEmergencial = false;
    $dateLabels = [];
    foreach ($isoKeys as $isoDate) {
        $rule = resolveDayUseChargeLocal((string) $isoDate);
        $amount += (float) ($rule['amount'] ?? 77.0);
        if (($rule['daily_type'] ?? 'planejada') === 'emergencial') {
            $hasEmergencial = true;
        }
        $dateLabels[] = date('d/m/Y', strtotime((string) $isoDate));
    }

    return [
        'amount' => $amount,
        'daily_base_type' => $hasEmergencial ? 'emergencial' : 'planejada',
        'date_label' => implode(', ', $dateLabels),
    ];
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    \App\Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        \App\Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $paymentIds = $payload['payment_ids'] ?? [];
    if (!is_array($paymentIds) || empty($paymentIds)) {
        \App\Helpers::json(['ok' => false, 'error' => 'Nenhuma cobrança pendente selecionada.'], 422);
    }

    $paymentIds = array_values(array_unique(array_filter(array_map(
        static fn($id) => trim((string) $id),
        $paymentIds
    ))));
    if (empty($paymentIds)) {
        \App\Helpers::json(['ok' => false, 'error' => 'IDs inválidos.'], 422);
    }

    $asaas = new \App\AsaasClient(new \App\HttpClient());
    $mailer = new \App\Mailer();
    $client = new \App\SupabaseClient(new \App\HttpClient());
    $today = date('Y-m-d');
    $portalLink = \App\Helpers::baseUrl() ?: 'https://diarias.village.einsteinhub.co';
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    $results = [];

    foreach ($paymentIds as $paymentId) {
        try {
            $paymentResult = $client->select(
                'payments',
                'select=id,guardian_id,student_id,payment_date,daily_type,amount,status,billing_type'
                . '&id=eq.' . urlencode($paymentId) . '&limit=1'
            );
            $paymentRow = $paymentResult['data'][0] ?? null;
            if (!$paymentRow) {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Cobrança não encontrada.'];
                continue;
            }
            if (($paymentRow['billing_type'] ?? '') !== 'PIX_MANUAL_QUEUE' || ($paymentRow['status'] ?? '') !== 'queued') {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Cobrança já enviada ou inválida para envio.'];
                continue;
            }
            $paymentSignature = buildChargeSignature((array) $paymentRow, $today);

            $studentId = trim((string) ($paymentRow['student_id'] ?? ''));
            $guardianId = trim((string) ($paymentRow['guardian_id'] ?? ''));
            $possibleDuplicatesQuery = 'select=id,status,billing_type,daily_type,payment_date,paid_at,asaas_payment_id'
                . '&guardian_id=eq.' . urlencode($guardianId)
                . '&limit=200';
            if ($studentId !== '') {
                $possibleDuplicatesQuery .= '&student_id=eq.' . urlencode($studentId);
            }
            $possibleDuplicatesResult = $client->select('payments', $possibleDuplicatesQuery);
            $possibleDuplicates = ($possibleDuplicatesResult['ok'] ?? false) && is_array($possibleDuplicatesResult['data'] ?? null)
                ? $possibleDuplicatesResult['data']
                : [];

            $duplicateFound = null;
            foreach ($possibleDuplicates as $candidate) {
                $candidateId = trim((string) ($candidate['id'] ?? ''));
                if ($candidateId === '' || $candidateId === $paymentId) {
                    continue;
                }
                $candidateStatus = strtolower(trim((string) ($candidate['status'] ?? '')));
                if (in_array($candidateStatus, ['canceled', 'cancelled', 'deleted', 'refunded'], true)) {
                    continue;
                }
                if ($candidateStatus === 'queued' && strtoupper((string) ($candidate['billing_type'] ?? '')) === 'PIX_MANUAL_QUEUE') {
                    continue;
                }
                $candidateSignature = buildChargeSignature((array) $candidate, $today);
                if ($candidateSignature !== $paymentSignature) {
                    continue;
                }
                $candidateAsaasId = trim((string) ($candidate['asaas_payment_id'] ?? ''));
                if (
                    $candidateAsaasId !== ''
                    || in_array($candidateStatus, ['pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis', 'paid'], true)
                    || !empty($candidate['paid_at'])
                ) {
                    $duplicateFound = [
                        'id' => $candidateId,
                        'status' => $candidateStatus !== '' ? $candidateStatus : 'desconhecido',
                    ];
                    break;
                }
            }
            if (is_array($duplicateFound)) {
                $results[] = [
                    'id' => $paymentId,
                    'ok' => false,
                    'error' => 'Cobrança duplicada bloqueada: já existe cobrança para este responsável/aluno nas mesmas datas (ID '
                        . $duplicateFound['id'] . ', status ' . $duplicateFound['status'] . ').',
                ];
                continue;
            }

            $guardianResult = $client->select(
                'guardians',
                'select=id,parent_name,parent_phone,parent_document,email,asaas_customer_id'
                . '&id=eq.' . urlencode((string) ($paymentRow['guardian_id'] ?? '')) . '&limit=1'
            );
            $guardian = $guardianResult['data'][0] ?? null;
            if (!$guardian) {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Responsável não encontrado.'];
                continue;
            }
            $studentResult = $client->select(
                'students',
                'select=id,name&' . 'id=eq.' . urlencode((string) ($paymentRow['student_id'] ?? '')) . '&limit=1'
            );
            $student = $studentResult['data'][0] ?? null;
            if (!$student) {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Aluno não encontrado.'];
                continue;
            }

            $guardianName = trim((string) ($guardian['parent_name'] ?? 'Responsável'));
            $guardianEmail = trim((string) ($guardian['email'] ?? ''));
            $guardianDoc = preg_replace('/\D+/', '', (string) ($guardian['parent_document'] ?? '')) ?? '';
            $guardianPhone = preg_replace('/\D+/', '', (string) ($guardian['parent_phone'] ?? '')) ?? '';
            if (!isDeliverableGuardianEmailLocal($guardianEmail)) {
                $results[] = [
                    'id' => $paymentId,
                    'ok' => false,
                    'error' => 'E-mail do responsável inválido ou placeholder. Atualize o e-mail antes de enviar.',
                ];
                continue;
            }

            $customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));
            if ($customerId === '') {
                $customerPayload = ['name' => $guardianName, 'email' => $guardianEmail];
                if ($guardianDoc !== '') {
                    $customerPayload['cpfCnpj'] = $guardianDoc;
                }
                if ($guardianPhone !== '') {
                    $customerPayload['mobilePhone'] = $guardianPhone;
                }
                $customer = $asaas->createCustomer($customerPayload);
                if (!$customer['ok']) {
                    $results[] = ['id' => $paymentId, 'ok' => false, 'error' => extractAsaasError($customer)];
                    continue;
                }
                $customerId = (string) ($customer['data']['id'] ?? '');
                if ($customerId === '') {
                    $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Cliente Asaas inválido.'];
                    continue;
                }
                $client->update('guardians', 'id=eq.' . urlencode((string) $guardian['id']), [
                    'asaas_customer_id' => $customerId,
                ]);
            }
            $chargeRule = resolveQueuedChargeRule((array) $paymentRow, $today);

            $payment = $asaas->createPayment([
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => (float) ($chargeRule['amount'] ?? 77.00),
                'dueDate' => $today,
                'description' => 'Diária ' . ($chargeRule['daily_base_type'] ?? 'planejada') . ' - cobrança manual - Einstein Village',
            ]);
            if (!$payment['ok']) {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => extractAsaasError($payment)];
                continue;
            }

            $paymentData = $payment['data'] ?? [];
            $invoiceUrl = $paymentData['invoiceUrl'] ?? $paymentData['bankSlipUrl'] ?? $portalLink;
            $dailyBaseType = (string) ($chargeRule['daily_base_type'] ?? 'planejada');
            $dateLabel = (string) ($chargeRule['date_label'] ?? date('d/m/Y', strtotime((string) ($paymentRow['payment_date'] ?? $today))));
            $amountFormatted = number_format((float) ($chargeRule['amount'] ?? 77.00), 2, ',', '.');

            $updatePayment = $client->update('payments', 'id=eq.' . urlencode($paymentId), [
                'billing_type' => 'PIX_MANUAL',
                'status' => 'pending_asaas',
                'asaas_payment_id' => $paymentData['id'] ?? null,
                'amount' => (float) ($chargeRule['amount'] ?? 77.00),
                'daily_type' => $dailyBaseType . '|' . $dateLabel,
            ]);
            if (!($updatePayment['ok'] ?? false)) {
                $results[] = ['id' => $paymentId, 'ok' => false, 'error' => 'Cobrança criada no Asaas, mas falhou ao atualizar status local.'];
                continue;
            }

            $mailHtml = '<p>Olá!</p>'
              . '<p>Identificamos uma diária pendente de <strong>' . htmlspecialchars((string) ($student['name'] ?? 'Aluno'), ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
              . '<p>Datas: <strong>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</strong><br>'
              . 'Valor: <strong>R$ ' . $amountFormatted . '</strong></p>'
              . '<p><a href="' . htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8') . '">Clique aqui para pagar</a></p>';
            try {
                $mailResult = $mailer->send($guardianEmail, 'Regularização da diária utilizada • Diárias Village', $mailHtml);
                if (!($mailResult['ok'] ?? false)) {
                    $results[] = [
                        'id' => $paymentId,
                        'ok' => true,
                        'invoice_url' => $invoiceUrl,
                        'warning' => 'Cobrança criada, mas houve falha no envio do e-mail.',
                    ];
                    continue;
                }
            } catch (\Throwable $mailError) {
                $results[] = [
                    'id' => $paymentId,
                    'ok' => true,
                    'invoice_url' => $invoiceUrl,
                    'warning' => 'Cobrança criada, mas houve exceção no envio do e-mail.',
                ];
                continue;
            }

            $results[] = ['id' => $paymentId, 'ok' => true, 'invoice_url' => $invoiceUrl];
        } catch (\Throwable $itemError) {
            @file_put_contents($logPath, '[admin-send-pending-charges:item:' . $paymentId . '] ' . $itemError->getMessage() . PHP_EOL, FILE_APPEND);
            $results[] = [
                'id' => $paymentId,
                'ok' => false,
                'error' => 'Falha interna ao processar esta cobrança.',
            ];
            continue;
        }
    }

    $failures = array_values(array_filter($results, static fn($row) => !($row['ok'] ?? false)));
    \App\Helpers::json([
        'ok' => empty($failures),
        'results' => $results,
        'error' => empty($failures) ? null : ($failures[0]['error'] ?? 'Falha ao enviar cobranças pendentes.'),
    ]);
} catch (\Throwable $e) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    @file_put_contents(
        $logPath,
        '[admin-send-pending-charges] ' . $e->getMessage() . ' | file=' . $e->getFile() . ' | line=' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
    \App\Helpers::json([
        'ok' => false,
        'error' => 'Falha interna ao enviar cobranças pendentes. Tente novamente em 1 minuto.',
        'details' => $e->getMessage(),
    ]);
}
