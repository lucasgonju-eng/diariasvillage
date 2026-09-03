import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsManualCharges(runtime: AdminRuntime): void {
  runtime.addChargeItem = async function addChargeItem(studentRecord: RuntimeValue) {
      const studentName = String(studentRecord?.name || '').trim();
      if (!studentName || !studentRecord?.id)
          return;
      const studentKey = studentRecord?.id ? String(studentRecord.id) : studentName;
      if (runtime.selectedStudents.has(studentKey))
          return;
      const monthlyPlan = runtime.getMonthlyPlanForStudent(studentRecord);
      if (monthlyPlan && Number(monthlyPlan.weekly_days || 0) > 0) {
          await runtime.showAdminAlert(`Aluno ${studentName} é mensalista de ${monthlyPlan.weekly_days} dias por semana.`, { title: 'Atenção: aluno mensalista' });
      }
      runtime.selectedStudents.add(studentKey);
      const wrapper = document.createElement('div');
      wrapper.className = 'charge-item';
      wrapper.dataset.student = studentName;
      wrapper.dataset.studentId = studentRecord?.id ? String(studentRecord.id) : '';
      wrapper.dataset.guardianId = '';
      wrapper.innerHTML = `
      <div class="charge-header">
        <strong>Aluno: ${runtime.escapeHtml(studentName)}</strong>
        <button class="btn btn-ghost btn-sm" type="button">Remover</button>
      </div>
      <div class="charge-fields">
        <div class="form-group">
          <label>Escolher responsável</label>
          <select name="guardian_selector">
            <option value="">Digite os dados manualmente</option>
          </select>
        </div>
        <div class="form-group">
          <label>Nome do responsável</label>
          <input type="text" name="guardian_name" required />
        </div>
        <div class="form-group">
          <label>E-mail</label>
          <input type="email" name="guardian_email" required />
        </div>
        <div class="form-group">
          <label>Whatsapp</label>
          <input type="tel" name="guardian_whatsapp" placeholder="(DDD) 99999-9999" />
        </div>
        <div class="form-group">
          <label>CPF/CNPJ</label>
          <input type="text" name="guardian_document" placeholder="Digite o CPF ou CNPJ" />
        </div>
        <div class="form-group">
          <label>Datas do day-use</label>
          <div class="date-list">
            <div class="date-row">
              <input type="text" name="day_use_dates[]" placeholder="dd/mm/aa" inputmode="numeric" />
              <div class="date-actions">
                <button class="btn btn-ghost btn-sm" type="button" data-action="add-date">+</button>
                <button class="btn btn-ghost btn-sm" type="button" data-action="remove-date">-</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
      wrapper.querySelector<HTMLButtonElement>('.charge-header button')?.addEventListener('click', () => {
          runtime.selectedStudents.delete(studentKey);
          wrapper.remove();
      });
      const dateList = wrapper.querySelector<HTMLDivElement>('.date-list');
      if (!dateList)
          return;
      dateList.addEventListener('click', (event: RuntimeValue) => {
          const target = event.target;
          if (!(target instanceof HTMLElement))
              return;
          if (target.dataset.action === 'add-date') {
              const row = document.createElement('div');
              row.className = 'date-row';
              row.innerHTML = `
          <input type="text" name="day_use_dates[]" placeholder="dd/mm/aa" inputmode="numeric" />
          <div class="date-actions">
            <button class="btn btn-ghost btn-sm" type="button" data-action="add-date">+</button>
            <button class="btn btn-ghost btn-sm" type="button" data-action="remove-date">-</button>
          </div>
        `;
              dateList.appendChild(row);
              return;
          }
          if (target.dataset.action === 'remove-date') {
              const row = target.closest('.date-row');
              if (row)
                  row.remove();
          }
      });
      dateList.addEventListener('input', (event: RuntimeValue) => {
          const target = event.target;
          if (!(target instanceof HTMLInputElement))
              return;
          if (target.name !== 'day_use_dates[]')
              return;
          const digits = target.value.replace(/\D/g, '').slice(0, 6);
          let value = digits;
          if (digits.length > 2) {
              value = `${digits.slice(0, 2)}/${digits.slice(2)}`;
          }
          if (digits.length > 4) {
              value = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
          }
          target.value = value;
      });
      runtime.chargeList.appendChild(wrapper);
      const selector = wrapper.querySelector<HTMLSelectElement>('[name="guardian_selector"]');
      const nameInput = wrapper.querySelector<HTMLInputElement>('[name="guardian_name"]');
      const emailInput = wrapper.querySelector<HTMLInputElement>('[name="guardian_email"]');
      const phoneInput = wrapper.querySelector<HTMLInputElement>('[name="guardian_whatsapp"]');
      const docInput = wrapper.querySelector<HTMLInputElement>('[name="guardian_document"]');
      function fillGuardianFields(guardian: RuntimeValue) {
          if (!guardian)
              return;
          if (nameInput)
              nameInput.value = guardian.parent_name || '';
          if (emailInput)
              emailInput.value = guardian.email || '';
          if (phoneInput)
              phoneInput.value = guardian.parent_phone || '';
          if (docInput)
              docInput.value = guardian.parent_document || '';
      }
      function bindGuardianSelector(guardians: RuntimeValue) {
          if (!selector)
              return;
          const list = Array.isArray(guardians) ? guardians : [];
          selector.innerHTML = '<option value="">Selecione ou digite os dados manualmente</option>';
          list.forEach((guardian: RuntimeValue) => {
              const option = document.createElement('option');
              const labelName = guardian.parent_name || 'Sem nome';
              const labelEmail = guardian.email || 'sem e-mail';
              option.value = String(guardian.id || '');
              option.textContent = `${labelName} (${labelEmail})`;
              selector.appendChild(option);
          });
          selector.addEventListener('change', () => {
              const selectedId = String(selector.value || '');
              const selectedGuardian = list.find((guardian: RuntimeValue) => String(guardian.id || '') === selectedId);
              wrapper.dataset.guardianId = selectedGuardian?.id ? String(selectedGuardian.id) : '';
              if (!selectedGuardian) {
                  return;
              }
              fillGuardianFields(selectedGuardian);
          });
          [nameInput, emailInput, phoneInput, docInput].forEach((input: RuntimeValue) => {
              input?.addEventListener('input', () => {
                  if (wrapper.dataset.guardianId) {
                      wrapper.dataset.guardianId = '';
                      selector.value = '';
                  }
              });
          });
      }
      if (runtime.guardianCache.has(studentKey)) {
          const cached = runtime.guardianCache.get(studentKey);
          bindGuardianSelector(cached);
          return;
      }
      try {
          const params = new URLSearchParams({ student_id: String(studentRecord?.id || '') });
          const res = await fetch(`/api/admin-guardians-by-student.php?${params.toString()}`);
          const data = await res.json();
          let guardians = [];
          if (data.ok) {
              if (Array.isArray(data.guardians)) {
                  guardians = data.guardians;
              }
              else if (data.guardian) {
                  guardians = [data.guardian];
              }
          }
          runtime.guardianCache.set(studentKey, guardians);
          bindGuardianSelector(guardians);
      }
      catch (err: RuntimeValue) {
          runtime.guardianCache.set(studentKey, []);
      }
  };
  
  runtime.applyStudentsToLists = function applyStudentsToLists(students: RuntimeValue) {
      runtime.adminStudents = Array.isArray(students) ? students : [];
      if (runtime.studentList)
          runtime.studentList.innerHTML = '';
      if (runtime.viewUserStudentsList)
          runtime.viewUserStudentsList.innerHTML = '';
      if (runtime.pendenciaStudentsList)
          runtime.pendenciaStudentsList.innerHTML = '';
      if (runtime.attendanceStudentsList)
          runtime.attendanceStudentsList.innerHTML = '';
      if (runtime.monthlyStudentsList)
          runtime.monthlyStudentsList.innerHTML = '';
      runtime.studentLookupByLabel.clear();
      runtime.adminStudents.forEach((student: RuntimeValue) => {
          const studentName = (student.name || '').trim();
          if (!studentName)
              return;
          const option = document.createElement('option');
          option.value = runtime.formatStudentIdentityLabel(student);
          if (runtime.studentList)
              runtime.studentList.appendChild(option);
          if (runtime.attendanceStudentsList) {
              const optionAttendance = document.createElement('option');
              optionAttendance.value = runtime.formatStudentIdentityLabel(student);
              runtime.attendanceStudentsList.appendChild(optionAttendance);
          }
          if (runtime.monthlyStudentsList) {
              const optionMonthly = document.createElement('option');
              optionMonthly.value = runtime.formatStudentIdentityLabel(student);
              runtime.monthlyStudentsList.appendChild(optionMonthly);
          }
          if (runtime.pendenciaStudentsList) {
              const gradeLabel = student.grade ? `${student.grade}º ano` : '';
              const classLabel = (student.class_name || '').trim();
              const enrollmentLabel = (student.enrollment || '').trim();
              const details = [gradeLabel, classLabel, enrollmentLabel ? `Matrícula ${enrollmentLabel}` : '']
                  .filter(Boolean)
                  .join(' • ');
              const lookupLabel = details ? `${studentName} • ${details}` : studentName;
              const optionPendencia = document.createElement('option');
              optionPendencia.value = lookupLabel;
              runtime.pendenciaStudentsList.appendChild(optionPendencia);
              if (student.id) {
                  runtime.studentLookupByLabel.set(lookupLabel, student);
              }
          }
      });
      runtime.updateViewUserAutocompleteOptions('');
  };
  
  runtime.loadStudents = async function loadStudents() {
      if (!runtime.studentList && !runtime.viewUserStudentsList && !runtime.pendenciaStudentsList && !runtime.attendanceStudentsList)
          return;
      const bootStudents = Array.isArray(window.__adminStudents) ? window.__adminStudents : null;
      if (bootStudents && bootStudents.length) {
          runtime.applyStudentsToLists(bootStudents);
          return;
      }
      let data = null;
      try {
          const res = await fetch('/api/students.php', {
              headers: { Accept: 'application/json' },
          });
          try {
              data = await res.json();
          }
          catch {
              data = null;
          }
          if (!res.ok || !data?.ok) {
              console.error('[admin-dashboard] loadStudents falhou', {
                  status: res.status,
                  payload: data,
              });
              return;
          }
      }
      catch (error: RuntimeValue) {
          console.error('[admin-dashboard] loadStudents erro de rede', error);
          return;
      }
      runtime.applyStudentsToLists(data.students);
  };
  
  runtime.tryAddStudentFromInput = function tryAddStudentFromInput() {
      if (!runtime.studentInput)
          return;
      const value = runtime.studentInput.value.trim();
      const resolved = runtime.resolveStudentIdentityForAdmin(value);
      if (!resolved.ok || !resolved.student)
          return;
      runtime.addChargeItem(resolved.student);
      runtime.studentInput.value = '';
  };
  
  runtime.collectCharges = function collectCharges() {
      const items = [...runtime.chargeList.querySelectorAll('.charge-item')];
      return items.map((item: RuntimeValue) => ({
          student_name: item.dataset.student,
          student_id: item.dataset.studentId || '',
          guardian_id: item.dataset.guardianId || '',
          guardian_name: item.querySelector('[name="guardian_name"]').value.trim(),
          guardian_email: item.querySelector('[name="guardian_email"]').value.trim(),
          guardian_whatsapp: item.querySelector('[name="guardian_whatsapp"]').value.trim(),
          guardian_document: item.querySelector('[name="guardian_document"]').value.trim(),
          day_use_dates: [...item.querySelectorAll('[name="day_use_dates[]"]')]
              .map((input: RuntimeValue) => input.value.trim())
              .filter(Boolean),
      }));
  };
  
  runtime.showChargeMessage = function showChargeMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.chargeMessage)
          return;
      runtime.chargeMessage.textContent = text;
      runtime.chargeMessage.className = `charge-message ${isError ? 'error' : 'success'}`;
  };
  
  runtime.formatIsoDateBr = function formatIsoDateBr(value: RuntimeValue) {
      if (!value)
          return value;
      const parts = String(value).split('-');
      if (parts.length !== 3)
          return value;
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
  };
  
  runtime.buildDuplicatesPopupMessage = function buildDuplicatesPopupMessage(duplicates: RuntimeValue) {
      const lines = ['Atenção: encontramos possíveis coincidências de diária já registrada:'];
      duplicates.slice(0, 15).forEach((dup: RuntimeValue) => {
          const student = dup.student_name || '-';
          const date = runtime.formatIsoDateBr(dup.date || '-');
          const source = dup.source || '-';
          const status = dup.status || '-';
          lines.push(`- ${student} | ${date} | fonte: ${source} | status: ${status}`);
      });
      if (duplicates.length > 15) {
          lines.push(`... e mais ${duplicates.length - 15} coincidência(s).`);
      }
      lines.push('');
      lines.push('Deseja continuar mesmo assim?');
      return lines.join('\n');
  };
  
  runtime.resetChargeForm = function resetChargeForm() {
      if (runtime.chargeList) {
          runtime.chargeList.innerHTML = '';
      }
      runtime.selectedStudents.clear();
      if (runtime.studentInput) {
          runtime.studentInput.value = '';
      }
  };
  
  if (runtime.studentInput) {
      runtime.studentInput.addEventListener('change', runtime.tryAddStudentFromInput);
      runtime.studentInput.addEventListener('blur', runtime.tryAddStudentFromInput);
      runtime.studentInput.addEventListener('input', runtime.tryAddStudentFromInput);
      runtime.studentInput.addEventListener('keydown', (event: RuntimeValue) => {
          if (event.key === 'Enter') {
              event.preventDefault();
              runtime.tryAddStudentFromInput();
          }
      });
  }
  
  if (runtime.sendChargesButton) {
      runtime.sendChargesButton.addEventListener('click', async () => {
          const charges = runtime.collectCharges();
          if (!charges.length) {
              runtime.showChargeMessage('Selecione ao menos um aluno.', true);
              return;
          }
          const invalid = charges.find((item: RuntimeValue) => !item.guardian_name || !item.guardian_email || !item.student_name);
          if (invalid) {
              runtime.showChargeMessage('Preencha nome e e-mail do responsável para todos os alunos.', true);
              return;
          }
          runtime.sendChargesButton.disabled = true;
          runtime.sendChargesButton.textContent = 'Registrando...';
          runtime.showChargeMessage('');
          try {
              const duplicateRes = await fetch('/api/admin-check-duplicate-dayuse.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ charges }),
              });
              const duplicateData = await duplicateRes.json();
              const duplicates = Array.isArray(duplicateData?.duplicates) ? duplicateData.duplicates : [];
              if (!duplicateRes.ok || !duplicateData?.ok) {
                  runtime.showChargeMessage(duplicateData?.error || 'Falha ao validar coincidências.', true);
                  return;
              }
              if (duplicates.length > 0) {
                  const wantsToContinue = await runtime.showAdminConfirm(runtime.buildDuplicatesPopupMessage(duplicates), { title: 'Possíveis coincidências de cobrança', confirmText: 'Continuar assim' });
                  if (!wantsToContinue) {
                      runtime.showChargeMessage('Envio cancelado para revisão de coincidências.', true);
                      return;
                  }
              }
              const res = await fetch('/api/admin-charge.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ charges }),
              });
              let data = null;
              try {
                  data = await res.json();
              }
              catch {
                  data = null;
              }
              if (!data?.ok) {
                  const statusInfo = !res.ok ? ` (HTTP ${res.status})` : '';
                  runtime.showChargeMessage((data?.error || 'Falha ao registrar cobranças manuais.') + statusInfo, true);
              }
              else {
                  const results = Array.isArray(data.results) ? data.results : [];
                  const failures = results.filter((item: RuntimeValue) => !item.ok);
                  const monthlyCoveredOnly = results.filter((item: RuntimeValue) => item?.ok && item?.monthly_covered);
                  const monthlyPartial = results.filter((item: RuntimeValue) => item?.ok &&
                      !item?.monthly_covered &&
                      Array.isArray(item?.covered_dates) &&
                      item.covered_dates.length > 0 &&
                      Array.isArray(item?.overflow_dates) &&
                      item.overflow_dates.length > 0);
                  if (monthlyCoveredOnly.length || monthlyPartial.length) {
                      const lines: string[] = [];
                      monthlyCoveredOnly.forEach((item: RuntimeValue) => {
                          lines.push(`- ${item.student_name}: dentro da franquia mensalista (${item.monthly_days || '?'} dias/semana), sem cobrança.`);
                      });
                      monthlyPartial.forEach((item: RuntimeValue) => {
                          lines.push(`- ${item.student_name}: mensalista (${item.monthly_days || '?'} dias). Cobrança criada só para excedente.`);
                      });
                      await runtime.showAdminAlert(lines.join('\n'), { title: 'Regra de mensalistas aplicada' });
                  }
                  if (failures.length) {
                      runtime.showChargeMessage('Algumas cobranças manuais não foram registradas. Verifique os dados.', true);
                  }
                  else if (results.length > 0 && results.every((item: RuntimeValue) => item?.ok && item?.monthly_covered)) {
                      runtime.showChargeMessage('Nenhuma cobrança gerada: todas as datas estão dentro da franquia de mensalistas.');
                      runtime.resetChargeForm();
                  }
                  else {
                      runtime.showChargeMessage('Cobranças manuais registradas na fila (sem envio). Abrindo Cobranças em aberto...');
                      runtime.resetChargeForm();
                      setTimeout(() => {
                          window.location.href = '/admin/dashboard.php?tab=inadimplentes';
                      }, 350);
                  }
              }
          }
          catch (err: RuntimeValue) {
              runtime.showChargeMessage('Falha ao registrar cobranças manuais.', true);
          }
          finally {
              runtime.sendChargesButton.disabled = false;
              runtime.sendChargesButton.textContent = 'Registrar cobranças manuais (sem envio)';
          }
      });
  }
}
