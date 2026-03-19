<?php
$bootstrapCandidates = [
    __DIR__ . '/src/Bootstrap.php',
    dirname(__DIR__) . '/src/Bootstrap.php',
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
use App\MonthlyStudents;
use App\AsaasClient;
use App\SupabaseClient;

function parse_day_type(string $raw): string
{
    $base = strtolower(trim(explode('|', $raw, 2)[0] ?? ''));
    if ($base === 'emergencial') {
        return 'Emergencial';
    }
    return 'Planejada';
}

function date_key(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $time = strtotime($raw);
    if ($time === false) {
        return '';
    }
    return date('Y-m-d', $time);
}

function normalize_text_key(string $value): string
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

function parse_single_day_use_date(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return date_key($value);
    }
    if (preg_match('/^\d{2}\/\d{2}\/\d{2,4}$/', $value)) {
        [$day, $month, $year] = explode('/', $value);
        $yearInt = (int) $year;
        if ($yearInt < 100) {
            $yearInt += 2000;
        }
        if (checkdate((int) $month, (int) $day, $yearInt)) {
            return sprintf('%04d-%02d-%02d', $yearInt, (int) $month, (int) $day);
        }
    }
    return date_key($value);
}

function parse_day_use_dates(string $dailyTypeRaw, string $fallbackDate): array
{
    $dates = [];
    $parts = explode('|', $dailyTypeRaw, 2);
    $datesRaw = trim((string) ($parts[1] ?? ''));
    if ($datesRaw !== '') {
        $normalized = str_replace(["\r\n", "\n", "\r", ';', '+'], ',', $datesRaw);
        $tokens = array_map('trim', explode(',', $normalized));
        foreach ($tokens as $token) {
            $parsed = parse_single_day_use_date($token);
            if ($parsed !== '') {
                $dates[$parsed] = true;
            }
        }
    }

    $fallback = date_key($fallbackDate);
    if ($fallback !== '') {
        $dates[$fallback] = true;
    }
    return array_keys($dates);
}

function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function mask_cpf(string $digits): string
{
    if (strlen($digits) !== 11) {
        return $digits;
    }
    return substr($digits, 0, 3) . '.'
        . substr($digits, 3, 3) . '.'
        . substr($digits, 6, 3) . '-'
        . substr($digits, 9, 2);
}

$user = Helpers::requireAuthWeb();
$client = new SupabaseClient(new HttpClient());
$financeiroError = isset($_SESSION['financeiro_error']) ? (string) $_SESSION['financeiro_error'] : '';
unset($_SESSION['financeiro_error']);
$guardianIds = [];
$studentIdsScope = [];
$sessionGuardianId = trim((string) ($user['id'] ?? ''));
if ($sessionGuardianId !== '') {
    $guardianIds[$sessionGuardianId] = true;
}
$sessionStudentId = trim((string) ($user['student_id'] ?? ''));
if ($sessionStudentId !== '') {
    $studentIdsScope[$sessionStudentId] = true;
}

