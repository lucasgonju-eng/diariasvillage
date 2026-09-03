<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('mensalistas', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$monthlyRowsForJs = $context['monthlyRowsForJs'];
?>
<section id="tab-mensalistas" class="<?php echo $activeTab === 'mensalistas' ? '' : 'hidden'; ?>">
        <h2>Mensalistas</h2>
        <p class="muted">Marque no cadastro os alunos mensalistas com 2, 3, 4 ou 5 dias por semana.</p>
        <datalist id="monthly-students-list"></datalist>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>Aluno</label>
            <input id="monthly-student" type="text" list="monthly-students-list" placeholder="Nome • Matrícula" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Dias por semana</label>
            <div class="monthly-days-wrap">
              <label><input type="radio" name="monthly-days" value="5" /> 5 dias</label>
              <label><input type="radio" name="monthly-days" value="4" /> 4 dias</label>
              <label><input type="radio" name="monthly-days" value="3" /> 3 dias</label>
              <label><input type="radio" name="monthly-days" value="2" /> 2 dias</label>
            </div>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="monthly-save-btn" class="btn btn-primary btn-sm" type="button">Salvar mensalista</button>
            <button id="monthly-remove-btn" class="btn btn-danger btn-sm" type="button">Remover mensalista</button>
          </div>
        </div>
        <div id="monthly-message" class="charge-message"></div>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matrícula</th>
                <th>Plano semanal</th>
                <th>Atualizado em</th>
                <th>Atualizado por</th>
              </tr>
            </thead>
            <tbody id="monthly-table-body">
              <?php if (empty($monthlyRowsForJs)): ?>
                <tr><td colspan="5">Nenhum mensalista cadastrado.</td></tr>
              <?php else: ?>
                <?php foreach ($monthlyRowsForJs as $monthlyRow): ?>
                  <tr data-student-id="<?php echo htmlspecialchars((string) ($monthlyRow['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['enrollment'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($monthlyRow['weekly_days'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> dias/semana</td>
                    <td><?php echo !empty($monthlyRow['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $monthlyRow['updated_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td><?php echo htmlspecialchars((string) (($monthlyRow['updated_by'] ?? '') ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
