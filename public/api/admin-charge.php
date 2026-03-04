<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
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

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$charges = $payload['charges'] ?? [];

if (!is_array($charges) || !$charges) {
    Helpers::json(['ok' => false, 'error' => 'Nenhuma cobrança informada.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$results = [];
$today = date('Y-m-d');

foreach ($charges as $charge) {
    $studentName = trim((string) ($charge['student_name'] ?? ''));
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

    $studentResult = $client->select('students', 'select=id,name&name=eq.' . urlencode($studentName) . '&limit=1');
    $studentRow = $studentResult['data'][0] ?? null;
    if (!$studentRow) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'Aluno não encontrado no cadastro.',
        ];
        continue;
    }

    $documentDigits = preg_replace('/\D+/', '', $guardianDocument) ?? '';
    $guardianResult = $client->select('guardians', 'select=*&email=eq.' . urlencode($guardianEmail) . '&limit=1');
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
        $guardianRow = $insertGuardian['data'][0] ?? null;
    } else {
        $client->update('guardians', 'id=eq.' . urlencode((string) $guardianRow['id']), [
            'parent_name' => $guardianName,
            'parent_phone' => $guardianWhatsapp !== '' ? $guardianWhatsapp : null,
            'parent_document' => $documentDigits !== '' ? $documentDigits : null,
        ]);
    }

    if (!$guardianRow || empty($guardianRow['id'])) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'Falha ao preparar responsável.',
        ];
        continue;
    }

    $daysCount = count($dayUseDates);
    $amount = 97.00 * $daysCount;
    $dailyType = 'emergencial|' . implode(', ', $dayUseDates);
    $firstDate = parseDayUseDate($dayUseDates[0] ?? '');
    $paymentDateValue = $firstDate ?: $today;

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
    if (!$paymentRow) {
        $results[] = [
            'student_name' => $studentName,
            'ok' => false,
            'error' => 'Falha ao salvar pendência local.',
        ];
        continue;
    }

    $results[] = [
        'student_name' => $studentName,
        'ok' => true,
        'payment_id' => (string) ($paymentRow['id'] ?? ''),
        'queued' => true,
    ];
}

$failures = array_values(array_filter($results, static fn($item) => !$item['ok']));
$allOk = !$failures;
$error = $failures ? ($failures[0]['error'] ?? 'Falha ao salvar pendências.') : null;
Helpers::json(['ok' => $allOk, 'error' => $error, 'results' => $results]);
