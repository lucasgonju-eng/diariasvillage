<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('fluxo-caixa', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-fluxo-caixa" class="<?php echo $activeTab === 'fluxo-caixa' ? '' : 'hidden'; ?>">
        <h2>Fluxo de Caixa</h2>
        <p class="muted">Visão operacional para conferência financeira com planilha offline.</p>

        <div class="cashflow-filters">
          <div class="form-group">
            <label>Data inicial</label>
            <input id="cashflow-from" type="date" />
          </div>
          <div class="form-group">
            <label>Data final</label>
            <input id="cashflow-to" type="date" />
          </div>
          <div class="form-group">
            <label>Aluno</label>
            <input id="cashflow-student" type="text" placeholder="Nome do aluno" />
          </div>
          <div class="form-group">
            <label>Matrícula</label>
            <input id="cashflow-enrollment" type="text" placeholder="Número de matrícula" />
          </div>
          <div class="form-group">
            <label>Tipo do day-use</label>
            <select id="cashflow-day-type">
              <option value="">Todos</option>
              <option value="planejada">Planejada</option>
              <option value="emergencial">Emergencial</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status pagamento</label>
            <select id="cashflow-status">
              <option value="">Todos</option>
              <option value="paid">Pago</option>
              <option value="pending">Pendente</option>
              <option value="overdue">Vencido</option>
              <option value="canceled">Cancelado</option>
              <option value="refunded">Estornado</option>
            </select>
          </div>
          <div class="form-group">
            <label>Forma pagamento</label>
            <select id="cashflow-billing-type">
              <option value="">Todas</option>
              <option value="PIX">PIX</option>
              <option value="PIX_MANUAL">PIX manual</option>
              <option value="DEBIT_CARD">Cartão débito</option>
            </select>
          </div>
          <div class="form-group">
            <label>Mensalistas</label>
            <select id="cashflow-monthly-mode">
              <option value="subtract">Subtrair mensalistas (Aluno mensalista)</option>
              <option value="show">Mostrar mensalistas</option>
            </select>
          </div>
          <div class="form-group">
            <label>Não mostrar aluno</label>
            <input id="cashflow-exclude-student" type="text" placeholder="Ex.: Maria + João (ou vírgula)" />
          </div>
          <div class="form-group">
            <label>Não mostrar termo</label>
            <input id="cashflow-exclude-term" type="text" placeholder="Ex.: pendência + pix manual" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="cashflow-search" class="btn btn-primary btn-sm" type="button">Buscar</button>
            <button id="cashflow-clear" class="btn btn-ghost btn-sm" type="button">Limpar</button>
          </div>
        </div>

        <div id="cashflow-message" class="charge-message"></div>
        <div id="cashflow-summary" class="cashflow-summary"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Data</th>
                <th>Tipo day-use</th>
                <th>Matrícula</th>
                <th>Valor pago</th>
                <th>Status pagamento</th>
                <th>Forma pagamento</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody id="cashflow-tbody">
              <tr>
                <td colspan="8">Clique em "Buscar" para carregar.</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:#F8FAFC;">
                <td colspan="4">Totais do filtro</td>
                <td id="cashflow-total-amount">R$ 0,00</td>
                <td id="cashflow-total-paid">Pago: R$ 0,00</td>
                <td id="cashflow-total-count">0 registro(s)</td>
                <td>-</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