$sessionCpfDigits = only_digits((string) ($user['parent_document'] ?? ''));
if ($sessionCpfDigits !== '') {
    $cpfAttempts = [
        'parent_document=eq.' . urlencode($sessionCpfDigits) . '&select=id,student_id',
        'parent_document=eq.' . urlencode(mask_cpf($sessionCpfDigits)) . '&select=id,student_id',
        'parent_document=ilike.' . urlencode('*' . $sessionCpfDigits . '*') . '&select=id,student_id',
    ];
    foreach ($cpfAttempts as $query) {
        $guardiansByCpf = $client->select('guardians', $query);
        if (!($guardiansByCpf['ok'] ?? false) || empty($guardiansByCpf['data'])) {
            continue;
        }
        foreach ($guardiansByCpf['data'] as $row) {
            $gid = trim((string) ($row['id'] ?? ''));
            if ($gid !== '') {
                $guardianIds[$gid] = true;
            }
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

$sessionEmail = trim((string) ($user['email'] ?? ''));
if ($sessionEmail !== '') {
    $emailAttempts = [
        'email=eq.' . urlencode($sessionEmail) . '&select=id,student_id&limit=200',
        'email=ilike.' . urlencode($sessionEmail) . '&select=id,student_id&limit=200',
    ];
    foreach ($emailAttempts as $query) {
        $guardiansByEmail = $client->select('guardians', $query);
        if (!($guardiansByEmail['ok'] ?? false) || empty($guardiansByEmail['data'])) {
            continue;
        }
        foreach ($guardiansByEmail['data'] as $row) {
            $gid = trim((string) ($row['id'] ?? ''));
            if ($gid !== '') {
                $guardianIds[$gid] = true;
            }
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

if (!empty($guardianIds)) {
    $quotedGuardianIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', array_keys($guardianIds));
    $guardiansByStudent = $client->select(
        'guardians',
        'select=id,student_id&id=in.(' . implode(',', $quotedGuardianIds) . ')&limit=500'
    );
    if (($guardiansByStudent['ok'] ?? false) && !empty($guardiansByStudent['data'])) {
        foreach ($guardiansByStudent['data'] as $row) {
            $gid = trim((string) ($row['id'] ?? ''));
            if ($gid !== '') {
                $guardianIds[$gid] = true;
            }
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

$payments = [];
$paymentColumns = 'select=id,student_id,payment_date,daily_type,amount,status,billing_type,paid_at,created_at,guardian_id';
$paymentsById = [];
$appendPayments = static function (array $rows) use (&$payments, &$paymentsById): void {
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = trim((string) ($row['id'] ?? ''));
        if ($pid !== '' && isset($paymentsById[$pid])) {
            continue;
        }
        if ($pid !== '') {
            $paymentsById[$pid] = true;
        }
        $payments[] = $row;
    }
};

$studentIdList = array_keys($studentIdsScope);
if (!empty($studentIdList)) {
    $quotedStudentIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', $studentIdList);
    $paymentsByStudent = $client->select(
        'payments',
        $paymentColumns
        . '&student_id=in.(' . implode(',', $quotedStudentIds) . ')'
        . '&order=payment_date.desc&limit=5000'
    );
    if ($paymentsByStudent['ok'] ?? false) {
        $appendPayments($paymentsByStudent['data'] ?? []);
    }
}

$guardianIdList = array_keys($guardianIds);
if (!empty($guardianIdList)) {
    $quotedGuardianIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', $guardianIdList);
    $paymentsByGuardian = $client->select(
        'payments',
        $paymentColumns
        . '&guardian_id=in.(' . implode(',', $quotedGuardianIds) . ')'
        . '&order=payment_date.desc&limit=5000'
    );
    if ($paymentsByGuardian['ok'] ?? false) {
        $appendPayments($paymentsByGuardian['data'] ?? []);
    }
}
if (empty($payments) && $sessionGuardianId !== '') {
    $paymentsFallback = $client->select(
        'payments',
        $paymentColumns
        . '&guardian_id=eq.' . urlencode($sessionGuardianId)
        . '&order=payment_date.desc&limit=1000'
    );
    if ($paymentsFallback['ok'] ?? false) {
        $appendPayments($paymentsFallback['data'] ?? []);
    }
}

$monthlyItems = MonthlyStudents::load();
$monthlyById = MonthlyStudents::mapByStudentId($monthlyItems);
$monthlyByName = MonthlyStudents::mapByNormalizedName($monthlyItems);
if (!empty($payments) && (!empty($monthlyById) || !empty($monthlyByName))) {
    $paymentsForQuota = array_values(array_filter($payments, static function ($payment): bool {
        $status = strtolower(trim((string) ($payment['status'] ?? '')));
        return !in_array($status, ['canceled', 'refunded', 'deleted'], true);
    }));
    $classified = MonthlyStudents::classifyRowsByQuota(
        $paymentsForQuota,
        static function (array $payment): array {
            return [
                'student_id' => (string) ($payment['student_id'] ?? ''),
                'student_name' => '',
                'dates' => MonthlyStudents::extractDatesFromPayment(
                    (string) ($payment['daily_type'] ?? ''),
                    (string) ($payment['payment_date'] ?? '')
                ),
                'created_at' => (string) ($payment['created_at'] ?? ''),
            ];
        },
        $monthlyById,
        $monthlyByName
    );
    $visibleIds = [];
    foreach (($classified['visible'] ?? []) as $rowVisible) {
        $pid = trim((string) ($rowVisible['id'] ?? ''));
        if ($pid !== '') {
            $visibleIds[$pid] = true;
        }
    }
    $payments = array_values(array_filter($payments, static function ($payment) use ($visibleIds): bool {
        $pid = trim((string) ($payment['id'] ?? ''));
        if ($pid === '') {
            return true;
        }
        return isset($visibleIds[$pid]);
    }));
}
$studentsById = [];
$studentIds = [];
foreach ($payments as $payment) {
    $sid = trim((string) ($payment['student_id'] ?? ''));
    if ($sid !== '') {
        $studentIds[$sid] = true;
    }
}
if (!empty($studentIds)) {
    $quoted = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', array_keys($studentIds));
    $studentsResult = $client->select(
        'students',
        'select=id,name,enrollment&id=in.(' . implode(',', $quoted) . ')&limit=1000'
    );
    if (($studentsResult['ok'] ?? false) && !empty($studentsResult['data'])) {
        foreach ($studentsResult['data'] as $student) {
            $sid = (string) ($student['id'] ?? '');
            if ($sid !== '') {
                $studentsById[$sid] = $student;
            }
        }
    }
}

$cutoffDate = '2026-03-16';
$asaas = new AsaasClient(new HttpClient());
$paymentLinksByAsaasId = [];
$paymentStatusByAsaasId = [];
$rows = [];
$totalBase = 0.0;
$totalEffective = 0.0;
foreach ($payments as $payment) {
    $statusRaw = strtolower(trim((string) ($payment['status'] ?? '')));
    $paidAtRaw = trim((string) ($payment['paid_at'] ?? ''));
    $isReceived = $statusRaw === 'paid';
    $isOpen = $paidAtRaw === '' && !in_array($statusRaw, ['paid', 'canceled', 'refunded', 'deleted'], true);

    // Espelha a regra do Admin:
    // - Cobranças recebidas: status paid
    // - Cobranças em aberto: não pagas e sem status de exclusão/cancelamento
    if (!$isReceived && !$isOpen) {
        continue;
    }

    $student = $studentsById[(string) ($payment['student_id'] ?? '')] ?? [];
    $studentName = trim((string) ($student['name'] ?? 'Aluno'));
    $paymentDate = date_key((string) ($payment['payment_date'] ?? ''));
    if ($paymentDate === '') {
        $paymentDate = date_key((string) ($payment['paid_at'] ?? ''));
    }
    if ($paymentDate === '') {
        $paymentDate = date_key((string) ($payment['created_at'] ?? ''));
    }
    $dailyTypeRaw = (string) ($payment['daily_type'] ?? 'planejada');
    $typeLabel = parse_day_type($dailyTypeRaw);
    $basePerDay = $typeLabel === 'Emergencial' ? 97.00 : 77.00;
    $storedAmount = (float) ($payment['amount'] ?? 0);
    $dates = parse_day_use_dates($dailyTypeRaw, $paymentDate);
    if (empty($dates)) {
        $dates = [''];
    }
    $statusLabel = $isReceived ? 'Pago' : 'Pendente';
    $statusRank = $statusRaw === 'paid' ? 2 : 1;
    $createdAtRaw = (string) ($payment['created_at'] ?? '');
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $payProxyUrl = $paymentId !== '' ? '/api/financeiro-pay.php?payment_id=' . rawurlencode($paymentId) : '';
    $asaasPaymentId = trim((string) ($payment['asaas_payment_id'] ?? ''));
    $payUrl = '';
    if ($isOpen && $asaasPaymentId !== '') {
        if (!array_key_exists($asaasPaymentId, $paymentLinksByAsaasId)) {
            $paymentLinksByAsaasId[$asaasPaymentId] = '';
            $paymentStatusByAsaasId[$asaasPaymentId] = '';
            $asaasResponse = $asaas->getPayment($asaasPaymentId);
            if (($asaasResponse['ok'] ?? false) && is_array($asaasResponse['data'] ?? null)) {
                $asaasData = $asaasResponse['data'];
                $paymentLinksByAsaasId[$asaasPaymentId] = trim((string) ($asaasData['invoiceUrl'] ?? ($asaasData['bankSlipUrl'] ?? '')));
                $paymentStatusByAsaasId[$asaasPaymentId] = strtoupper(trim((string) ($asaasData['status'] ?? '')));
            }
        }
        $payUrl = (string) ($paymentLinksByAsaasId[$asaasPaymentId] ?? '');
        $asaasStatus = (string) ($paymentStatusByAsaasId[$asaasPaymentId] ?? '');
        if (in_array($asaasStatus, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            $statusLabel = 'Pago';
            $statusRank = 2;
        }
    }

    $isSingleDate = count($dates) === 1;
    $baseSingle = ($storedAmount > 0 && $isSingleDate && $storedAmount > 97.01) ? $storedAmount : $basePerDay;
    foreach ($dates as $dayUseDate) {
        $baseAmount = $baseSingle;
        $effectiveAmount = $baseAmount;
        if ($dayUseDate !== '' && $dayUseDate <= $cutoffDate && $baseAmount <= 97.01) {
            $effectiveAmount = 77.00;
        }

        $totalBase += $baseAmount;
        $totalEffective += $effectiveAmount;

        $rows[] = [
            'student_id' => (string) ($payment['student_id'] ?? ''),
            'student_name' => $studentName,
            'date' => $dayUseDate,
            'type' => $typeLabel,
            'base_amount' => $baseAmount,
            'effective_amount' => $effectiveAmount,
            'status' => $statusLabel,
            'status_rank' => $statusRank,
            'created_at' => $createdAtRaw,
            'payment_id' => $paymentId,
            'pay_url' => $payUrl,
            'pay_proxy_url' => $payProxyUrl,
        ];
    }
}

usort($rows, static function (array $a, array $b): int {
    $byDate = strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
    if ($byDate !== 0) {
        return $byDate;
    }
    $byStatus = ((int) ($b['status_rank'] ?? 0)) <=> ((int) ($a['status_rank'] ?? 0));
    if ($byStatus !== 0) {
        return $byStatus;
    }
    return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
});

$deduped = [];
$seenRows = [];
$hiddenDuplicates = 0;
foreach ($rows as $row) {
    $studentKey = trim((string) ($row['student_id'] ?? ''));
    if ($studentKey === '') {
        $studentKey = normalize_text_key((string) ($row['student_name'] ?? ''));
    }
    $key = $studentKey . '|' . (string) ($row['date'] ?? '');
    if ($key !== '|' && isset($seenRows[$key])) {
        $hiddenDuplicates++;
        continue;
    }
    $seenRows[$key] = true;
    $deduped[] = $row;
}
$rows = $deduped;

$totalBase = 0.0;
$totalEffective = 0.0;
foreach ($rows as $row) {
    $totalBase += (float) ($row['base_amount'] ?? 0);
    $totalEffective += (float) ($row['effective_amount'] ?? 0);
}
$pendingBase = 0.0;
$pendingEffective = 0.0;
foreach ($rows as $row) {
    if (strtolower(trim((string) ($row['status'] ?? ''))) !== 'pendente') {
        continue;
    }
    $pendingBase += (float) ($row['base_amount'] ?? 0);
    $pendingEffective += (float) ($row['effective_amount'] ?? 0);
}

$economy = max(0, $totalBase - $totalEffective);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Financeiro - Diárias Village</title>
  <meta name="description" content="Histórico financeiro das diárias utilizadas." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    .finance-table{width:100%;border-collapse:collapse}
    .finance-table th,.finance-table td{padding:10px 8px;border-top:1px solid #e8edf6;text-align:left}
    .finance-kpis{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0}
    .finance-pill{display:inline-block;padding:8px 12px;border-radius:999px;border:1px solid #e2e8f0;background:#f8fafc;font-size:13px}
    .discount-note{margin-top:10px;padding:12px 14px;border-radius:12px;background:#fff7db;border:1px solid #f4d37a;color:#5e4700;font-size:14px;line-height:1.5}
    .status-badge{display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.2;border:1px solid transparent}
    .status-paid{background:#e8f1ff;color:#1e4f9c;border-color:#c9dcff}
    .status-pending{background:#fff1f1;color:#a13a3a;border-color:#f3caca}
    .finance-pay-btn{padding:8px 12px;border-radius:12px;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;min-width:72px}
    .finance-pay-muted{font-size:12px;color:#64748b}
  </style>
</head>
<body>
  <header class="hero" id="top">
    <div class="container">
      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title">DIÁRIAS VILLAGE</div>
            <div class="brand-sub">Painel financeiro</div>
          </div>
        </div>
        <div class="cta">
          <a class="btn btn-ghost btn-sm" href="/dashboard.php">Dashboard</a>
          <a class="btn btn-ghost btn-sm" href="/profile.php">Perfil</a>
          <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
        </div>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Financeiro</div>
          <h1>Resumo das diárias utilizadas.</h1>
          <p class="lead">Acompanhe o histórico de day-use e os valores cobrados.</p>
          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Planejada: R$ 77,00</span>
            <span class="microchip" role="listitem">Emergencial: R$ 97,00</span>
            <span class="microchip" role="listitem">Regras de transição aplicadas</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Resumo financeiro">
          <h3>Totais do período</h3>
          <p class="muted">Histórico total desta conta.</p>
          <div class="finance-kpis">
            <span class="finance-pill">Diárias: <?php echo count($rows); ?></span>
            <span class="finance-pill">Total base: <?php echo money($totalBase); ?></span>
            <span class="finance-pill">Total final: <?php echo money($totalEffective); ?></span>
            <?php if ($hiddenDuplicates > 0): ?>
              <span class="finance-pill">Duplicadas ocultadas: <?php echo $hiddenDuplicates; ?></span>
            <?php endif; ?>
          </div>
          <div class="discount-note">
            <strong>Desconto de transição de sistema</strong> - Todas as diárias até o dia 15 de março de 2026 serão de R$77,00.
            Você economizou <strong><?php echo money($economy); ?></strong>.
          </div>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section section-alt">
      <div class="container">
        <div class="section-head">
          <h2>Histórico financeiro</h2>
          <p class="muted">Data, tipo e valor aplicado em cada day-use utilizado.</p>
        </div>

        <div style="overflow-x:auto;background:#fff;border:1px solid #e6ebf3;border-radius:14px;padding:8px;">
          <?php if ($financeiroError !== ''): ?>
            <div class="error" style="margin:8px 8px 12px 8px;">
              <?php echo htmlspecialchars($financeiroError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
          <table class="finance-table">
            <thead>
              <tr>
                <th>Aluno</th>
                <th>Data do day-use</th>
                <th>Tipo</th>
                <th>Valor base</th>
                <th>Valor final</th>
                <th>Status</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="7">Nenhuma diária encontrada para esta conta.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                    $dateLabel = $row['date'] !== '' ? date('d/m/Y', strtotime($row['date'])) : '-';
                    $base = money((float) $row['base_amount']);
                    $final = money((float) $row['effective_amount']);
                    $hasDiscount = ((float) $row['effective_amount']) < ((float) $row['base_amount']);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($base, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <?php echo htmlspecialchars($final, ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($hasDiscount): ?>
                        <span class="small" style="color:#1f6f38;">(com desconto)</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $statusText = (string) ($row['status'] ?? '');
                        $statusClass = strtolower(trim($statusText)) === 'pago' ? 'status-paid' : 'status-pending';
                        $payUrl = trim((string) ($row['pay_url'] ?? ''));
                        $payProxyUrl = trim((string) ($row['pay_proxy_url'] ?? ''));
                        $isPending = strtolower(trim($statusText)) === 'pendente';
                      ?>
                      <span class="status-badge <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($isPending && $payUrl !== ''): ?>
                        <a
                          class="btn btn-primary btn-sm finance-pay-btn"
                          href="<?php echo htmlspecialchars($payUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          PAGAR
                        </a>
                      <?php elseif ($isPending && $payProxyUrl !== ''): ?>
                        <a
                          class="btn btn-primary btn-sm finance-pay-btn"
                          href="<?php echo htmlspecialchars($payProxyUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          PAGAR
                        </a>
                      <?php elseif ($isPending): ?>
                        <span class="finance-pay-muted">Link indisponível</span>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
              <tfoot>
                <tr style="font-weight:800;background:#f8fafc;">
                  <td colspan="3">Pendente</td>
                  <td><?php echo htmlspecialchars(money($pendingBase), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(money($pendingEffective), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>Pendente</td>
                  <td>-</td>
                </tr>
              </tfoot>
            <?php endif; ?>
          </table>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
      <a class="tinyLink" href="/admin/" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>
</body>
</html>
