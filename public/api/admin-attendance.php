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

use App\AttendanceCalls;
use App\AsaasClient;
use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\MonthlyStudents;
use App\SupabaseClient;

function isAdminUser(): bool
{
    return (string) ($_SESSION['admin_user'] ?? '') === 'admin';
}

function parseAttendanceDate(string $raw): ?string
{
    $date = AttendanceCalls::normalizeDate($raw);
    if ($date !== null) {
        return $date;
    }
    $time = strtotime($raw);
    if ($time === false) {
        return null;
    }
    return AttendanceCalls::normalizeDate(date('Y-m-d', $time));
}

function formatDateBr(string $isoDate): string
{
    $time = strtotime($isoDate);
    if ($time === false) {
        return $isoDate;
    }
    return date('d/m/Y', $time);
}

/**
 * @param array<int, string> $isoDates
 */
function formatDateListBr(array $isoDates): string
{
    $dates = [];
    foreach ($isoDates as $date) {
        $iso = parseAttendanceDate((string) $date);
        if ($iso === null) {
            continue;
        }
        $dates[$iso] = true;
    }
    $keys = array_keys($dates);
    sort($keys);
    if (empty($keys)) {
        return '-';
    }
    return implode(', ', array_map(static fn($iso) => formatDateBr((string) $iso), $keys));
}

function saveAttendanceRowsOrFail(array $rows): void
{
    if (!AttendanceCalls::save($rows)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao salvar chamada.'], 500);
    }
}

function normalizeAttendanceKey(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtoupper')) {
        $value = mb_strtoupper($value, 'UTF-8');
    } else {
        $value = strtoupper($value);
    }
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    if ($translit !== false) {
        $value = $translit;
    }
    $value = preg_replace('/[^A-Z0-9]+/', '', $value) ?? '';
    return trim($value);
}

