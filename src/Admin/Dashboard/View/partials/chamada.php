<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('chamada', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$canAttendanceApprove = $context['canAttendanceApprove'];
?>
<section id="tab-chamada" class="<?php echo $activeTab === 'chamada' ? '' : 'hidden'; ?>">
        <h2>Chamada</h2>
        <p class="muted">Secretaria e admin montam a lista do dia e finalizam em lote no botão Fechar dia de chamada. Somente o admin autoriza e, após checagens, a cobrança emergencial vai para a fila.</p>
        <datalist id="attendance-students-list"></datalist>
        <datalist id="attendance-offices-list"></datalist>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>Data</label>
            <input id="attendance-date" type="date" value="<?php echo date('Y-m-d'); ?>" />
          </div>
          <div class="form-group">
            <label>Aluno</label>
            <input id="attendance-student" type="text" list="attendance-students-list" placeholder="Nome • Matrícula" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Oficina modular (opcional)</label>
            <input id="attendance-office" type="text" list="attendance-offices-list" placeholder="Autocomplete do mês corrente" autocomplete="off" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="attendance-add-btn" class="btn btn-primary btn-sm" type="button">Adicionar aluno ao dia</button>
            <button id="attendance-close-day-btn" class="btn btn-primary btn-sm" type="button">Fechar dia de chamada</button>
            <?php if ($canAttendanceApprove): ?>
              <button id="attendance-go-inadimplentes-btn" class="btn btn-warning-yellow btn-sm" type="button">Soltar a fila</button>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($canAttendanceApprove): ?>
          <p class="muted" style="margin:-4px 0 10px 0;">Após autorizar, use "Soltar a fila" e clique em "Enviar cobranças da fila".</p>
        <?php endif; ?>
        <h3 style="margin:8px 0;">Lista do dia (pré-fechamento)</h3>
        <div style="overflow-x:auto;margin-bottom:12px;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Data Day Use</th>
                <th>Aluno</th>
                <th>Oficina</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody id="attendance-day-list">
              <tr>
                <td colspan="4">Nenhum aluno adicionado para o fechamento do dia.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="attendance-message" class="charge-message"></div>
        <h3 style="margin:8px 0;">Histórico de chamadas</h3>
        <div class="charge-fields" style="margin-bottom:8px;">
          <div class="form-group">
            <label>De</label>
            <input id="attendance-filter-from" type="date" />
          </div>
          <div class="form-group">
            <label>Até</label>
            <input id="attendance-filter-to" type="date" />
          </div>
          <div class="form-group">
            <label>Autorização</label>
            <label class="attendance-pending-filter" for="attendance-pending-only">
              <input id="attendance-pending-only" type="checkbox" checked />
              <span>Mostrar só pendentes de autorização</span>
            </label>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="attendance-filter-btn" class="btn btn-ghost btn-sm" type="button">Filtrar</button>
            <button id="attendance-clear-btn" class="btn btn-ghost btn-sm" type="button">Limpar</button>
            <button id="attendance-export-btn" class="btn btn-primary btn-sm" type="button">Exportar Excel</button>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Data Day Use</th>
                <th>Aluno</th>
                <th>Oficina</th>
                <th>Tipo</th>
                <th>Desconto</th>
                <th>Status</th>
                <th>Lançado por</th>
                <th>Lançado em</th>
                <th>Revisão</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="attendance-tbody">
              <tr>
                <td colspan="10">Nenhuma chamada lançada.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <?php if (!$canAttendanceApprove): ?>
          <p class="muted" style="margin-top:8px;">A autorização final é feita pelo usuário admin.</p>
        <?php endif; ?>
      </section>
