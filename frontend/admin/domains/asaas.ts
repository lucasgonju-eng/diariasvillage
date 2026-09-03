import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsAsaas(runtime: AdminRuntime): void {
  runtime.setAsaasDataMessage = function setAsaasDataMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.asaasDataMessage)
          return;
      runtime.asaasDataMessage.textContent = text;
      runtime.asaasDataMessage.className = `charge-message ${isError ? 'error' : ''}`.trim();
  };
  
  runtime.formatDateTimeBR = function formatDateTimeBR(value: RuntimeValue) {
      if (!value)
          return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime()))
          return value;
      return date.toLocaleString('pt-BR');
  };
  
  runtime.renderAsaasGroupRows = function renderAsaasGroupRows(tbody: RuntimeValue, items: RuntimeValue) {
      if (!tbody)
          return;
      const list = Array.isArray(items) ? items : [];
      if (!list.length) {
          tbody.innerHTML = '<tr><td colspan="10">Nenhum registro.</td></tr>';
          return;
      }
      tbody.innerHTML = list
          .map((item: RuntimeValue) => {
          const safeInvoiceUrl = runtime.safeAsaasHttpsUrl(item.invoice_url);
          const link = safeInvoiceUrl
              ? `<a href="${runtime.escapeHtml(safeInvoiceUrl)}" target="_blank" rel="noopener noreferrer">Abrir</a>`
              : '-';
          const customer = [item.student_name, item.customer_name, item.customer_id].filter(Boolean).join(' • ') || '-';
          const fee = Number(item.fee_value || 0);
          const paidAt = item.paid_at || item.date || '-';
          const dueDate = item.due_date || item.date || '-';
          const billingType = item.billing_type || item.payment_id || '-';
          const value = Number(item.value || 0);
          const rowClass = value < 0 && !item.is_realizacao_inter_ci ? 'asaas-row-debit' : '';
          return `
        <tr class="${rowClass}">
          <td>${runtime.escapeHtml(item.id || '-')}</td>
          <td>${runtime.escapeHtml(item.status || '-')}</td>
          <td>${runtime.escapeHtml(customer)}</td>
          <td>${runtime.escapeHtml(item.description || '-')}</td>
          <td>${runtime.escapeHtml(runtime.formatDateBR(dueDate))}</td>
          <td>${runtime.escapeHtml(runtime.formatDateTimeBR(paidAt))}</td>
          <td>${runtime.escapeHtml(billingType)}</td>
          <td>${runtime.formatCurrency(item.value)}</td>
          <td>${runtime.formatCurrency(fee)}</td>
          <td>${link}</td>
        </tr>
        `;
      })
          .join('');
  };
  
  runtime.renderAsaasKpis = function renderAsaasKpis(analytics: RuntimeValue) {
      if (!runtime.asaasKpis)
          return;
      const k = analytics?.kpis || {};
      const entries = Number(k.entries_total || 0);
      const debitTotal = Number(k.debit_total || 0);
      const realization = Number(k.realization_total || 0);
      const fees = Number(k.fees_total || 0);
      const net = Number(k.net_total || 0);
      const balance = Number.isFinite(Number(k.balance_available)) ? Number(k.balance_available) : null;
      const paidCount = Number(k.paid_count || 0);
      const openCount = Number(k.open_count || 0);
      runtime.asaasKpis.innerHTML = `
      <div class="asaas-kpi-card"><div class="asaas-kpi-label">Entradas no período</div><div class="asaas-kpi-value">${runtime.formatCurrency(entries)}</div></div>
      <div class="asaas-kpi-card"><div class="asaas-kpi-label">Realização (Transferência p/ Inter CI)</div><div class="asaas-kpi-value">${runtime.formatCurrency(realization)}</div></div>
      <div class="asaas-kpi-card danger"><div class="asaas-kpi-label">Movimentos de débito</div><div class="asaas-kpi-value">${runtime.formatCurrency(debitTotal)}</div></div>
      <div class="asaas-kpi-card danger"><div class="asaas-kpi-label">Taxas no período</div><div class="asaas-kpi-value">${runtime.formatCurrency(fees)}</div></div>
      <div class="asaas-kpi-card"><div class="asaas-kpi-label">Líquido no período</div><div class="asaas-kpi-value">${runtime.formatCurrency(net)}</div></div>
      <div class="asaas-kpi-card"><div class="asaas-kpi-label">Saldo disponível</div><div class="asaas-kpi-value">${balance === null ? 'n/d' : runtime.formatCurrency(balance)}</div></div>
      <div class="asaas-kpi-card"><div class="asaas-kpi-label">Pagas x Em aberto</div><div class="asaas-kpi-value">${paidCount} / ${openCount}</div></div>
    `;
  };
  
  runtime.renderSimpleBars = function renderSimpleBars(container: RuntimeValue, rows: RuntimeValue, valueKey: RuntimeValue, labelKey: RuntimeValue, maxItems: RuntimeValue = 12, red: RuntimeValue = false) {
      if (!container)
          return;
      const list = Array.isArray(rows) ? rows.slice(-maxItems) : [];
      if (!list.length) {
          container.innerHTML = '<div class="muted">Sem dados para o período.</div>';
          return;
      }
      const max = Math.max(...list.map((r: RuntimeValue) => Math.abs(Number(r?.[valueKey] || 0))), 1);
      container.innerHTML = list
          .map((row: RuntimeValue) => {
          const value = Number(row?.[valueKey] || 0);
          const width = Math.max(2, Math.round((Math.abs(value) / max) * 100));
          const label = String(row?.[labelKey] || '-');
          const rowRed = red || Boolean(row?._red);
          return `
          <div class="asaas-bar-row">
            <div>${runtime.escapeHtml(label)}</div>
            <div class="asaas-bar-track"><div class="asaas-bar-fill ${rowRed ? 'red' : ''}" style="width:${width}%"></div></div>
            <div>${runtime.formatCurrency(value)}</div>
          </div>
        `;
      })
          .join('');
  };
  
  runtime.renderCompositionBars = function renderCompositionBars(analytics: RuntimeValue) {
      const k = analytics?.kpis || {};
      const rows = [
          { label: 'Entradas', value: Number(k.entries_total || 0), red: false },
          { label: 'Realização Inter CI', value: Number(k.realization_total || 0), red: true },
          { label: 'Débitos totais', value: Number(k.debit_total || 0), red: true },
          { label: 'Taxas', value: Number(k.fees_total || 0), red: true },
          { label: 'Líquido', value: Number(k.net_total || 0), red: Number(k.net_total || 0) < 0 },
      ];
      if (!runtime.asaasCompositionBars)
          return;
      const max = Math.max(...rows.map((r: RuntimeValue) => Math.abs(r.value)), 1);
      runtime.asaasCompositionBars.innerHTML = rows
          .map((r: RuntimeValue) => {
          const width = Math.max(4, Math.round((Math.abs(r.value) / max) * 100));
          return `
          <div class="asaas-bar-row">
            <div>${runtime.escapeHtml(r.label)}</div>
            <div class="asaas-bar-track"><div class="asaas-bar-fill ${r.red ? 'red' : ''}" style="width:${width}%"></div></div>
            <div>${runtime.formatCurrency(r.value)}</div>
          </div>
        `;
      })
          .join('');
  };
  
  runtime.renderRanking = function renderRanking(container: RuntimeValue, rows: RuntimeValue, valueKey: RuntimeValue, countKey: RuntimeValue, bad: RuntimeValue = false) {
      if (!container)
          return;
      const list = Array.isArray(rows) ? rows : [];
      if (!list.length) {
          container.innerHTML = '<div class="muted">Sem dados no período.</div>';
          return;
      }
      container.innerHTML = list
          .map((row: RuntimeValue, idx: RuntimeValue) => `
        <div class="asaas-ranking-item ${bad ? 'bad' : ''}">
          <div class="idx">${idx + 1}</div>
          <div class="name" title="${runtime.escapeHtml(row.customer || '-')}" >${runtime.escapeHtml(row.customer || '-')} (${Number(row[countKey] || 0)})</div>
          <div class="value">${runtime.formatCurrency(row[valueKey] || 0)}</div>
        </div>
      `)
          .join('');
  };
  
  runtime.renderAsaasSummary = function renderAsaasSummary(groups: RuntimeValue, generatedAt: RuntimeValue, warnings: RuntimeValue) {
      if (!runtime.asaasDataSummary)
          return;
      const extrato = groups?.__extrato || {};
      const creditos = groups?.creditos || {};
      const debitos = groups?.debitos || {};
      const taxas = groups?.taxas || {};
      const creditsTotal = Number(extrato.credits_total ?? creditos.total_value ?? 0);
      const debitsTotal = Number(extrato.debits_total ?? debitos.total_value ?? 0);
      const realizationTotal = Number(extrato.realization_total ?? 0);
      const netTotal = Number(extrato.net_total ?? 0);
      const feeTotal = Number(extrato.total_fee_value ?? 0);
      const balanceAvailable = Number.isFinite(Number(extrato.balance_available))
          ? Number(extrato.balance_available)
          : null;
      const warnCount = Array.isArray(warnings) ? warnings.length : 0;
      const balanceLabel = balanceAvailable === null
          ? 'Saldo disponível Asaas: n/d'
          : `Saldo disponível Asaas: ${runtime.formatCurrency(balanceAvailable)}`;
      runtime.asaasDataSummary.innerHTML = `
      <span class="cashflow-pill">Atualizado em: ${runtime.escapeHtml(runtime.formatDateTimeBR(generatedAt))}</span>
      <span class="cashflow-pill">Créditos extrato: ${runtime.formatCurrency(creditsTotal)}</span>
      <span class="cashflow-pill">Realizações Inter CI: ${runtime.formatCurrency(realizationTotal)}</span>
      <span class="cashflow-pill">Outros débitos: ${runtime.formatCurrency(debitsTotal)}</span>
      <span class="cashflow-pill">Taxas no período: ${runtime.formatCurrency(feeTotal)}</span>
      <span class="cashflow-pill">Líquido do período: ${runtime.formatCurrency(netTotal)}</span>
      <span class="cashflow-pill">${balanceLabel}</span>
      <span class="cashflow-pill">Itens de taxa/desconto: ${Number(taxas.count || 0)}</span>
      <span class="cashflow-pill">Avisos: ${warnCount}</span>
    `;
  };
  
  runtime.renderAsaasAnalytics = function renderAsaasAnalytics(analytics: RuntimeValue) {
      runtime.renderAsaasKpis(analytics);
      const daily = Array.isArray(analytics?.daily_series) ? analytics.daily_series : [];
      const dailyRows = daily.map((row: RuntimeValue) => ({
          date: runtime.formatDateBR(row.date),
          value: Number(row.credits || 0) + Number(row.debits || 0),
          _red: (Number(row.credits || 0) + Number(row.debits || 0)) < 0,
      }));
      runtime.renderSimpleBars(runtime.asaasDailyBars, dailyRows, 'value', 'date', 14, false);
      runtime.renderCompositionBars(analytics);
      runtime.renderRanking(runtime.asaasTopAdimplentes, analytics?.top_adimplentes || [], 'paid_total', 'paid_count', false);
      runtime.renderRanking(runtime.asaasTopInadimplentes, analytics?.top_inadimplentes || [], 'open_total', 'open_count', true);
  };
  
  runtime.clearAsaasAnalytics = function clearAsaasAnalytics() {
      if (runtime.asaasKpis)
          runtime.asaasKpis.innerHTML = '';
      if (runtime.asaasDailyBars)
          runtime.asaasDailyBars.innerHTML = '<div class="muted">Sem dados para o período.</div>';
      if (runtime.asaasCompositionBars)
          runtime.asaasCompositionBars.innerHTML = '<div class="muted">Sem dados para o período.</div>';
      if (runtime.asaasTopAdimplentes)
          runtime.asaasTopAdimplentes.innerHTML = '<div class="muted">Sem dados no período.</div>';
      if (runtime.asaasTopInadimplentes)
          runtime.asaasTopInadimplentes.innerHTML = '<div class="muted">Sem dados no período.</div>';
  };
  
  runtime.csvEscape = function csvEscape(value: RuntimeValue) {
      const text = String(value ?? '');
      if (text.includes('"') || text.includes(';') || text.includes('\n')) {
          return `"${text.replace(/"/g, '""')}"`;
      }
      return text;
  };
  
  runtime.asaasBuildExportRows = function asaasBuildExportRows(payload: RuntimeValue) {
      const rows = [];
      const now = new Date().toLocaleString('pt-BR');
      const period = payload?.period || {};
      const analytics = payload?.analytics || {};
      const kpis = analytics?.kpis || {};
      const groups = payload?.groups || {};
      const creditos = groups?.creditos?.items || [];
      const debitos = groups?.debitos?.items || [];
      const taxas = groups?.taxas?.items || [];
      const topAdimplentes = analytics?.top_adimplentes || [];
      const topInadimplentes = analytics?.top_inadimplentes || [];
      const dailySeries = analytics?.daily_series || [];
      rows.push(['Relatorio', 'Dashboard Asaas']);
      rows.push(['Gerado em', now]);
      rows.push(['Periodo de', period.from || '-']);
      rows.push(['Periodo ate', period.to || '-']);
      rows.push([]);
      rows.push(['KPIs', 'Valor']);
      rows.push(['Entradas no periodo', Number(kpis.entries_total || 0)]);
      rows.push(['Realizacao Transferencia Inter CI', Number(kpis.realization_total || 0)]);
      rows.push(['Movimentos de debito', Number(kpis.debit_total || 0)]);
      rows.push(['Taxas no periodo', Number(kpis.fees_total || 0)]);
      rows.push(['Liquido no periodo', Number(kpis.net_total || 0)]);
      rows.push(['Saldo disponivel', kpis.balance_available == null ? 'n/d' : Number(kpis.balance_available)]);
      rows.push(['Pagas', Number(kpis.paid_count || 0)]);
      rows.push(['Em aberto', Number(kpis.open_count || 0)]);
      rows.push([]);
      rows.push(['Evolucao diaria']);
      rows.push(['Data', 'Entradas', 'Saidas', 'Liquido']);
      dailySeries.forEach((item: RuntimeValue) => {
          rows.push([item.date || '-', Number(item.credits || 0), Number(item.debits || 0), Number(item.net || 0)]);
      });
      rows.push([]);
      rows.push(['Top adimplentes']);
      rows.push(['Posicao', 'Cliente', 'Qtde pagas', 'Total pago']);
      topAdimplentes.forEach((item: RuntimeValue, idx: RuntimeValue) => {
          rows.push([idx + 1, item.customer || '-', Number(item.paid_count || 0), Number(item.paid_total || 0)]);
      });
      rows.push([]);
      rows.push(['Top inadimplentes']);
      rows.push(['Posicao', 'Cliente', 'Qtde em aberto', 'Total em aberto']);
      topInadimplentes.forEach((item: RuntimeValue, idx: RuntimeValue) => {
          rows.push([idx + 1, item.customer || '-', Number(item.open_count || 0), Number(item.open_total || 0)]);
      });
      rows.push([]);
      const addSection = (title: RuntimeValue, list: RuntimeValue) => {
          rows.push([title]);
          rows.push(['ID', 'Status', 'Tipo', 'Descricao', 'Data', 'Valor', 'Taxa', 'Payment ID']);
          list.forEach((item: RuntimeValue) => {
              rows.push([
                  item.id || '-',
                  item.status || '-',
                  item.type || '-',
                  item.description || '-',
                  item.date || item.paid_at || '-',
                  Number(item.value || 0),
                  Number(item.fee_value || 0),
                  item.payment_id || item.external_reference || '-',
              ]);
          });
          rows.push([]);
      };
      addSection('Transacoes - Creditos', creditos);
      addSection('Transacoes - Realizacoes Inter CI', groups?.realizacoes?.items || []);
      addSection('Transacoes - Debitos', debitos);
      addSection('Transacoes - Taxas e descontos', taxas);
      return rows;
  };
  
  runtime.exportAsaasExcelCsv = function exportAsaasExcelCsv() {
      if (!runtime.asaasDataLastPayload) {
          runtime.setAsaasDataMessage('Carregue os dados do Asaas antes de exportar.', true);
          return;
      }
      const rows = runtime.asaasBuildExportRows(runtime.asaasDataLastPayload);
      const csvContent = rows
          .map((row: RuntimeValue) => row.map((cell: RuntimeValue) => runtime.csvEscape(cell)).join(';'))
          .join('\r\n');
      const bom = '\uFEFF';
      const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
      const period = runtime.asaasDataLastPayload?.period || {};
      const fileName = `dashboard-asaas-${period.from || 'inicio'}-a-${period.to || 'hoje'}.csv`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      runtime.setAsaasDataMessage('Exportação concluída. Abra o CSV no Excel.');
  };
  
  runtime.loadAsaasData = async function loadAsaasData(force: RuntimeValue = false) {
      if (!runtime.asaasPaidTbody || !runtime.asaasPendingTbody || !runtime.asaasOverdueTbody) {
          return;
      }
      if (runtime.asaasDataLoaded && !force) {
          return;
      }
      if (runtime.asaasDataRefreshButton)
          runtime.asaasDataRefreshButton.setAttribute('disabled', 'disabled');
      runtime.setAsaasDataMessage('Buscando dados diretamente do Asaas...');
      runtime.renderAsaasGroupRows(runtime.asaasPaidTbody, []);
      runtime.renderAsaasGroupRows(runtime.asaasPendingTbody, []);
      runtime.renderAsaasGroupRows(runtime.asaasOverdueTbody, []);
      runtime.clearAsaasAnalytics();
      try {
          const params = new URLSearchParams({ ts: String(Date.now()) });
          if (runtime.cashflowFromInput?.value)
              params.set('from', runtime.cashflowFromInput.value);
          if (runtime.cashflowToInput?.value)
              params.set('to', runtime.cashflowToInput.value);
          const res = await fetch(`/api/admin-asaas-data.php?${params.toString()}`);
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              const warningsText = Array.isArray(data?.warnings) ? ` ${data.warnings.join(' | ')}` : '';
              runtime.setAsaasDataMessage((data?.error || 'Falha ao carregar dados do Asaas.') + warningsText, true);
              runtime.clearAsaasAnalytics();
              return;
          }
          const groups = data.groups || {};
          const summaryGroups = { ...groups, __extrato: data.extrato || {} };
          runtime.renderAsaasGroupRows(runtime.asaasPaidTbody, groups?.creditos?.items || []);
          runtime.renderAsaasGroupRows(runtime.asaasPendingTbody, groups?.realizacoes?.items || []);
          runtime.renderAsaasGroupRows(runtime.asaasOverdueTbody, groups?.taxas?.items || []);
          runtime.renderAsaasSummary(summaryGroups, data.generated_at, data.warnings || []);
          runtime.renderAsaasAnalytics(data.analytics || null);
          runtime.asaasDataLastPayload = data;
          const warningsText = Array.isArray(data.warnings) && data.warnings.length
              ? ` (com avisos: ${data.warnings.join(' | ')})`
              : '';
          runtime.setAsaasDataMessage('Dados carregados diretamente do Asaas.' + warningsText);
          runtime.asaasDataLoaded = true;
      }
      catch {
          runtime.setAsaasDataMessage('Falha ao carregar dados do Asaas.', true);
          runtime.clearAsaasAnalytics();
          runtime.asaasDataLastPayload = null;
      }
      finally {
          if (runtime.asaasDataRefreshButton)
              runtime.asaasDataRefreshButton.removeAttribute('disabled');
      }
  };
  
  if (runtime.asaasDataRefreshButton) {
      runtime.asaasDataRefreshButton.addEventListener('click', () => {
          runtime.loadAsaasData(true);
      });
  }
  
  if (runtime.asaasDataExportButton) {
      runtime.asaasDataExportButton.addEventListener('click', () => {
          runtime.exportAsaasExcelCsv();
      });
  }
}