function extractAsaasError(array $response): string
{
    if (!empty($response['error'])) {
        return (string) $response['error'];
    }
    $data = $response['data'] ?? null;
    if (is_array($data) && !empty($data['message'])) {
        return (string) $data['message'];
    }
    return 'Falha ao processar cobrança no Asaas.';
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$client = new SupabaseClient(new HttpClient());
$asaas = new AsaasClient(new HttpClient());

if ($method === 'GET') {
    $from = parseAttendanceDate((string) ($_GET['from'] ?? ''));
    $to = parseAttendanceDate((string) ($_GET['to'] ?? ''));
    $items = AttendanceCalls::load();
    if ($from !== null) {
        $items = array_values(array_filter($items, static fn($row): bool => (string) ($row['attendance_date'] ?? '') >= $from));
    }
    if ($to !== null) {
        $items = array_values(array_filter($items, static fn($row): bool => (string) ($row['attendance_date'] ?? '') <= $to));
    }
    Helpers::json([
        'ok' => true,
        'items' => $items,
        'can_approve' => isAdminUser(),
        'admin_user' => (string) ($_SESSION['admin_user'] ?? ''),
    ]);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}
$action = strtolower(trim((string) ($payload['action'] ?? 'create')));
$isAudit = $action === 'audit';

$rows = AttendanceCalls::load();

if ($action === 'close_day') {
    $attendanceDate = parseAttendanceDate((string) ($payload['attendance_date'] ?? date('Y-m-d')));
    if ($attendanceDate === null) {
        Helpers::json(['ok' => false, 'error' => 'Data inválida para fechamento da chamada.'], 422);
    }
    $entries = $payload['entries'] ?? [];
    if (!is_array($entries) || empty($entries)) {
        Helpers::json(['ok' => false, 'error' => 'Nenhum aluno informado para fechar o dia.'], 422);
    }

    $createdItems = [];
    $blockedItems = [];
    $skippedCount = 0;
    $seen = [];

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            $skippedCount++;
            continue;
        }
        $studentIdInput = trim((string) ($entry['student_id'] ?? ''));
        $studentNameInput = trim((string) ($entry['student_name'] ?? ''));
        if ($studentIdInput === '' && $studentNameInput === '') {
            $skippedCount++;
            continue;
        }
        $entryKey = $studentIdInput !== ''
            ? 'id:' . $studentIdInput
            : 'name:' . normalizeAttendanceKey($studentNameInput);
        if ($entryKey === '' || isset($seen[$entryKey])) {
            $skippedCount++;
            continue;
        }
        $seen[$entryKey] = true;

        if ($studentIdInput !== '') {
            $studentResult = $client->select(
                'students',
                'select=id,name,enrollment&id=eq.' . urlencode($studentIdInput) . '&limit=1'
            );
        } else {
            $studentResult = $client->select(
                'students',
                'select=id,name,enrollment&name=eq.' . urlencode($studentNameInput) . '&limit=1'
            );
        }
        if (!($studentResult['ok'] ?? false) || empty($studentResult['data'][0])) {
            $blockedItems[] = [
                'student_name' => $studentNameInput !== '' ? $studentNameInput : '-',
                'error' => 'Aluno não encontrado no banco.',
            ];
            continue;
        }
        $student = $studentResult['data'][0];
        $studentId = trim((string) ($student['id'] ?? ''));
        $studentName = trim((string) ($student['name'] ?? $studentNameInput));
        if ($studentId === '' || $studentName === '') {
            $blockedItems[] = [
                'student_name' => $studentNameInput !== '' ? $studentNameInput : '-',
                'error' => 'Aluno inválido para chamada.',
            ];
            continue;
        }

        $duplicateFound = false;
        foreach ($rows as $existing) {
            if (!is_array($existing)) {
                continue;
            }
            if ((string) ($existing['student_id'] ?? '') !== $studentId) {
                continue;
            }
            if ((string) ($existing['attendance_date'] ?? '') !== $attendanceDate) {
                continue;
            }
            $status = (string) ($existing['status'] ?? '');
            if ($status !== AttendanceCalls::STATUS_REJEITADA) {
                $duplicateFound = true;
                break;
            }
        }
        if ($duplicateFound) {
            $blockedItems[] = [
                'student_name' => $studentName,
                'error' => 'Aluno já lançado na chamada para essa data.',
            ];
            continue;
        }

        $officeId = trim((string) ($entry['office_id'] ?? ''));
        $officeName = trim((string) ($entry['office_name'] ?? ''));
        $officeCode = '';
        if ($officeId !== '') {
            $officeResult = $client->select(
                'oficina_modular',
                'select=id,nome,codigo,ativa&id=eq.' . urlencode($officeId) . '&limit=1'
            );
            if (!($officeResult['ok'] ?? false) || empty($officeResult['data'][0])) {
                $blockedItems[] = [
                    'student_name' => $studentName,
                    'error' => 'Oficina informada não encontrada.',
                ];
                continue;
            }
            $officeRow = $officeResult['data'][0];
            if (($officeRow['ativa'] ?? true) === false) {
                $blockedItems[] = [
                    'student_name' => $studentName,
                    'error' => 'Oficina informada está inativa.',
                ];
                continue;
            }
            $officeId = trim((string) ($officeRow['id'] ?? ''));
            $officeName = trim((string) ($officeRow['nome'] ?? $officeName));
            $officeCode = trim((string) ($officeRow['codigo'] ?? ''));
        }

        $newRow = [
            'id' => AttendanceCalls::createId(),
            'attendance_date' => $attendanceDate,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'office_id' => $officeId,
            'office_name' => $officeName,
            'office_code' => $officeCode,
            'status' => AttendanceCalls::STATUS_EM_REVISAO,
            'created_at' => date('c'),
            'created_by_role' => (string) ($_SESSION['admin_user'] ?? ''),
            'created_by_user' => (string) ($_SESSION['admin_user'] ?? ''),
            'reviewed_at' => '',
            'reviewed_by' => '',
            'review_note' => '',
            'queue_payment_id' => '',
            'warning' => '',
        ];
        $rows[] = $newRow;
        $createdItems[] = $newRow;
    }

    if (!empty($createdItems)) {
        saveAttendanceRowsOrFail($rows);
    }

    $emailWarning = '';
    if (!empty($createdItems)) {
        $listHtml = '<ul>';
        foreach ($createdItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $officeLabel = trim((string) ($item['office_name'] ?? ''));
            if ($officeLabel === '') {
                $officeLabel = 'Não informada';
            }
            $listHtml .= '<li>'
                . htmlspecialchars((string) ($item['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8')
                . ' • Oficina: '
                . htmlspecialchars($officeLabel, ENT_QUOTES, 'UTF-8')
                . '</li>';
        }
        $listHtml .= '</ul>';
        $mailHtml = '<p>Fechamento do dia de chamada realizado no administrativo.</p>'
            . '<p><strong>Data:</strong> ' . htmlspecialchars(formatDateBr($attendanceDate), ENT_QUOTES, 'UTF-8') . '<br>'
            . '<strong>Lançado por:</strong> ' . htmlspecialchars((string) ($_SESSION['admin_user'] ?? 'secretaria'), ENT_QUOTES, 'UTF-8') . '<br>'
            . '<strong>Total incluído:</strong> ' . count($createdItems) . '</p>'
            . $listHtml
            . '<p>Acesse o painel administrativo para revisar e autorizar.</p>';
        $mailer = new Mailer();
        $mailResult = $mailer->send('admin@village.einsteinhub.co', 'Fechamento do dia de chamada • Diárias Village', $mailHtml);
        if (!($mailResult['ok'] ?? false)) {
            $emailWarning = 'Fechamento salvo, mas não foi possível enviar e-mail ao admin.';
        }
    }

    Helpers::json([
        'ok' => true,
        'attendance_date' => $attendanceDate,
        'created_count' => count($createdItems),
        'blocked_count' => count($blockedItems),
        'skipped_count' => $skippedCount,
        'created_items' => $createdItems,
        'blocked_items' => $blockedItems,
        'email_warning' => $emailWarning !== '' ? $emailWarning : null,
    ]);
}

if ($action === 'edit') {
    $id = trim((string) ($payload['id'] ?? ''));
    if ($id === '') {
        Helpers::json(['ok' => false, 'error' => 'ID inválido para edição da chamada.'], 422);
    }
    $newDate = parseAttendanceDate((string) ($payload['attendance_date'] ?? ''));
    if ($newDate === null) {
        Helpers::json(['ok' => false, 'error' => 'Data Day Use inválida. Use DD/MM/AAAA ou AAAA-MM-DD.'], 422);
    }

    $targetIndex = null;
    foreach ($rows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['id'] ?? '') === $id) {
            $targetIndex = $idx;
            break;
        }
    }
    if ($targetIndex === null) {
        Helpers::json(['ok' => false, 'error' => 'Chamada não encontrada.'], 404);
    }

    $target = $rows[$targetIndex];
    $oldDate = parseAttendanceDate((string) ($target['attendance_date'] ?? ''));
    if ($oldDate === null) {
        $oldDate = (string) ($target['attendance_date'] ?? '');
    }
    if ($oldDate === $newDate) {
        Helpers::json(['ok' => true, 'item' => $target, 'message' => 'Data Day Use já está correta.']);
    }

    $studentId = trim((string) ($target['student_id'] ?? ''));
    foreach ($rows as $checkRow) {
        if (!is_array($checkRow)) {
            continue;
        }
        if ((string) ($checkRow['id'] ?? '') === $id) {
            continue;
        }
        if ($studentId === '' || (string) ($checkRow['student_id'] ?? '') !== $studentId) {
            continue;
        }
        if ((string) ($checkRow['attendance_date'] ?? '') !== $newDate) {
            continue;
        }
        if ((string) ($checkRow['status'] ?? '') !== AttendanceCalls::STATUS_REJEITADA) {
            Helpers::json([
                'ok' => false,
                'error' => 'Já existe chamada ativa para esse aluno na nova Data Day Use.',
            ], 409);
        }
    }

    $rows[$targetIndex]['attendance_date'] = $newDate;
    $rows[$targetIndex]['reviewed_at'] = date('c');
    $rows[$targetIndex]['reviewed_by'] = (string) ($_SESSION['admin_user'] ?? '');
    $rows[$targetIndex]['review_note'] = 'Data Day Use ajustada manualmente de '
        . formatDateBr((string) $oldDate) . ' para ' . formatDateBr($newDate) . '.';

    $updatedPayment = false;
    $queuePaymentId = trim((string) ($rows[$targetIndex]['queue_payment_id'] ?? ''));
    if ($queuePaymentId !== '') {
        $updatePayment = $client->update('payments', 'id=eq.' . urlencode($queuePaymentId), [
            'payment_date' => $newDate,
            'daily_type' => 'emergencial|' . formatDateBr($newDate),
        ]);
        $updatedPayment = (bool) ($updatePayment['ok'] ?? false);
    }

    saveAttendanceRowsOrFail($rows);

    Helpers::json([
        'ok' => true,
        'item' => $rows[$targetIndex],
        'updated_payment' => $updatedPayment,
        'message' => $updatedPayment
            ? 'Data Day Use atualizada na chamada e na cobrança vinculada.'
            : 'Data Day Use atualizada na chamada.',
    ]);
}

