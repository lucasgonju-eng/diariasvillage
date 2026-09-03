<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('inadimplentes', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$inadimplentesMonthlyMetaById = $context['inadimplentesMonthlyMetaById'];
$manualPending = $context['manualPending'];
$queuedPending = $context['queuedPending'];
?>
<section id="tab-inadimplentes" class="<?php echo $activeTab === 'inadimplentes' ? '' : 'hidden'; ?>">
        <h2>Cobranças em aberto</h2>
        <p class="muted">Inclui cobranças da fila de envio e cobranças já enviadas que ainda não foram pagas.</p>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px;">
          <div class="form-group" style="min-width:260px;">
            <label>Filtrar por aluno</label>
            <input id="inadimplentes-student-filter" type="text" list="inadimplentes-students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
            <datalist id="inadimplentes-students-list"></datalist>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="inadimplentes-student-filter-clear" class="btn btn-ghost btn-sm" type="button">Limpar filtro</button>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
          <button id="send-selected-pending" class="btn btn-primary btn-sm" type="button">Enviar cobranças da fila</button>
          <button id="sync-charges-payments-inadimplentes-btn" class="btn btn-sm btn-sync-reconcile" type="button">Conciliar com Asaas antes de enviar</button>
          <label style="display:flex;gap:6px;align-items:center;font-size:13px;">
            <input id="select-all-pending" type="checkbox" />
            Selecionar todas da fila de envio
          </label>
        </div>
        <div id="send-pending-message" class="charge-message"></div>
        <div id="sync-charges-payments-inadimplentes-message" class="charge-message"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Enviar</th>
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($queuedPending) && empty($manualPending)): ?>
                <tr>
                  <td colspan="9">Nenhuma cobrança em aberto.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($queuedPending as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $effectiveAmount = resolveOpenAmountFromPaymentRow((array) $payment);
                    $amount = number_format($effectiveAmount, 2, ',', '.');
                    $created = $payment['created_at'] ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                    $dayUseCount = count(array_filter(array_map('trim', explode(',', (string) $datesLabel))));
                    if ($dayUseCount < 1) {
                        $dayUseCount = 1;
                    }
                  ?>
                  <?php
                    $paymentIdRow = trim((string) ($payment['id'] ?? ''));
                    $monthlyMeta = $paymentIdRow !== '' ? ($inadimplentesMonthlyMetaById[$paymentIdRow] ?? null) : null;
                    $isMonthlyCheck = is_array($monthlyMeta);
                    $monthlyDays = (int) ($monthlyMeta['weekly_days'] ?? 0);
                    $monthlyWarning = $isMonthlyCheck ? 'Aluno mensalista. Checar' : '';
                    $canApplyIsabelVoucher = isIsabelVoucherStudent((string) ($student['name'] ?? ''));
                  ?>
                  <tr
                    class="inadimplente-row<?php echo $isMonthlyCheck ? ' monthly-check-row' : ''; ?>"
                    data-payment-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-student="<?php echo htmlspecialchars((string) ($student['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-date="<?php echo htmlspecialchars((string) $datesLabel, ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-count="<?php echo (int) $dayUseCount; ?>"
                    data-amount="<?php echo htmlspecialchars((string) $effectiveAmount, ENT_QUOTES, 'UTF-8'); ?>"
                    data-has-asaas="0"
                    data-monthly="<?php echo $isMonthlyCheck ? '1' : '0'; ?>"
                    data-monthly-days="<?php echo $isMonthlyCheck ? htmlspecialchars((string) $monthlyDays, ENT_QUOTES, 'UTF-8') : ''; ?>"
                  >
                    <td>
                      <input
                        class="pending-send-checkbox"
                        type="checkbox"
                        value="<?php echo htmlspecialchars($payment['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $isMonthlyCheck ? 'disabled title="Aluno mensalista: revisar antes de enviar cobrança."' : ''; ?>
                      />
                    </td>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td>
                      Na fila (não enviada no Asaas)
                      <?php if ($isMonthlyCheck): ?>
                        <span class="monthly-check-badge"><?php echo htmlspecialchars($monthlyWarning . ' • ' . $monthlyDays . ' dias', ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $created; ?></td>
                    <td>
                      <?php if ($canApplyIsabelVoucher): ?>
                        <button
                          class="btn btn-primary btn-sm js-isabel-voucher"
                          type="button"
                          data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                          style="margin-bottom:6px;"
                        >
                          Liquidar voucher
                        </button>
                      <?php endif; ?>
                      <button
                        class="btn btn-danger btn-sm js-delete-payment"
                        type="button"
                        data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        Cancelar
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($manualPending as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $effectiveAmount = resolveOpenAmountFromPaymentRow((array) $payment);
                    $amount = number_format($effectiveAmount, 2, ',', '.');
                    $created = $payment['created_at'] ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                    $dayUseCount = count(array_filter(array_map('trim', explode(',', (string) $datesLabel))));
                    if ($dayUseCount < 1) {
                        $dayUseCount = 1;
                    }
                    $statusRaw = strtolower(trim((string) ($payment['status'] ?? 'pending')));
                    $statusMap = [
                        'pending' => 'Pendente no Asaas',
                        'pending_asaas' => 'Pendente no Asaas',
                        'paid' => 'Concluído',
                        'overdue' => 'Vencida',
                        'awaiting_risk_analysis' => 'Em análise de risco',
                        'processing_asaas' => 'Processamento financeiro em andamento',
                        'queued' => 'Na fila (não enviada)',
                    ];
                    $statusLabel = $statusMap[$statusRaw] ?? (trim((string) ($payment['status'] ?? '')) !== '' ? (string) $payment['status'] : 'Pendente no Asaas');
                  ?>
                  <?php
                    $paymentIdRow = trim((string) ($payment['id'] ?? ''));
                    $monthlyMeta = $paymentIdRow !== '' ? ($inadimplentesMonthlyMetaById[$paymentIdRow] ?? null) : null;
                    $isMonthlyCheck = is_array($monthlyMeta);
                    $monthlyDays = (int) ($monthlyMeta['weekly_days'] ?? 0);
                    $monthlyWarning = $isMonthlyCheck ? 'Aluno mensalista. Checar' : '';
                    $hasAsaasId = trim((string) ($payment['asaas_payment_id'] ?? '')) !== '';
                    $isFebruaryOnly = isFebruaryChargeOnly((array) $payment);
                    $canResendFebruaryCharge = $hasAsaasId
                        && $isFebruaryOnly
                        && !in_array($statusRaw, ['paid', 'canceled', 'cancelled', 'deleted', 'refunded'], true);
                    if ($isMonthlyCheck) {
                        $statusLabel .= ' • ' . $monthlyWarning;
                    }
                    if (!$hasAsaasId) {
                        $statusLabel .= ' • Sem ID Asaas';
                    }
                    $canApplyIsabelVoucher = isIsabelVoucherStudent((string) ($student['name'] ?? ''));
                  ?>
                  <tr
                    class="inadimplente-row<?php echo $isMonthlyCheck ? ' monthly-check-row' : ''; ?>"
                    data-payment-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-student="<?php echo htmlspecialchars((string) ($student['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-date="<?php echo htmlspecialchars((string) $datesLabel, ENT_QUOTES, 'UTF-8'); ?>"
                    data-dayuse-count="<?php echo (int) $dayUseCount; ?>"
                    data-amount="<?php echo htmlspecialchars((string) $effectiveAmount, ENT_QUOTES, 'UTF-8'); ?>"
                    data-has-asaas="<?php echo $hasAsaasId ? '1' : '0'; ?>"
                    data-monthly="<?php echo $isMonthlyCheck ? '1' : '0'; ?>"
                    data-monthly-days="<?php echo $isMonthlyCheck ? htmlspecialchars((string) $monthlyDays, ENT_QUOTES, 'UTF-8') : ''; ?>"
                  >
                    <td>-</td>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td>
                      <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($isMonthlyCheck): ?>
                        <span class="monthly-check-badge"><?php echo htmlspecialchars($monthlyDays . ' dias/semana', ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo $created; ?></td>
                    <td>
                      <?php if ($canApplyIsabelVoucher): ?>
                        <button
                          class="btn btn-primary btn-sm js-isabel-voucher"
                          type="button"
                          data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                          style="margin-bottom:6px;"
                        >
                          Liquidar voucher
                        </button>
                      <?php endif; ?>
                      <button
                        class="btn btn-danger btn-sm js-delete-payment"
                        type="button"
                        data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                      >
                        Cancelar
                      </button>
                      <?php if ($canResendFebruaryCharge): ?>
                        <button
                          class="btn btn-primary btn-sm js-resend-feb-charge"
                          type="button"
                          data-id="<?php echo htmlspecialchars((string) ($payment['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                          style="margin-top:6px;"
                        >
                          Reenviar cobrança (fev)
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div id="inadimplentes-summary" class="open-charges-summary"></div>
      </section>
