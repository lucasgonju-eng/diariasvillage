<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('sem-whatsapp', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$missingWhatsapp = $context['missingWhatsapp'];
?>
<section id="tab-sem-whatsapp" class="<?php echo $activeTab === 'sem-whatsapp' ? '' : 'hidden'; ?>">
        <h2>Responsáveis sem WhatsApp</h2>
        <p class="muted">Aba de auditoria (somente conferência) para responsáveis sem celular cadastrado.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>CPF/CNPJ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($missingWhatsapp)): ?>
                <tr>
                  <td colspan="4">Nenhum responsável sem WhatsApp no momento.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($missingWhatsapp as $guardian): ?>
                  <?php $student = $guardian['students'] ?? []; ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_document'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
