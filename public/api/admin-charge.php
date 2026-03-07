<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
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

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

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
        $guardianName = trim((string) ($charge['guardian_name'] ?? ''));
        $guardianEmail = trim((string) ($charge['guardian_email'] ?? ''));
        $guardianWhatsapp = trim((string) ($charge['guardian_whatsapp'] ?? ''));
        $guardianDocument = trim((string) ($charge['guardian_document'] ?? ''));
        $dayUseDates = $charge['day_use_dates'] ?? [];
        if (!is_array($dayUseDates)) {
            $dayUseDates = [$dayUseDates];
        }
        $dayUseDates = array_values(array_filter(array_map('trim', $dayUseDates)));

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

        if ($studentIdInput !== '') {
            $studentResult = $client->select(
                'students',
                'select=id,name&active=eq.true&id=eq.' . urlencode($studentIdInput) . '&limit=1'
            );
        } else {
            $studentResult = $client->select(
                'students',
                'select=id,name&active=eq.true&name=eq.' . urlencode($studentName) . '&limit=1'
            );
        }
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
            if ($studentIdInput !== '') {
                $studentResult = $client->select(
                    'students',
                    'select=id,name&active=eq.true&name=eq.' . urlencode($studentName) . '&limit=1'
                );
                $studentRow = (($studentResult['ok'] ?? false) && !empty($studentResult['data'][0]))
                    ? $studentResult['data'][0]
                    : null;
            }
        }
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
                $existingResult = $client->select(
                    'payments',
                    'select=id,daily_type,payment_date,status&student_id=eq.'
                        . urlencode((string) $studentRow['id'])
                        . '&order=payment_date.asc&limit=5000'
                );
                $existingRows = ($existingResult['ok'] ?? false) ? ($existingResult['data'] ?? []) : [];
                $usedByWeek = MonthlyStudents::collectUsedDatesByWeek($existingRows);
                $split = MonthlyStudents::splitRequestedDatesByQuota($requestedDatesIso, $weeklyDays, $usedByWeek);
                $monthlyCoveredDates = $split['covered'] ?? [];
                $monthlyOverflowDates = $split['overflow'] ?? [];

                if (empty($monthlyOverflowDates)) {
                    $results[] = [
                        'student_name' => $studentName,
                        'ok' => true,
                        'monthly_covered' => true,
                        'monthly_days' => $weeklyDays,
                        'covered_dates' => $monthlyCoveredDates,
                        'overflow_dates' => [],
                        'message' => sprintf(
                            'Aluno mensalista (%d dias). Nenhuma cobrança gerada para as datas dentro da franquia semanal.',
                            $weeklyDays
                        ),
                    ];
                    continue;
                }
            }
        }

        $documentDigits = preg_replace('/\D+/', '', $guardianDocument) ?? '';
        $guardianResult = $client->select('guardians', 'select=*&email=eq.' . urlencode($guardianEmail) . '&limit=1');
        if (!($guardianResult['ok'] ?? false)) {
            $results[] = [
                'student_name' => $studentName,
                'ok' => false,
                'error' => apiErrorMessage($guardianResult, 'Falha ao buscar responsável.'),
            ];
            continue;
        }
        $guardianRow = $guardianResult['data'][0] ?? null;
        if (!$guardianRow) {
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
        } else {
            $updateGuardian = $client->update('guardians', 'id=eq.' . urlencode((string) $guardianRow['id']), [
                'parent_name' => $guardianName,
                'parent_phone' => $guardianWhatsapp !== '' ? $guardianWhatsapp : null,
                'parent_document' => $documentDigits !== '' ? $documentDigits : null,
            ]);
            if (!($updateGuardian['ok'] ?? false)) {
                $results[] = [
                    'student_name' => $studentName,
                    'ok' => false,
                    'error' => apiErrorMessage($updateGuardian, 'Falha ao atualizar responsável.'),
                ];
                continue;
            }
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
        $daysCount = count($dayUseDatesForPayment);
        $amount = 97.00 * $daysCount;
        $dailyType = 'emergencial|' . implode(', ', $dayUseDatesForPayment);
        $paymentDateValue = $monthlyOverflowDates[0] ?? $today;

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
