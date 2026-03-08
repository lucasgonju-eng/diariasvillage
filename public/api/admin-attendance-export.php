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

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Não autorizado.';
    exit;
}

function parseDateFilter(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
        [$day, $month, $year] = explode('/', $raw);
        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
    }
    return null;
}

function formatDateBr(string $isoDate): string
{
    $time = strtotime($isoDate);
    if ($time === false) {
        return $isoDate;
    }
    return date('d/m/Y', $time);
}

function formatDateTimeBr(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '-';
    }
    $time = strtotime($raw);
    if ($time === false) {
        return $raw;
    }
    return date('d/m/Y H:i', $time);
}

function statusLabel(string $status): string
{
    $map = [
        'em_revisao' => 'Em revisão',
        'autorizada_cobranca' => 'Autorizada (cobrança na fila)',
        'rejeitada' => 'Rejeitada',
        'aluno_mensalista' => 'Aluno mensalista',
        'bloqueada_ja_paga' => 'Bloqueada: já paga',
        'bloqueada_duplicidade' => 'Bloqueada: cobrança existente',
        'erro_cobranca' => 'Erro ao lançar cobrança',
    ];
    $key = trim($status);
    return $map[$key] ?? ($key !== '' ? $key : '-');
}

$from = parseDateFilter((string) ($_GET['from'] ?? ''));
$to = parseDateFilter((string) ($_GET['to'] ?? ''));

$items = AttendanceCalls::load();
if ($from !== null) {
    $items = array_values(array_filter($items, static fn($row): bool => (string) ($row['attendance_date'] ?? '') >= $from));
}
if ($to !== null) {
    $items = array_values(array_filter($items, static fn($row): bool => (string) ($row['attendance_date'] ?? '') <= $to));
}

$filename = 'relatorio_chamada_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";
?>
<html>
<head>
  <meta charset="UTF-8">
  <title>Relatório de Chamada</title>
</head>
<body>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Data</th>
        <th>Aluno</th>
        <th>Oficina</th>
        <th>Status</th>
        <th>Lançado por</th>
        <th>Lançado em</th>
        <th>Revisão</th>
        <th>ID cobrança fila</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
        <tr>
          <td colspan="8">Nenhuma chamada encontrada para o filtro informado.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <?php
            $office = trim((string) ($item['office_name'] ?? ''));
            $officeCode = trim((string) ($item['office_code'] ?? ''));
            if ($office !== '' && $officeCode !== '') {
                $office .= ' (' . $officeCode . ')';
            }
            if ($office === '') {
                $office = '-';
            }
            $review = trim((string) ($item['review_note'] ?? ''));
            if ($review === '') {
                $review = '-';
            }
          ?>
          <tr>
            <td><?php echo htmlspecialchars(formatDateBr((string) ($item['attendance_date'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($item['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(statusLabel((string) ($item['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) (($item['created_by_user'] ?? '') ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(formatDateTimeBr((string) ($item['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($review, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) (($item['queue_payment_id'] ?? '') ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>