if ($action === 'create') {
    $attendanceDate = parseAttendanceDate((string) ($payload['attendance_date'] ?? date('Y-m-d')));
    if ($attendanceDate === null) {
        Helpers::json(['ok' => false, 'error' => 'Data inválida para chamada.'], 422);
    }

    $studentId = trim((string) ($payload['student_id'] ?? ''));
    $studentNameInput = trim((string) ($payload['student_name'] ?? ''));
    if ($studentId === '' && $studentNameInput === '') {
        Helpers::json(['ok' => false, 'error' => 'Selecione um aluno para lançar chamada.'], 422);
    }

    if ($studentId !== '') {
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment&id=eq.' . urlencode($studentId) . '&limit=1'
        );
    } else {
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment&name=eq.' . urlencode($studentNameInput) . '&limit=1'
        );
    }
    if (!($studentResult['ok'] ?? false) || empty($studentResult['data'][0])) {
        Helpers::json(['ok' => false, 'error' => 'Aluno não encontrado no banco.'], 404);
    }
    $student = $studentResult['data'][0];
    $studentId = trim((string) ($student['id'] ?? ''));
    $studentName = trim((string) ($student['name'] ?? $studentNameInput));
    if ($studentId === '' || $studentName === '') {
        Helpers::json(['ok' => false, 'error' => 'Aluno inválido para chamada.'], 422);
    }

    foreach ($rows as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if ((string) ($existing['student_id'] ?? '') !== $studentId) {
            continue;
        }
        if ((string) ($existing['attendance_date'] ?? '') !== $attendanceDate) {
            continue;
        }
        $status = (string) ($existing['status'] ?? '');
        if ($status !== AttendanceCalls::STATUS_REJEITADA) {
            Helpers::json(['ok' => false, 'error' => 'Aluno já lançado na chamada para essa data.'], 409);
        }
    }

    $officeId = trim((string) ($payload['office_id'] ?? ''));
    $officeName = trim((string) ($payload['office_name'] ?? ''));
    $officeCode = '';
    if ($officeId !== '') {
        $officeResult = $client->select(
            'oficina_modular',
            'select=id,nome,codigo,ativa&id=eq.' . urlencode($officeId) . '&limit=1'
        );
        if (!($officeResult['ok'] ?? false) || empty($officeResult['data'][0])) {
            Helpers::json(['ok' => false, 'error' => 'Oficina informada não encontrada.'], 404);
        }
        $officeRow = $officeResult['data'][0];
        if (($officeRow['ativa'] ?? true) === false) {
            Helpers::json(['ok' => false, 'error' => 'Oficina informada está inativa.'], 422);
        }
        $officeId = trim((string) ($officeRow['id'] ?? ''));
        $officeName = trim((string) ($officeRow['nome'] ?? $officeName));
        $officeCode = trim((string) ($officeRow['codigo'] ?? ''));
    }

    $newRow = [
        'id' => AttendanceCalls::createId(),
        'attendance_date' => $attendanceDate,
        'student_id' => $studentId,
        'student_name' => $studentName,
        'office_id' => $officeId,
        'office_name' => $officeName,
        'office_code' => $officeCode,
        'status' => AttendanceCalls::STATUS_EM_REVISAO,
        'created_at' => date('c'),
        'created_by_role' => (string) ($_SESSION['admin_user'] ?? ''),
        'created_by_user' => (string) ($_SESSION['admin_user'] ?? ''),
        'reviewed_at' => '',
        'reviewed_by' => '',
        'review_note' => '',
        'queue_payment_id' => '',
        'warning' => '',
    ];

    $rows[] = $newRow;
    saveAttendanceRowsOrFail($rows);

    $emailWarning = '';
    $mailer = new Mailer();
    $mailHtml = '<p>Nova chamada lançada no administrativo.</p>'
        . '<p><strong>Data:</strong> ' . htmlspecialchars(formatDateBr($attendanceDate), ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Aluno:</strong> ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Lançado por:</strong> ' . htmlspecialchars((string) ($_SESSION['admin_user'] ?? 'secretaria'), ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Oficina:</strong> ' . htmlspecialchars($officeName !== '' ? $officeName : 'Não informada', ENT_QUOTES, 'UTF-8')
        . '</p>'
        . '<p>Acesse o painel administrativo para revisar e autorizar.</p>';
    $mailResult = $mailer->send('admin@village.einsteinhub.co', 'Nova chamada lançada • Diárias Village', $mailHtml);
    if (!($mailResult['ok'] ?? false)) {
        $emailWarning = 'Chamada lançada, mas não foi possível enviar e-mail ao admin.';
    }

    Helpers::json([
        'ok' => true,
        'item' => $newRow,
        'warning' => $emailWarning !== '' ? $emailWarning : null,
    ]);
}

