import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsPendingSync(runtime: AdminRuntime): void {
  runtime.normalizeCpf = function normalizeCpf(value: RuntimeValue) {
      return value.replace(/\D/g, '').slice(0, 11);
  };
  
  runtime.findPendenciaRow = function findPendenciaRow(id: RuntimeValue) {
      if (!id)
          return null;
      return document.querySelector(`[data-pendencia-id="${id}"]`);
  };
  
  runtime.ensurePendenciasEmptyState = function ensurePendenciasEmptyState() {
      const tbody = document.querySelector('#tab-pendencias tbody');
      if (!tbody)
          return;
      const rows = tbody.querySelectorAll('tr[data-pendencia-id]');
      if (rows.length > 0)
          return;
      tbody.innerHTML = '<tr><td colspan="11">Nenhuma pendência registrada.</td></tr>';
  };
  
  runtime.removePendenciaRowsByIds = function removePendenciaRowsByIds(ids: RuntimeValue) {
      const uniqueIds = Array.from(new Set((Array.isArray(ids) ? ids : []).map((id: RuntimeValue) => String(id || '').trim()).filter(Boolean)));
      let removed = 0;
      uniqueIds.forEach((id: RuntimeValue) => {
          const row = runtime.findPendenciaRow(id);
          if (row) {
              row.remove();
              removed += 1;
          }
      });
      if (removed > 0) {
          runtime.ensurePendenciasEmptyState();
      }
      return removed;
  };
  
  runtime.updatePendenciaRow = function updatePendenciaRow(row: RuntimeValue, data: RuntimeValue) {
      if (!row)
          return;
      const paidCell = row.querySelector('[data-col="paid-at"]');
      const statusCell = row.querySelector('[data-col="asaas-status"]');
      const actionCell = row.querySelector('[data-col="action"]');
      if (statusCell)
          statusCell.textContent = data.status || '-';
      if (data.paid_at && paidCell) {
          const date = new Date(data.paid_at);
          paidCell.textContent = isNaN(date.getTime())
              ? data.paid_at
              : date.toLocaleString('pt-BR');
          if (actionCell)
              actionCell.textContent = '-';
      }
  };
  
  runtime.findStudentFromLookup = function findStudentFromLookup(lookupValue: RuntimeValue) {
      const value = String(lookupValue || '').trim();
      if (!value) {
          return { student: null, error: 'Informe o aluno existente para fazer a mesclagem.' };
      }
      const byLabel = runtime.studentLookupByLabel.get(value);
      if (byLabel && byLabel.id) {
          return { student: byLabel, error: '' };
      }
      const byName = runtime.adminStudents.filter((student: RuntimeValue) => String(student.name || '').trim().toLowerCase() === value.toLowerCase());
      if (byName.length === 1 && byName[0]?.id) {
          return { student: byName[0], error: '' };
      }
      if (byName.length > 1) {
          return {
              student: null,
              error: 'Mais de um aluno com esse nome. Selecione pela lista sugerida com série/turma/matrícula.',
          };
      }
      return { student: null, error: 'Aluno não encontrado no banco para mesclagem.' };
  };
  
  runtime.renderPendenciaStudentLink = function renderPendenciaStudentLink(row: RuntimeValue, student: RuntimeValue) {
      if (!row || !student)
          return;
      const studentId = String(student.id || '').trim();
      const studentName = String(student.name || '').trim();
      const enrollment = String(student.enrollment || '').trim();
      if (studentId) {
          row.dataset.studentId = studentId;
      }
      const studentNameCell = row.querySelector('[data-col="student-name"]');
      if (studentNameCell && studentName) {
          studentNameCell.textContent = studentName;
      }
      const studentLinkCell = row.querySelector('[data-col="student-link"]');
      if (studentLinkCell) {
          const label = studentId
              ? `Vinculado${enrollment ? ` • Matrícula ${enrollment}` : ''}`
              : 'Pendente de vínculo';
          studentLinkCell.innerHTML = `<div class="pendencia-student-link">${runtime.escapeHtml(label)}</div>`;
      }
  };
  
  runtime.pendenciaButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const pendenciaId = button.dataset.id;
          if (!pendenciaId)
              return;
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Checando pagamento...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-check-pendencia.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ id: pendenciaId }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
              }
              else {
                  const row = button.closest('tr');
                  const paidCell = row ? row.querySelector('[data-col="paid-at"]') : null;
                  const statusCell = row ? row.querySelector('[data-col="asaas-status"]') : null;
                  const actionCell = row ? row.querySelector('[data-col="action"]') : null;
                  if (statusCell) {
                      statusCell.textContent = data.status || '-';
                  }
                  if (data.paid_at && paidCell) {
                      const date = new Date(data.paid_at);
                      paidCell.textContent = isNaN(date.getTime())
                          ? data.paid_at
                          : date.toLocaleString('pt-BR');
                      if (actionCell) {
                          actionCell.textContent = '-';
                      }
                      if (runtime.pendenciaMessage) {
                          runtime.pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
                          runtime.pendenciaMessage.className = 'charge-message success';
                      }
                  }
                  else if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent =
                          data.status === 'NOT_FOUND'
                              ? 'Pagamento não encontrado no Asaas.'
                              : 'Pagamento ainda não identificado pelo Asaas.';
                      runtime.pendenciaMessage.className = 'charge-message';
                  }
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao checar pendência.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  if (runtime.pendenciaCpfButton && runtime.pendenciaCpfInput) {
      runtime.pendenciaCpfInput.addEventListener('input', (event: RuntimeValue) => {
          event.target.value = runtime.normalizeCpf(event.target.value);
      });
      runtime.pendenciaCpfButton.addEventListener('click', async () => {
          const cpf = runtime.normalizeCpf(runtime.pendenciaCpfInput.value || '');
          if (cpf.length !== 11) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'CPF inválido.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          runtime.pendenciaCpfButton.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Checando pagamento...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-check-pendencia-by-cpf.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ cpf }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const row = runtime.findPendenciaRow(data.pendencia_id);
              runtime.updatePendenciaRow(row, data);
              if (data.paid_at) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
                      runtime.pendenciaMessage.className = 'charge-message success';
                  }
                  return;
              }
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent =
                      data.status === 'NOT_FOUND'
                          ? 'Pagamento não encontrado no Asaas.'
                          : 'Pagamento ainda não identificado pelo Asaas.';
                  runtime.pendenciaMessage.className = 'charge-message';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao checar pendência.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              runtime.pendenciaCpfButton.removeAttribute('disabled');
          }
      });
  }
  
  if (runtime.pendenciaAsaasButton && runtime.pendenciaAsaasInput) {
      runtime.pendenciaAsaasInput.addEventListener('input', (event: RuntimeValue) => {
          event.target.value = event.target.value.trim().slice(0, 120);
      });
      runtime.pendenciaAsaasButton.addEventListener('click', async () => {
          const asaasId = (runtime.pendenciaAsaasInput.value || '').trim();
          if (!asaasId) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Informe o número da cobrança Asaas.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          runtime.pendenciaAsaasButton.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Checando pagamento...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-check-pendencia-by-asaas.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ asaas_id: asaasId }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const row = runtime.findPendenciaRow(data.pendencia_id);
              runtime.updatePendenciaRow(row, data);
              if (data.paid_at) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
                      runtime.pendenciaMessage.className = 'charge-message success';
                  }
                  return;
              }
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent =
                      data.status === 'NOT_FOUND'
                          ? 'Pagamento não encontrado no Asaas.'
                          : 'Pagamento ainda não identificado pelo Asaas.';
                  runtime.pendenciaMessage.className = 'charge-message';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao checar pendência.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              runtime.pendenciaAsaasButton.removeAttribute('disabled');
          }
      });
  }
  
  runtime.pendenciaLinkButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          const row = button.closest('tr');
          const pendenciaId = row ? row.dataset.pendenciaId : null;
          const input = row ? row.querySelector('[data-col="asaas-link"] input') : null;
          const asaasId = input ? input.value.trim() : '';
          if (!pendenciaId || !asaasId) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Informe a cobrança Asaas para vincular.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Vinculando cobrança...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-link-pendencia-by-asaas.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ pendencia_id: pendenciaId, asaas_id: asaasId }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data.error || 'Falha ao vincular pendência.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              runtime.updatePendenciaRow(row, data);
              if (data.paid_at) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = 'Cobrança vinculada e paga.';
                      runtime.pendenciaMessage.className = 'charge-message success';
                  }
                  return;
              }
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Cobrança vinculada. Aguardando pagamento.';
                  runtime.pendenciaMessage.className = 'charge-message';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao vincular pendência.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.pendenciaSettleButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          const pendenciaId = button.dataset.id;
          if (!pendenciaId)
              return;
          const confirmSettle = await runtime.showAdminConfirm('Confirmar baixa manual desta pendência?', { title: 'Baixa manual', confirmText: 'Confirmar baixa' });
          if (!confirmSettle)
              return;
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Dando baixa...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-settle-pendencia.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ id: pendenciaId }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data.error || 'Falha ao dar baixa.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const row = runtime.findPendenciaRow(pendenciaId);
              runtime.updatePendenciaRow(row, { paid_at: data.paid_at, status: 'BAIXA_MANUAL' });
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Baixa manual registrada.';
                  runtime.pendenciaMessage.className = 'charge-message success';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao dar baixa.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.pendenciaDeleteButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const pendenciaId = button.dataset.id;
          const row = button.closest('tr');
          if (!pendenciaId || !row)
              return;
          const student = row.children?.[0]?.textContent?.trim() || 'Aluno';
          const guardian = row.children?.[1]?.textContent?.trim() || 'Responsável';
          const dayUseDate = row.children?.[4]?.textContent?.trim() || '-';
          const chooseReason = await runtime.showAdminConfirm(`Cancelar pendência?\n\nAluno: ${student}\nResponsável: ${guardian}\nData do day-use: ${dayUseDate}\n\nOpção: DIÁRIA NÃO USADA`, { title: 'Cancelar pendência' });
          if (!chooseReason)
              return;
          const confirmDelete = await runtime.showAdminConfirm('CONFIRMAR CANCELAMENTO DA PENDÊNCIA E DA COBRANÇA NO ASAAS?', { title: 'Confirmação final', confirmText: 'Cancelar pendência' });
          if (!confirmDelete)
              return;
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Cancelando pendência...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-delete-pendencia.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ id: pendenciaId, reason: 'DIARIA_NAO_USADA' }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data?.error || 'Falha ao cancelar pendência.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              row.remove();
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Pendência cancelada e preservada no histórico.';
                  runtime.pendenciaMessage.className = 'charge-message success';
              }
              await runtime.showAdminAlert('PENDÊNCIA E COBRANÇA ASAAS CANCELADAS.', { title: 'Pendência cancelada' });
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao cancelar pendência.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.postPendenciaStudentReconcile = async function postPendenciaStudentReconcile(payload: RuntimeValue) {
      const res = await fetch('/api/admin-reconcile-pendencia-student.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
      });
      const data = await res.json();
      return { res, data };
  };
  
  runtime.pendenciaLinkStudentButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const pendenciaId = button.dataset.id;
          const row = button.closest('tr');
          const lookupInput = row ? row.querySelector<HTMLInputElement>('.pendencia-student-lookup') : null;
          const lookupValue = lookupInput ? lookupInput.value : '';
          if (!pendenciaId || !row)
              return;
          const found = runtime.findStudentFromLookup(lookupValue);
          if (!found.student) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = found.error;
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Mesclando pendência com aluno existente...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const { res, data } = await runtime.postPendenciaStudentReconcile({
                  pendencia_id: pendenciaId,
                  action: 'link_existing',
                  student_id: found.student.id,
              });
              if (!res.ok || !data?.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data?.error || 'Falha ao mesclar pendência com aluno existente.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const linkedIds = Array.isArray(data?.linked_pendencia_ids) && data.linked_pendencia_ids.length
                  ? data.linked_pendencia_ids
                  : [pendenciaId];
              const removedRows = runtime.removePendenciaRowsByIds(linkedIds);
              if (removedRows === 0) {
                  runtime.renderPendenciaStudentLink(row, data.student || found.student);
              }
              if (lookupInput)
                  lookupInput.value = '';
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = removedRows > 1
                      ? `Pendências vinculadas e removidas da lista: ${removedRows}.`
                      : 'Pendência mesclada com aluno existente e removida da lista.';
                  runtime.pendenciaMessage.className = 'charge-message success';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao mesclar pendência com aluno existente.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.pendenciaCreateStudentButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const pendenciaId = button.dataset.id;
          const row = button.closest('tr');
          if (!pendenciaId || !row)
              return;
          const rowStudentName = row.querySelector('[data-col="student-name"]')?.textContent?.trim() || '';
          const studentName = window.prompt('Nome do aluno para incluir no banco:', rowStudentName || '');
          if (studentName === null)
              return;
          const studentNameTrimmed = studentName.trim();
          if (!studentNameTrimmed) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Informe o nome do aluno para incluir no banco.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          const gradeRaw = window.prompt('Série do aluno (6, 7 ou 8):', '6');
          if (gradeRaw === null)
              return;
          const gradeDigits = String(gradeRaw).replace(/\D/g, '');
          if (!['6', '7', '8'].includes(gradeDigits)) {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Série inválida. Use 6, 7 ou 8.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
              return;
          }
          const classNameRaw = window.prompt('Turma (opcional, ex: 6º Ano - A):', '');
          if (classNameRaw === null)
              return;
          const enrollmentRaw = window.prompt('Matrícula (opcional):', '');
          if (enrollmentRaw === null)
              return;
          button.setAttribute('disabled', 'disabled');
          if (runtime.pendenciaMessage) {
              runtime.pendenciaMessage.textContent = 'Incluindo aluno no banco e vinculando pendência...';
              runtime.pendenciaMessage.className = 'charge-message';
          }
          try {
              const { res, data } = await runtime.postPendenciaStudentReconcile({
                  pendencia_id: pendenciaId,
                  action: 'create_student',
                  student_name: studentNameTrimmed,
                  grade: Number(gradeDigits),
                  class_name: String(classNameRaw || '').trim(),
                  enrollment: String(enrollmentRaw || '').trim(),
              });
              if (!res.ok || !data?.ok) {
                  if (runtime.pendenciaMessage) {
                      runtime.pendenciaMessage.textContent = data?.error || 'Falha ao incluir aluno no banco.';
                      runtime.pendenciaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const student = data.student || null;
              if (student) {
                  const linkedIds = Array.isArray(data?.linked_pendencia_ids) && data.linked_pendencia_ids.length
                      ? data.linked_pendencia_ids
                      : [pendenciaId];
                  const removedRows = runtime.removePendenciaRowsByIds(linkedIds);
                  if (removedRows === 0) {
                      runtime.renderPendenciaStudentLink(row, student);
                  }
                  const newId = String(student.id || '').trim();
                  const newName = String(student.name || '').trim();
                  const alreadyLoaded = runtime.adminStudents.some(
                      (loadedStudent: RuntimeValue) =>
                          String(loadedStudent?.id || '').trim() === newId,
                  );
                  if (newId && newName && !alreadyLoaded) {
                      runtime.adminStudents.push(student);
                      const identityLabel = runtime.formatStudentIdentityLabel(student);
                      if (runtime.studentList) {
                          const option = document.createElement('option');
                          option.value = identityLabel;
                          runtime.studentList.appendChild(option);
                      }
                      if (runtime.viewUserStudentsList) {
                          const optionView = document.createElement('option');
                          optionView.value = identityLabel;
                          runtime.viewUserStudentsList.appendChild(optionView);
                      }
                      if (runtime.attendanceStudentsList) {
                          const optionAttendance = document.createElement('option');
                          optionAttendance.value = identityLabel;
                          runtime.attendanceStudentsList.appendChild(optionAttendance);
                      }
                      if (runtime.monthlyStudentsList) {
                          const optionMonthly = document.createElement('option');
                          optionMonthly.value = identityLabel;
                          runtime.monthlyStudentsList.appendChild(optionMonthly);
                      }
                      if (runtime.pendenciaStudentsList) {
                          const gradeLabel = student.grade ? `${student.grade}º ano` : '';
                          const classLabel = String(student.class_name || '').trim();
                          const enrollmentLabel = String(student.enrollment || '').trim();
                          const details = [gradeLabel, classLabel, enrollmentLabel ? `Matrícula ${enrollmentLabel}` : '']
                              .filter(Boolean)
                              .join(' • ');
                          const lookupLabel = details ? `${newName} • ${details}` : newName;
                          const optionPendencia = document.createElement('option');
                          optionPendencia.value = lookupLabel;
                          runtime.pendenciaStudentsList.appendChild(optionPendencia);
                          if (student.id) {
                              runtime.studentLookupByLabel.set(lookupLabel, student);
                          }
                      }
                  }
              }
              if (runtime.pendenciaMessage) {
                  const linkedCount = Array.isArray(data?.linked_pendencia_ids) ? data.linked_pendencia_ids.length : 1;
                  if (data.created_student) {
                      runtime.pendenciaMessage.textContent = linkedCount > 1
                          ? `Aluno incluído e ${linkedCount} pendências vinculadas/removidas da lista.`
                          : 'Aluno incluído no banco e pendência vinculada/removida da lista.';
                  }
                  else {
                      runtime.pendenciaMessage.textContent = linkedCount > 1
                          ? `${linkedCount} pendências vinculadas ao aluno existente e removidas da lista.`
                          : 'Aluno já existia no banco e a pendência foi vinculada/removida da lista.';
                  }
                  runtime.pendenciaMessage.className = 'charge-message success';
              }
          }
          catch {
              if (runtime.pendenciaMessage) {
                  runtime.pendenciaMessage.textContent = 'Falha ao incluir aluno no banco.';
                  runtime.pendenciaMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  runtime.buildSyncDuplicateDayUsePopupMessage = function buildSyncDuplicateDayUsePopupMessage(duplicates: RuntimeValue) {
      const sourceLabel = (source: RuntimeValue) => {
          if (source === 'payments_paid')
              return 'Cobrança paga (payments)';
          if (source === 'pendencia_paid')
              return 'Pendência já paga';
          return source || '-';
      };
      const lines = [
          'Atenção: encontramos pendências duplicadas no SAAS para o MESMO dia de day-use.',
          'Essas pendências já possuem uma cobrança paga para o mesmo aluno/data do day-use.',
          '',
          'Revise abaixo antes de confirmar a remoção:',
      ];
      duplicates.slice(0, 12).forEach((item: RuntimeValue) => {
          const student = item.student_name || '-';
          const guardian = item.guardian_name || '-';
          const date = runtime.formatIsoDateBr(item.payment_date || '-');
          const paidSource = sourceLabel(item.paid_source);
          const paidDate = runtime.formatIsoDateBr(item.paid_payment_date || item.payment_date || '-');
          const paidAt = runtime.formatDateTimeBR(item.paid_at || '-');
          const paidAmount = runtime.formatCurrency(item.paid_amount || 0);
          const paidBilling = item.paid_billing_type || '-';
          const paidAsaasId = item.paid_asaas_payment_id || '-';
          lines.push(`- Pendência: ${student} | Responsável: ${guardian} | Day-use: ${date}`);
          lines.push(`  Pagamento encontrado: ${paidSource} | Dia: ${paidDate} | Valor: ${paidAmount}`);
          lines.push(`  Forma: ${paidBilling} | Asaas: ${paidAsaasId} | Pago em: ${paidAt}`);
      });
      if (duplicates.length > 12) {
          lines.push(`... e mais ${duplicates.length - 12} ocorrência(s).`);
      }
      lines.push('');
      lines.push('Deseja retirar essas pendências duplicadas da aba Pendências e continuar a atualização?');
      return lines.join('\n');
  };
  
  runtime.buildSyncDuplicatePaymentsPopupMessage = function buildSyncDuplicatePaymentsPopupMessage(duplicates: RuntimeValue) {
      const lines = [
          'ATENÇÃO: encontramos cobranças duplicadas para o MESMO dia de day-use.',
          'O aluno não deve ter duas cobranças para o mesmo dia.',
          '',
          'Cobranças duplicadas detectadas (serão removidas as extras):',
      ];
      duplicates.slice(0, 12).forEach((item: RuntimeValue) => {
          const student = item.student_name || '-';
          const guardian = item.guardian_name || '-';
          const date = runtime.formatIsoDateBr(item.payment_date || '-');
          const amount = runtime.formatCurrency(item.remove_amount || 0);
          const keepId = item.keep_payment_id || '-';
          const removeId = item.remove_payment_id || '-';
          lines.push(`- ${student} | Responsável: ${guardian} | Day-use: ${date} | Valor: ${amount}`);
          lines.push(`  Manter: ${keepId} | Excluir: ${removeId}`);
      });
      if (duplicates.length > 12) {
          lines.push(`... e mais ${duplicates.length - 12} ocorrência(s).`);
      }
      lines.push('');
      lines.push('Deseja excluir as cobranças duplicadas extras e continuar?');
      return lines.join('\n');
  };
  
  runtime.postSyncChargesPayments = async function postSyncChargesPayments(payload: RuntimeValue) {
      const res = await fetch('/api/admin-sync-charges-payments.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload || {}),
      });
      const data = await res.json();
      return { res, data };
  };
  
  runtime.runSyncChargesPayments = async function runSyncChargesPayments(button: RuntimeValue, messageNode: RuntimeValue) {
      if (!button)
          return;
      button.setAttribute('disabled', 'disabled');
      const originalText = button.textContent;
      button.textContent = 'Analisando duplicidades...';
      if (messageNode) {
          messageNode.textContent = 'Checando duplicidade de dia (mesmo aluno + mesmo day-use)...';
          messageNode.className = 'charge-message';
      }
      try {
          const previewResult = await runtime.postSyncChargesPayments({ preview_duplicate_dayuse: true });
          const previewRes = previewResult.res;
          const previewData = previewResult.data;
          if (!previewRes.ok || !previewData?.ok) {
              if (messageNode) {
                  messageNode.textContent = previewData?.error || 'Falha ao checar duplicidades de day-use.';
                  messageNode.className = 'charge-message error';
              }
              return;
          }
          const duplicateItems = Array.isArray(previewData?.duplicate_dayuse?.items)
              ? previewData.duplicate_dayuse.items
              : [];
          const duplicatePaymentItems = Array.isArray(previewData?.duplicate_payments?.items)
              ? previewData.duplicate_payments.items
              : [];
          let syncPayload = {};
          if (duplicateItems.length > 0) {
              const wantsToRemove = await runtime.showAdminConfirm(runtime.buildSyncDuplicateDayUsePopupMessage(duplicateItems), { title: 'Duplicidades em pendências', confirmText: 'Remover duplicidades' });
              if (!wantsToRemove) {
                  if (messageNode) {
                      messageNode.textContent = 'Atualização cancelada. Revise as pendências duplicadas de day-use.';
                      messageNode.className = 'charge-message';
                  }
                  return;
              }
              syncPayload = { confirm_remove_duplicate_dayuse: true };
          }
          if (duplicatePaymentItems.length > 0) {
              const wantsToRemovePayments = await runtime.showAdminConfirm(runtime.buildSyncDuplicatePaymentsPopupMessage(duplicatePaymentItems), { title: 'Duplicidades em cobranças', confirmText: 'Excluir extras' });
              if (!wantsToRemovePayments) {
                  if (messageNode) {
                      messageNode.textContent = 'Atualização cancelada. Revise as cobranças duplicadas de day-use.';
                      messageNode.className = 'charge-message';
                  }
                  return;
              }
              syncPayload = {
                  ...syncPayload,
                  confirm_remove_duplicate_payments: true,
              };
          }
          button.textContent = 'Sincronizando...';
          if (messageNode) {
              messageNode.textContent = 'Executando varredura de cobranças/pagamentos no Asaas...';
              messageNode.className = 'charge-message';
          }
          const syncResult = await runtime.postSyncChargesPayments(syncPayload);
          const res = syncResult.res;
          const data = syncResult.data;
          if (!res.ok || !data?.ok) {
              if (messageNode) {
                  messageNode.textContent = data?.error || 'Falha ao sincronizar cobranças e pagamentos.';
                  messageNode.className = 'charge-message error';
              }
              return;
          }
          const summary = data.summary || {};
          if (messageNode) {
              messageNode.textContent = `Sincronização concluída. Duplicidades em pendências (mesmo dia): ${summary.duplicate_dayuse_detected || 0}, removidas: ${summary.pendencias_removed_duplicate_dayuse || 0}. Duplicidades em cobranças: ${summary.duplicate_payments_detected || 0}, removidas: ${summary.duplicate_payments_removed || 0}. Payments verificados: ${summary.payments_checked || 0}, atualizados para pago: ${summary.payments_paid_updated || 0}, cancelados: ${summary.payments_canceled_updated || 0}, não encontrados: ${summary.payments_not_found || 0}. Pendências verificadas: ${summary.pendencias_checked || 0}, pagas: ${summary.pendencias_paid_updated || 0}, removidas sem cobrança no Asaas: ${summary.pendencias_removed_no_charge || 0}, desvinculadas: ${summary.pendencias_unlinked || 0}.`;
              messageNode.className = 'charge-message success';
          }
      }
      catch {
          if (messageNode) {
              messageNode.textContent = 'Falha ao sincronizar cobranças e pagamentos.';
              messageNode.className = 'charge-message error';
          }
      }
      finally {
          button.removeAttribute('disabled');
          button.textContent = originalText;
      }
  };
  
  if (runtime.syncChargesPaymentsButton) {
      runtime.syncChargesPaymentsButton.addEventListener('click', async () => {
          await runtime.runSyncChargesPayments(runtime.syncChargesPaymentsButton, runtime.syncChargesPaymentsMessage);
      });
  }
  
  if (runtime.syncChargesPaymentsInadimplentesButton) {
      runtime.syncChargesPaymentsInadimplentesButton.addEventListener('click', async () => {
          await runtime.runSyncChargesPayments(runtime.syncChargesPaymentsInadimplentesButton, runtime.syncChargesPaymentsInadimplentesMessage);
      });
  }
}
