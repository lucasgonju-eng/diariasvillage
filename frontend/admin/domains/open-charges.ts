import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsOpenCharges(runtime: AdminRuntime): void {
  runtime.showSendPendingMessage = function showSendPendingMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.sendPendingMessage)
          return;
      runtime.sendPendingMessage.textContent = text;
      runtime.sendPendingMessage.className = `charge-message ${isError ? 'error' : 'success'}`;
  };
  
  runtime.updateInadimplentesSummary = function updateInadimplentesSummary() {
      if (!runtime.inadimplentesSummary)
          return;
      const rows = [...document.querySelectorAll('.inadimplente-row')]
          .filter((row: RuntimeValue) => row.style.display !== 'none');
      const totalCount = rows.length;
      const uniqueStudents = new Set(rows
          .map((row: RuntimeValue) => runtime.normalizeSearchText(row.getAttribute('data-student') || ''))
          .filter((name: RuntimeValue) => name !== ''));
      const totalDayUse = rows.reduce((sum: RuntimeValue, row: RuntimeValue) => {
          const directCount = Number(row.getAttribute('data-dayuse-count') || 0);
          if (directCount > 0)
              return sum + directCount;
          const datesRaw = String(row.getAttribute('data-dayuse-date') || '').trim();
          if (!datesRaw)
              return sum + 1;
          const matchedDates = datesRaw.match(/\b\d{2}\/\d{2}\/\d{2,4}\b/g);
          if (matchedDates && matchedDates.length)
              return sum + matchedDates.length;
          return sum + 1;
      }, 0);
      const totalAmount = rows.reduce((sum: RuntimeValue, row: RuntimeValue) => sum + Number(row.getAttribute('data-amount') || 0), 0);
      const missingAsaasCount = rows.reduce((sum: RuntimeValue, row: RuntimeValue) => sum + (row.getAttribute('data-has-asaas') === '1' ? 0 : 1), 0);
      runtime.inadimplentesSummary.textContent =
          `Cobranças em aberto: ${totalCount} • ` +
              `Alunos únicos em aberto: ${uniqueStudents.size} • ` +
              `Day use em aberto: ${totalDayUse} • ` +
              `Valor total: ${runtime.formatCurrency(totalAmount)} • ` +
              `Sem cobrança gerada no Asaas: ${missingAsaasCount}`;
  };
  
  runtime.buildInadimplentesStudentAutocomplete = function buildInadimplentesStudentAutocomplete() {
      if (!runtime.inadimplentesStudentFilterList)
          return;
      const names = new Set();
      document.querySelectorAll('.inadimplente-row').forEach((row: RuntimeValue) => {
          const name = String(row.getAttribute('data-student') || '').trim();
          if (name)
              names.add(name);
      });
      const sorted = [...names].sort((a: RuntimeValue, b: RuntimeValue) => a.localeCompare(b, 'pt-BR'));
      runtime.inadimplentesStudentFilterList.innerHTML = sorted
          .map((name: RuntimeValue) => `<option value="${runtime.escapeHtml(name)}"></option>`)
          .join('');
  };
  
  runtime.applyInadimplentesStudentFilter = function applyInadimplentesStudentFilter() {
      const query = runtime.normalizeSearchText(runtime.inadimplentesStudentFilterInput?.value || '');
      document.querySelectorAll('.inadimplente-row').forEach((row: RuntimeValue) => {
          const student = runtime.normalizeSearchText(row.getAttribute('data-student') || '');
          const visible = query === '' || student.includes(query);
          row.style.display = visible ? '' : 'none';
      });
      runtime.updateInadimplentesSummary();
  };
  
  if (runtime.selectAllPendingInput) {
      runtime.selectAllPendingInput.addEventListener('change', () => {
          const checked = !!runtime.selectAllPendingInput.checked;
          document.querySelectorAll('.pending-send-checkbox').forEach((checkbox: RuntimeValue) => {
              if (!(checkbox instanceof HTMLInputElement))
                  return;
              if (checkbox.disabled)
                  return;
              const row = checkbox.closest('.inadimplente-row');
              if (row instanceof HTMLElement && row.style.display === 'none')
                  return;
              checkbox.checked = checked;
          });
      });
  }
  
  if (runtime.sendSelectedPendingButton) {
      runtime.sendSelectedPendingButton.addEventListener('click', async () => {
          const queueCheckboxes = [...document.querySelectorAll('.pending-send-checkbox')]
              .filter((el: RuntimeValue) => el instanceof HTMLInputElement);
          const visibleQueueCheckboxes = queueCheckboxes.filter((checkbox: RuntimeValue) => {
              if (checkbox.disabled)
                  return false;
              const row = checkbox.closest('.inadimplente-row');
              return !row || row.style.display !== 'none';
          });
          const selectedRows = [...document.querySelectorAll('.pending-send-checkbox')]
              .filter((el: RuntimeValue) => el instanceof HTMLInputElement && el.checked)
              .map((el: RuntimeValue) => ({ id: el.value, row: el.closest('.inadimplente-row') }))
              .filter((item: RuntimeValue) => item.id);
          const blockedMonthly = selectedRows.filter((item: RuntimeValue) => item.row?.getAttribute('data-monthly') === '1');
          if (blockedMonthly.length) {
              runtime.showSendPendingMessage('Há aluno(s) mensalista(s) selecionado(s). Remova-os da seleção e concilie antes de enviar.', true);
              return;
          }
          const selected = selectedRows.map((item: RuntimeValue) => item.id);
          if (!selected.length) {
              if (!visibleQueueCheckboxes.length) {
                  runtime.showSendPendingMessage('Não há cobrança "na fila de envio" neste filtro. Este aluno já está em "Pendente no Asaas".', true);
              }
              else {
                  runtime.showSendPendingMessage('Selecione ao menos uma cobrança da fila de envio.', true);
              }
              return;
          }
          let batchDiscountAmount = null;
          if (runtime.adminCanApproveAttendance) {
              const discountResult = await runtime.showAdminDiscountInput();
              if (!discountResult?.ok) {
                  runtime.showSendPendingMessage('Envio cancelado antes de processar as cobranças.');
                  return;
              }
              const parsedDiscount = runtime.parseDiscountInput(discountResult?.value || '');
              if (!parsedDiscount.ok) {
                  runtime.showSendPendingMessage(parsedDiscount.error || 'Desconto inválido.', true);
                  return;
              }
              batchDiscountAmount = parsedDiscount.value;
          }
          runtime.sendSelectedPendingButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.sendSelectedPendingButton.textContent;
          runtime.sendSelectedPendingButton.textContent = 'Enviando em lotes...';
          runtime.showSendPendingMessage('');
          try {
              const batchSize = 10;
              const chunks = [];
              for (let i = 0; i < selected.length; i += batchSize) {
                  chunks.push(selected.slice(i, i + batchSize));
              }
              const successIds = [];
              const failures = [];
              const warnings = [];
              let processed = 0;
              for (const chunk of chunks) {
                  processed += chunk.length;
                  runtime.sendSelectedPendingButton.textContent = `Enviando (${processed}/${selected.length})...`;
                  let res;
                  let data;
                  try {
                      res = await fetch('/api/admin-send-pending-charges-v2.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify({
                              payment_ids: chunk,
                              ...(typeof batchDiscountAmount === 'number' && batchDiscountAmount > 0
                                  ? { discount_amount: batchDiscountAmount }
                                  : {}),
                          }),
                      });
                      try {
                          data = await res.json();
                      }
                      catch {
                          data = null;
                      }
                  }
                  catch {
                      chunk.forEach((id: RuntimeValue) => {
                          failures.push({ id, ok: false, error: 'Falha de rede ao enviar lote.' });
                      });
                      continue;
                  }
                  const results = Array.isArray(data?.results) ? data.results : [];
                  const chunkSuccessIds = results
                      .filter((row: RuntimeValue) => row && row.ok && row.id)
                      .map((row: RuntimeValue) => String(row.id));
                  const chunkFailures = results.filter((row: RuntimeValue) => row && !row.ok);
                  const chunkWarnings = results
                      .filter((row: RuntimeValue) => row && row.ok && row.warning)
                      .map((row: RuntimeValue) => String(row.warning).trim())
                      .filter(Boolean);
                  successIds.push(...chunkSuccessIds);
                  failures.push(...chunkFailures);
                  warnings.push(...chunkWarnings);
                  if (!res.ok && chunkSuccessIds.length === 0) {
                      const batchError = data?.error || 'Falha ao enviar lote de cobranças.';
                      chunk.forEach((id: RuntimeValue) => {
                          if (!chunkFailures.some((row: RuntimeValue) => String(row?.id || '') === String(id))) {
                              failures.push({ id, ok: false, error: batchError });
                          }
                      });
                  }
              }
              successIds.forEach((paymentId: RuntimeValue) => {
                  const row = document.querySelector(`.inadimplente-row[data-payment-id="${paymentId}"]`);
                  if (!row)
                      return;
                  const firstCell = row.querySelector('td');
                  if (firstCell) {
                      firstCell.textContent = '-';
                  }
                  const statusCell = row.children?.[6];
                  if (statusCell) {
                      statusCell.textContent = 'Pendente no Asaas';
                  }
                  row.setAttribute('data-has-asaas', '1');
              });
              runtime.updateInadimplentesSummary();
              if (runtime.selectAllPendingInput) {
                  runtime.selectAllPendingInput.checked = false;
              }
              if (successIds.length && failures.length) {
                  const firstError = failures[0]?.error || 'Erro em parte dos envios.';
                  runtime.showSendPendingMessage(`${successIds.length} cobrança(s) enviada(s). ${failures.length} com erro: ${firstError}`, true);
                  return;
              }
              if (successIds.length) {
                  const warningSuffix = warnings.length
                      ? ` Avisos de e-mail: ${warnings.length}.`
                      : '';
                  const discountSuffix = typeof batchDiscountAmount === 'number' && batchDiscountAmount > 0
                      ? ` Desconto aplicado: ${runtime.formatCurrency(batchDiscountAmount)} por cobrança.`
                      : '';
                  runtime.showSendPendingMessage(`Cobranças da fila enviadas com sucesso. Tabela atualizada sem recarregar a página.${discountSuffix}${warningSuffix}`);
                  return;
              }
              runtime.showSendPendingMessage('Falha ao enviar cobranças da fila.', true);
          }
          catch {
              runtime.showSendPendingMessage('Falha ao enviar cobranças da fila.', true);
          }
          finally {
              runtime.sendSelectedPendingButton.removeAttribute('disabled');
              runtime.sendSelectedPendingButton.textContent = originalText;
          }
      });
  }
  
  runtime.normalizeDuplicateKey = function normalizeDuplicateKey(value: RuntimeValue) {
      return String(value || '').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z0-9]+/g, '');
  };
  
  runtime.detectInadimplentesDuplicates = function detectInadimplentesDuplicates() {
      const rows = [...document.querySelectorAll('.inadimplente-row')];
      const groups = new Map();
      rows.forEach((row: RuntimeValue) => {
          const student = runtime.normalizeDuplicateKey(row.getAttribute('data-student') || '');
          const dayUseDates = runtime.normalizeDuplicateKey(row.getAttribute('data-dayuse-date') || '');
          if (!student || !dayUseDates)
              return;
          const key = `${student}|${dayUseDates}`;
          if (!groups.has(key))
              groups.set(key, []);
          groups.get(key).push(row);
      });
      const duplicates: RuntimeValue[] = [];
      groups.forEach((groupRows: RuntimeValue) => {
          if (groupRows.length <= 1)
              return;
          const first = groupRows[0];
          const studentName = first.getAttribute('data-student') || '-';
          const dayUseDates = first.getAttribute('data-dayuse-date') || '-';
          duplicates.push({
              student_name: studentName,
              day_use_dates: dayUseDates,
              count: groupRows.length,
              rows: groupRows,
          });
      });
      return duplicates;
  };
  
  runtime.highlightInadimplentesDuplicates = function highlightInadimplentesDuplicates(duplicates: RuntimeValue) {
      document.querySelectorAll('.inadimplente-row').forEach((row: RuntimeValue) => {
          row.style.background = '';
      });
      duplicates.forEach((dup: RuntimeValue) => {
          dup.rows.forEach((row: RuntimeValue) => {
              row.style.background = '#FEF2F2';
          });
      });
  };
  
  runtime.maybeAlertInadimplentesDuplicates = function maybeAlertInadimplentesDuplicates(force: RuntimeValue = false) {
      if (runtime.inadimplentesDuplicatesPopupShown && !force)
          return;
      const duplicates = runtime.detectInadimplentesDuplicates();
      if (!duplicates.length)
          return;
      runtime.inadimplentesDuplicatesPopupShown = true;
      runtime.highlightInadimplentesDuplicates(duplicates);
      const lines = [
          'ATENÇÃO: existem cobranças em duplicidade na aba Cobranças em aberto.',
          'Verifique os casos abaixo e exclua uma das cobranças duplicadas.',
          '',
      ];
      duplicates.slice(0, 12).forEach((dup: RuntimeValue) => {
          lines.push(`- ${dup.student_name} | Datas: ${dup.day_use_dates} | Duplicadas: ${dup.count}`);
      });
      if (duplicates.length > 12) {
          lines.push(`... e mais ${duplicates.length - 12} grupo(s) duplicado(s).`);
      }
      lines.push('');
      lines.push('Use o botão Excluir e selecione o motivo: COBRANÇA EM DUPLICIDADE.');
      runtime.showAdminAlert(lines.join('\n'), { title: 'Cobranças em duplicidade' });
  };
  
  runtime.maybeAlertInadimplentesMonthly = function maybeAlertInadimplentesMonthly(force: RuntimeValue = false) {
      if (runtime.inadimplentesMonthlyPopupShown && !force)
          return;
      const rows = [...document.querySelectorAll('.inadimplente-row[data-monthly="1"]')];
      if (!rows.length)
          return;
      runtime.inadimplentesMonthlyPopupShown = true;
      const lines = ['Alunos mensalistas encontrados em Cobranças em aberto:'];
      rows.slice(0, 12).forEach((row: RuntimeValue) => {
          const student = row.getAttribute('data-student') || 'Aluno';
          const dates = row.getAttribute('data-dayuse-date') || '-';
          const days = row.getAttribute('data-monthly-days') || '?';
          lines.push(`- ${student} (${days} dias/semana) | Day-use: ${dates}`);
      });
      if (rows.length > 12) {
          lines.push(`... e mais ${rows.length - 12} registro(s).`);
      }
      lines.push('');
      lines.push('Aluno mensalista. Checar antes de enviar cobrança.');
      runtime.showAdminAlert(lines.join('\n'), { title: 'Atenção: alunos mensalistas' });
  };
  
  runtime.pendingDeleteButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const paymentId = button.dataset.id;
          const row = button.closest('tr');
          if (!paymentId || !row)
              return;
          const student = row.getAttribute('data-student') || 'Aluno';
          const dayUseDates = row.getAttribute('data-dayuse-date') || '-';
          const amountRaw = Number(row.getAttribute('data-amount') || 0);
          const amount = runtime.formatCurrency(amountRaw);
          const chooseReason = await runtime.showAdminConfirm(`Cancelar cobrança?\n\nAluno: ${student}\nDatas do day-use: ${dayUseDates}\nValor: ${amount}\n\nMotivo: COBRANÇA EM DUPLICIDADE`, { title: 'Cancelar cobrança' });
          if (!chooseReason)
              return;
          const confirmDelete = await runtime.showAdminConfirm('Confirmar cancelamento desta cobrança em duplicidade no Asaas?', { title: 'Confirmação final', confirmText: 'Cancelar cobrança' });
          if (!confirmDelete)
              return;
          button.setAttribute('disabled', 'disabled');
          runtime.showSendPendingMessage('Cancelando cobrança...', false);
          try {
              const res = await fetch('/api/admin-delete-payment.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ id: paymentId, reason: 'COBRANCA_EM_DUPLICIDADE' }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.showSendPendingMessage(data?.error || 'Falha ao cancelar cobrança.', true);
                  return;
              }
              row.remove();
              runtime.buildInadimplentesStudentAutocomplete();
              runtime.updateInadimplentesSummary();
              runtime.showSendPendingMessage('Cobrança cancelada e preservada no histórico.');
              runtime.maybeAlertInadimplentesDuplicates(true);
          }
          catch {
              runtime.showSendPendingMessage('Falha ao cancelar cobrança.', true);
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.isabelVoucherButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const paymentId = button.dataset.id;
          const row = button.closest('tr');
          if (!paymentId || !row)
              return;
          const student = row.getAttribute('data-student') || 'Isabel Gonçalves Rauen';
          const dayUseDates = row.getAttribute('data-dayuse-date') || '-';
          const amountRaw = Number(row.getAttribute('data-amount') || 0);
          const amount = runtime.formatCurrency(amountRaw);
          const confirmed = await runtime.showAdminConfirm(`Liquidar esta cobrança como voucher sem custo?\n\nAluno: ${student}\nDatas do day-use: ${dayUseDates}\nValor atual: ${amount}\n\nA cobrança será zerada, marcada como paga e identificada como Voucher X/30.`, { title: 'Liquidar voucher', confirmText: 'Liquidar voucher' });
          if (!confirmed)
              return;
          button.setAttribute('disabled', 'disabled');
          const originalText = button.textContent;
          button.textContent = 'Liquidando...';
          runtime.showSendPendingMessage('Liquidando voucher...', false);
          try {
              const res = await fetch('/api/admin-voucher-isabel.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ payment_id: paymentId }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.showSendPendingMessage(data?.error || 'Falha ao liquidar voucher.', true);
                  return;
              }
              row.remove();
              runtime.buildInadimplentesStudentAutocomplete();
              runtime.updateInadimplentesSummary();
              const warning = data?.asaas_warning ? ` Atenção: ${data.asaas_warning}` : '';
              runtime.showSendPendingMessage(`${data?.message || 'Voucher liquidado.'}${warning}`, Boolean(data?.asaas_warning));
              runtime.maybeAlertInadimplentesDuplicates(true);
          }
          catch {
              runtime.showSendPendingMessage('Falha ao liquidar voucher.', true);
          }
          finally {
              button.removeAttribute('disabled');
              button.textContent = originalText;
          }
      });
  });
  
  runtime.resendFebChargeButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const paymentId = button.dataset.id;
          const row = button.closest('tr');
          if (!paymentId || !row)
              return;
          const student = row.getAttribute('data-student') || 'Aluno';
          const dayUseDates = row.getAttribute('data-dayuse-date') || '-';
          const amountRaw = Number(row.getAttribute('data-amount') || 0);
          const amount = runtime.formatCurrency(amountRaw);
          const statusCell = row.children?.[6];
          const currentStatus = (statusCell?.textContent || '').trim() || 'Pendente no Asaas';
          const confirmed = await runtime.showAdminConfirm(`Reenviar cobrança de fevereiro para o responsável?\n\nAluno: ${student}\nDatas do day-use: ${dayUseDates}\nValor: ${amount}\nStatus atual: ${currentStatus}\n\nSe estiver vencida no Asaas, uma nova cobrança será criada automaticamente.`, { title: 'Reenviar cobrança de fevereiro', confirmText: 'Reenviar' });
          if (!confirmed)
              return;
          button.setAttribute('disabled', 'disabled');
          const originalText = button.textContent;
          button.textContent = 'Reenviando...';
          runtime.showSendPendingMessage('Reenviando cobrança de fevereiro...', false);
          try {
              const res = await fetch('/api/admin-resend-feb-charge.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ payment_id: paymentId }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.showSendPendingMessage(data?.error || 'Falha ao reenviar cobrança de fevereiro.', true);
                  return;
              }
              row.setAttribute('data-has-asaas', '1');
              if (statusCell) {
                  statusCell.textContent = data?.created_new_charge
                      ? 'Pendente no Asaas (nova cobrança criada)'
                      : 'Pendente no Asaas (cobrança reenviada)';
              }
              runtime.updateInadimplentesSummary();
              const emailSent = data?.email_sent !== false;
              const asaasPaymentId = String(data?.asaas_payment_id || '').trim();
              const invoiceUrl = String(data?.invoice_url || '').trim();
              const successMessage = data?.created_new_charge
                  ? 'Nova cobrança criada no Asaas e reenviada para o responsável.'
                  : 'Cobrança de fevereiro reenviada para o responsável.';
              if (emailSent) {
                  runtime.showSendPendingMessage(successMessage);
              }
              else {
                  runtime.showSendPendingMessage(`${successMessage} Atenção: houve falha no envio do e-mail ao responsável.`, true);
              }
              const details = [
                  `Aluno: ${student}`,
                  `Cobrança no Asaas: ${data?.created_new_charge ? 'Nova criada' : 'Cobrança existente reutilizada'}`,
                  `E-mail para o responsável: ${emailSent ? 'Enviado com sucesso' : 'Falhou no envio'}`,
              ];
              if (asaasPaymentId) {
                  details.push(`ID Asaas: ${asaasPaymentId}`);
              }
              if (invoiceUrl) {
                  details.push(`Link de pagamento: ${invoiceUrl}`);
              }
              await runtime.showAdminAlert(details.join('\n'), { title: 'Resultado do reenvio' });
          }
          catch {
              runtime.showSendPendingMessage('Falha ao reenviar cobrança de fevereiro.', true);
          }
          finally {
              button.removeAttribute('disabled');
              button.textContent = originalText;
          }
      });
  });
  
  if (runtime.syncRecebidasButton) {
      runtime.syncRecebidasButton.addEventListener('click', async () => {
          runtime.syncRecebidasButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.syncRecebidasButton.textContent;
          runtime.syncRecebidasButton.textContent = 'Atualizando...';
          if (runtime.syncRecebidasMessage) {
              runtime.syncRecebidasMessage.textContent = 'Buscando cobranças RECEIVED/CONFIRMED no Asaas...';
              runtime.syncRecebidasMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-sync-recebidas.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({}),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  if (runtime.syncRecebidasMessage) {
                      runtime.syncRecebidasMessage.textContent = data?.error || 'Falha ao atualizar cobrancas recebidas.';
                      runtime.syncRecebidasMessage.className = 'charge-message error';
                  }
                  return;
              }
              const summary = data.summary || {};
              if (runtime.syncRecebidasMessage) {
                  runtime.syncRecebidasMessage.textContent = `Atualização concluída. Locais promovidos para pago: ${summary.payments_promoted_paid || 0}. Pendências locais movidas para pagas: ${summary.pendencias_promoted_paid || 0}. Asaas varrido: ${summary.asaas_scanned_total || 0}. Pagos encontrados: ${summary.asaas_paid_found || 0}. Importados em payments: ${summary.asaas_paid_imported_payments || 0}. Importados em recebidas (pendências pagas): ${summary.asaas_paid_imported_pendencias || 0}. Não mapeados: ${summary.asaas_paid_unmapped || 0}. Recarregue a página quando quiser refletir tudo na tabela.`;
                  runtime.syncRecebidasMessage.className = 'charge-message success';
              }
          }
          catch {
              if (runtime.syncRecebidasMessage) {
                  runtime.syncRecebidasMessage.textContent = 'Falha ao atualizar cobrancas recebidas.';
                  runtime.syncRecebidasMessage.className = 'charge-message error';
              }
          }
          finally {
              runtime.syncRecebidasButton.removeAttribute('disabled');
              runtime.syncRecebidasButton.textContent = originalText;
          }
      });
  }
}
