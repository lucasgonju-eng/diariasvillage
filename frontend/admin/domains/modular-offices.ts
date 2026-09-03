import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsModularOffices(runtime: AdminRuntime): void {
  runtime.setModularOfficeMessage = function setModularOfficeMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.modularCreateMessage)
          return;
      runtime.modularCreateMessage.textContent = String(text || '');
      runtime.modularCreateMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
  };
  
  runtime.getSelectedModularPeriod = function getSelectedModularPeriod() {
      const month = Number(runtime.modularCreateMonthInput?.value || new Date().getMonth() + 1);
      const year = Number(runtime.modularCreateYearInput?.value || new Date().getFullYear());
      const safeMonth = Number.isInteger(month) && month >= 1 && month <= 12 ? month : new Date().getMonth() + 1;
      const safeYear = Number.isInteger(year) && year >= 2025 && year <= 2099 ? year : new Date().getFullYear();
      const from = `${safeYear}-${String(safeMonth).padStart(2, '0')}-01`;
      const endDate = new Date(safeYear, safeMonth, 0);
      const to = `${safeYear}-${String(safeMonth).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;
      return { month: safeMonth, year: safeYear, from, to };
  };
  
  runtime.officeAvailableInSelectedPeriod = function officeAvailableInSelectedPeriod(office: RuntimeValue) {
      if (!office || office.active !== true)
          return false;
      const period = runtime.getSelectedModularPeriod();
      const start = String(office.validity_start || '').trim();
      const end = String(office.validity_end || '').trim();
      if (!start || !end)
          return false;
      return start <= period.to && end >= period.from;
  };
  
  runtime.populateModularCatalogAndTeacherLists = function populateModularCatalogAndTeacherLists(payload: RuntimeValue) {
      if (runtime.modularCatalogList) {
          runtime.modularCatalogList.innerHTML = '';
          const names = Array.isArray(payload?.catalog_names) ? payload.catalog_names : [];
          names.forEach((name: RuntimeValue) => {
              const value = String(name || '').trim();
              if (!value)
                  return;
              const option = document.createElement('option');
              option.value = value;
              runtime.modularCatalogList.appendChild(option);
          });
      }
      if (runtime.modularTeachersList) {
          runtime.modularTeachersList.innerHTML = '';
          const teachers = Array.isArray(payload?.teachers) ? payload.teachers : [];
          teachers.forEach((name: RuntimeValue) => {
              const value = String(name || '').trim();
              if (!value)
                  return;
              const option = document.createElement('option');
              option.value = value;
              runtime.modularTeachersList.appendChild(option);
          });
      }
  };
  
  runtime.findCatalogOfficeByName = function findCatalogOfficeByName(rawName: RuntimeValue) {
      const target = runtime.normalizeSearchText(rawName);
      if (!target)
          return null;
      return runtime.modularOffices.find((office: RuntimeValue) => runtime.normalizeSearchText(office?.name || '') === target) || null;
  };
  
  runtime.formatDayName = function formatDayName(day: RuntimeValue) {
      const labels: Record<number, string> = {
          1: 'Segunda-feira',
          2: 'Terça-feira',
          3: 'Quarta-feira',
          4: 'Quinta-feira',
          5: 'Sexta-feira',
          6: 'Sábado',
          7: 'Domingo',
      };
      return labels[Number(day)] || `Dia ${day}`;
  };
  
  runtime.formatDayShort = function formatDayShort(day: RuntimeValue) {
      const labels: Record<number, string> = {
          1: 'Seg',
          2: 'Ter',
          3: 'Qua',
          4: 'Qui',
          5: 'Sex',
          6: 'Sáb',
          7: 'Dom',
      };
      return labels[Number(day)] || `Dia ${day}`;
  };
  
  runtime.formatScheduleList = function formatScheduleList(office: RuntimeValue) {
      const schedules = Array.isArray(office?.schedules) ? office.schedules : [];
      if (!schedules.length)
          return '-';
      return schedules
          .map((slot: RuntimeValue) => `${runtime.formatDayShort(slot.day_of_week)} ${slot.start}-${slot.end}`)
          .join(', ');
  };
  
  runtime.renderModularOfficeAlunoPreview = function renderModularOfficeAlunoPreview() {
      if (!runtime.modularPreviewAluno1400 || !runtime.modularPreviewAluno1540)
          return;
      const selectedDay = Number(runtime.modularPreviewDayInput?.value || 1);
      const by1400: RuntimeValue[] = [];
      const by1540: RuntimeValue[] = [];
      runtime.modularOffices.forEach((office: RuntimeValue) => {
          if (!office || !runtime.officeAvailableInSelectedPeriod(office))
              return;
          const schedules = Array.isArray(office.schedules) ? office.schedules : [];
          schedules.forEach((slot: RuntimeValue) => {
              if (Number(slot.day_of_week) !== selectedDay)
                  return;
              const item = {
                  id: office.id,
                  name: office.name || 'Oficina Modular',
                  teacher_name: office.teacher_name || 'Professor(a) não informado(a)',
                  status_quorum: office.status_quorum || 'LIVRE',
              };
              if (slot.start === '14:00' && slot.end === '15:00') {
                  by1400.push(item);
              }
              else if (slot.start === '15:40' && slot.end === '16:40') {
                  by1540.push(item);
              }
          });
      });
      const sortByName = (a: RuntimeValue, b: RuntimeValue) => runtime.normalizeSearchText(a.name).localeCompare(runtime.normalizeSearchText(b.name), 'pt-BR');
      by1400.sort(sortByName);
      by1540.sort(sortByName);
      const renderItems = (items: RuntimeValue) => {
          if (!items.length) {
              return '<div class="muted">Nenhuma oficina neste horário para o dia selecionado.</div>';
          }
          return items
              .map((item: RuntimeValue) => `
            <div class="office-preview-item">
              <strong>${runtime.escapeHtml(item.name)}</strong>
              <div class="meta">Professor(a): ${runtime.escapeHtml(item.teacher_name)}</div>
              <div class="meta">Status: ${runtime.escapeHtml(item.status_quorum)}</div>
            </div>
          `)
              .join('');
      };
      runtime.modularPreviewAluno1400.innerHTML = renderItems(by1400);
      runtime.modularPreviewAluno1540.innerHTML = renderItems(by1540);
  };
  
  runtime.renderModularOfficeSecretariaPreview = function renderModularOfficeSecretariaPreview() {
      if (!runtime.modularPreviewSecretariaBody)
          return;
      const items = [...runtime.modularOffices]
          .filter((office: RuntimeValue) => runtime.officeAvailableInSelectedPeriod(office))
          .sort((a: RuntimeValue, b: RuntimeValue) => runtime.normalizeSearchText(a?.name || '').localeCompare(runtime.normalizeSearchText(b?.name || ''), 'pt-BR'));
      if (!items.length) {
          runtime.modularPreviewSecretariaBody.innerHTML = '<tr><td colspan="5">Nenhuma oficina cadastrada.</td></tr>';
          return;
      }
      runtime.modularPreviewSecretariaBody.innerHTML = items
          .map((office: RuntimeValue) => {
          const days = Array.isArray(office.days_of_week) ? office.days_of_week.map(runtime.formatDayShort).join(', ') : '-';
          const status = office.active ? (office.status_quorum || 'LIVRE') : 'INATIVA';
          return `
          <tr>
            <td>${runtime.escapeHtml(office.name || '-')}</td>
            <td>${runtime.escapeHtml(office.teacher_name || '-')}</td>
            <td>${runtime.escapeHtml(days || '-')}</td>
            <td>${runtime.escapeHtml(runtime.formatScheduleList(office))}</td>
            <td>${runtime.escapeHtml(status)}</td>
          </tr>
        `;
      })
          .join('');
  };
  
  runtime.renderModularOfficeAdminPreview = function renderModularOfficeAdminPreview() {
      if (!runtime.modularPreviewAdminBody)
          return;
      const items = [...runtime.modularOffices]
          .filter((office: RuntimeValue) => runtime.officeAvailableInSelectedPeriod(office))
          .sort((a: RuntimeValue, b: RuntimeValue) => runtime.normalizeSearchText(a?.name || '').localeCompare(runtime.normalizeSearchText(b?.name || ''), 'pt-BR'));
      if (!items.length) {
          runtime.modularPreviewAdminBody.innerHTML = '<tr><td colspan="6">Nenhuma oficina cadastrada.</td></tr>';
          return;
      }
      runtime.modularPreviewAdminBody.innerHTML = items
          .map((office: RuntimeValue) => {
          const visibleMonth = runtime.officeAvailableInSelectedPeriod(office) ? 'Sim' : 'Não';
          return `
          <tr>
            <td>${runtime.escapeHtml(office.code || '-')}</td>
            <td>${runtime.escapeHtml(office.name || '-')}</td>
            <td>${runtime.escapeHtml(office.tipo || '-')}</td>
            <td>${runtime.escapeHtml(String(office.capacity ?? 0))}</td>
            <td>${runtime.escapeHtml(runtime.formatScheduleList(office))}</td>
            <td>${runtime.escapeHtml(visibleMonth)}</td>
          </tr>
        `;
      })
          .join('');
  };
  
  runtime.renderModularOfficePreviews = function renderModularOfficePreviews() {
      runtime.renderModularOfficeAlunoPreview();
      runtime.renderModularOfficeSecretariaPreview();
      runtime.renderModularOfficeAdminPreview();
  };
  
  runtime.loadModularOffices = async function loadModularOffices(force: RuntimeValue = false) {
      if (!runtime.tabOficinasModulares)
          return;
      if (runtime.modularOfficesLoaded && !force) {
          runtime.renderModularOfficePreviews();
          return;
      }
      runtime.setModularOfficeMessage('Carregando oficinas modulares...');
      try {
          const res = await fetch('/api/admin-oficinas-modulares.php', {
              headers: { Accept: 'application/json' },
          });
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.setModularOfficeMessage(data?.error || 'Falha ao carregar oficinas modulares.', true);
              return;
          }
          runtime.modularOffices = Array.isArray(data.items) ? data.items : [];
          runtime.populateModularCatalogAndTeacherLists(data);
          runtime.modularOfficesLoaded = true;
          runtime.renderModularOfficePreviews();
          runtime.setModularOfficeMessage(`Oficinas carregadas: ${runtime.modularOffices.length}.`);
      }
      catch {
          runtime.setModularOfficeMessage('Falha ao carregar oficinas modulares.', true);
      }
  };
  
  runtime.createModularOffice = async function createModularOffice() {
      if (!runtime.modularCreateButton)
          return;
      const name = String(runtime.modularCreateNameInput?.value || '').trim();
      const teacherName = String(runtime.modularCreateTeacherInput?.value || '').trim();
      const rawMonth = Number(runtime.modularCreateMonthInput?.value || 0);
      const rawYear = Number(runtime.modularCreateYearInput?.value || 0);
      if (!Number.isInteger(rawMonth) || rawMonth < 1 || rawMonth > 12) {
          runtime.setModularOfficeMessage('Informe um mês válido para a grade mensal.', true);
          return;
      }
      if (!Number.isInteger(rawYear) || rawYear < 2025 || rawYear > 2099) {
          runtime.setModularOfficeMessage('Informe um ano válido para a grade mensal.', true);
          return;
      }
      const period = runtime.getSelectedModularPeriod();
      const selectedWeekSlots = [...runtime.modularCreateWeekSlotInputs]
          .filter((input: RuntimeValue) => input.checked)
          .map((input: RuntimeValue) => String(input.value || '').trim())
          .filter((value: RuntimeValue) => /^\d_[12]$/.test(value));
      if (!name) {
          runtime.setModularOfficeMessage('Informe o nome da Oficina Modular.', true);
          return;
      }
      if (!teacherName) {
          runtime.setModularOfficeMessage('Informe o nome do(a) professor(a).', true);
          return;
      }
      if (!selectedWeekSlots.length) {
          runtime.setModularOfficeMessage('Selecione pelo menos um dia/horário semanal da oficina.', true);
          return;
      }
      const originalLabel = runtime.modularCreateButton.textContent;
      runtime.modularCreateButton.setAttribute('disabled', 'disabled');
      runtime.modularCreateButton.textContent = 'Criando...';
      runtime.setModularOfficeMessage('Criando oficina modular...');
      try {
          const res = await fetch('/api/admin-oficinas-modulares.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                  action: 'create',
                  month: period.month,
                  year: period.year,
                  name,
                  teacher_name: teacherName,
                  week_slots: selectedWeekSlots,
              }),
          });
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.setModularOfficeMessage(data?.error || 'Falha ao criar oficina modular.', true);
              return;
          }
          runtime.modularOffices = Array.isArray(data.items) ? data.items : runtime.modularOffices;
          runtime.populateModularCatalogAndTeacherLists(data);
          runtime.modularOfficesLoaded = true;
          runtime.renderModularOfficePreviews();
          if (runtime.modularCreateNameInput)
              runtime.modularCreateNameInput.value = '';
          if (runtime.modularCreateTeacherInput)
              runtime.modularCreateTeacherInput.value = '';
          runtime.modularCreateWeekSlotInputs.forEach((input: RuntimeValue) => {
              input.checked = false;
          });
          runtime.setModularOfficeMessage(data?.message || 'Oficina modular criada com sucesso.');
      }
      catch {
          runtime.setModularOfficeMessage('Falha ao criar oficina modular.', true);
      }
      finally {
          runtime.modularCreateButton.removeAttribute('disabled');
          runtime.modularCreateButton.textContent = originalLabel;
      }
  };
  
  if (runtime.modularPreviewDayInput) {
      runtime.modularPreviewDayInput.addEventListener('change', () => {
          runtime.renderModularOfficeAlunoPreview();
      });
  }
  
  if (runtime.modularCreateMonthInput) {
      runtime.modularCreateMonthInput.addEventListener('change', () => {
          runtime.renderModularOfficePreviews();
      });
  }
  
  if (runtime.modularCreateYearInput) {
      runtime.modularCreateYearInput.addEventListener('change', () => {
          runtime.renderModularOfficePreviews();
      });
  }
  
  if (runtime.modularCreateNameInput) {
      const syncTeacherFromCatalog = () => {
          const office = runtime.findCatalogOfficeByName(runtime.modularCreateNameInput.value);
          if (!office)
              return;
          if (runtime.modularCreateTeacherInput && !String(runtime.modularCreateTeacherInput.value || '').trim()) {
              runtime.modularCreateTeacherInput.value = String(office.teacher_name || '').trim();
          }
      };
      runtime.modularCreateNameInput.addEventListener('change', syncTeacherFromCatalog);
      runtime.modularCreateNameInput.addEventListener('blur', syncTeacherFromCatalog);
  }
}
