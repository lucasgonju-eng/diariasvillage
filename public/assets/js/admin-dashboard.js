const tabs = document.querySelectorAll('[data-tab]');
console.info('[admin-dashboard] bootstrap ok', { tabs: tabs.length });
const tabEntries = document.querySelector('#tab-entries');
const tabCharges = document.querySelector('#tab-charges');
const tabInadimplentes = document.querySelector('#tab-inadimplentes');
const tabRecebidas = document.querySelector('#tab-recebidas');
const tabSemWhatsapp = document.querySelector('#tab-sem-whatsapp');
const tabDuplicados = document.querySelector('#tab-duplicados');
const tabPendencias = document.querySelector('#tab-pendencias');
const tabResetSenha = document.querySelector('#tab-reset-senha');
const tabFluxoCaixa = document.querySelector('#tab-fluxo-caixa');
const studentInput = document.querySelector('#charge-student');
const studentList = document.querySelector('#students-list');
const chargeList = document.querySelector('#charge-list');
const sendChargesButton = document.querySelector('#send-charges');
const chargeMessage = document.querySelector('#charge-message');
const sendSelectedPendingButton = document.querySelector('#send-selected-pending');
const selectAllPendingInput = document.querySelector('#select-all-pending');
const sendPendingMessage = document.querySelector('#send-pending-message');
const viewUserStudentInput = document.querySelector('#admin-view-user-student');
const viewUserStudentsList = document.querySelector('#admin-students-list');
const viewUserButton = document.querySelector('#admin-view-user-btn');
const addGuardianButton = document.querySelector('#admin-add-guardian-btn');
const viewUserForm = document.querySelector('#admin-view-user-form');
const viewUserStudentNameInput = document.querySelector('#view-user-student-name');
const viewUserParentNameInput = document.querySelector('#view-user-parent-name');
const viewUserParentEmailInput = document.querySelector('#view-user-parent-email');
const viewUserParentPhoneInput = document.querySelector('#view-user-parent-phone');
const viewUserParentDocumentInput = document.querySelector('#view-user-parent-document');
const viewUserForceCreateInput = document.querySelector('#view-user-force-create');
const viewUserSaveGuardianButton = document.querySelector('#view-user-save-guardian');
const viewUserCancelGuardianButton = document.querySelector('#view-user-cancel-guardian');
const viewUserFormMessage = document.querySelector('#view-user-form-message');

const selectedStudents = new Set();
const guardianCache = new Map();
const studentNames = new Set();

function setActiveTab(name) {
  if (
    !tabEntries ||
    !tabCharges ||
    !tabInadimplentes ||
    !tabRecebidas ||
    !tabSemWhatsapp ||
    !tabDuplicados ||
    !tabPendencias
  ) {
    return;
  }
  tabEntries.classList.toggle('hidden', name !== 'entries');
  tabCharges.classList.toggle('hidden', name !== 'charges');
  tabInadimplentes.classList.toggle('hidden', name !== 'inadimplentes');
  tabRecebidas.classList.toggle('hidden', name !== 'recebidas');
  tabSemWhatsapp.classList.toggle('hidden', name !== 'sem-whatsapp');
  tabDuplicados.classList.toggle('hidden', name !== 'duplicados');
  tabPendencias.classList.toggle('hidden', name !== 'pendencias');
  if (tabResetSenha) tabResetSenha.classList.toggle('hidden', name !== 'reset-senha');
  if (tabFluxoCaixa) tabFluxoCaixa.classList.toggle('hidden', name !== 'fluxo-caixa');
  tabs.forEach((btn) => {
    const isActive = btn.dataset.tab === name;
    btn.classList.toggle('btn-primary', isActive);
    btn.classList.toggle('admin-tab', !isActive);
    btn.style.opacity = isActive ? '1' : '0.95';
  });
}

const cashflowFromInput = document.querySelector('#cashflow-from');
const cashflowToInput = document.querySelector('#cashflow-to');
const cashflowStudentInput = document.querySelector('#cashflow-student');
const cashflowEnrollmentInput = document.querySelector('#cashflow-enrollment');
const cashflowDayTypeInput = document.querySelector('#cashflow-day-type');
const cashflowStatusInput = document.querySelector('#cashflow-status');
const cashflowBillingTypeInput = document.querySelector('#cashflow-billing-type');
const cashflowExcludeStudentInput = document.querySelector('#cashflow-exclude-student');
const cashflowExcludeTermInput = document.querySelector('#cashflow-exclude-term');
const cashflowSearchButton = document.querySelector('#cashflow-search');
const cashflowClearButton = document.querySelector('#cashflow-clear');
const cashflowMessage = document.querySelector('#cashflow-message');
const cashflowSummary = document.querySelector('#cashflow-summary');
const cashflowTbody = document.querySelector('#cashflow-tbody');
let cashflowLoaded = false;