if ($action === 'retry') {
    if (!isAdminUser()) {
        Helpers::json(['ok' => false, 'error' => 'Apenas admin pode relançar cobrança.'], 403);
    }
    $id = trim((string) ($payload['id'] ?? ''));
    if ($id === '') {
        Helpers::json(['ok' => false, 'error' => 'ID inválido para relançar cobrança.'], 422);
    }

    $updated = null;
    foreach ($rows as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        if ((string) ($row['status'] ?? '') !== AttendanceCalls::STATUS_ERRO_COBRANCA) {
            Helpers::json([
                'ok' => false,
                'error' => 'Somente chamadas com erro de cobrança podem ser relançadas.',
                'item' => $row,
            ], 409);
        }
        $row['status'] = AttendanceCalls::STATUS_EM_REVISAO;
        $row['reviewed_at'] = date('c');
        $row['reviewed_by'] = 'admin';
        $row['review_note'] = 'Relançada pelo admin para nova tentativa de autorização/cobrança.';
        $row['queue_payment_id'] = '';
        $updated = $row;
        break;
    }
    unset($row);
    if (!is_array($updated)) {
        Helpers::json(['ok' => false, 'error' => 'Chamada não encontrada.'], 404);
    }

    saveAttendanceRowsOrFail($rows);
    Helpers::json([
        'ok' => true,
        'item' => $updated,
        'message' => 'Chamada relançada. Agora você pode clicar em Autorizar novamente.',
    ]);
}

