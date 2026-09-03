<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('duplicados', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$cpfDuplicateGroups = $context['cpfDuplicateGroups'];
$duplicateEnrollmentGroups = $context['duplicateEnrollmentGroups'];
$duplicateGroups = $context['duplicateGroups'];
?>
<section id="tab-duplicados" class="<?php echo $activeTab === 'duplicados' ? '' : 'hidden'; ?>">
        <h2>Alunos duplicados</h2>
        <p class="muted">Mescla automática por nome ou matrícula (mantém o registro mais antigo). Abaixo listamos possíveis duplicados por CPF do responsável.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Critério</th>
                <th>Aluno</th>
                <th>IDs</th>
                <th>Matrículas</th>
                <th>Ativo</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($duplicateGroups) && empty($duplicateEnrollmentGroups)): ?>
                <tr>
                  <td colspan="6">Nenhum duplicado encontrado.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($duplicateGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Nome</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($duplicateEnrollmentGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Matrícula</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($cpfDuplicateGroups)): ?>
          <div style="margin-top:18px;overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr style="text-align:left;">
                  <th>Possível duplicado (CPF do responsável)</th>
                  <th>CPF</th>
                  <th>IDs</th>
                  <th>Matrículas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cpfDuplicateGroups as $group): ?>
                  <?php
                    $cpf = $group[0]['parent_document'] ?? '-';
                    $names = array_map(static fn($g) => ($g['students']['name'] ?? '-'), $group);
                    $ids = array_map(static fn($g) => ($g['students']['id'] ?? '-'), $group);
                    $enrollments = array_map(static fn($g) => ($g['students']['enrollment'] ?? '-'), $group);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars(implode(' | ', $names), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <div class="charge-message" id="merge-message"></div>
      </section>
