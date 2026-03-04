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

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

function parse_day_use_date(string $date): ?string
{
    $parts = explode('/', trim($date));
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

function payment_dates_to_iso(array $payment): array
{
    $dates = [];
    $paymentDate = trim((string) ($payment['payment_date'] ?? ''));
    if ($paymentDate !== '') {
        $ts = strtotime($paymentDate);
        if ($ts !== false) {
            $dates[] = date('Y-m-d', $ts);
        }
    }

    $dailyType = (string) ($payment['daily_type'] ?? '');
    $parts = explode('|', $dailyType, 2);
    if (count($parts) === 2) {
        $rawDates = array_map('trim', explode(',', $parts[1]));
        foreach ($rawDates as $raw) {
            if ($raw === '') {
                continue;
            }
            $iso = parse_day_use_date($raw);
            if ($iso !== null) {
                $dates[] = $iso;
            }
        }
    }

    return array_values(array_unique($dates));
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$charges = is_array($payload['charges'] ?? null) ? $payload['charges'] : [];

if (empty($charges)) {
    Helpers::json(['ok' => true, 'duplicates' => []]);
}

$client = new SupabaseClient(new HttpClient());
$duplicates = [];
$seenBatch = [];

foreach ($charges as $charge) {
    $studentName = trim((string) ($charge['student_name'] ?? ''));
    $dayUseDatesRaw = $charge['day_use_dates'] ?? [];
    if (!is_array($dayUseDatesRaw)) {
        $dayUseDatesRaw = [$dayUseDatesRaw];
    }

    if ($studentName === '') {
        continue;
    }

    $studentResult = $client->select('students', 'select=id,name&name=eq.' . urlencode($studentName) . '&limit=1');
    $student = $studentResult['data'][0] ?? null;
    if (!$student) {
        continue;
    }
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') {
        continue;
    }

    $paymentsResult = $client->select(
        'payments',
        'select=id,status,billing_type,payment_date,daily_type,created_at,paid_at&student_id=eq.' . urlencode($studentId) . '&limit=500'
    );
    $payments = ($paymentsResult['ok'] ?? false) ? ($paymentsResult['data'] ?? []) : [];

    $pendenciasResult = $client->select(
        'pendencia_de_cadastro',
        'select=id,payment_date,created_at,paid_at&student_name=eq.' . urlencode($studentName) . '&limit=500'
    );
    $pendencias = ($pendenciasResult['ok'] ?? false) ? ($pendenciasResult['data'] ?? []) : [];

    $requestedIsoDates = [];
    foreach ($dayUseDatesRaw as $rawDate) {
        $isoDate = parse_day_use_date((string) $rawDate);
        if ($isoDate !== null) {
            $requestedIsoDates[] = $isoDate;
            $batchKey = $studentId . '|' . $isoDate;
            if (isset($seenBatch[$batchKey])) {
                $duplicates[] = [
                    'student_name' => $studentName,
                    'date' => $isoDate,
                    'source' => 'lancamento_atual',
                    'status' => 'duplicado_no_mesmo_envio',
                ];
            }
            $seenBatch[$batchKey] = true;
        }
    }
    $requestedIsoDates = array_values(array_unique($requestedIsoDates));

    foreach ($payments as $payment) {
        $existingDates = payment_dates_to_iso((array) $payment);
        foreach ($existingDates as $existingDate) {
            if (!in_array($existingDate, $requestedIsoDates, true)) {
                continue;
            }
            $duplicates[] = [
                'student_name' => $studentName,
                'date' => $existingDate,
                'source' => 'payments',
                'status' => (string) ($payment['status'] ?? '-'),
                'billing_type' => (string) ($payment['billing_type'] ?? '-'),
                'payment_id' => (string) ($payment['id'] ?? ''),
            ];
        }
    }

    foreach ($pendencias as $pendencia) {
        $dateRaw = trim((string) ($pendencia['payment_date'] ?? ''));
        if ($dateRaw === '') {
            continue;
        }
        $ts = strtotime($dateRaw);
        if ($ts === false) {
            continue;
        }
        $existingDate = date('Y-m-d', $ts);
        if (!in_array($existingDate, $requestedIsoDates, true)) {
            continue;
        }
        $duplicates[] = [
            'student_name' => $studentName,
            'date' => $existingDate,
            'source' => 'pendencia_de_cadastro',
            'status' => !empty($pendencia['paid_at']) ? 'paid' : 'pending',
            'payment_id' => (string) ($pendencia['id'] ?? ''),
        ];
    }
}

if (!empty($duplicates)) {
    usort($duplicates, static function (array $a, array $b): int {
        $byStudent = strcmp((string) ($a['student_name'] ?? ''), (string) ($b['student_name'] ?? ''));
        if ($byStudent !== 0) {
            return $byStudent;
        }
        return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
    });
    $unique = [];
    $seen = [];
    foreach ($duplicates as $dup) {
        $key = implode('|', [
            (string) ($dup['student_name'] ?? ''),
            (string) ($dup['date'] ?? ''),
            (string) ($dup['source'] ?? ''),
            (string) ($dup['payment_id'] ?? ''),
            (string) ($dup['status'] ?? ''),
        ]);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $dup;
    }
    $duplicates = $unique;
}

Helpers::json([
    'ok' => true,
    'duplicates' => $duplicates,
]);