if ($action === 'reject') {
    if (!isAdminUser()) {
        Helpers::json(['ok' => false, 'error' => 'Apenas admin pode rejeitar chamada.'], 403);
    }
    $id = trim((string) ($payload['id'] ?? ''));
    if ($id === '') {
        Helpers::json(['ok' => false, 'error' => 'ID inválido para rejeição.'], 422);
    }
    $note = trim((string) ($payload['review_note'] ?? 'Rejeitada pelo admin.'));

    $updated = null;
    foreach ($rows as &$row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $row['status'] = AttendanceCalls::STATUS_REJEITADA;
        $row['reviewed_at'] = date('c');
        $row['reviewed_by'] = 'admin';
        $row['review_note'] = $note;
        $updated = $row;
        break;
    }
    unset($row);
    if (!is_array($updated)) {
        Helpers::json(['ok' => false, 'error' => 'Chamada não encontrada.'], 404);
    }

    saveAttendanceRowsOrFail($rows);
    Helpers::json(['ok' => true, 'item' => $updated, 'message' => 'Chamada rejeitada.']);
}

if (!in_array($action, ['approve', 'audit'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Ação inválida para chamada.'], 422);
}

if (!isAdminUser()) {
    Helpers::json(['ok' => false, 'error' => 'Apenas admin pode autorizar chamada.'], 403);
}

$id = trim((string) ($payload['id'] ?? ''));
if ($id === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido para autorização.'], 422);
}

$targetIndex = null;
foreach ($rows as $idx => $row) {
    if (!is_array($row)) {
        continue;
    }
    if ((string) ($row['id'] ?? '') === $id) {
        $targetIndex = $idx;
        break;
    }
}
if ($targetIndex === null) {
    Helpers::json(['ok' => false, 'error' => 'Chamada não encontrada.'], 404);
}

$target = $rows[$targetIndex];
if ((string) ($target['status'] ?? '') !== AttendanceCalls::STATUS_EM_REVISAO) {
    Helpers::json(['ok' => false, 'error' => 'A chamada já foi revisada.', 'item' => $target], 409);
}

$attendanceDate = parseAttendanceDate((string) ($target['attendance_date'] ?? ''));
if ($attendanceDate === null) {
    Helpers::json(['ok' => false, 'error' => 'Data inválida na chamada.'], 422);
}

$studentId = trim((string) ($target['student_id'] ?? ''));
$studentName = trim((string) ($target['student_name'] ?? ''));
if ($studentId === '' && $studentName !== '') {
    $studentResult = $client->select(
        'students',
        'select=id,name,enrollment&name=eq.' . urlencode($studentName) . '&limit=50'
    );
    $studentRows = (($studentResult['ok'] ?? false) && is_array($studentResult['data'] ?? null))
        ? $studentResult['data']
        : [];
    if (count($studentRows) === 1) {
        $studentId = trim((string) ($studentRows[0]['id'] ?? ''));
        $studentName = trim((string) ($studentRows[0]['name'] ?? $studentName));
    } elseif (count($studentRows) > 1) {
        Helpers::json([
            'ok' => false,
            'error' => 'Mais de um aluno com esse nome. Ajuste o vínculo da chamada antes de autorizar.',
        ], 422);
    }
}
if ($studentId === '') {
    Helpers::json(['ok' => false, 'error' => 'Aluno da chamada sem vínculo válido.'], 422);
}

$paymentsResult = $client->select(
    'payments',
    'select=id,student_id,payment_date,daily_type,amount,status,billing_type,guardian_id,created_at,paid_at'
        . '&student_id=eq.' . urlencode($studentId)
        . '&order=created_at.desc&limit=5000'
);
if (!($paymentsResult['ok'] ?? false) || !is_array($paymentsResult['data'] ?? null)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Não foi possível validar histórico de cobranças deste aluno. Operação bloqueada para evitar cobrança indevida.',
    ], 503);
}
$payments = $paymentsResult['data'];

