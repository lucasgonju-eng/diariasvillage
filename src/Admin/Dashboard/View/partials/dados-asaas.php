<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('dados-asaas', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-dados-asaas" class="<?php echo $activeTab === 'dados-asaas' ? '' : 'hidden'; ?>">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <h2 style="margin:0;">Dados do Asaas</h2>
          <button id="asaas-data-refresh" class="btn btn-primary btn-sm" type="button">Atualizar direto do Asaas</button>
          <button id="asaas-data-export" class="btn btn-ghost btn-sm" type="button">Exportar Excel</button>
        </div>
        <p class="muted">Aba separada de conferência direta no Asaas (não altera dados locais).</p>
        <div id="asaas-data-message" class="charge-message"></div>
        <div id="asaas-data-summary" class="cashflow-summary"></div>
        <div id="asaas-kpis" class="asaas-kpi-grid"></div>
        <div class="asaas-analytics-grid">
          <div class="asaas-chart-card">
            <div class="asaas-chart-title">Evolução diária (entradas x saídas)</div>
            <div id="asaas-daily-bars" class="asaas-bars"></div>
          </div>
          <div class="asaas-chart-card">
            <div class="asaas-chart-title">Composição do período</div>
            <div id="asaas-composition-bars" class="asaas-bars"></div>
          </div>
        </div>
        <div class="asaas-ranking-grid">
          <div class="asaas-ranking-card">
            <div class="asaas-chart-title">Top 10 adimplentes</div>
            <div id="asaas-top-adimplentes" class="asaas-ranking-list"></div>
          </div>
          <div class="asaas-ranking-card">
            <div class="asaas-chart-title">Top 10 inadimplentes</div>
            <div id="asaas-top-inadimplentes" class="asaas-ranking-list"></div>
          </div>
        </div>

        <h3 style="margin-top:12px;">Créditos do extrato</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Taxa Asaas</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-paid-tbody">
              <tr><td colspan="10">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>

        <h3 style="margin-top:18px;">Realizações / transferências Inter CI</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Taxa Asaas</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-pending-tbody">
              <tr><td colspan="10">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>

        <h3 style="margin-top:18px;">Taxas e descontos</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>ID Asaas</th>
                <th>Status</th>
                <th>Cliente</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th>Pago em</th>
                <th>Forma</th>
                <th>Valor</th>
                <th>Taxa Asaas</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody id="asaas-overdue-tbody">
              <tr><td colspan="10">Clique em "Atualizar direto do Asaas".</td></tr>
            </tbody>
          </table>
        </div>
      </section>
