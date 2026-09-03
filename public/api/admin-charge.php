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

use App\Helpers;
use App\HttpClient;
use App\AsaasCustomerIdentity;
use App\MonthlyStudents;
use App\SupabaseClient;

function parseDayUseDate(string $date): ?string
{
    $parts = explode('/', $date);
    if (count($parts) !== 3) {
        return null;
    }
    [$day, $month, $year] = $parts;
    $day = (int) $day;
    $month = (int) $month;
    $year = (int) $year;
    if ($year < 100) {
        $year += 2000;
    }
    if (!checkdate($month, $day, $year)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function extractPaymentDates(array $payment): array
{
    $dates = [];
    $primary = trim((string) ($payment['payment_date'] ?? ''));
    if ($primary !== '') {
        $time = strtotime($primary);
        if ($time !== false) {
            $dates[date('Y-m-d', $time)] = true;
        }
    }

    $dailyType = (string) ($payment['daily_type'] ?? '');
    if (preg_match_all('/\b(\d{2}\/\d{2}\/(?:\d{2}|\d{4}))\b/', $dailyType, $matches)) {
        foreach ($matches[1] as $rawDate) {
            $iso = parseDayUseDate($rawDate);
            if ($iso !== null) {
                $dates[$iso] = true;
            }
        }
    }
    return array_keys($dates);
}

function apiErrorMessage(array $response, string $fallback): string
{
    $error = trim((string) ($response['error'] ?? ''));
    if ($error !== '') {
        return $error;
    }
    $data = $response['data'] ?? null;
    if (is_array($data)) {
        $message = trim((string) ($data['message'] ?? ''));
        if ($message !== '') {
            return $message;
        }
        $errors = $data['errors'] ?? null;
        if (is_array($errors) && !empty($errors[0]['description'])) {
            return trim((string) $errors[0]['description']);
        }
    }
    return $fallback;
}

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);

try {
    Helpers::requirePost();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $charges = $payload['charges'] ?? [];

    if (!is_array($charges) || !$charges) {
        Helpers::json(['ok' => false, 'error' => 'Nenhuma cobrança informada.'], 422);
    }

    $client = new SupabaseClient(new HttpClient());
    $monthlyItems = MonthlyStudents::load();
    $monthlyById = MonthlyStudents::mapByStudentId($monthlyItems);
    $monthlyByName = MonthlyStudents::mapByNormalizedName($monthlyItems);
    $results = [];
    $today = date('Y-m-d');

    foreach ($charges as $charge) {
        $studentName = trim((string) ($charge['student_name'] ?? ''));
        $studentIdInput = trim((string) ($charge['student_id'] ?? ''));
        $guardianIdInput = trim((string) ($charge['guardian_id'] ?? ''));
        $guardianName = trim((string) ($charge['guardian_name'] ?? ''));
        $guardianEmail = trim((string) ($charge['guardian_email'] ?? ''));
        $guardianWhatsapp = trim((string) ($charge['guardian_whatsapp'] ?? ''));
        $guardianDocument = trim((string) ($charge['guardian_document'] ?? ''));
        $dayUseDates = $charge['day_use_dates'] ?? [];
        if (!is_array($dayUseDates)) {
            $dayUseDates = [$dayUseDates];
        }
        $dayUseDates = array_values(array_filter(array_map('trim', $dayUseDates)));

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $studentIdInput)) {
            $results[] = [
                'student_name' => $studentName ?: '(sem nome)',
                'ok' => false,
                'error' => 'Selecione o aluno pelo cadastro com matrícula.',
            ];
            continue;
        }
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

        $studentResult = $client->select(
            'students',
            'select=id,name&active=eq.true&id=eq.' . rawurlencode($studentIdInput) . '&limit=1'
        );
        if (!($studentResult['ok'] ?? false)) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => apiErrorMessage($studentResult, 'Falha ao buscar aluno.'),
            ];
            continue;
        }
        $studentRow = $studentResult['data'][0] ?? null;
        if (!$studentRow || empty($studentRow['id'])) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => 'Aluno não encontrado no cadastro.',
            ];
            continue;
        }

        $requestedDatesIso = [];
        $invalidDate = null;
        foreach ($dayUseDates as $rawDate) {
            $parsed = parseDayUseDate((string) $rawDate);
            if ($parsed === null) {
                $invalidDate = (string) $rawDate;
                break;
            }
            $requestedDatesIso[$parsed] = true;
        }
        if ($invalidDate !== null) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => 'Data inválida no day-use: ' . $invalidDate,
            ];
            continue;
        }
        $requestedDatesIso = array_keys($requestedDatesIso);
        sort($requestedDatesIso);
        if (empty($requestedDatesIso)) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => 'Informe ao menos uma data válida de day-use.',
            ];
            continue;
        }

        $monthlyPlan = MonthlyStudents::resolvePlan(
            (string) ($studentRow['id'] ?? ''),
            (string) ($studentRow['name'] ?? $studentName),
            $monthlyById,
            $monthlyByName
        );
        $monthlyCoveredDates = [];
        $monthlyOverflowDates = $requestedDatesIso;
        if (is_array($monthlyPlan)) {
            $weeklyDays = (int) ($monthlyPlan['weekly_days'] ?? 0);
            if (in_array($weeklyDays, [2, 3, 4, 5], true)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => true,
                    'monthly_covered' => true,
                    'monthly_days' => $weeklyDays,
                    'covered_dates' => $requestedDatesIso,
                    'overflow_dates' => [],
                    'message' => sprintf(
                        'Aluno mensalista (%d dias). Nenhuma cobrança é gerada para mensalistas.',
                        $weeklyDays
                    ),
                ];
                continue;
            }
        }

        $documentDigits = preg_replace('/\D+/', '', $guardianDocument) ?? '';
        $guardianRow = null;
        if ($guardianIdInput !== '') {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $guardianIdInput)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'Responsável inválido.',
                ];
                continue;
            }
            $guardianResult = $client->select(
                'guardians',
                'select=*&id=eq.' . rawurlencode($guardianIdInput)
                    . '&student_id=eq.' . rawurlencode((string) $studentRow['id'])
                    . '&limit=1'
            );
            $guardianRow = (($guardianResult['ok'] ?? false) && !empty($guardianResult['data'][0]))
                ? $guardianResult['data'][0]
                : null;
            if (!$guardianRow) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'O responsável selecionado não pertence a este aluno.',
                ];
                continue;
            }

            $storedDocument = AsaasCustomerIdentity::normalizeDocument(
                (string) ($guardianRow['parent_document'] ?? '')
            );
            if (
                strcasecmp(trim((string) ($guardianRow['email'] ?? '')), $guardianEmail) !== 0
                || ($documentDigits !== '' && $storedDocument !== $documentDigits)
            ) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'Os dados informados não correspondem ao responsável selecionado.',
                ];
                continue;
            }
        } else {
            if (!AsaasCustomerIdentity::isValidCpfOrCnpj($documentDigits)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'Informe um CPF ou CNPJ válido para o novo responsável.',
                ];
                continue;
            }
            $existingGuardian = $client->select(
                'guardians',
                'select=id&student_id=eq.' . rawurlencode((string) $studentRow['id'])
                    . '&email=eq.' . rawurlencode($guardianEmail)
                    . '&limit=1'
            );
            if (($existingGuardian['ok'] ?? false) && !empty($existingGuardian['data'])) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'Este responsável já existe para o aluno. Selecione-o na lista.',
                ];
                continue;
            }

            $documentGuardians = $client->select(
                'guardians',
                'select=id,parent_name,email,parent_document&limit=10000'
            );
            if (!($documentGuardians['ok'] ?? false)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => apiErrorMessage($documentGuardians, 'Falha ao validar o CPF/CNPJ do responsável.'),
                ];
                continue;
            }
            $documentConflict = false;
            foreach (($documentGuardians['data'] ?? []) as $registeredGuardian) {
                if (
                    AsaasCustomerIdentity::normalizeDocument(
                        (string) ($registeredGuardian['parent_document'] ?? '')
                    ) !== $documentDigits
                ) {
                    continue;
                }
                if (!AsaasCustomerIdentity::matchesLocalGuardian(
                    (array) $registeredGuardian,
                    $guardianName,
                    $guardianEmail,
                    $documentDigits
                )) {
                    $documentConflict = true;
                    break;
                }
            }
            if ($documentConflict) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => 'O CPF/CNPJ já está associado a outra identidade. Revise o cadastro.',
                ];
                continue;
            }

            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $insertGuardian = $client->insert('guardians', [[
                'student_id' => $studentRow['id'],
                'email' => $guardianEmail,
                'password_hash' => $passwordHash,
                'parent_name' => $guardianName,
                'parent_phone' => $guardianWhatsapp !== '' ? $guardianWhatsapp : null,
                'parent_document' => $documentDigits !== '' ? $documentDigits : null,
            ]]);
            if (!($insertGuardian['ok'] ?? false)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => apiErrorMessage($insertGuardian, 'Falha ao criar responsável.'),
                ];
                continue;
            }
            $guardianRow = $insertGuardian['data'][0] ?? null;
        }

        if (!$guardianRow || empty($guardianRow['id'])) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => 'Falha ao preparar responsável.',
            ];
            continue;
        }

        $dayUseDatesForPayment = array_map(static fn($isoDate) => date('d/m/Y', strtotime($isoDate)), $monthlyOverflowDates);
        $amount = 0.0;
        $hasEmergencialDate = false;
        foreach ($monthlyOverflowDates as $isoDate) {
            $chargeRule = Helpers::resolveDayUseCharge((string) $isoDate);
            $amount += (float) ($chargeRule['amount'] ?? 77.00);
            if (($chargeRule['daily_type'] ?? 'planejada') === 'emergencial') {
                $hasEmergencialDate = true;
            }
        }
        $dailyBaseType = $hasEmergencialDate ? 'emergencial' : 'planejada';
        $dailyType = $dailyBaseType . '|' . implode(', ', $dayUseDatesForPayment);
        $paymentDateValue = $monthlyOverflowDates[0] ?? $today;

        $openPaymentsResult = $client->select(
            'payments',
            'select=id,payment_date,daily_type,status'
                . '&student_id=eq.' . rawurlencode((string) $studentRow['id'])
                . '&paid_at=is.null'
                . '&status=in.(queued,processing_asaas,pending,pending_asaas,overdue,awaiting_risk_analysis)'
                . '&limit=1000'
        );
        if (!($openPaymentsResult['ok'] ?? false)) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => apiErrorMessage($openPaymentsResult, 'Falha ao validar cobranças existentes.'),
            ];
            continue;
        }
        $requestedDateMap = array_fill_keys($monthlyOverflowDates, true);
        $duplicateDates = [];
        foreach (($openPaymentsResult['data'] ?? []) as $existingPayment) {
            foreach (extractPaymentDates((array) $existingPayment) as $existingDate) {
                if (isset($requestedDateMap[$existingDate])) {
                    $duplicateDates[$existingDate] = true;
                }
            }
        }
        if ($duplicateDates) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => 'Já existe cobrança aberta para: ' . implode(', ', array_keys($duplicateDates)) . '.',
            ];
            continue;
        }

        $idempotencyDates = $monthlyOverflowDates;
        sort($idempotencyDates);
        $idempotencyKey = 'manual-dayuse:' . (string) $studentRow['id'] . ':' . implode(',', $idempotencyDates);

        // Apenas salva localmente em fila para aparecer na aba Inadimplentes.
        $insertPayment = $client->insert('payments', [[
            'guardian_id' => $guardianRow['id'],
            'student_id' => $studentRow['id'],
            'payment_date' => $paymentDateValue,
            'daily_type' => $dailyType,
            'amount' => $amount,
            'status' => 'queued',
            'billing_type' => 'PIX_MANUAL_QUEUE',
            'asaas_payment_id' => null,
            'idempotency_key' => $idempotencyKey,
        ]]);
        $paymentRow = $insertPayment['data'][0] ?? null;
        if (!($insertPayment['ok'] ?? false) || !$paymentRow) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => apiErrorMessage($insertPayment, 'Falha ao salvar cobrança local.'),
            ];
            continue;
        }

        $results[] = [
            'student_name' => $studentName,
            'ok' => true,
            'payment_id' => (string) ($paymentRow['id'] ?? ''),
            'queued' => true,
            'monthly_days' => is_array($monthlyPlan) ? (int) ($monthlyPlan['weekly_days'] ?? 0) : null,
            'covered_dates' => $monthlyCoveredDates,
            'overflow_dates' => $monthlyOverflowDates,
        ];
    }

    $failures = array_values(array_filter($results, static fn($item) => !$item['ok']));
    $allOk = !$failures;
    $error = $failures ? ($failures[0]['error'] ?? 'Falha ao salvar pendências.') : null;
    Helpers::json(['ok' => $allOk, 'error' => $error, 'results' => $results]);
} catch (\Throwable $e) {
    $logPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'error_log_custom.txt';
    @file_put_contents($logPath, '[admin-charge] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    Helpers::json([
        'ok' => false,
        'error' => 'Falha interna ao salvar cobranças.',
        'details' => $e->getMessage(),
    ], 500);
}