$hasPaidSameDate = false;
$hasOpenSameDate = false;
foreach ($payments as $payment) {
    if (!is_array($payment)) {
        continue;
    }
    $status = strtolower(trim((string) ($payment['status'] ?? '')));
    if (in_array($status, ['canceled', 'refunded', 'deleted'], true)) {
        continue;
    }
    $dates = MonthlyStudents::extractDatesFromPayment(
        (string) ($payment['daily_type'] ?? ''),
        (string) ($payment['payment_date'] ?? '')
    );
    if (!in_array($attendanceDate, $dates, true)) {
        continue;
    }
    $isPaid = $status === 'paid' || !empty($payment['paid_at']);
    if ($isPaid) {
        $hasPaidSameDate = true;
    } else {
        $hasOpenSameDate = true;
    }
}

if ($hasPaidSameDate || $hasOpenSameDate) {
    $rows[$targetIndex]['status'] = $hasPaidSameDate
        ? AttendanceCalls::STATUS_BLOQUEADA_JA_PAGA
        : AttendanceCalls::STATUS_BLOQUEADA_DUPLICIDADE;
    $rows[$targetIndex]['reviewed_at'] = date('c');
    $rows[$targetIndex]['reviewed_by'] = 'admin';
    $rows[$targetIndex]['review_note'] = $hasPaidSameDate
        ? 'Já existe cobrança paga para esse aluno nessa data.'
        : 'Já existe cobrança aberta para esse aluno nessa data.';
    saveAttendanceRowsOrFail($rows);

    Helpers::json([
        'ok' => true,
        'audit' => $isAudit,
        'blocked' => true,
        'blocked_reason' => $hasPaidSameDate ? 'already_paid' : 'already_open',
        'item' => $rows[$targetIndex],
        'message' => $hasPaidSameDate
            ? 'Bloqueado: esta diária já foi paga no SaaS.'
            : 'Bloqueado: já existe cobrança em aberto para essa data.',
    ]);
}

$monthlyItems = MonthlyStudents::load();
$monthlyStoragePath = MonthlyStudents::storagePath();
if (!is_file($monthlyStoragePath)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Cadastro de mensalistas indisponível no momento. Operação bloqueada para evitar cobrança indevida.',
    ], 503);
}
$monthlyById = MonthlyStudents::mapByStudentId($monthlyItems);
$monthlyByName = MonthlyStudents::mapByNormalizedName($monthlyItems);
$plan = MonthlyStudents::resolvePlan($studentId, $studentName, $monthlyById, $monthlyByName);

if (is_array($plan) && in_array((int) ($plan['weekly_days'] ?? 0), [2, 3, 4, 5], true)) {
    $weeklyDays = (int) ($plan['weekly_days'] ?? 0);
    $usedByWeek = MonthlyStudents::collectUsedDatesByWeek($payments);
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['id'] ?? '') === $id) {
            continue;
        }
        if ((string) ($row['student_id'] ?? '') !== $studentId) {
            continue;
        }
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, [AttendanceCalls::STATUS_ALUNO_MENSALISTA, AttendanceCalls::STATUS_AUTORIZADA_COBRANCA], true)) {
            continue;
        }
        $date = parseAttendanceDate((string) ($row['attendance_date'] ?? ''));
        if ($date === null) {
            continue;
        }
        $week = MonthlyStudents::weekKey($date);
        if ($week === '') {
            continue;
        }
        if (!isset($usedByWeek[$week])) {
            $usedByWeek[$week] = [];
        }
        $usedByWeek[$week][$date] = true;
    }

    $split = MonthlyStudents::splitRequestedDatesByQuota([$attendanceDate], $weeklyDays, $usedByWeek);
    $overflow = $split['overflow'] ?? [];
    if (empty($overflow)) {
        $weekKey = MonthlyStudents::weekKey($attendanceDate);
        $usedBefore = [];
        if ($weekKey !== '' && isset($usedByWeek[$weekKey]) && is_array($usedByWeek[$weekKey])) {
            $usedBefore = array_keys($usedByWeek[$weekKey]);
        }
        $usedWithCall = array_values(array_unique(array_merge($usedBefore, [$attendanceDate])));
        sort($usedWithCall);
        $remaining = max(0, $weeklyDays - count($usedWithCall));

        $rows[$targetIndex]['status'] = AttendanceCalls::STATUS_ALUNO_MENSALISTA;
        $rows[$targetIndex]['reviewed_at'] = date('c');
        $rows[$targetIndex]['reviewed_by'] = 'admin';
        $rows[$targetIndex]['review_note'] = 'Aluno mensalista (' . $weeklyDays
            . ' dias/semana). Datas registradas na semana: '
            . formatDateListBr($usedWithCall)
            . '. Saldo restante: ' . $remaining . ' dia(s).';
        saveAttendanceRowsOrFail($rows);

        Helpers::json([
            'ok' => true,
            'audit' => $isAudit,
            'blocked' => true,
            'blocked_reason' => 'monthly_covered',
            'item' => $rows[$targetIndex],
            'message' => 'Aluno mensalista: sem cobrança para essa data. Datas da semana: ' . formatDateListBr($usedWithCall),
            'monthly' => [
                'weekly_days' => $weeklyDays,
                'attendance_date' => $attendanceDate,
                'week_key' => $weekKey,
                'used_dates' => $usedWithCall,
                'remaining_days' => $remaining,
            ],
        ]);
    }
}