function getCashflowDefaultFromDate() {
  const now = new Date();
  const year = now.getFullYear();
  return `${year}-01-05`;
}

function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number(value || 0),
  );
}

function formatDateBR(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('pt-BR');
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function setCashflowMessage(text, isError = false) {
  if (!cashflowMessage) return;
  cashflowMessage.textContent = text;
  cashflowMessage.className = `charge-message ${isError ? 'error' : ''}`.trim();
}

function renderCashflowRows(items) {
  if (!cashflowTbody) return;
  if (!items.length) {
    cashflowTbody.innerHTML = '<tr><td colspan="7">Nenhum registro para os filtros selecionados.</td></tr>';
    return;
  }
  cashflowTbody.innerHTML = items
    .map(
      (item) => `
      <tr>
        <td>${escapeHtml(item.student_name || '-')}</td>
        <td>${formatDateBR(item.date)}</td>
        <td>${escapeHtml(item.day_use_type || '-')}</td>
        <td>${escapeHtml(item.enrollment || '-')}</td>
        <td>${formatCurrency(item.amount)}</td>
        <td>${escapeHtml(item.status || '-')}</td>
        <td>${escapeHtml(item.billing_type || '-')}</td>
      </tr>
    `,
    )
    .join('');
}

function renderCashflowSummary(totals, period) {
  if (!cashflowSummary) return;
  if (!totals) {
    cashflowSummary.innerHTML = '';
    return;
  }
  cashflowSummary.innerHTML = `
    <span class="cashflow-pill">Período: ${formatDateBR(period?.from)} até ${formatDateBR(period?.to)}</span>
    <span class="cashflow-pill">Registros: ${totals.count || 0}</span>
    <span class="cashflow-pill">Total geral: ${formatCurrency(totals.amount || 0)}</span>
    <span class="cashflow-pill">Total pago: ${formatCurrency(totals.paid_amount || 0)}</span>
  `;
}

async function loadCashflow() {
  if (
    !cashflowFromInput ||
    !cashflowToInput ||
    !cashflowSearchButton ||
    !cashflowTbody ||
    !cashflowStudentInput ||
    !cashflowEnrollmentInput ||
    !cashflowDayTypeInput ||
    !cashflowStatusInput ||
    !cashflowBillingTypeInput ||
    !cashflowExcludeStudentInput ||
    !cashflowExcludeTermInput
  ) {
    return;
  }

  const params = new URLSearchParams({
    from: cashflowFromInput.value,
    to: cashflowToInput.value,
  });
  if (cashflowStudentInput.value.trim()) params.set('student_name', cashflowStudentInput.value.trim());
  if (cashflowEnrollmentInput.value.trim())
    params.set('enrollment', cashflowEnrollmentInput.value.trim());
  if (cashflowDayTypeInput.value) params.set('day_use_type', cashflowDayTypeInput.value);
  if (cashflowStatusInput.value) params.set('status', cashflowStatusInput.value);
  if (cashflowBillingTypeInput.value) params.set('billing_type', cashflowBillingTypeInput.value);
  if (cashflowExcludeStudentInput.value.trim())
    params.set('exclude_student', cashflowExcludeStudentInput.value.trim());
  if (cashflowExcludeTermInput.value.trim())
    params.set('exclude_term', cashflowExcludeTermInput.value.trim());

  cashflowSearchButton.setAttribute('disabled', 'disabled');
  setCashflowMessage('Carregando fluxo de caixa...');
  try {
    const res = await fetch(`/api/admin-cashflow.php?${params.toString()}`);
    const data = await res.json();
    if (!data.ok) {
      setCashflowMessage(data.error || 'Falha ao carregar fluxo de caixa.', true);
      renderCashflowRows([]);
      renderCashflowSummary(null, null);
      return;
    }
    renderCashflowRows(data.items || []);
    renderCashflowSummary(data.totals || null, data.period || null);
    setCashflowMessage('');
    cashflowLoaded = true;
  } catch {
    setCashflowMessage('Falha ao carregar fluxo de caixa.', true);
    renderCashflowRows([]);
    renderCashflowSummary(null, null);
  } finally {
    cashflowSearchButton.removeAttribute('disabled');
  }
}

async function addChargeItem(studentName) {
  if (!studentName || selectedStudents.has(studentName)) return;
  selectedStudents.add(studentName);

  const wrapper = document.createElement('div');
  wrapper.className = 'charge-item';
  wrapper.dataset.student = studentName;
  wrapper.innerHTML = `
    <div class="charge-header">
      <strong>Aluno: ${studentName}</strong>
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

  wrapper.querySelector('.charge-header button').addEventListener('click', () => {
    selectedStudents.delete(studentName);
    wrapper.remove();
  });

  const dateList = wrapper.querySelector('.date-list');
  dateList.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
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
      if (row) row.remove();
    }
  });

  dateList.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.name !== 'day_use_dates[]') return;

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

  chargeList.appendChild(wrapper);

  const selector = wrapper.querySelector('[name="guardian_selector"]');
  const nameInput = wrapper.querySelector('[name="guardian_name"]');
  const emailInput = wrapper.querySelector('[name="guardian_email"]');
  const phoneInput = wrapper.querySelector('[name="guardian_whatsapp"]');
  const docInput = wrapper.querySelector('[name="guardian_document"]');

  function fillGuardianFields(guardian) {
    if (!guardian) return;
    if (nameInput) nameInput.value = guardian.parent_name || '';
    if (emailInput) emailInput.value = guardian.email || '';
    if (phoneInput) phoneInput.value = guardian.parent_phone || '';
    if (docInput) docInput.value = guardian.parent_document || '';
  }

  function bindGuardianSelector(guardians) {
    if (!selector) return;
    const list = Array.isArray(guardians) ? guardians : [];
    selector.innerHTML = '<option value="">Digite os dados manualmente</option>';
    list.forEach((guardian, index) => {
      const option = document.createElement('option');
      const labelName = guardian.parent_name || 'Sem nome';
      const labelEmail = guardian.email || 'sem e-mail';
      option.value = String(index);
      option.textContent = `${labelName} (${labelEmail})`;
      selector.appendChild(option);
    });

    selector.addEventListener('change', () => {
      const selectedIndex = Number(selector.value);
      if (Number.isNaN(selectedIndex) || selectedIndex < 0 || !list[selectedIndex]) {
        return;
      }
      fillGuardianFields(list[selectedIndex]);
    });

    if (list.length > 0) {
      selector.value = '0';
      fillGuardianFields(list[0]);
    }
  }

  if (guardianCache.has(studentName)) {
    const cached = guardianCache.get(studentName);
    bindGuardianSelector(cached);
    return;
  }

  try {
    const res = await fetch(`/api/admin-guardians-by-student.php?name=${encodeURIComponent(studentName)}`);
    const data = await res.json();
    let guardians = [];
    if (data.ok) {
      if (Array.isArray(data.guardians)) {
        guardians = data.guardians;
      } else if (data.guardian) {
        guardians = [data.guardian];
      }
    }
    guardianCache.set(studentName, guardians);
    bindGuardianSelector(guardians);
  } catch (err) {
    guardianCache.set(studentName, []);
  }
}

async function loadStudents() {
  if (!studentList && !viewUserStudentsList) return;
  const res = await fetch('/api/students.php');
  const data = await res.json();
  if (!data.ok) return;
  if (studentList) studentList.innerHTML = '';
  if (viewUserStudentsList) viewUserStudentsList.innerHTML = '';
  studentNames.clear();
  data.students.forEach((student) => {
    const option = document.createElement('option');
    option.value = student.name;
    if (studentList) studentList.appendChild(option);
    if (viewUserStudentsList) {
      const optionView = document.createElement('option');
      optionView.value = student.name;
      viewUserStudentsList.appendChild(optionView);
    }
    studentNames.add(student.name);
  });
}

function tryAddStudentFromInput() {
  if (!studentInput) return;
  const value = studentInput.value.trim();
  if (!value || !studentNames.has(value)) return;
  addChargeItem(value);
  studentInput.value = '';
}

function collectCharges() {
  const items = [...chargeList.querySelectorAll('.charge-item')];
  return items.map((item) => ({
    student_name: item.dataset.student,
    guardian_name: item.querySelector('[name="guardian_name"]').value.trim(),
    guardian_email: item.querySelector('[name="guardian_email"]').value.trim(),
    guardian_whatsapp: item.querySelector('[name="guardian_whatsapp"]').value.trim(),
    guardian_document: item.querySelector('[name="guardian_document"]').value.trim(),
    day_use_dates: [...item.querySelectorAll('[name="day_use_dates[]"]')]
      .map((input) => input.value.trim())
      .filter(Boolean),
  }));
}

function showChargeMessage(text, isError = false) {
  if (!chargeMessage) return;
  chargeMessage.textContent = text;
  chargeMessage.className = `charge-message ${isError ? 'error' : 'success'}`;
}

function formatIsoDateBr(value) {
  if (!value) return value;
  const parts = String(value).split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function buildDuplicatesPopupMessage(duplicates) {
  const lines = ['Atenção: encontramos possíveis coincidências de diária já registrada:'];
  duplicates.slice(0, 15).forEach((dup) => {
    const student = dup.student_name || '-';
    const date = formatIsoDateBr(dup.date || '-');
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
}

function resetChargeForm() {
  if (chargeList) {
    chargeList.innerHTML = '';
  }
  selectedStudents.clear();
  if (studentInput) {
    studentInput.value = '';
  }
}

tabs.forEach((btn) => {
  btn.addEventListener('click', () => {
    setActiveTab(btn.dataset.tab);
    if (btn.dataset.tab === 'fluxo-caixa' && !cashflowLoaded) {
      loadCashflow();
    }
  });
});

if (studentInput) {
  studentInput.addEventListener('change', tryAddStudentFromInput);
  studentInput.addEventListener('blur', tryAddStudentFromInput);
  studentInput.addEventListener('input', tryAddStudentFromInput);
  studentInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      tryAddStudentFromInput();
    }
  });
}

if (sendChargesButton) {
  sendChargesButton.addEventListener('click', async () => {
    const charges = collectCharges();
    if (!charges.length) {
      showChargeMessage('Selecione ao menos um aluno.', true);
      return;
    }

    const invalid = charges.find(
      (item) => !item.guardian_name || !item.guardian_email || !item.student_name,
    );
    if (invalid) {
      showChargeMessage('Preencha nome e e-mail do responsável para todos os alunos.', true);
      return;
    }

    sendChargesButton.disabled = true;
    sendChargesButton.textContent = 'Enviando...';
    showChargeMessage('');

    try {
      const duplicateRes = await fetch('/api/admin-check-duplicate-dayuse.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ charges }),
      });
      const duplicateData = await duplicateRes.json();
      const duplicates = Array.isArray(duplicateData?.duplicates) ? duplicateData.duplicates : [];
      if (!duplicateRes.ok || !duplicateData?.ok) {
        showChargeMessage(duplicateData?.error || 'Falha ao validar coincidências.', true);
        return;
      }
      if (duplicates.length > 0) {
        const wantsToContinue = window.confirm(buildDuplicatesPopupMessage(duplicates));
        if (!wantsToContinue) {
          showChargeMessage('Envio cancelado para revisão de coincidências.', true);
          return;
        }
      }

      const res = await fetch('/api/admin-charge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ charges }),
      });
      const data = await res.json();
      if (!data.ok) {
        showChargeMessage(data.error || 'Falha ao enviar cobranças.', true);
      } else {
        const failures = data.results.filter((item) => !item.ok);
        if (failures.length) {
          showChargeMessage('Algumas pendências não foram salvas. Verifique os dados.', true);
        } else {
          showChargeMessage('Pendências salvas no SaaS (sem envio).');
          resetChargeForm();
          window.location.reload();
        }
      }
    } catch (err) {
      showChargeMessage('Falha ao enviar cobranças.', true);
    } finally {
      sendChargesButton.disabled = false;
      sendChargesButton.textContent = 'Salvar pendências (sem enviar)';
    }
  });
}

function showSendPendingMessage(text, isError = false) {
  if (!sendPendingMessage) return;
  sendPendingMessage.textContent = text;
  sendPendingMessage.className = `charge-message ${isError ? 'error' : 'success'}`;
}

if (selectAllPendingInput) {
  selectAllPendingInput.addEventListener('change', () => {
    const checked = !!selectAllPendingInput.checked;
    document.querySelectorAll('.pending-send-checkbox').forEach((checkbox) => {
      if (checkbox instanceof HTMLInputElement) checkbox.checked = checked;
    });
  });
}

if (sendSelectedPendingButton) {
  sendSelectedPendingButton.addEventListener('click', async () => {
    const selected = [...document.querySelectorAll('.pending-send-checkbox')]
      .filter((el) => el instanceof HTMLInputElement && el.checked)
      .map((el) => el.value)
      .filter(Boolean);

    if (!selected.length) {
      showSendPendingMessage('Selecione ao menos uma cobrança pendente da fila.', true);
      return;
    }

    sendSelectedPendingButton.setAttribute('disabled', 'disabled');
    const originalText = sendSelectedPendingButton.textContent;
    sendSelectedPendingButton.textContent = 'Enviando...';
    showSendPendingMessage('');

    try {
      const res = await fetch('/api/admin-send-pending-charges.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payment_ids: selected }),
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) {
        showSendPendingMessage(data?.error || 'Falha ao enviar cobranças pendentes.', true);
        return;
      }
      showSendPendingMessage('Cobranças pendentes enviadas com sucesso.');
      window.location.reload();
    } catch {
      showSendPendingMessage('Falha ao enviar cobranças pendentes.', true);
    } finally {
      sendSelectedPendingButton.removeAttribute('disabled');
      sendSelectedPendingButton.textContent = originalText;
    }
  });
}

const mergeMessage = document.querySelector('#merge-message');
const pendenciaMessage = document.querySelector('#pendencia-message');
const pendenciaButtons = document.querySelectorAll('.js-check-pendencia');
const pendenciaCpfInput = document.querySelector('#pendencia-cpf');
const pendenciaCpfButton = document.querySelector('#check-pendencia-cpf');
const pendenciaAsaasInput = document.querySelector('#pendencia-asaas-id');
const pendenciaAsaasButton = document.querySelector('#check-pendencia-asaas');
const pendenciaLinkButtons = document.querySelectorAll('.js-link-asaas');
const pendenciaSettleButtons = document.querySelectorAll('.js-settle-pendencia');
const syncChargesPaymentsButton = document.querySelector('#sync-charges-payments-btn');
const syncChargesPaymentsMessage = document.querySelector('#sync-charges-payments-message');

function normalizeCpf(value) {
  return value.replace(/\D/g, '').slice(0, 11);
}

function findPendenciaRow(id) {
  if (!id) return null;
  return document.querySelector(`[data-pendencia-id="${id}"]`);
}

function updatePendenciaRow(row, data) {
  if (!row) return;
  const paidCell = row.querySelector('[data-col="paid-at"]');
  const statusCell = row.querySelector('[data-col="asaas-status"]');
  const actionCell = row.querySelector('[data-col="action"]');
  if (statusCell) statusCell.textContent = data.status || '-';
  if (data.paid_at && paidCell) {
    const date = new Date(data.paid_at);
    paidCell.textContent = isNaN(date.getTime())
      ? data.paid_at
      : date.toLocaleString('pt-BR');
    if (actionCell) actionCell.textContent = '-';
  }
}

pendenciaButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const pendenciaId = button.dataset.id;
    if (!pendenciaId) return;

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Checando pagamento...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-check-pendencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: pendenciaId }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
          pendenciaMessage.className = 'charge-message error';
        }
      } else {
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
          if (pendenciaMessage) {
            pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
            pendenciaMessage.className = 'charge-message success';
          }
        } else if (pendenciaMessage) {
          pendenciaMessage.textContent =
            data.status === 'NOT_FOUND'
              ? 'Pagamento não encontrado no Asaas.'
              : 'Pagamento ainda não identificado pelo Asaas.';
          pendenciaMessage.className = 'charge-message';
        }
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao checar pendência.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

if (pendenciaCpfButton && pendenciaCpfInput) {
  pendenciaCpfInput.addEventListener('input', (event) => {
    event.target.value = normalizeCpf(event.target.value);
  });
  pendenciaCpfButton.addEventListener('click', async () => {
    const cpf = normalizeCpf(pendenciaCpfInput.value || '');
    if (cpf.length !== 11) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'CPF inválido.';
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    pendenciaCpfButton.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Checando pagamento...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-check-pendencia-by-cpf.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cpf }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }

      const row = findPendenciaRow(data.pendencia_id);
      updatePendenciaRow(row, data);
      if (data.paid_at) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
          pendenciaMessage.className = 'charge-message success';
        }
        return;
      }
      if (pendenciaMessage) {
        pendenciaMessage.textContent =
          data.status === 'NOT_FOUND'
            ? 'Pagamento não encontrado no Asaas.'
            : 'Pagamento ainda não identificado pelo Asaas.';
        pendenciaMessage.className = 'charge-message';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao checar pendência.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      pendenciaCpfButton.removeAttribute('disabled');
    }
  });
}

if (pendenciaAsaasButton && pendenciaAsaasInput) {
  pendenciaAsaasInput.addEventListener('input', (event) => {
    event.target.value = event.target.value.trim().slice(0, 120);
  });
  pendenciaAsaasButton.addEventListener('click', async () => {
    const asaasId = (pendenciaAsaasInput.value || '').trim();
    if (!asaasId) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Informe o número da cobrança Asaas.';
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    pendenciaAsaasButton.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Checando pagamento...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-check-pendencia-by-asaas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ asaas_id: asaasId }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data.error || 'Falha ao checar pendência.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }

      const row = findPendenciaRow(data.pendencia_id);
      updatePendenciaRow(row, data);
      if (data.paid_at) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = 'Pagamento confirmado pelo Asaas.';
          pendenciaMessage.className = 'charge-message success';
        }
        return;
      }
      if (pendenciaMessage) {
        pendenciaMessage.textContent =
          data.status === 'NOT_FOUND'
            ? 'Pagamento não encontrado no Asaas.'
            : 'Pagamento ainda não identificado pelo Asaas.';
        pendenciaMessage.className = 'charge-message';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao checar pendência.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      pendenciaAsaasButton.removeAttribute('disabled');
    }
  });
}

pendenciaLinkButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    const row = button.closest('tr');
    const pendenciaId = row ? row.dataset.pendenciaId : null;
    const input = row ? row.querySelector('[data-col="asaas-link"] input') : null;
    const asaasId = input ? input.value.trim() : '';
    if (!pendenciaId || !asaasId) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Informe a cobrança Asaas para vincular.';
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Vinculando cobrança...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-link-pendencia-by-asaas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pendencia_id: pendenciaId, asaas_id: asaasId }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data.error || 'Falha ao vincular pendência.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }

      updatePendenciaRow(row, data);
      if (data.paid_at) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = 'Cobrança vinculada e paga.';
          pendenciaMessage.className = 'charge-message success';
        }
        return;
      }
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Cobrança vinculada. Aguardando pagamento.';
        pendenciaMessage.className = 'charge-message';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao vincular pendência.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

pendenciaSettleButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    const pendenciaId = button.dataset.id;
    if (!pendenciaId) return;
    if (!confirm('Confirmar baixa manual desta pendência?')) return;

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Dando baixa...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-settle-pendencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: pendenciaId }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data.error || 'Falha ao dar baixa.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }
      const row = findPendenciaRow(pendenciaId);
      updatePendenciaRow(row, { paid_at: data.paid_at, status: 'BAIXA_MANUAL' });
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Baixa manual registrada.';
        pendenciaMessage.className = 'charge-message success';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao dar baixa.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

if (syncChargesPaymentsButton) {
  syncChargesPaymentsButton.addEventListener('click', async () => {
    syncChargesPaymentsButton.setAttribute('disabled', 'disabled');
    const originalText = syncChargesPaymentsButton.textContent;
    syncChargesPaymentsButton.textContent = 'Sincronizando...';
    if (syncChargesPaymentsMessage) {
      syncChargesPaymentsMessage.textContent = 'Executando varredura de cobranças/pagamentos no Asaas...';
      syncChargesPaymentsMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-sync-charges-payments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) {
        if (syncChargesPaymentsMessage) {
          syncChargesPaymentsMessage.textContent = data?.error || 'Falha ao sincronizar cobranças e pagamentos.';
          syncChargesPaymentsMessage.className = 'charge-message error';
        }
        return;
      }

      const summary = data.summary || {};
      if (syncChargesPaymentsMessage) {
        syncChargesPaymentsMessage.textContent = `Sincronização concluída. Payments verificados: ${summary.payments_checked || 0}, atualizados para pago: ${summary.payments_paid_updated || 0}, cancelados: ${summary.payments_canceled_updated || 0}, não encontrados: ${summary.payments_not_found || 0}. Pendências verificadas: ${summary.pendencias_checked || 0}, pagas: ${summary.pendencias_paid_updated || 0}, desvinculadas: ${summary.pendencias_unlinked || 0}.`;
        syncChargesPaymentsMessage.className = 'charge-message success';
      }
      setTimeout(() => window.location.reload(), 1000);
    } catch {
      if (syncChargesPaymentsMessage) {
        syncChargesPaymentsMessage.textContent = 'Falha ao sincronizar cobranças e pagamentos.';
        syncChargesPaymentsMessage.className = 'charge-message error';
      }
    } finally {
      syncChargesPaymentsButton.removeAttribute('disabled');
      syncChargesPaymentsButton.textContent = originalText;
    }
  });
}

const mergeButtons = document.querySelectorAll('.js-merge-duplicates');
mergeButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const primaryId = button.dataset.primary;
    const duplicatesRaw = button.dataset.duplicates || '[]';
    let duplicates = [];
    try {
      duplicates = JSON.parse(duplicatesRaw);
    } catch {
      duplicates = [];
    }
    if (!primaryId || !duplicates.length) return;

    button.setAttribute('disabled', 'disabled');
    if (mergeMessage) {
      mergeMessage.textContent = 'Mesclando duplicados...';
      mergeMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-merge-duplicates.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ primary_id: primaryId, duplicate_ids: duplicates }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (mergeMessage) {
          mergeMessage.textContent = data.error || 'Falha ao mesclar duplicados.';
          mergeMessage.className = 'charge-message error';
        }
      } else if (mergeMessage) {
        mergeMessage.textContent = 'Duplicados mesclados com sucesso.';
        mergeMessage.className = 'charge-message success';
        setTimeout(() => window.location.reload(), 800);
      }
    } catch {
      if (mergeMessage) {
        mergeMessage.textContent = 'Falha ao mesclar duplicados.';
        mergeMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

const resetCpfInput = document.querySelector('#reset-cpf');
const resetSenhaNovaInput = document.querySelector('#reset-senha-nova');
const resetSenhaConfirmInput = document.querySelector('#reset-senha-confirm');
const resetSenhaBtn = document.querySelector('#reset-senha-btn');
const resetSenhaMessage = document.querySelector('#reset-senha-message');

if (resetSenhaBtn && resetCpfInput && resetSenhaNovaInput && resetSenhaConfirmInput) {
  resetCpfInput.addEventListener('input', (event) => {
    event.target.value = normalizeCpf(event.target.value);
  });
  resetSenhaBtn.addEventListener('click', async () => {
    const cpf = normalizeCpf(resetCpfInput.value || '');
    const novaSenha = (resetSenhaNovaInput.value || '').trim();
    const confirmSenha = (resetSenhaConfirmInput.value || '').trim();

    if (cpf.length !== 11) {
      if (resetSenhaMessage) {
        resetSenhaMessage.textContent = 'Informe um CPF válido (11 dígitos).';
        resetSenhaMessage.className = 'charge-message error';
      }
      return;
    }
    if (novaSenha.length < 6) {
      if (resetSenhaMessage) {
        resetSenhaMessage.textContent = 'A nova senha deve ter pelo menos 6 caracteres.';
        resetSenhaMessage.className = 'charge-message error';
      }
      return;
    }
    if (novaSenha !== confirmSenha) {
      if (resetSenhaMessage) {
        resetSenhaMessage.textContent = 'As senhas não conferem.';
        resetSenhaMessage.className = 'charge-message error';
      }
      return;
    }

    resetSenhaBtn.setAttribute('disabled', 'disabled');
    if (resetSenhaMessage) {
      resetSenhaMessage.textContent = 'Alterando senha...';
      resetSenhaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-reset-password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cpf, nova_senha: novaSenha }),
      });
      let data;
      try {
        data = await res.json();
      } catch {
        if (resetSenhaMessage) {
          resetSenhaMessage.textContent = `Erro no servidor (${res.status || 'sem resposta'}). Tente novamente.`;
          resetSenhaMessage.className = 'charge-message error';
        }
        return;
      }
      if (!data.ok) {
        const errMsg =
          data.error ||
          data.error_description ||
          data.message ||
          data.msg ||
          'Falha ao resetar senha.';
        if (resetSenhaMessage) {
          resetSenhaMessage.textContent = errMsg;
          resetSenhaMessage.className = 'charge-message error';
        }
      } else {
        if (resetSenhaMessage) {
          const name = data.guardian_name ? ` (${data.guardian_name})` : '';
          resetSenhaMessage.textContent = data.message + name;
          resetSenhaMessage.className = 'charge-message success';
        }
        resetCpfInput.value = '';
        resetSenhaNovaInput.value = '';
        resetSenhaConfirmInput.value = '';
      }
    } catch {
      if (resetSenhaMessage) {
        resetSenhaMessage.textContent = 'Falha ao resetar senha.';
        resetSenhaMessage.className = 'charge-message error';
      }
    } finally {
      resetSenhaBtn.removeAttribute('disabled');
    }
  });
}

if (cashflowFromInput && cashflowToInput) {
  const todayIso = new Date().toISOString().slice(0, 10);
  if (!cashflowFromInput.value) cashflowFromInput.value = getCashflowDefaultFromDate();
  if (!cashflowToInput.value) cashflowToInput.value = todayIso;
}

if (cashflowSearchButton) {
  cashflowSearchButton.addEventListener('click', loadCashflow);
}

if (cashflowClearButton) {
  cashflowClearButton.addEventListener('click', () => {
    if (cashflowFromInput) cashflowFromInput.value = getCashflowDefaultFromDate();
    if (cashflowToInput) cashflowToInput.value = new Date().toISOString().slice(0, 10);
    if (cashflowStudentInput) cashflowStudentInput.value = '';
    if (cashflowEnrollmentInput) cashflowEnrollmentInput.value = '';
    if (cashflowDayTypeInput) cashflowDayTypeInput.value = '';
    if (cashflowStatusInput) cashflowStatusInput.value = '';
    if (cashflowBillingTypeInput) cashflowBillingTypeInput.value = '';
    if (cashflowExcludeStudentInput) cashflowExcludeStudentInput.value = '';
    if (cashflowExcludeTermInput) cashflowExcludeTermInput.value = '';
    loadCashflow();
  });
}

if (viewUserButton && viewUserStudentInput) {
  let viewUserSaveMode = 'open_user';
  const showViewUserForm = (studentName, mode = 'open_user') => {
    viewUserSaveMode = mode;
    if (!viewUserForm) return;
    viewUserForm.classList.remove('hidden');
    if (viewUserStudentNameInput) viewUserStudentNameInput.value = studentName || '';
    if (viewUserParentNameInput) viewUserParentNameInput.value = '';
    if (viewUserParentEmailInput) viewUserParentEmailInput.value = '';
    if (viewUserParentPhoneInput) viewUserParentPhoneInput.value = '';
    if (viewUserParentDocumentInput) viewUserParentDocumentInput.value = '';
    if (viewUserForceCreateInput) viewUserForceCreateInput.checked = mode === 'create_more';
    if (viewUserFormMessage) {
      viewUserFormMessage.textContent = mode === 'create_more'
        ? 'Cadastre um novo responsável para o aluno selecionado.'
        : 'Este aluno ainda não tem responsável. Preencha os dados para cadastrar automaticamente.';
      viewUserFormMessage.className = 'charge-message';
    }
  };
  const hideViewUserForm = () => {
    if (!viewUserForm) return;
    viewUserForm.classList.add('hidden');
    if (viewUserFormMessage) {
      viewUserFormMessage.textContent = '';
      viewUserFormMessage.className = 'charge-message';
    }
  };

  viewUserButton.addEventListener('click', async () => {
    const studentName = viewUserStudentInput.value.trim();
    if (!studentName) {
      alert('Selecione um aluno para abrir o modo usuário.');
      return;
    }
    if (!studentNames.has(studentName)) {
      alert('Aluno não encontrado na lista.');
      return;
    }

    viewUserButton.setAttribute('disabled', 'disabled');
    const originalText = viewUserButton.textContent;
    viewUserButton.textContent = 'Abrindo...';
    try {
      const res = await fetch('/api/admin-view-as-user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student_name: studentName }),
      });
      const data = await res.json();
      if (!data.ok) {
        if (data.code === 'GUARDIAN_NOT_FOUND') {
            showViewUserForm(data.student?.name || studentName, 'open_user');
          return;
        }
        alert(data.error || 'Falha ao abrir visão de usuário.');
        return;
      }
      const url = data.url || '/dashboard.php';
      const win = window.open(url, '_blank', 'noopener');
      if (!win) {
        window.location.href = url;
      }
    } catch {
      alert('Falha ao abrir visão de usuário.');
    } finally {
      viewUserButton.removeAttribute('disabled');
      viewUserButton.textContent = originalText;
    }
  });

  if (viewUserCancelGuardianButton) {
    viewUserCancelGuardianButton.addEventListener('click', () => {
      hideViewUserForm();
    });
  }

  if (addGuardianButton) {
    addGuardianButton.addEventListener('click', () => {
      const studentName = viewUserStudentInput.value.trim();
      if (!studentName) {
        alert('Selecione um aluno para cadastrar responsável.');
        return;
      }
      if (!studentNames.has(studentName)) {
        alert('Aluno não encontrado na lista.');
        return;
      }
      showViewUserForm(studentName, 'create_more');
    });
  }

  if (viewUserSaveGuardianButton) {
    viewUserSaveGuardianButton.addEventListener('click', async () => {
      const studentName =
        (viewUserStudentNameInput && viewUserStudentNameInput.value.trim()) ||
        (viewUserStudentInput && viewUserStudentInput.value.trim()) ||
        '';
      const parentName = (viewUserParentNameInput && viewUserParentNameInput.value.trim()) || '';
      const email = (viewUserParentEmailInput && viewUserParentEmailInput.value.trim()) || '';
      const parentPhone = (viewUserParentPhoneInput && viewUserParentPhoneInput.value.trim()) || '';
      const parentDocument =
        (viewUserParentDocumentInput && viewUserParentDocumentInput.value.trim()) || '';

      if (!studentName || !parentName) {
        if (viewUserFormMessage) {
          viewUserFormMessage.textContent = 'Informe aluno e nome do responsável.';
          viewUserFormMessage.className = 'charge-message error';
        }
        return;
      }

      viewUserSaveGuardianButton.setAttribute('disabled', 'disabled');
      const originalText = viewUserSaveGuardianButton.textContent;
      viewUserSaveGuardianButton.textContent = 'Salvando...';
      if (viewUserFormMessage) {
        viewUserFormMessage.textContent = 'Salvando responsável no banco...';
        viewUserFormMessage.className = 'charge-message';
      }
      try {
        const res = await fetch('/api/admin-upsert-guardian-for-student.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            student_name: studentName,
            parent_name: parentName,
            email,
            parent_phone: parentPhone,
            parent_document: parentDocument,
            force_create: !!(viewUserForceCreateInput && viewUserForceCreateInput.checked),
          }),
        });
        const data = await res.json();
        if (!data.ok) {
          if (viewUserFormMessage) {
            viewUserFormMessage.textContent = data.error || 'Falha ao salvar responsável.';
            viewUserFormMessage.className = 'charge-message error';
          }
          return;
        }

        if (viewUserFormMessage) {
          viewUserFormMessage.textContent = viewUserSaveMode === 'open_user'
            ? 'Responsável salvo. Abrindo visão do usuário em nova aba...'
            : 'Responsável salvo com sucesso. Você pode cadastrar outro.';
          viewUserFormMessage.className = 'charge-message success';
        }
        if (viewUserStudentInput) viewUserStudentInput.value = studentName;
        if (viewUserSaveMode === 'open_user') {
          hideViewUserForm();
          viewUserButton.click();
        } else {
          if (viewUserParentNameInput) viewUserParentNameInput.value = '';
          if (viewUserParentEmailInput) viewUserParentEmailInput.value = '';
          if (viewUserParentPhoneInput) viewUserParentPhoneInput.value = '';
          if (viewUserParentDocumentInput) viewUserParentDocumentInput.value = '';
          if (viewUserForceCreateInput) viewUserForceCreateInput.checked = true;
        }
      } catch {
        if (viewUserFormMessage) {
          viewUserFormMessage.textContent = 'Falha ao salvar responsável.';
          viewUserFormMessage.className = 'charge-message error';
        }
      } finally {
        viewUserSaveGuardianButton.removeAttribute('disabled');
        viewUserSaveGuardianButton.textContent = originalText;
      }
    });
  }
}

setActiveTab('charges');
loadStudents();
