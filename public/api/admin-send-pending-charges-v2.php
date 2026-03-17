<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');
ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(180);
}

function asaasErrorMessage($response)
{
    if (!is_array($response)) {
        return 'Falha ao processar cobrança no Asaas.';
    }
    $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
    if (isset($data['errors']) && is_array($data['errors'])) {
        $messages = array();
        foreach ($data['errors'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $description = trim((string) (isset($entry['description']) ? $entry['description'] : ''));
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

function parseDateToIso($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $raw)) {
        $parts = explode('/', $raw);
        if (count($parts) !== 3) {
            return null;
        }
        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];
        if ($year < 100) {
            $year += 2000;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    $time = strtotime($raw);
    if ($time === false) {
        return null;
    }
    return date('Y-m-d', $time);
}

function extractIsoDates($paymentRow, $todayIso)
{
    $dailyRaw = trim((string) (isset($paymentRow['daily_type']) ? $paymentRow['daily_type'] : ''));
    $parts = explode('|', $dailyRaw, 2);
    $datesLabelRaw = trim((string) (isset($parts[1]) ? $parts[1] : ''));

    $isoDates = array();
    if ($datesLabelRaw !== '') {
        $chunks = explode(',', $datesLabelRaw);
        foreach ($chunks as $chunk) {
            $iso = parseDateToIso($chunk);
            if ($iso !== null) {
                $isoDates[$iso] = true;
            }
        }
    }
    if (empty($isoDates)) {
        $fallback = parseDateToIso(isset($paymentRow['payment_date']) ? $paymentRow['payment_date'] : '');
        if ($fallback !== null) {
            $isoDates[$fallback] = true;
        }
    }
    if (empty($isoDates)) {
        $isoDates[$todayIso] = true;
    }
    $keys = array_keys($isoDates);
    sort($keys);
    return $keys;
}

function resolveDayUseCharge($dayUseDate)
{
    $timestamp = strtotime((string) $dayUseDate);
    if ($timestamp === false) {
        return array('amount' => 77.00, 'daily_type' => 'planejada');
    }
    $dayUseIso = date('Y-m-d', $timestamp);
    $tz = new DateTimeZone('America/Sao_Paulo');
    $now = new DateTimeImmutable('now', $tz);
    $today = $now->format('Y-m-d');
    $hour = (int) $now->format('H');

    if ($dayUseIso <= '2026-03-16') {
        return array('amount' => 77.00, 'daily_type' => 'planejada');
    }
    if ($dayUseIso > $today) {
        return array('amount' => 77.00, 'daily_type' => 'planejada');
    }
    if ($dayUseIso === $today && $hour < 10) {
        return array('amount' => 77.00, 'daily_type' => 'planejada');
    }
    return array('amount' => 97.00, 'daily_type' => 'emergencial');
}

function resolveChargeRule($paymentRow, $todayIso)
{
    $isoDates = extractIsoDates($paymentRow, $todayIso);
    $amount = 0.0;
    $hasEmergencial = false;
    $labels = array();
    foreach ($isoDates as $iso) {
        $rule = resolveDayUseCharge($iso);
        $amount += (float) (isset($rule['amount']) ? $rule['amount'] : 77.0);
        if ((isset($rule['daily_type']) ? $rule['daily_type'] : 'planejada') === 'emergencial') {
            $hasEmergencial = true;
        }
        $labels[] = date('d/m/Y', strtotime($iso));
    }
    return array(
        'amount' => $amount,
        'daily_base_type' => $hasEmergencial ? 'emergencial' : 'planejada',
        'date_label' => implode(', ', $labels),
        'signature' => implode(',', $isoDates),
    );
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    \App\Helpers::json(array('ok' => false, 'error' => 'Não autorizado.'), 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    \App\Helpers::json(array('ok' => false, 'error' => 'Método inválido.'), 405);
}

$logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = array();
    }
    $paymentIds = isset($payload['payment_ids']) && is_array($payload['payment_ids']) ? $payload['payment_ids'] : array();
    if (empty($paymentIds)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'Nenhuma cobrança pendente selecionada.'), 422);
    }

    $normalizedIds = array();
    foreach ($paymentIds as $id) {
        $id = trim((string) $id);
        if ($id === '') {
            continue;
        }
        $normalizedIds[$id] = true;
    }
    $paymentIds = array_keys($normalizedIds);
    if (empty($paymentIds)) {
        \App\Helpers::json(array('ok' => false, 'error' => 'IDs inválidos.'), 422);
    }

    $asaas = new \App\AsaasClient(new \App\HttpClient());
    $client = new \App\SupabaseClient(new \App\HttpClient());
    $mailer = new \App\Mailer();
    $today = date('Y-m-d');
    $portalLink = \App\Helpers::baseUrl() ?: 'https://diarias.village.einsteinhub.co';
    $results = array();

    foreach ($paymentIds as $paymentId) {
        try {
            $paymentResult = $client->select(
                'payments',
                'select=id,guardian_id,student_id,payment_date,daily_type,amount,status,billing_type'
                . '&id=eq.' . urlencode($paymentId) . '&limit=1'
            );
            $paymentRow = isset($paymentResult['data'][0]) ? $paymentResult['data'][0] : null;
            if (!$paymentRow) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Cobrança não encontrada.');
                continue;
            }
            if (($paymentRow['billing_type'] ?? '') !== 'PIX_MANUAL_QUEUE' || ($paymentRow['status'] ?? '') !== 'queued') {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Cobrança já enviada ou inválida para envio.');
                continue;
            }

            $chargeRule = resolveChargeRule($paymentRow, $today);
            $signature = (string) $chargeRule['signature'];
            $guardianId = trim((string) ($paymentRow['guardian_id'] ?? ''));
            $studentId = trim((string) ($paymentRow['student_id'] ?? ''));
            $dupQuery = 'select=id,status,billing_type,daily_type,payment_date,paid_at,asaas_payment_id'
                . '&guardian_id=eq.' . urlencode($guardianId)
                . '&limit=200';
            if ($studentId !== '') {
                $dupQuery .= '&student_id=eq.' . urlencode($studentId);
            }
            $dupResult = $client->select('payments', $dupQuery);
            $dupRows = (($dupResult['ok'] ?? false) && is_array($dupResult['data'] ?? null)) ? $dupResult['data'] : array();
            $duplicateFound = null;
            foreach ($dupRows as $candidate) {
                $candidateId = trim((string) ($candidate['id'] ?? ''));
                if ($candidateId === '' || $candidateId === $paymentId) {
                    continue;
                }
                $candidateStatus = strtolower(trim((string) ($candidate['status'] ?? '')));
                if (in_array($candidateStatus, array('canceled', 'cancelled', 'deleted', 'refunded'), true)) {
                    continue;
                }
                if ($candidateStatus === 'queued' && strtoupper((string) ($candidate['billing_type'] ?? '')) === 'PIX_MANUAL_QUEUE') {
                    continue;
                }
                $candidateRule = resolveChargeRule($candidate, $today);
                if (((string) $candidateRule['signature']) !== $signature) {
                    continue;
                }
                $candidateAsaas = trim((string) ($candidate['asaas_payment_id'] ?? ''));
                if (
                    $candidateAsaas !== ''
                    || in_array($candidateStatus, array('pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis', 'paid'), true)
                    || !empty($candidate['paid_at'])
                ) {
                    $duplicateFound = array('id' => $candidateId, 'status' => $candidateStatus !== '' ? $candidateStatus : 'desconhecido');
                    break;
                }
            }
            if ($duplicateFound !== null) {
                $results[] = array(
                    'id' => $paymentId,
                    'ok' => false,
                    'error' => 'Cobrança duplicada bloqueada: já existe cobrança para este responsável/aluno nas mesmas datas (ID '
                        . $duplicateFound['id'] . ', status ' . $duplicateFound['status'] . ').',
                );
                continue;
            }

            $guardianResult = $client->select(
                'guardians',
                'select=id,parent_name,parent_phone,parent_document,email,asaas_customer_id'
                . '&id=eq.' . urlencode($guardianId) . '&limit=1'
            );
            $guardian = isset($guardianResult['data'][0]) ? $guardianResult['data'][0] : null;
            if (!$guardian) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Responsável não encontrado.');
                continue;
            }

            $studentResult = $client->select(
                'students',
                'select=id,name&id=eq.' . urlencode($studentId) . '&limit=1'
            );
            $student = isset($studentResult['data'][0]) ? $studentResult['data'][0] : null;
            if (!$student) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Aluno não encontrado.');
                continue;
            }

            $guardianName = trim((string) ($guardian['parent_name'] ?? 'Responsável'));
            $guardianEmail = trim((string) ($guardian['email'] ?? ''));
            if ($guardianEmail === '' || !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'E-mail do responsável inválido.');
                continue;
            }
            $guardianDoc = preg_replace('/\D+/', '', (string) ($guardian['parent_document'] ?? ''));
            $guardianPhone = preg_replace('/\D+/', '', (string) ($guardian['parent_phone'] ?? ''));

            $customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));
            if ($customerId === '') {
                $customerPayload = array('name' => $guardianName, 'email' => $guardianEmail);
                if ($guardianDoc !== '') {
                    $customerPayload['cpfCnpj'] = $guardianDoc;
                }
                if ($guardianPhone !== '') {
                    $customerPayload['mobilePhone'] = $guardianPhone;
                }
                $customer = $asaas->createCustomer($customerPayload);
                if (!($customer['ok'] ?? false)) {
                    $results[] = array('id' => $paymentId, 'ok' => false, 'error' => asaasErrorMessage($customer));
                    continue;
                }
                $customerId = (string) (($customer['data']['id'] ?? ''));
                if ($customerId === '') {
                    $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Cliente Asaas inválido.');
                    continue;
                }
                $client->update('guardians', 'id=eq.' . urlencode((string) $guardian['id']), array('asaas_customer_id' => $customerId));
            }

            $payment = $asaas->createPayment(array(
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => (float) ($chargeRule['amount'] ?? 77.00),
                'dueDate' => $today,
                'description' => 'Diária ' . ($chargeRule['daily_base_type'] ?? 'planejada') . ' - cobrança manual - Einstein Village',
            ));
            if (!($payment['ok'] ?? false)) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => asaasErrorMessage($payment));
                continue;
            }

            $paymentData = is_array($payment['data'] ?? null) ? $payment['data'] : array();
            $invoiceUrl = (string) ($paymentData['invoiceUrl'] ?? ($paymentData['bankSlipUrl'] ?? $portalLink));
            $dailyBaseType = (string) ($chargeRule['daily_base_type'] ?? 'planejada');
            $dateLabel = (string) ($chargeRule['date_label'] ?? date('d/m/Y'));
            $amountFormatted = number_format((float) ($chargeRule['amount'] ?? 77.00), 2, ',', '.');

            $update = $client->update('payments', 'id=eq.' . urlencode($paymentId), array(
                'billing_type' => 'PIX_MANUAL',
                'status' => 'pending_asaas',
                'asaas_payment_id' => isset($paymentData['id']) ? $paymentData['id'] : null,
                'amount' => (float) ($chargeRule['amount'] ?? 77.00),
                'daily_type' => $dailyBaseType . '|' . $dateLabel,
            ));
            if (!($update['ok'] ?? false)) {
                $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Cobrança criada no Asaas, mas falhou ao atualizar status local.');
                continue;
            }

            $mailHtml = '<p>Olá!</p>'
                . '<p>Identificamos uma diária pendente de <strong>' . htmlspecialchars((string) ($student['name'] ?? 'Aluno'), ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                . '<p>Datas: <strong>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</strong><br>'
                . 'Valor: <strong>R$ ' . $amountFormatted . '</strong></p>'
                . '<p><a href="' . htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8') . '">Clique aqui para pagar</a></p>';

            $warning = null;
            try {
                $mailResult = $mailer->send($guardianEmail, 'Regularização da diária utilizada • Diárias Village', $mailHtml);
                if (!($mailResult['ok'] ?? false)) {
                    $warning = 'Cobrança criada, mas houve falha no envio do e-mail.';
                }
            } catch (Throwable $mailError) {
                $warning = 'Cobrança criada, mas houve exceção no envio do e-mail.';
            }

            $row = array('id' => $paymentId, 'ok' => true, 'invoice_url' => $invoiceUrl);
            if ($warning !== null) {
                $row['warning'] = $warning;
            }
            $results[] = $row;
        } catch (Throwable $itemError) {
            @file_put_contents(
                $logPath,
                '[admin-send-pending-charges-v2:item:' . $paymentId . '] ' . $itemError->getMessage() . ' | file=' . $itemError->getFile() . ' | line=' . $itemError->getLine() . PHP_EOL,
                FILE_APPEND
            );
            $results[] = array('id' => $paymentId, 'ok' => false, 'error' => 'Falha interna ao processar esta cobrança.');
        }
    }

    $failures = array();
    foreach ($results as $r) {
        if (empty($r['ok'])) {
            $failures[] = $r;
        }
    }

    \App\Helpers::json(array(
        'ok' => empty($failures),
        'results' => $results,
        'error' => empty($failures) ? null : (isset($failures[0]['error']) ? $failures[0]['error'] : 'Falha ao enviar cobranças pendentes.'),
    ));
} catch (Throwable $e) {
    @file_put_contents(
        $logPath,
        '[admin-send-pending-charges-v2] ' . $e->getMessage() . ' | file=' . $e->getFile() . ' | line=' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
    \App\Helpers::json(array(
        'ok' => false,
        'error' => 'Falha interna ao enviar cobranças pendentes.',
        'details' => $e->getMessage(),
    ));
}
