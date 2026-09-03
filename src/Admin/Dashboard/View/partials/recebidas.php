<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('recebidas', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$manualPaid = $context['manualPaid'];
$pendenciasPagas = $context['pendenciasPagas'];
$valorPendencia = $context['valorPendencia'];
?>
<section id="tab-recebidas" class="<?php echo $activeTab === 'recebidas' ? '' : 'hidden'; ?>">
        <h2>Cobranças recebidas</h2>
        <p class="muted">Apenas conferência de cobranças pagas e regularizadas.</p>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
          <button id="sync-recebidas-btn" class="btn btn-primary btn-sm" type="button">Atualizar conferência no Asaas</button>
          <div id="sync-recebidas-message" class="charge-message"></div>
        </div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Recebido em</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($manualPaid) && empty($pendenciasPagas)): ?>
                <tr>
                  <td colspan="6">Nenhuma cobrança recebida para conferência.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($manualPaid as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $paidAt = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $paidAt; ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($pendenciasPagas as $p): ?>
                  <?php
                    $paidAt = $p['paid_at'] ? date('d/m/Y H:i', strtotime($p['paid_at'])) : '-';
                    $amount = number_format((float) $valorPendencia, 2, ',', '.');
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($p['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($p['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>Pendência cadastro</td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $paidAt; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
