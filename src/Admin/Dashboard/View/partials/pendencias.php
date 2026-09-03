<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('pendencias', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$pendencias = $context['pendencias'];
?>
<section id="tab-pendencias" class="<?php echo $activeTab === 'pendencias' ? '' : 'hidden'; ?>">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
          <h2 style="margin:0;">Pendência de cadastro</h2>
          <a href="/admin/settle-pendencia.php" class="btn btn-danger btn-sm">Baixa manual (página dedicada)</a>
          <button id="sync-charges-payments-btn" class="btn btn-primary btn-sm" type="button">Atualizar cobranças e pagamentos</button>
        </div>
        <p class="muted">Esta aba deve receber apenas solicitações do botão Abrir Formulário no primeiro cadastro. Você pode mesclar com aluno existente ou incluir um novo aluno no banco.</p>
        <datalist id="pendencia-students-list"></datalist>
        <div id="sync-charges-payments-message" class="charge-message"></div>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>CPF para rechecagem</label>
            <input id="pendencia-cpf" type="text" placeholder="Digite o CPF" inputmode="numeric" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="check-pendencia-cpf" class="btn btn-danger btn-sm" type="button">Checar por CPF</button>
          </div>
          <div class="form-group">
            <label>Cobrança Asaas</label>
            <input id="pendencia-asaas-id" type="text" placeholder="Ex: 742559970, pay_... ou link" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="check-pendencia-asaas" class="btn btn-danger btn-sm" type="button">Checar por cobrança</button>
          </div>
        </div>
        <div id="pendencia-message" class="charge-message"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>CPF</th>
                <th>E-mail</th>
                <th>Data do day-use</th>
                <th>Registrado em</th>
                <th>Aluno no banco</th>
                <th>Ações do aluno</th>
                <th>Status Asaas</th>
                <th>Pago em</th>
                <th>Cancelar pendência</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pendencias)): ?>
                <tr>
                  <td colspan="11">Nenhuma pendência registrada.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($pendencias as $pendencia): ?>
                  <?php
                    $created = $pendencia['created_at'] ? date('d/m/Y H:i', strtotime($pendencia['created_at'])) : '-';
                    $paidAt = $pendencia['paid_at'] ? date('d/m/Y H:i', strtotime($pendencia['paid_at'])) : '-';
                    $dayUseDate = !empty($pendencia['payment_date']) ? date('d/m/Y', strtotime($pendencia['payment_date'])) : 'Não informado';
                    $linkedEnrollment = trim((string) ($pendencia['enrollment'] ?? ''));
                    $linkedStudentId = trim((string) ($pendencia['student_id'] ?? ''));
                    $linkedLabel = $linkedStudentId !== ''
                        ? 'Vinculado' . ($linkedEnrollment !== '' ? ' • Matrícula ' . $linkedEnrollment : '')
                        : 'Pendente de vínculo';
                  ?>
                  <tr
                    data-pendencia-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-student-id="<?php echo htmlspecialchars($linkedStudentId, ENT_QUOTES, 'UTF-8'); ?>"
                  >
                    <td data-col="student-name"><?php echo htmlspecialchars($pendencia['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_cpf'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($dayUseDate, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $created; ?></td>
                    <td data-col="student-link">
                      <div class="pendencia-student-link">
                        <?php if ($linkedStudentId !== ''): ?>
                          <?php echo htmlspecialchars($linkedLabel, ENT_QUOTES, 'UTF-8'); ?>
                        <?php else: ?>
                          <span class="pending">Pendente de vínculo</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <div class="pendencia-student-actions">
                        <input
                          type="text"
                          class="input-sm pendencia-student-lookup"
                          list="pendencia-students-list"
                          placeholder="Aluno existente no banco"
                        />
                        <button
                          class="btn btn-ghost btn-sm js-pendencia-link-student"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Mesclar com existente
                        </button>
                        <button
                          class="btn btn-primary btn-sm js-pendencia-create-student"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Incluir aluno no banco
                        </button>
                      </div>
                    </td>
                    <td data-col="asaas-status">-</td>
                    <td data-col="paid-at"><?php echo $paidAt; ?></td>
                    <td data-col="action">
                      <?php if (!empty($pendencia['paid_at'])): ?>
                        -
                      <?php else: ?>
                        <button
                          class="btn btn-danger btn-sm js-delete-pendencia"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Cancelar
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
