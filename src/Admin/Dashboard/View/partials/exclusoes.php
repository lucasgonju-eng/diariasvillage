<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('exclusoes', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$exclusionsLog = $context['exclusionsLog'];
?>
<section id="tab-exclusoes" class="<?php echo $activeTab === 'exclusoes' ? '' : 'hidden'; ?>">
        <h2>Histórico de exclusões</h2>
        <p class="muted">Registro de exclusões de cobranças e pendências com motivo informado.</p>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Data da exclusão</th>
                <th>Tipo</th>
                <th>Aluno</th>
                <th>Responsável</th>
                <th>Data do day-use</th>
                <th>Valor</th>
                <th>Motivo</th>
                <th>Origem</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($exclusionsLog)): ?>
                <tr>
                  <td colspan="8">Nenhuma exclusão registrada ainda.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($exclusionsLog as $entry): ?>
                  <?php
                    $deletedAt = !empty($entry['deleted_at']) ? date('d/m/Y H:i', strtotime((string) $entry['deleted_at'])) : '-';
                    $dayUseDate = !empty($entry['payment_date']) ? date('d/m/Y', strtotime((string) $entry['payment_date'])) : '-';
                    $amountNumber = (float) ($entry['amount'] ?? 0);
                    $amountLabel = $amountNumber > 0 ? ('R$ ' . number_format($amountNumber, 2, ',', '.')) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($deletedAt, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['entity_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['guardian_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($dayUseDate, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($entry['source'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
