<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('entries', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$monthlyEntries = $context['monthlyEntries'];
$monthlySubmissions = $context['monthlySubmissions'];
$payments = $context['payments'];
$pendenciasPagas = $context['pendenciasPagas'];
$valorPendencia = $context['valorPendencia'];
?>
<section id="tab-entries" class="<?php echo $activeTab === 'entries' ? '' : 'hidden'; ?>">
        <h2>Entradas confirmadas</h2>
        <p class="muted">Apenas conferência de day-use criado e pago corretamente.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matricula</th>
                <th>Pagamento</th>
                <th>Tipo</th>
                <th>Data do day-use</th>
                <th>Confirmado em</th>
                <th>Valor</th>
                <th>Codigo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments) && empty($pendenciasPagas) && empty($monthlyEntries)): ?>
                <tr>
                  <td colspan="8">Nenhuma entrada confirmada para conferência.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $voucherLabel = voucherLabelFromBillingType((string) ($payment['billing_type'] ?? ''));
                    $billing = $voucherLabel !== ''
                        ? $voucherLabel
                        : (in_array($payment['billing_type'], ['PIX', 'PIX_MANUAL'], true) ? 'PIX' : 'Debito');
                    $dailyRaw = $payment['daily_type'] ?? '';
                    $dailyBase = explode('|', $dailyRaw, 2)[0] ?? $dailyRaw;
                    $dailyLabel = $dailyBase === 'emergencial' ? 'Emergencial' : 'Planejada';
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $dayUse = date('d/m/Y', strtotime($payment['payment_date']));
                    $confirmed = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['enrollment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $billing; ?></td>
                    <td><?php echo $dailyLabel; ?></td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($payment['access_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($pendenciasPagas as $p): ?>
                  <?php
                    $confirmed = $p['paid_at'] ? date('d/m/Y H:i', strtotime($p['paid_at'])) : '-';
                    $amount = number_format((float) $valorPendencia, 2, ',', '.');
                    $dayUse = !empty($p['payment_date']) ? date('d/m/Y', strtotime($p['payment_date'])) : '-';
                    $matricula = $p['enrollment'] ?? null;
                    $codigo = $p['access_code'] ?? null;
                    $cpfNaoVinculado = empty($matricula) && !empty($p['paid_at']);
                  ?>
                  <tr<?php echo $cpfNaoVinculado ? ' style="background:#FEF2F2;"' : ''; ?>>
                    <td><?php echo htmlspecialchars($p['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <?php echo htmlspecialchars($matricula ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($cpfNaoVinculado): ?>
                        <span title="CPF não vinculado ao aluno matriculado" style="color:#B91C1C;font-size:11px;">⚠️</span>
                      <?php endif; ?>
                    </td>
                    <td>PIX</td>
                    <td>Pendência cadastro</td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($codigo ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($monthlyEntries as $monthlyEntry): ?>
                  <?php
                    $student = is_array($monthlyEntry['students'] ?? null) ? $monthlyEntry['students'] : [];
                    $slot = is_array($monthlyEntry['monthly_workshop_slots'] ?? null)
                        ? $monthlyEntry['monthly_workshop_slots']
                        : [];
                    $office = is_array($slot['oficina_modular'] ?? null) ? $slot['oficina_modular'] : [];
                    $officeName = !empty($slot['orientadora'])
                        ? 'Escolha pela Orientadora'
                        : (string) ($office['nome'] ?? 'Oficina');
                    $entryDate = !empty($monthlyEntry['entry_date'])
                        ? date('d/m/Y', strtotime((string) $monthlyEntry['entry_date']))
                        : '-';
                    $confirmed = !empty($monthlyEntry['created_at'])
                        ? date('d/m/Y H:i', strtotime((string) $monthlyEntry['created_at']))
                        : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) ($student['name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($student['enrollment'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>Plano mensal</td>
                    <td><?php echo htmlspecialchars($officeName, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $entryDate; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>Incluído</td>
                    <td><?php echo htmlspecialchars((string) ($monthlyEntry['access_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <h3 style="margin-top:20px;">Confirmações de oficinas do mês</h3>
        <p class="muted">Após o desbloqueio, as entradas recorrentes são canceladas e o responsável pode confirmar novamente.</p>
        <div id="monthly-confirmations-message" class="charge-message"></div>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matrícula</th>
                <th>Competência</th>
                <th>Plano</th>
                <th>Status</th>
                <th>Confirmado em</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($monthlySubmissions)): ?>
                <tr><td colspan="7">Nenhuma confirmação mensal registrada nesta competência.</td></tr>
              <?php else: ?>
                <?php foreach ($monthlySubmissions as $submission): ?>
                  <?php $submissionStudent = is_array($submission['students'] ?? null) ? $submission['students'] : []; ?>
                  <tr>
                    <td><?php echo htmlspecialchars((string) ($submissionStudent['name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($submissionStudent['enrollment'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo date('m/Y', strtotime((string) ($submission['reference_month'] ?? date('Y-m-01')))); ?></td>
                    <td><?php echo (int) ($submission['weekly_days_snapshot'] ?? 0); ?> dias • <?php echo (int) ($submission['required_slots'] ?? 0); ?> encontros</td>
                    <td><?php echo ($submission['status'] ?? '') === 'CONFIRMED' ? 'Confirmada' : 'Desbloqueada'; ?></td>
                    <td><?php echo !empty($submission['confirmed_at']) ? date('d/m/Y H:i', strtotime((string) $submission['confirmed_at'])) : '-'; ?></td>
                    <td>
                      <?php if (($submission['status'] ?? '') === 'CONFIRMED'): ?>
                        <button
                          class="btn btn-warning-yellow btn-sm monthly-unlock-btn"
                          type="button"
                          data-submission-id="<?php echo htmlspecialchars((string) ($submission['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >Desbloquear</button>
                      <?php else: ?>
                        -
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
