import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsCashflow(runtime: AdminRuntime): void {
  runtime.setCashflowMessage = function setCashflowMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.cashflowMessage)
          return;
      runtime.cashflowMessage.textContent = text;
      runtime.cashflowMessage.className = `charge-message ${isError ? 'error' : ''}`.trim();
  };
  
  runtime.renderCashflowRows = function renderCashflowRows(items: RuntimeValue) {
      if (!runtime.cashflowTbody)
          return;
      if (!items.length) {
          runtime.cashflowTbody.innerHTML = '<tr><td colspan="8">Nenhum registro para os filtros selecionados.</td></tr>';
          return;
      }
      runtime.cashflowTbody.innerHTML = items
          .map((item: RuntimeValue) => {
          const canManualSettle = item?.source === 'payments' && item?.id && item?.can_manual_settle === true;
          const action = canManualSettle
              ? `<button class="btn btn-danger btn-sm js-cashflow-settle" type="button" data-id="${runtime.escapeHtml(item.id || '')}" data-student="${runtime.escapeHtml(item.student_name || '')}" data-date="${runtime.escapeHtml(item.date || '')}" data-amount="${runtime.escapeHtml(String(item.amount ?? ''))}">Dar baixa</button>`
              : '-';
          return `
        <tr data-payment-id="${runtime.escapeHtml(item.id || '')}">
          <td>${runtime.escapeHtml(item.student_name || '-')}</td>
          <td>${runtime.escapeHtml(runtime.formatDateBR(item.date))}</td>
          <td>${runtime.escapeHtml(item.day_use_type || '-')}</td>
          <td>${runtime.escapeHtml(item.enrollment || '-')}</td>
          <td>${runtime.formatCurrency(item.amount)}</td>
          <td>${runtime.escapeHtml(item.status || '-')}</td>
          <td>${runtime.escapeHtml(item.billing_type || '-')}</td>
          <td>${action}</td>
        </tr>
      `;
      })
          .join('');
  };
  
  runtime.renderCashflowFooterTotals = function renderCashflowFooterTotals(totals: RuntimeValue) {
      if (!runtime.cashflowTotalAmountCell || !runtime.cashflowTotalPaidCell || !runtime.cashflowTotalCountCell)
          return;
      const normalizedTotals = totals || { count: 0, amount: 0, paid_amount: 0 };
      runtime.cashflowTotalAmountCell.textContent = runtime.formatCurrency(normalizedTotals.amount || 0);
      runtime.cashflowTotalPaidCell.textContent = `Pago: ${runtime.formatCurrency(normalizedTotals.paid_amount || 0)}`;
      runtime.cashflowTotalCountCell.textContent = `${normalizedTotals.count || 0} registro(s)`;
  };
  
  runtime.renderCashflowSummary = function renderCashflowSummary(totals: RuntimeValue, period: RuntimeValue, monthlyAdjustment: RuntimeValue = null) {
      const normalizedTotals = totals || { count: 0, amount: 0, paid_amount: 0 };
      runtime.renderCashflowFooterTotals(normalizedTotals);
      if (!runtime.cashflowSummary)
          return;
      const paidByAccount = normalizedTotals.paid_by_account || {};
      const interManual = Number(paidByAccount.inter_pix_manual || 0);
      const boleto = Number(paidByAccount.boleto || 0);
      const asaasNet = Number(paidByAccount.asaas || 0);
      const asaasCredit = Number(paidByAccount.asaas_extrato_credit || 0);
      const asaasDebit = Number(paidByAccount.asaas_extrato_debit || 0);
      const asaasFees = Number(paidByAccount.asaas_extrato_fees || 0);
      const asaasBalanceAvailable = Number.isFinite(Number(paidByAccount.asaas_balance_available))
          ? Number(paidByAccount.asaas_balance_available)
          : null;
      const asaasBalanceLabel = asaasBalanceAvailable === null
          ? 'n/d'
          : runtime.formatCurrency(asaasBalanceAvailable);
      const monthlyCount = Number(monthlyAdjustment?.count || 0);
      const monthlyAmount = Number(monthlyAdjustment?.amount || 0);
      const monthlyLabel = monthlyCount > 0
          ? `<span class="cashflow-pill">Subtraído mensalistas: ${runtime.formatCurrency(monthlyAmount)} (${monthlyCount} registro(s) • Aluno mensalista)</span>`
          : '';
      runtime.cashflowSummary.innerHTML = `
      <span class="cashflow-pill">Período: ${runtime.escapeHtml(runtime.formatDateBR(period?.from))} até ${runtime.escapeHtml(runtime.formatDateBR(period?.to))}</span>
      <span class="cashflow-pill">Registros: ${Number(normalizedTotals.count || 0)}</span>
      <span class="cashflow-pill">Total geral: ${runtime.formatCurrency(normalizedTotals.amount || 0)}</span>
      <span class="cashflow-pill">Total pago: ${runtime.formatCurrency(normalizedTotals.paid_amount || 0)}</span>
      <span class="cashflow-pill">Conta Inter CI (PIX_MANUAL): ${runtime.formatCurrency(interManual)}</span>
      <span class="cashflow-pill">Boleto: ${runtime.formatCurrency(boleto)}</span>
      <span class="cashflow-pill">Asaas créditos extrato: ${runtime.formatCurrency(asaasCredit)}</span>
      <span class="cashflow-pill">Asaas débitos extrato: ${runtime.formatCurrency(asaasDebit)}</span>
      <span class="cashflow-pill">Asaas taxas (débito): ${runtime.formatCurrency(asaasFees)}</span>
      <span class="cashflow-pill">Asaas líquido período: ${runtime.formatCurrency(asaasNet)}</span>
      <span class="cashflow-pill">Asaas saldo disponível: ${asaasBalanceLabel}</span>
      ${monthlyLabel}
    `;
  };
  
  runtime.loadCashflow = async function loadCashflow() {
      if (!runtime.cashflowFromInput ||
          !runtime.cashflowToInput ||
          !runtime.cashflowSearchButton ||
          !runtime.cashflowTbody ||
          !runtime.cashflowStudentInput ||
          !runtime.cashflowEnrollmentInput ||
          !runtime.cashflowDayTypeInput ||
          !runtime.cashflowStatusInput ||
          !runtime.cashflowBillingTypeInput ||
          !runtime.cashflowMonthlyModeInput ||
          !runtime.cashflowExcludeStudentInput ||
          !runtime.cashflowExcludeTermInput) {
          return;
      }
      const params = new URLSearchParams({
          from: runtime.cashflowFromInput.value,
          to: runtime.cashflowToInput.value,
      });
      if (runtime.cashflowStudentInput.value.trim())
          params.set('student_name', runtime.cashflowStudentInput.value.trim());
      if (runtime.cashflowEnrollmentInput.value.trim())
          params.set('enrollment', runtime.cashflowEnrollmentInput.value.trim());
      if (runtime.cashflowDayTypeInput.value)
          params.set('day_use_type', runtime.cashflowDayTypeInput.value);
      if (runtime.cashflowStatusInput.value)
          params.set('status', runtime.cashflowStatusInput.value);
      if (runtime.cashflowBillingTypeInput.value)
          params.set('billing_type', runtime.cashflowBillingTypeInput.value);
      if (runtime.cashflowMonthlyModeInput.value)
          params.set('monthly_mode', runtime.cashflowMonthlyModeInput.value);
      if (runtime.cashflowExcludeStudentInput.value.trim())
          params.set('exclude_student', runtime.cashflowExcludeStudentInput.value.trim());
      if (runtime.cashflowExcludeTermInput.value.trim())
          params.set('exclude_term', runtime.cashflowExcludeTermInput.value.trim());
      runtime.cashflowSearchButton.setAttribute('disabled', 'disabled');
      runtime.setCashflowMessage('Carregando fluxo de caixa...');
      try {
          const res = await fetch(`/api/admin-cashflow.php?${params.toString()}`);
          const data = await res.json();
          if (!data.ok) {
              runtime.setCashflowMessage(data.error || 'Falha ao carregar fluxo de caixa.', true);
              runtime.renderCashflowRows([]);
              runtime.renderCashflowSummary({ count: 0, amount: 0, paid_amount: 0 }, {
                  from: runtime.cashflowFromInput?.value || '',
                  to: runtime.cashflowToInput?.value || '',
              }, null);
              return;
          }
          runtime.renderCashflowRows(data.items || []);
          runtime.renderCashflowSummary(data.totals || null, data.period || null, data.monthly_adjustment || null);
          runtime.setCashflowMessage('');
          runtime.cashflowLoaded = true;
      }
      catch {
          runtime.setCashflowMessage('Falha ao carregar fluxo de caixa.', true);
          runtime.renderCashflowRows([]);
          runtime.renderCashflowSummary({ count: 0, amount: 0, paid_amount: 0 }, {
              from: runtime.cashflowFromInput?.value || '',
              to: runtime.cashflowToInput?.value || '',
          }, null);
      }
      finally {
          runtime.cashflowSearchButton.removeAttribute('disabled');
      }
  };
  
  if (runtime.cashflowFromInput && runtime.cashflowToInput) {
      const todayIso = new Date().toISOString().slice(0, 10);
      if (!runtime.cashflowFromInput.value)
          runtime.cashflowFromInput.value = runtime.getCashflowDefaultFromDate();
      if (!runtime.cashflowToInput.value)
          runtime.cashflowToInput.value = todayIso;
      runtime.renderCashflowSummary({ count: 0, amount: 0, paid_amount: 0 }, { from: runtime.cashflowFromInput.value, to: runtime.cashflowToInput.value }, null);
  }
  
  if (runtime.cashflowSearchButton) {
      runtime.cashflowSearchButton.addEventListener('click', runtime.loadCashflow);
  }
  
  if (runtime.cashflowClearButton) {
      runtime.cashflowClearButton.addEventListener('click', () => {
          if (runtime.cashflowFromInput)
              runtime.cashflowFromInput.value = runtime.getCashflowDefaultFromDate();
          if (runtime.cashflowToInput)
              runtime.cashflowToInput.value = new Date().toISOString().slice(0, 10);
          if (runtime.cashflowStudentInput)
              runtime.cashflowStudentInput.value = '';
          if (runtime.cashflowEnrollmentInput)
              runtime.cashflowEnrollmentInput.value = '';
          if (runtime.cashflowDayTypeInput)
              runtime.cashflowDayTypeInput.value = '';
          if (runtime.cashflowStatusInput)
              runtime.cashflowStatusInput.value = '';
          if (runtime.cashflowBillingTypeInput)
              runtime.cashflowBillingTypeInput.value = '';
          if (runtime.cashflowMonthlyModeInput)
              runtime.cashflowMonthlyModeInput.value = 'subtract';
          if (runtime.cashflowExcludeStudentInput)
              runtime.cashflowExcludeStudentInput.value = '';
          if (runtime.cashflowExcludeTermInput)
              runtime.cashflowExcludeTermInput.value = '';
          runtime.loadCashflow();
      });
  }
  
  if (runtime.cashflowTbody) {
      runtime.cashflowTbody.addEventListener('click', async (event: RuntimeValue) => {
          const target = event.target;
          if (!(target instanceof HTMLElement))
              return;
          const button = target.closest('.js-cashflow-settle');
          if (!(button instanceof HTMLElement))
              return;
          const paymentId = button.getAttribute('data-id') || '';
          if (!paymentId)
              return;
          const student = button.getAttribute('data-student') || 'Aluno';
          const dateLabel = runtime.formatDateBR(button.getAttribute('data-date') || '');
          const amount = runtime.formatCurrency(Number(button.getAttribute('data-amount') || 0));
          const noteResult = await runtime.showManualSettlementInput({ student, date: dateLabel, amount });
          if (!noteResult?.ok)
              return;
          button.setAttribute('disabled', 'disabled');
          const originalText = button.textContent;
          button.textContent = 'Baixando...';
          runtime.setCashflowMessage('Registrando baixa manual...');
          try {
              const res = await fetch('/api/admin-settle-payment.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ payment_id: paymentId, note: noteResult.value }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.setCashflowMessage(data?.error || 'Falha ao registrar baixa manual.', true);
                  return;
              }
              runtime.setCashflowMessage(data?.message || 'Baixa manual registrada.');
              await runtime.loadCashflow();
          }
          catch {
              runtime.setCashflowMessage('Falha ao registrar baixa manual.', true);
          }
          finally {
              button.removeAttribute('disabled');
              button.textContent = originalText;
          }
      });
  }
}