$guardianResult = $client->select(
    'guardians',
    'select=id,parent_name,email,parent_phone,parent_document,asaas_customer_id,created_at'
        . '&student_id=eq.' . urlencode($studentId)
        . '&order=created_at.desc&limit=200'
);
$guardians = (($guardianResult['ok'] ?? false) && is_array($guardianResult['data'] ?? null))
    ? $guardianResult['data']
    : [];

$guardian = null;
foreach ($guardians as $row) {
    if (!is_array($row)) {
        continue;
    }
    $email = trim((string) ($row['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $guardian = $row;
        break;
    }
}
if ($guardian === null && !empty($guardians[0]) && is_array($guardians[0])) {
    $guardian = $guardians[0];
}

if (!is_array($guardian) || empty($guardian['id'])) {
    if ($isAudit) {
        Helpers::json([
            'ok' => true,
            'audit' => true,
            'blocked' => true,
            'blocked_reason' => 'missing_guardian',
            'error' => 'Sem responsável válido para gerar cobrança.',
            'student_id' => $studentId,
            'attendance_date' => $attendanceDate,
        ]);
    }

    $rows[$targetIndex]['status'] = AttendanceCalls::STATUS_ERRO_COBRANCA;
    $rows[$targetIndex]['reviewed_at'] = date('c');
    $rows[$targetIndex]['reviewed_by'] = 'admin';
    $rows[$targetIndex]['review_note'] = 'Não foi possível localizar responsável válido para cobrança.';
    saveAttendanceRowsOrFail($rows);

    Helpers::json([
        'ok' => false,
        'item' => $rows[$targetIndex],
        'error' => 'Sem responsável válido para gerar cobrança.',
    ], 422);
}

$guardianName = trim((string) ($guardian['parent_name'] ?? 'Responsável'));
$guardianEmail = trim((string) ($guardian['email'] ?? ''));
$guardianDoc = preg_replace('/\D+/', '', (string) ($guardian['parent_document'] ?? '')) ?? '';
$guardianPhone = preg_replace('/\D+/', '', (string) ($guardian['parent_phone'] ?? '')) ?? '';
$customerId = trim((string) ($guardian['asaas_customer_id'] ?? ''));
$chargeRule = Helpers::resolveDayUseCharge($attendanceDate);
$amount = (float) ($chargeRule['amount'] ?? 77.00);
$dailyBaseType = (string) ($chargeRule['daily_type'] ?? 'planejada');
$dailyType = $dailyBaseType . '|' . formatDateBr($attendanceDate);
$today = date('Y-m-d');
$dueDate = $attendanceDate < $today ? $today : $attendanceDate;
$asaasError = '';

if ($isAudit) {
    $auditReason = is_array($plan)
        ? 'monthly_overflow'
        : 'non_monthly';
    Helpers::json([
        'ok' => true,
        'audit' => true,
        'blocked' => false,
        'can_charge' => true,
        'reason' => $auditReason,
        'student_id' => $studentId,
        'attendance_date' => $attendanceDate,
        'guardian_id' => (string) ($guardian['id'] ?? ''),
        'guardian_email' => $guardianEmail,
        'monthly' => is_array($plan) ? [
            'weekly_days' => (int) ($plan['weekly_days'] ?? 0),
        ] : null,
    ]);
}

if ($customerId === '') {
    $customerPayload = [
        'name' => $guardianName !== '' ? $guardianName : 'Responsável',
    ];
    if ($guardianEmail !== '' && filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
        $customerPayload['email'] = $guardianEmail;
    }
    if ($guardianDoc !== '') {
        $customerPayload['cpfCnpj'] = $guardianDoc;
    }
    if ($guardianPhone !== '') {
        $customerPayload['mobilePhone'] = $guardianPhone;
    }
    $customer = $asaas->createCustomer($customerPayload);
    if ($customer['ok'] ?? false) {
        $customerId = trim((string) ($customer['data']['id'] ?? ''));
        if ($customerId !== '') {
            $client->update('guardians', 'id=eq.' . urlencode((string) ($guardian['id'] ?? '')), [
                'asaas_customer_id' => $customerId,
            ]);
        } else {
            $asaasError = 'Cliente Asaas inválido.';
        }
    } else {
        $asaasError = extractAsaasError($customer);
    }
}

$insertPayload = [
    'guardian_id' => (string) ($guardian['id'] ?? ''),
    'student_id' => $studentId,
    'payment_date' => $attendanceDate,
    'daily_type' => $dailyType,
    'amount' => $amount,
    'status' => 'queued',
    'billing_type' => 'PIX_MANUAL_QUEUE',
    'asaas_payment_id' => null,
];

if ($asaasError === '' && $customerId !== '') {
    $payment = $asaas->createPayment([
        'customer' => $customerId,
        'billingType' => 'PIX',
        'value' => $amount,
        'dueDate' => $dueDate,
        'description' => 'Diária ' . $dailyBaseType . ' - ' . ($studentName !== '' ? $studentName : 'Aluno') . ' - ' . formatDateBr($attendanceDate),
    ]);
    if ($payment['ok'] ?? false) {
        $paymentData = $payment['data'] ?? [];
        $asaasPaymentId = trim((string) ($paymentData['id'] ?? ''));
        if ($asaasPaymentId !== '') {
            $insertPayload['status'] = 'pending';
            $insertPayload['billing_type'] = 'PIX_MANUAL';
            $insertPayload['asaas_payment_id'] = $asaasPaymentId;
        } else {
            $asaasError = 'Cobrança criada no Asaas sem ID retornado.';
        }
    } else {
        $asaasError = extractAsaasError($payment);
    }
}

$insertPayment = $client->insert('payments', [$insertPayload]);

if (!($insertPayment['ok'] ?? false) || empty($insertPayment['data'][0])) {
    $rows[$targetIndex]['status'] = AttendanceCalls::STATUS_ERRO_COBRANCA;
    $rows[$targetIndex]['reviewed_at'] = date('c');
    $rows[$targetIndex]['reviewed_by'] = 'admin';
    $rows[$targetIndex]['review_note'] = 'Falha ao criar cobrança em fila.';
    saveAttendanceRowsOrFail($rows);
    Helpers::json([
        'ok' => false,
        'item' => $rows[$targetIndex],
        'error' => 'Falha ao criar cobrança em fila.',
    ], 500);
}

$payment = $insertPayment['data'][0];
$rows[$targetIndex]['status'] = AttendanceCalls::STATUS_AUTORIZADA_COBRANCA;
$rows[$targetIndex]['queue_payment_id'] = (string) ($payment['id'] ?? '');
$rows[$targetIndex]['reviewed_at'] = date('c');
$rows[$targetIndex]['reviewed_by'] = 'admin';
$rows[$targetIndex]['review_note'] = $asaasError === ''
    ? 'Autorizada pelo admin e cobrança emergencial criada no Asaas.'
    : 'Autorizada pelo admin, mas falha no Asaas (' . $asaasError . '). Cobrança ficou na fila para reenvio.';
saveAttendanceRowsOrFail($rows);

Helpers::json([
    'ok' => true,
    'item' => $rows[$targetIndex],
    'message' => $asaasError === ''
        ? 'Autorizada com sucesso. Cobrança emergencial criada no Asaas.'
        : 'Autorizada com sucesso, mas houve falha ao gerar no Asaas. Cobrança ficou na fila para envio manual.',
    'asaas_error' => $asaasError !== '' ? $asaasError : null,
    'created_in_asaas' => $asaasError === '',
    'charge' => [
        'payment_id' => (string) ($payment['id'] ?? ''),
        'status' => (string) ($payment['status'] ?? ''),
        'billing_type' => (string) ($payment['billing_type'] ?? ''),
        'asaas_payment_id' => (string) ($payment['asaas_payment_id'] ?? ''),
        'student_id' => $studentId,
        'attendance_date' => $attendanceDate,
    ],
]);

