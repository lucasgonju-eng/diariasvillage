import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsAttendance(runtime: AdminRuntime): void {
  runtime.setAttendanceMessage = function setAttendanceMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.attendanceMessage)
          return;
      runtime.attendanceMessage.textContent = text;
      runtime.attendanceMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
  };
  
  runtime.attendanceStatusLabel = function attendanceStatusLabel(status: RuntimeValue) {
      const map: Record<string, string> = {
          em_revisao: 'Em revisão',
          autorizada_cobranca: 'Autorizada (cobrança na fila)',
          rejeitada: 'Rejeitada',
          aluno_mensalista: 'Aluno mensalista',
          bloqueada_ja_paga: 'Bloqueada: já paga',
          bloqueada_duplicidade: 'Bloqueada: cobrança existente',
          erro_cobranca: 'Erro ao lançar cobrança',
      };
      return map[String(status || '').trim()] || status || '-';
  };
  
  runtime.attendanceTypeLabel = function attendanceTypeLabel(type: RuntimeValue) {
      const normalized = String(type || '').trim().toLowerCase();
      if (normalized === 'planejada')
          return 'Planejada';
      if (normalized === 'emergencial')
          return 'Emergencial';
      return '-';
  };
  
  runtime.attendanceDiscountLabel = function attendanceDiscountLabel(item: RuntimeValue) {
      const discount = Number(item?.discount_amount || 0);
      if (!Number.isFinite(discount) || discount <= 0)
          return '-';
      const reason = String(item?.discount_reason || '').trim();
      if (!reason)
          return runtime.formatCurrency(discount);
      return `${runtime.formatCurrency(discount)} (${reason})`;
  };
  
  runtime.compareByStudentName = function compareByStudentName(a: RuntimeValue, b: RuntimeValue) {
      const aName = runtime.normalizeSearchText(a?.student_name || a?.name || '');
      const bName = runtime.normalizeSearchText(b?.student_name || b?.name || '');
      const byName = aName.localeCompare(bName, 'pt-BR');
      if (byName !== 0)
          return byName;
      const aDate = String(a?.attendance_date || '');
      const bDate = String(b?.attendance_date || '');
      return bDate.localeCompare(aDate);
  };
  
  runtime.getAttendanceFilterParams = function getAttendanceFilterParams() {
      const params = new URLSearchParams();
      const from = String(runtime.attendanceFilterFromInput?.value || '').trim();
      const to = String(runtime.attendanceFilterToInput?.value || '').trim();
      if (from)
          params.set('from', from);
      if (to)
          params.set('to', to);
      if (runtime.attendancePendingOnlyInput && !runtime.attendancePendingOnlyInput.checked)
          params.set('show_all', '1');
      return params;
  };
  
  runtime.resolveAttendanceOffice = function resolveAttendanceOffice(inputValue: RuntimeValue) {
      const raw = String(inputValue || '').trim();
      if (!raw)
          return { ok: true, officeId: '', officeName: '' };
      const byLabel = runtime.attendanceOfficeByLabel.get(raw);
      if (byLabel) {
          return {
              ok: true,
              officeId: String(byLabel.id || '').trim(),
              officeName: String(byLabel.name || '').trim(),
          };
      }
      for (const office of runtime.attendanceOfficeById.values()) {
          if (runtime.normalizeSearchText(office.name) === runtime.normalizeSearchText(raw)) {
              return {
                  ok: true,
                  officeId: String(office.id || '').trim(),
                  officeName: String(office.name || '').trim(),
              };
          }
      }
      return { ok: false, error: 'Selecione uma oficina válida da lista do mês corrente.' };
  };
  
  runtime.renderAttendanceRows = function renderAttendanceRows(items: RuntimeValue) {
      if (!runtime.attendanceTbody)
          return;
      const rows = Array.isArray(items) ? [...items] : [];
      rows.sort(runtime.compareByStudentName);
      if (!rows.length) {
          runtime.attendanceTbody.innerHTML = '<tr><td colspan="10">Nenhuma chamada lançada.</td></tr>';
          return;
      }
      runtime.attendanceTbody.innerHTML = rows
          .map((item: RuntimeValue) => {
          const office = item.office_name
              ? `${item.office_name}${item.office_code ? ` (${item.office_code})` : ''}`
              : '-';
          const reviewParts = [];
          if (item.review_note)
              reviewParts.push(String(item.review_note));
          if (item.reviewed_at)
              reviewParts.push(runtime.formatDateTimeBR(item.reviewed_at));
          const reviewText = reviewParts.length ? reviewParts.join(' • ') : '-';
          const canReview = runtime.adminCanApproveAttendance && String(item.status || '') === 'em_revisao';
          const canRetryCharge = runtime.adminCanApproveAttendance && String(item.status || '') === 'erro_cobranca';
          const actionParts = [];
          if (canReview) {
              actionParts.push(`<button class="btn btn-primary btn-sm js-attendance-approve" type="button" data-id="${runtime.escapeHtml(item.id || '')}">Autorizar</button>`);
              actionParts.push(`<button class="btn btn-danger btn-sm js-attendance-reject" type="button" data-id="${runtime.escapeHtml(item.id || '')}">Rejeitar</button>`);
          }
          if (canRetryCharge) {
              actionParts.push(`<button class="btn btn-primary btn-sm js-attendance-retry" type="button" data-id="${runtime.escapeHtml(item.id || '')}">Relançar</button>`);
          }
          actionParts.push(`<button class="btn btn-primary btn-sm js-attendance-edit" type="button" data-id="${runtime.escapeHtml(item.id || '')}" data-date="${runtime.escapeHtml(item.attendance_date || '')}" data-day-use-type="${runtime.escapeHtml(item.day_use_type || '')}" data-discount-amount="${runtime.escapeHtml(item.discount_amount ?? '')}" data-discount-reason="${runtime.escapeHtml(item.discount_reason || '')}">Editar</button>`);
          const actions = actionParts.join('');
          return `
          <tr data-attendance-id="${runtime.escapeHtml(item.id || '')}" data-attendance-date="${runtime.escapeHtml(item.attendance_date || '')}">
            <td>${runtime.escapeHtml(runtime.formatDateBR(item.attendance_date || '-'))}</td>
            <td>${runtime.escapeHtml(item.student_name || '-')}</td>
            <td>${runtime.escapeHtml(office)}</td>
            <td>${runtime.escapeHtml(runtime.attendanceTypeLabel(item.day_use_type || ''))}</td>
            <td>${runtime.escapeHtml(runtime.attendanceDiscountLabel(item))}</td>
            <td>${runtime.escapeHtml(runtime.attendanceStatusLabel(item.status || ''))}</td>
            <td>${runtime.escapeHtml(item.created_by_user || item.created_by_role || '-')}</td>
            <td>${runtime.escapeHtml(runtime.formatDateTimeBR(item.created_at || ''))}</td>
            <td>${runtime.escapeHtml(reviewText)}</td>
            <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">${actions}</td>
          </tr>
        `;
      })
          .join('');
  };
  
  runtime.loadAttendanceOffices = async function loadAttendanceOffices() {
      if (runtime.attendanceOfficesLoaded)
          return;
      if (!runtime.attendanceOfficesList)
          return;
      try {
          const res = await fetch(`/api/admin-oficinas-current-month.php?ts=${Date.now()}`);
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.attendanceOfficesList.innerHTML = '';
              runtime.attendanceOfficesLoaded = true;
              return;
          }
          runtime.attendanceOfficeById.clear();
          runtime.attendanceOfficeByLabel.clear();
          runtime.attendanceOfficesList.innerHTML = '';
          (Array.isArray(data.offices) ? data.offices : []).forEach((office: RuntimeValue) => {
              const id = String(office.id || '').trim();
              const name = String(office.name || '').trim();
              const label = String(office.label || name).trim();
              if (!id || !name)
                  return;
              const normalized = { id, name, label };
              runtime.attendanceOfficeById.set(id, normalized);
              runtime.attendanceOfficeByLabel.set(label, normalized);
              const option = document.createElement('option');
              option.value = label;
              runtime.attendanceOfficesList.appendChild(option);
          });
          runtime.attendanceOfficesLoaded = true;
      }
      catch {
          runtime.attendanceOfficesLoaded = true;
      }
  };
  
  runtime.loadAttendanceCalls = async function loadAttendanceCalls(force: RuntimeValue = false) {
      if (!runtime.attendanceTbody)
          return;
      runtime.setAttendanceMessage('Carregando chamadas...');
      try {
          const params = runtime.getAttendanceFilterParams();
          params.set('ts', Date.now().toString());
          const res = await fetch(`/api/admin-attendance.php?${params.toString()}`);
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.setAttendanceMessage(data?.error || 'Falha ao carregar chamadas.', true);
              runtime.renderAttendanceRows([]);
              return;
          }
          runtime.renderAttendanceRows(Array.isArray(data.items) ? data.items : []);
          runtime.setAttendanceMessage('');
          runtime.attendanceLoaded = true;
      }
      catch {
          runtime.setAttendanceMessage('Falha ao carregar chamadas.', true);
          runtime.renderAttendanceRows([]);
      }
  };
  
  runtime.postAttendanceAction = async function postAttendanceAction(payload: RuntimeValue) {
      const res = await fetch('/api/admin-attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload || {}),
      });
      const data = await res.json();
      return { res, data };
  };
  
  runtime.renderAttendanceDayQueue = function renderAttendanceDayQueue() {
      if (!runtime.attendanceDayList)
          return;
      if (!runtime.attendanceDayQueue.length) {
          runtime.attendanceDayList.innerHTML = '<tr><td colspan="4">Nenhum aluno adicionado para o fechamento do dia.</td></tr>';
          return;
      }
      runtime.attendanceDayQueue.sort(runtime.compareByStudentName);
      runtime.attendanceDayList.innerHTML = runtime.attendanceDayQueue.map((entry: RuntimeValue, index: RuntimeValue) => {
          const officeLabel = entry.office_name
              ? `${entry.office_name}${entry.office_code ? ` (${entry.office_code})` : ''}`
              : '-';
          return `
          <tr>
            <td>${runtime.escapeHtml(runtime.formatDateBR(entry.attendance_date || '-'))}</td>
            <td>${runtime.escapeHtml(entry.student_name || '-')}</td>
            <td>${runtime.escapeHtml(officeLabel)}</td>
            <td>
              <button class="btn btn-danger btn-sm js-attendance-queue-remove" type="button" data-index="${index}">Remover</button>
            </td>
          </tr>
        `;
      })
          .join('');
  };
  
  runtime.addAttendanceEntryToQueue = function addAttendanceEntryToQueue() {
      if (!runtime.attendanceDateInput || !runtime.attendanceStudentInput)
          return;
      const attendanceDate = String(runtime.attendanceDateInput.value || '').trim();
      if (!attendanceDate) {
          runtime.setAttendanceMessage('Informe a data da chamada.', true);
          return;
      }
      const resolvedStudent = runtime.resolveStudentIdentityForAdmin(runtime.attendanceStudentInput.value);
      if (!resolvedStudent.ok || !resolvedStudent.student) {
          runtime.setAttendanceMessage(resolvedStudent.error || 'Selecione um aluno válido.', true);
          return;
      }
      const student = resolvedStudent.student;
      const studentName = String(student.name || '').trim();
      runtime.attendanceStudentInput.value =
          resolvedStudent.label || runtime.formatStudentIdentityLabel(student);
      const officeResolved = runtime.resolveAttendanceOffice(runtime.attendanceOfficeInput?.value || '');
      if (!officeResolved.ok) {
          runtime.setAttendanceMessage(officeResolved.error || 'Oficina inválida.', true);
          return;
      }
      const office = officeResolved.officeId ? runtime.attendanceOfficeById.get(officeResolved.officeId) : null;
      const alreadyInQueue = runtime.attendanceDayQueue.some((entry: RuntimeValue) => String(entry.attendance_date || '') === attendanceDate && String(entry.student_id || '') === String(student.id || ''));
      if (alreadyInQueue) {
          runtime.setAttendanceMessage('Aluno já adicionado na lista deste dia.', true);
          return;
      }
      runtime.attendanceDayQueue.push({
          attendance_date: attendanceDate,
          student_id: String(student.id || ''),
          student_name: String(student.name || studentName),
          office_id: String(officeResolved.officeId || ''),
          office_name: String(officeResolved.officeName || ''),
          office_code: String(office?.code || ''),
      });
      runtime.attendanceDayQueue.sort(runtime.compareByStudentName);
      runtime.renderAttendanceDayQueue();
      runtime.attendanceStudentInput.value = '';
      if (runtime.attendanceOfficeInput)
          runtime.attendanceOfficeInput.value = '';
      runtime.setAttendanceMessage('Aluno adicionado na lista do dia.');
  };
  
  runtime.closeAttendanceDay = async function closeAttendanceDay() {
      if (!runtime.attendanceDateInput)
          return;
      const attendanceDate = String(runtime.attendanceDateInput.value || '').trim();
      if (!attendanceDate) {
          runtime.setAttendanceMessage('Informe a data da chamada.', true);
          return;
      }
      if (!runtime.attendanceDayQueue.length) {
          runtime.setAttendanceMessage('Adicione pelo menos um aluno antes de fechar o dia.', true);
          return;
      }
      const mixedDate = runtime.attendanceDayQueue.find((entry: RuntimeValue) => String(entry.attendance_date || '') !== attendanceDate);
      if (mixedDate) {
          runtime.setAttendanceMessage('A lista contém alunos de outra data. Ajuste a data ou remova os itens.', true);
          return;
      }
      const confirmed = await runtime.showAdminConfirm(`Fechar dia de chamada com ${runtime.attendanceDayQueue.length} aluno(s) para ${runtime.formatDateBR(attendanceDate)}?`, { title: 'Fechar dia de chamada', confirmText: 'Fechar dia' });
      if (!confirmed)
          return;
      if (runtime.attendanceCloseDayButton)
          runtime.attendanceCloseDayButton.setAttribute('disabled', 'disabled');
      if (runtime.attendanceAddButton)
          runtime.attendanceAddButton.setAttribute('disabled', 'disabled');
      runtime.setAttendanceMessage('Fechando dia de chamada...');
      try {
          const { res, data } = await runtime.postAttendanceAction({
              action: 'close_day',
              attendance_date: attendanceDate,
              entries: runtime.attendanceDayQueue.map((entry: RuntimeValue) => ({
                  student_id: entry.student_id,
                  student_name: entry.student_name,
                  office_id: entry.office_id,
                  office_name: entry.office_name,
              })),
          });
          if (!res.ok || !data?.ok) {
              runtime.setAttendanceMessage(data?.error || 'Falha ao fechar dia de chamada.', true);
              return;
          }
          const created = Number(data.created_count || 0);
          const blocked = Number(data.blocked_count || 0);
          const skipped = Number(data.skipped_count || 0);
          const emailWarning = data.email_warning ? ` Aviso: ${data.email_warning}` : '';
          runtime.setAttendanceMessage(`Dia fechado. Criadas: ${created}. Bloqueadas: ${blocked}. Ignoradas: ${skipped}.${emailWarning}`, blocked > 0);
          runtime.attendanceDayQueue = [];
          runtime.renderAttendanceDayQueue();
          await runtime.loadAttendanceCalls(true);
      }
      catch {
          runtime.setAttendanceMessage('Falha ao fechar dia de chamada.', true);
      }
      finally {
          if (runtime.attendanceCloseDayButton)
              runtime.attendanceCloseDayButton.removeAttribute('disabled');
          if (runtime.attendanceAddButton)
              runtime.attendanceAddButton.removeAttribute('disabled');
      }
  };
  
  runtime.handleAttendanceAction = async function handleAttendanceAction(event: RuntimeValue) {
      const target = event.target;
      if (!(target instanceof HTMLElement))
          return;
      const approveButton = target.closest('.js-attendance-approve');
      const rejectButton = target.closest('.js-attendance-reject');
      const retryButton = target.closest('.js-attendance-retry');
      const editButton = target.closest('.js-attendance-edit');
      if (!approveButton && !rejectButton && !retryButton && !editButton)
          return;
      const actionButton = approveButton || rejectButton || retryButton || editButton;
      if (!actionButton)
          return;
      const id = actionButton.getAttribute('data-id') || '';
      if (!id)
          return;
      if (editButton) {
          const currentRaw = String(editButton.getAttribute('data-date') || '').trim();
          const currentType = String(editButton.getAttribute('data-day-use-type') || '').trim().toLowerCase();
          const currentDiscountRaw = String(editButton.getAttribute('data-discount-amount') || '').trim();
          const currentDiscount = currentDiscountRaw === '' ? null : Number(currentDiscountRaw);
          const currentDiscountReason = String(editButton.getAttribute('data-discount-reason') || '').trim();
          const promptResult = await runtime.showAdminAttendanceEditInput({
              attendance_date: currentRaw,
              day_use_type: currentType,
              discount_amount: Number.isFinite(currentDiscount) ? currentDiscount : null,
              discount_reason: currentDiscountReason,
          });
          if (!promptResult?.ok)
              return;
          const newDate = String(promptResult.value?.attendance_date || '').trim();
          const newType = String(promptResult.value?.day_use_type || '').trim().toLowerCase();
          const discountAmount = promptResult.value?.discount_amount ?? null;
          const discountReason = String(promptResult.value?.discount_reason || '').trim();
          if (!newDate) {
              runtime.setAttendanceMessage('Informe uma data válida para edição.', true);
              return;
          }
          if (newType !== 'planejada' && newType !== 'emergencial') {
              runtime.setAttendanceMessage('Selecione um tipo de day use válido.', true);
              return;
          }
          actionButton.setAttribute('disabled', 'disabled');
          runtime.setAttendanceMessage('Atualizando Day Use...');
          try {
              const { res, data } = await runtime.postAttendanceAction({
                  action: 'edit',
                  id,
                  attendance_date: newDate,
                  day_use_type: newType,
                  discount_amount: discountAmount,
                  discount_reason: discountReason,
              });
              if (!res.ok || !data?.ok) {
                  runtime.setAttendanceMessage(data?.error || 'Falha ao editar Day Use.', true);
                  return;
              }
              runtime.setAttendanceMessage(data?.message || 'Day Use atualizado.');
              await runtime.loadAttendanceCalls(true);
          }
          catch {
              runtime.setAttendanceMessage('Falha ao editar Day Use.', true);
          }
          finally {
              actionButton.removeAttribute('disabled');
          }
          return;
      }
      if (retryButton) {
          const confirmed = await runtime.showAdminConfirm('Relançar esta chamada para tentar criar a cobrança novamente?', { title: 'Relançar cobrança', confirmText: 'Relançar' });
          if (!confirmed)
              return;
          actionButton.setAttribute('disabled', 'disabled');
          runtime.setAttendanceMessage('Relançando cobrança...');
          try {
              const { res, data } = await runtime.postAttendanceAction({ action: 'retry', id });
              if (!res.ok || !data?.ok) {
                  runtime.setAttendanceMessage(data?.error || 'Falha ao relançar cobrança.', true);
                  return;
              }
              runtime.setAttendanceMessage(data?.message || 'Chamada relançada.');
              await runtime.loadAttendanceCalls(true);
          }
          catch {
              runtime.setAttendanceMessage('Falha ao relançar cobrança.', true);
          }
          finally {
              actionButton.removeAttribute('disabled');
          }
          return;
      }
      if (approveButton) {
          actionButton.setAttribute('disabled', 'disabled');
          runtime.setAttendanceMessage('Auditando chamada...');
          try {
              const { res: auditRes, data: auditData } = await runtime.postAttendanceAction({ action: 'audit', id });
              if (!auditRes.ok || !auditData?.ok) {
                  runtime.setAttendanceMessage(auditData?.error || 'Falha ao auditar chamada.', true);
                  return;
              }
              if (auditData?.blocked) {
                  const blockedReason = String(auditData?.blocked_reason || '');
                  if (blockedReason === 'monthly_covered') {
                      const monthly = auditData?.monthly || {};
                      const usedDates = Array.isArray(monthly.used_dates) ? monthly.used_dates : [];
                      const usedLabel = usedDates.length
                          ? usedDates.map((d: RuntimeValue) => runtime.formatDateBR(d)).join(', ')
                          : 'Nenhuma';
                      await runtime.showAdminAlert(`Auditoria: aluno mensalista coberto na semana.\n` +
                          `Data da chamada: ${runtime.formatDateBR(monthly.attendance_date || '')}\n` +
                          `Cota semanal: ${monthly.weekly_days || '?'} dia(s)\n` +
                          `Datas já usadas: ${usedLabel}\n` +
                          `Saldo restante: ${monthly.remaining_days ?? '?'} dia(s)\n\n` +
                          'Resultado: cobrança bloqueada.', { title: 'Auditoria de cobrança' });
                  }
                  else if (blockedReason === 'already_paid') {
                      await runtime.showAdminAlert('Auditoria: este day use já está pago. Resultado: cobrança bloqueada.', { title: 'Auditoria de cobrança' });
                  }
                  else if (blockedReason === 'already_open') {
                      await runtime.showAdminAlert('Auditoria: já existe cobrança em aberto para esta data. Resultado: cobrança bloqueada.', { title: 'Auditoria de cobrança' });
                  }
                  else if (blockedReason === 'missing_guardian') {
                      await runtime.showAdminAlert('Auditoria: responsável inválido/ausente para gerar cobrança. Resultado: cobrança bloqueada.', { title: 'Auditoria de cobrança' });
                  }
                  else {
                      await runtime.showAdminAlert(auditData?.message || 'Auditoria bloqueou a cobrança.', {
                          title: 'Auditoria de cobrança',
                      });
                  }
                  if (blockedReason === 'already_paid' || blockedReason === 'already_open') {
                      runtime.setAttendanceMessage('Já existe cobrança para essa data', true);
                  }
                  else if (blockedReason === 'monthly_covered') {
                      runtime.setAttendanceMessage('Aluno mensalista: sem cobrança para essa data');
                  }
                  else {
                      runtime.setAttendanceMessage(auditData?.message || 'Cobrança bloqueada pela auditoria.', true);
                  }
                  await runtime.loadAttendanceCalls(true);
                  return;
              }
              const auditReason = String(auditData?.reason || '');
              const auditLabel = auditReason === 'monthly_overflow'
                  ? 'mensalista excedeu a cota semanal'
                  : 'aluno não mensalista';
              const goApprove = await runtime.showAdminConfirm(`Auditoria aprovada: ${auditLabel}.\nData: ${runtime.formatDateBR(auditData?.attendance_date || '')}\n\nDeseja autorizar e gerar cobrança agora?`, { title: 'Auditoria de cobrança', confirmText: 'Autorizar e cobrar' });
              if (!goApprove) {
                  runtime.setAttendanceMessage('Autorização cancelada após auditoria.');
                  return;
              }
              let discountAmount = null;
              if (runtime.adminCanApproveAttendance) {
                  const discountResult = await runtime.showAdminDiscountInput();
                  if (!discountResult?.ok) {
                      runtime.setAttendanceMessage('Autorização cancelada antes de gerar cobrança.');
                      return;
                  }
                  const parsedDiscount = runtime.parseDiscountInput(discountResult?.value || '');
                  if (!parsedDiscount.ok) {
                      runtime.setAttendanceMessage(parsedDiscount.error || 'Desconto inválido.', true);
                      return;
                  }
                  discountAmount = parsedDiscount.value;
              }
              runtime.setAttendanceMessage('Autorizando chamada...');
              const approvePayload: RuntimeValue = { action: 'approve', id };
              if (typeof discountAmount === 'number' && discountAmount > 0) {
                  approvePayload.discount_amount = discountAmount;
              }
              const { res, data } = await runtime.postAttendanceAction(approvePayload);
              if (!res.ok || !data?.ok) {
                  runtime.setAttendanceMessage(data?.error || 'Falha ao autorizar chamada.', true);
                  return;
              }
              if (data?.blocked) {
                  const blockedReason = String(data?.blocked_reason || '');
                  if (blockedReason === 'monthly_covered') {
                      const monthly = data?.monthly || {};
                      const usedDates = Array.isArray(monthly.used_dates) ? monthly.used_dates : [];
                      const usedLabel = usedDates.length
                          ? usedDates.map((d: RuntimeValue) => runtime.formatDateBR(d)).join(', ')
                          : 'Nenhuma';
                      await runtime.showAdminAlert(`Aluno mensalista (${monthly.weekly_days || '?'} dias/semana).\n` +
                          `Data da chamada: ${runtime.formatDateBR(monthly.attendance_date || '')}\n` +
                          `Datas registradas na semana: ${usedLabel}\n` +
                          `Saldo restante na semana: ${monthly.remaining_days ?? '?'} dia(s).\n\n` +
                          'Resultado: sem cobrança em Cobranças em aberto.', { title: 'Aviso de mensalista' });
                  }
                  else if (blockedReason === 'already_paid') {
                      await runtime.showAdminAlert('Esta data já está paga para o aluno. Nenhuma cobrança foi gerada.', { title: 'Bloqueio: diária já paga' });
                  }
                  else if (blockedReason === 'already_open') {
                      await runtime.showAdminAlert('Já existe cobrança em aberto para esta data. Nenhuma nova cobrança foi gerada.', { title: 'Bloqueio: cobrança já existente' });
                  }
              }
              const charge = data?.charge || null;
              if (charge?.payment_id) {
                  const channel = charge?.asaas_payment_id ? 'Asaas' : 'fila interna';
                  const discountSuffix = Number(charge?.discount_amount || 0) > 0
                      ? ` Desconto: ${runtime.formatCurrency(charge.discount_amount)}.`
                      : '';
                  const suffix = ` Cobrança #${charge.payment_id} (${channel}).${discountSuffix}`;
                  runtime.setAttendanceMessage((data?.message || 'Chamada autorizada.') + suffix);
              }
              else {
                  runtime.setAttendanceMessage(data?.message || 'Chamada autorizada.');
              }
              await runtime.loadAttendanceCalls(true);
          }
          catch {
              runtime.setAttendanceMessage('Falha ao autorizar chamada.', true);
          }
          finally {
              actionButton.removeAttribute('disabled');
          }
          return;
      }
      const confirmed = await runtime.showAdminConfirm('Confirmar rejeição desta chamada? A cobrança não será gerada.', { title: 'Rejeitar chamada', confirmText: 'Rejeitar' });
      if (!confirmed)
          return;
      actionButton.setAttribute('disabled', 'disabled');
      runtime.setAttendanceMessage('Rejeitando chamada...');
      try {
          const { res, data } = await runtime.postAttendanceAction({ action: 'reject', id });
          if (!res.ok || !data?.ok) {
              runtime.setAttendanceMessage(data?.error || 'Falha ao rejeitar chamada.', true);
              return;
          }
          runtime.setAttendanceMessage(data?.message || 'Chamada rejeitada.');
          await runtime.loadAttendanceCalls(true);
      }
      catch {
          runtime.setAttendanceMessage('Falha ao rejeitar chamada.', true);
      }
      finally {
          actionButton.removeAttribute('disabled');
      }
  };
  
  if (runtime.attendanceAddButton) {
      runtime.attendanceAddButton.addEventListener('click', () => {
          runtime.addAttendanceEntryToQueue();
      });
  }
  
  if (runtime.attendanceStudentInput) {
      runtime.attendanceStudentInput.addEventListener('keydown', (event: RuntimeValue) => {
          if (event.key === 'Enter') {
              event.preventDefault();
              runtime.addAttendanceEntryToQueue();
          }
      });
  }
  
  if (runtime.attendanceCloseDayButton) {
      runtime.attendanceCloseDayButton.addEventListener('click', () => {
          runtime.closeAttendanceDay();
      });
  }
  
  if (runtime.attendanceGoInadimplentesButton) {
      runtime.attendanceGoInadimplentesButton.addEventListener('click', () => {
          runtime.setActiveTab('inadimplentes');
          runtime.maybeAlertInadimplentesDuplicates();
          runtime.maybeAlertInadimplentesMonthly();
      });
  }
  
  if (runtime.attendanceFilterButton) {
      runtime.attendanceFilterButton.addEventListener('click', () => {
          runtime.loadAttendanceCalls(true);
      });
  }
  
  if (runtime.attendanceClearButton) {
      runtime.attendanceClearButton.addEventListener('click', () => {
          if (runtime.attendanceFilterFromInput)
              runtime.attendanceFilterFromInput.value = '';
          if (runtime.attendanceFilterToInput)
              runtime.attendanceFilterToInput.value = '';
          if (runtime.attendancePendingOnlyInput)
              runtime.attendancePendingOnlyInput.checked = true;
          runtime.loadAttendanceCalls(true);
      });
  }
  
  if (runtime.attendancePendingOnlyInput) {
      runtime.attendancePendingOnlyInput.addEventListener('change', () => {
          runtime.loadAttendanceCalls(true);
      });
  }
  
  if (runtime.modularCreateButton) {
      runtime.modularCreateButton.addEventListener('click', () => {
          runtime.createModularOffice();
      });
  }
  
  if (runtime.attendanceExportButton) {
      runtime.attendanceExportButton.addEventListener('click', () => {
          const params = runtime.getAttendanceFilterParams();
          params.set('ts', Date.now().toString());
          window.location.href = `/api/admin-attendance-export.php?${params.toString()}`;
      });
  }
  
  if (runtime.attendanceDayList) {
      runtime.attendanceDayList.addEventListener('click', (event: RuntimeValue) => {
          const target = event.target;
          if (!(target instanceof HTMLElement))
              return;
          const removeButton = target.closest('.js-attendance-queue-remove');
          if (!removeButton)
              return;
          const index = Number(removeButton.getAttribute('data-index') || -1);
          if (Number.isNaN(index) || index < 0 || index >= runtime.attendanceDayQueue.length)
              return;
          runtime.attendanceDayQueue.splice(index, 1);
          runtime.renderAttendanceDayQueue();
          runtime.setAttendanceMessage('Aluno removido da lista do dia.');
      });
  }
  
  if (runtime.attendanceTbody) {
      runtime.attendanceTbody.addEventListener('click', (event: RuntimeValue) => {
          runtime.handleAttendanceAction(event);
      });
  }
}
