const tabs = document.querySelectorAll('[data-tab]');
window.__adminDashboardBooted = true;
console.info('[admin-dashboard] bootstrap ok', { tabs: tabs.length });
const tabEntries = document.querySelector('#tab-entries');
const tabCharges = document.querySelector('#tab-charges');
const tabChamada = document.querySelector('#tab-chamada');
const tabInadimplentes = document.querySelector('#tab-inadimplentes');
const tabRecebidas = document.querySelector('#tab-recebidas');
const tabSemWhatsapp = document.querySelector('#tab-sem-whatsapp');
const tabDuplicados = document.querySelector('#tab-duplicados');
const tabPendencias = document.querySelector('#tab-pendencias');
const tabMensalistas = document.querySelector('#tab-mensalistas');
const tabExclusoes = document.querySelector('#tab-exclusoes');
const tabResetSenha = document.querySelector('#tab-reset-senha');
const tabFluxoCaixa = document.querySelector('#tab-fluxo-caixa');
const tabDadosAsaas = document.querySelector('#tab-dados-asaas');
const studentInput = document.querySelector('#charge-student');
const studentList = document.querySelector('#students-list');
const chargeList = document.querySelector('#charge-list');
const sendChargesButton = document.querySelector('#send-charges');
const chargeMessage = document.querySelector('#charge-message');
const sendSelectedPendingButton = document.querySelector('#send-selected-pending');
const selectAllPendingInput = document.querySelector('#select-all-pending');
const sendPendingMessage = document.querySelector('#send-pending-message');
const pendingDeleteButtons = document.querySelectorAll('.js-delete-payment');
let inadimplentesDuplicatesPopupShown = false;
let inadimplentesMonthlyPopupShown = false;
const syncRecebidasButton = document.querySelector('#sync-recebidas-btn');
const syncRecebidasMessage = document.querySelector('#sync-recebidas-message');
const viewUserStudentInput = document.querySelector('#admin-view-user-student');
const viewUserStudentsList = document.querySelector('#admin-students-list');
const pendenciaStudentsList = document.querySelector('#pendencia-students-list');
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
const monthlyStudentInput = document.querySelector('#monthly-student');
const monthlySaveButton = document.querySelector('#monthly-save-btn');
const monthlyRemoveButton = document.querySelector('#monthly-remove-btn');
const monthlyMessage = document.querySelector('#monthly-message');
const monthlyTableBody = document.querySelector('#monthly-table-body');
const attendanceDateInput = document.querySelector('#attendance-date');
const attendanceStudentInput = document.querySelector('#attendance-student');
const attendanceOfficeInput = document.querySelector('#attendance-office');
const attendanceAddButton = document.querySelector('#attendance-add-btn');
const attendanceCloseDayButton = document.querySelector('#attendance-close-day-btn');
const attendanceMessage = document.querySelector('#attendance-message');
const attendanceTbody = document.querySelector('#attendance-tbody');
const attendanceDayList = document.querySelector('#attendance-day-list');
const attendanceFilterFromInput = document.querySelector('#attendance-filter-from');
const attendanceFilterToInput = document.querySelector('#attendance-filter-to');
const attendanceFilterButton = document.querySelector('#attendance-filter-btn');
const attendanceClearButton = document.querySelector('#attendance-clear-btn');
const attendanceExportButton = document.querySelector('#attendance-export-btn');
const attendanceStudentsList = document.querySelector('#attendance-students-list');
const attendanceOfficesList = document.querySelector('#attendance-offices-list');

const selectedStudents = new Set();
const guardianCache = new Map();
const studentNames = new Set();
const studentLookupByLabel = new Map();
const MIN_ADMIN_AUTOCOMPLETE_CHARS = 3;
let adminStudents = [];
let monthlyStudents = Array.isArray(window.__monthlyStudents) ? window.__monthlyStudents : [];
const monthlyByStudentId = new Map();
const monthlyByName = new Map();
const adminCanApproveAttendance = window.__adminCanApproveAttendance === true;
const attendanceOfficeById = new Map();
const attendanceOfficeByLabel = new Map();
let attendanceLoaded = false;
let attendanceOfficesLoaded = false;
let attendanceDayQueue = [];

function setActiveTab(name) {
  if (
    !tabEntries ||
    !tabCharges ||
    !tabInadimplentes ||
    !tabRecebidas ||
    !tabSemWhatsapp ||
    !tabPendencias ||
    !tabMensalistas
  ) {
    return;
  }
  tabEntries.classList.toggle('hidden', name !== 'entries');
  tabCharges.classList.toggle('hidden', name !== 'charges');
  if (tabChamada) tabChamada.classList.toggle('hidden', name !== 'chamada');
  tabInadimplentes.classList.toggle('hidden', name !== 'inadimplentes');
  tabRecebidas.classList.toggle('hidden', name !== 'recebidas');
  tabSemWhatsapp.classList.toggle('hidden', name !== 'sem-whatsapp');
  if (tabDuplicados) tabDuplicados.classList.toggle('hidden', name !== 'duplicados');
  tabPendencias.classList.toggle('hidden', name !== 'pendencias');
  tabMensalistas.classList.toggle('hidden', name !== 'mensalistas');
  if (tabExclusoes) tabExclusoes.classList.toggle('hidden', name !== 'exclusoes');
  if (tabResetSenha) tabResetSenha.classList.toggle('hidden', name !== 'reset-senha');
  if (tabFluxoCaixa) tabFluxoCaixa.classList.toggle('hidden', name !== 'fluxo-caixa');
  if (tabDadosAsaas) tabDadosAsaas.classList.toggle('hidden', name !== 'dados-asaas');
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
const cashflowMonthlyModeInput = document.querySelector('#cashflow-monthly-mode');
const cashflowExcludeStudentInput = document.querySelector('#cashflow-exclude-student');
const cashflowExcludeTermInput = document.querySelector('#cashflow-exclude-term');
const cashflowSearchButton = document.querySelector('#cashflow-search');
const cashflowClearButton = document.querySelector('#cashflow-clear');
const cashflowMessage = document.querySelector('#cashflow-message');
const cashflowSummary = document.querySelector('#cashflow-summary');
const cashflowTbody = document.querySelector('#cashflow-tbody');
let cashflowLoaded = false;
const asaasDataRefreshButton = document.querySelector('#asaas-data-refresh');
const asaasDataMessage = document.querySelector('#asaas-data-message');
const asaasDataSummary = document.querySelector('#asaas-data-summary');
const asaasPaidTbody = document.querySelector('#asaas-paid-tbody');
const asaasPendingTbody = document.querySelector('#asaas-pending-tbody');
const asaasOverdueTbody = document.querySelector('#asaas-overdue-tbody');
let asaasDataLoaded = false;
let adminDialogInstance = null;

function ensureAdminDialog() {
  if (adminDialogInstance) return adminDialogInstance;

  if (!document.getElementById('admin-dialog-style')) {
    const style = document.createElement('style');
    style.id = 'admin-dialog-style';
    style.textContent = `
      .admin-dialog-overlay{
        position:fixed;inset:0;z-index:9999;
        background:rgba(10,15,26,.55);
        display:flex;align-items:center;justify-content:center;
        padding:16px;
      }
      .admin-dialog-overlay.hidden{display:none}
      .admin-dialog-panel{
        width:min(720px,100%);
        max-height:85vh;
        overflow:auto;
        background:#fff;
        border:1px solid #E5E7EB;
        border-radius:16px;
        box-shadow:0 24px 60px rgba(10,15,26,.35);
        padding:16px;
      }
      .admin-dialog-title{
        margin:0 0 10px 0;
        font-size:18px;
        font-weight:800;
        color:#0F172A;
      }
      .admin-dialog-message{
        margin:0;
        padding:12px;
        border:1px solid #E2E8F0;
        border-radius:12px;
        background:#F8FAFC;
        color:#0F172A;
        font-size:13px;
        line-height:1.5;
        white-space:pre-wrap;
      }
      .admin-dialog-input{
        margin-top:10px;
        width:100%;
        padding:10px 12px;
        border:1px solid #CBD5E1;
        border-radius:10px;
        font-size:16px;
        line-height:1.2;
      }
      .admin-dialog-actions{
        margin-top:12px;
        display:flex;
        justify-content:flex-end;
        gap:8px;
      }
      .admin-dialog-actions .hidden{display:none}
    `;
    document.head.appendChild(style);
  }

  const overlay = document.createElement('div');
  overlay.className = 'admin-dialog-overlay hidden';
  overlay.innerHTML = `
    <div class="admin-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="admin-dialog-title">
      <h3 id="admin-dialog-title" class="admin-dialog-title"></h3>
      <p class="admin-dialog-message"></p>
      <input class="admin-dialog-input hidden" type="text" inputmode="numeric" />
      <div class="admin-dialog-actions">
        <button type="button" class="btn btn-ghost btn-sm admin-dialog-cancel">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm admin-dialog-confirm">Confirmar</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  adminDialogInstance = {
    overlay,
    panel: overlay.querySelector('.admin-dialog-panel'),
    title: overlay.querySelector('.admin-dialog-title'),
    message: overlay.querySelector('.admin-dialog-message'),
    input: overlay.querySelector('.admin-dialog-input'),
    cancel: overlay.querySelector('.admin-dialog-cancel'),
    confirm: overlay.querySelector('.admin-dialog-confirm'),
  };
  return adminDialogInstance;
}

function openAdminDialog({
  title = 'Confirmação',
  message = '',
  confirmText = 'Confirmar',
  cancelText = 'Cancelar',
  showCancel = true,
}) {
  const ui = ensureAdminDialog();
  ui.title.textContent = title;
  ui.message.textContent = String(message || '');
  ui.confirm.textContent = confirmText;
  ui.cancel.textContent = cancelText;
  ui.cancel.classList.toggle('hidden', !showCancel);
  if (ui.input) {
    ui.input.value = '';
    ui.input.placeholder = '';
    ui.input.classList.add('hidden');
  }
  ui.overlay.classList.remove('hidden');

  return new Promise((resolve) => {
    let settled = false;
    const settle = (result) => {
      if (settled) return;
      settled = true;
      ui.overlay.classList.add('hidden');
      ui.confirm.removeEventListener('click', onConfirm);
      ui.cancel.removeEventListener('click', onCancel);
      ui.overlay.removeEventListener('click', onOverlayClick);
      document.removeEventListener('keydown', onKeyDown);
      resolve(result);
    };
    const onConfirm = () => settle(true);
    const onCancel = () => settle(false);
    const onOverlayClick = (event) => {
      if (event.target === ui.overlay) {
        settle(showCancel ? false : true);
      }
    };
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        settle(showCancel ? false : true);
        return;
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        settle(true);
      }
    };

    ui.confirm.addEventListener('click', onConfirm);
    ui.cancel.addEventListener('click', onCancel);
    ui.overlay.addEventListener('click', onOverlayClick);
    document.addEventListener('keydown', onKeyDown);
    ui.confirm.focus();
  });
}

function showAdminConfirm(message, options = {}) {
  return openAdminDialog({
    title: options.title || 'Confirmar ação',
    message,
    confirmText: options.confirmText || 'Confirmar',
    cancelText: options.cancelText || 'Cancelar',
    showCancel: true,
  });
}

function showAdminAlert(message, options = {}) {
  return openAdminDialog({
    title: options.title || 'Atenção',
    message,
    confirmText: options.confirmText || 'OK',
    cancelText: 'Cancelar',
    showCancel: false,
  });
}

function toShortMaskedDate(value) {
  const raw = String(value || '').trim();
  if (!raw) return '';
  const isoMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (isoMatch) {
    return `${isoMatch[3]}/${isoMatch[2]}/${isoMatch[1].slice(-2)}`;
  }
  const brMatch = /^(\d{2})\/(\d{2})\/(\d{2,4})$/.exec(raw);
  if (brMatch) {
    return `${brMatch[1]}/${brMatch[2]}/${String(brMatch[3]).slice(-2)}`;
  }
  return raw;
}

function applyShortDateMask(input) {
  const digits = String(input.value || '').replace(/\D/g, '').slice(0, 6);
  let value = digits;
  if (digits.length > 2) value = `${digits.slice(0, 2)}/${digits.slice(2)}`;
  if (digits.length > 4) value = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
  input.value = value;
}

function showAdminDateInput(initialValue = '') {
  const ui = ensureAdminDialog();
  ui.title.textContent = 'Editar Data Day Use';
  ui.message.textContent = 'Informe a nova Data Day Use (DD/MM/AA):';
  ui.confirm.textContent = 'Salvar';
  ui.cancel.textContent = 'Cancelar';
  ui.cancel.classList.remove('hidden');
  if (ui.input) {
    ui.input.classList.remove('hidden');
    ui.input.placeholder = 'DD/MM/AA';
    ui.input.value = toShortMaskedDate(initialValue);
  }
  ui.overlay.classList.remove('hidden');

  return new Promise((resolve) => {
    let settled = false;
    const settle = (result) => {
      if (settled) return;
      settled = true;
      ui.overlay.classList.add('hidden');
      ui.confirm.removeEventListener('click', onConfirm);
      ui.cancel.removeEventListener('click', onCancel);
      ui.overlay.removeEventListener('click', onOverlayClick);
      document.removeEventListener('keydown', onKeyDown);
      if (ui.input) {
        ui.input.removeEventListener('input', onInput);
        ui.input.classList.add('hidden');
        ui.input.placeholder = '';
      }
      resolve(result);
    };
    const onInput = () => {
      if (!ui.input) return;
      applyShortDateMask(ui.input);
    };
    const onConfirm = () => {
      const value = String(ui.input?.value || '').trim();
      if (!/^\d{2}\/\d{2}\/\d{2}$/.test(value)) {
        ui.message.textContent = 'Data inválida. Use exatamente DD/MM/AA.';
        if (ui.input) ui.input.focus();
        return;
      }
      settle({ ok: true, value });
    };
    const onCancel = () => settle({ ok: false, value: '' });
    const onOverlayClick = (event) => {
      if (event.target === ui.overlay) {
        settle({ ok: false, value: '' });
      }
    };
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        settle({ ok: false, value: '' });
        return;
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        onConfirm();
      }
    };

    ui.confirm.addEventListener('click', onConfirm);
    ui.cancel.addEventListener('click', onCancel);
    ui.overlay.addEventListener('click', onOverlayClick);
    document.addEventListener('keydown', onKeyDown);
    if (ui.input) {
      ui.input.addEventListener('input', onInput);
      applyShortDateMask(ui.input);
      ui.input.focus();
    } else {
      ui.confirm.focus();
    }
  });
}

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
  const raw = String(value).trim();
  const isoPrefix = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
  if (isoPrefix) {
    const [, year, month, day] = isoPrefix;
    return `${day}/${month}/${year}`;
  }
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

function normalizeSearchText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function updateViewUserAutocompleteOptions(rawQuery) {
  if (!viewUserStudentsList) return;
  const query = normalizeSearchText(rawQuery);
  viewUserStudentsList.innerHTML = '';
  if (query.length < MIN_ADMIN_AUTOCOMPLETE_CHARS) return;

  const seen = new Set();
  const startsWith = [];
  const contains = [];
  adminStudents.forEach((student) => {
    const name = String(student.name || '').trim();
    if (!name) return;
    const key = normalizeSearchText(name);
    if (!key || seen.has(key)) return;
    if (key.startsWith(query)) {
      startsWith.push(name);
      seen.add(key);
      return;
    }
    if (key.includes(query)) {
      contains.push(name);
      seen.add(key);
    }
  });

  [...startsWith, ...contains].slice(0, 40).forEach((name) => {
    const option = document.createElement('option');
    option.value = name;
    viewUserStudentsList.appendChild(option);
  });
}

function resolveStudentNameForAdmin(rawInput) {
  const input = String(rawInput || '').trim();
  if (!input) {
    return { ok: false, error: 'Digite pelo menos 3 letras do nome do aluno.' };
  }

  if (studentNames.has(input)) {
    return { ok: true, name: input };
  }

  const normalizedInput = normalizeSearchText(input);
  if (normalizedInput.length < MIN_ADMIN_AUTOCOMPLETE_CHARS) {
    return {
      ok: false,
      error: `Digite pelo menos ${MIN_ADMIN_AUTOCOMPLETE_CHARS} letras para buscar o aluno.`,
    };
  }

  const candidates = [];
  const seen = new Set();
  adminStudents.forEach((student) => {
    const name = String(student.name || '').trim();
    if (!name) return;
    const normalizedName = normalizeSearchText(name);
    if (!normalizedName || seen.has(normalizedName)) return;
    if (normalizedName.includes(normalizedInput)) {
      candidates.push({ name, normalizedName });
      seen.add(normalizedName);
    }
  });

  if (!candidates.length) {
    return { ok: false, error: 'Aluno não encontrado. Continue digitando para buscar.' };
  }
  if (candidates.length > 1) {
    return {
      ok: false,
      error: 'Mais de um aluno encontrado. Selecione um nome da lista de autocomplete.',
    };
  }
  return { ok: true, name: candidates[0].name };
}

function rebuildMonthlyMaps() {
  monthlyByStudentId.clear();
  monthlyByName.clear();
  const rows = Array.isArray(monthlyStudents) ? monthlyStudents : [];
  rows.forEach((row) => {
    if (!row || row.active === false) return;
    const studentId = String(row.student_id || '').trim();
    const studentName = String(row.student_name || '').trim();
    const weeklyDays = Number(row.weekly_days || 0);
    if (!studentId || ![2, 3, 4, 5].includes(weeklyDays)) return;
    monthlyByStudentId.set(studentId, row);
    if (studentName) {
      monthlyByName.set(normalizeSearchText(studentName), row);
    }
  });
}

function getStudentByName(studentName) {
  const value = String(studentName || '').trim().toLowerCase();
  if (!value) return null;
  return adminStudents.find((student) => String(student.name || '').trim().toLowerCase() === value) || null;
}

function getMonthlyPlanForStudent(studentName) {
  const student = getStudentByName(studentName);
  if (student?.id && monthlyByStudentId.has(String(student.id))) {
    return monthlyByStudentId.get(String(student.id));
  }
  const key = normalizeSearchText(studentName || '');
  if (key && monthlyByName.has(key)) {
    return monthlyByName.get(key);
  }
  return null;
}

function setMonthlyMessage(text, isError = false) {
  if (!monthlyMessage) return;
  monthlyMessage.textContent = text;
  monthlyMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
}

function renderMonthlyTable() {
  if (!monthlyTableBody) return;
  const rows = Array.isArray(monthlyStudents) ? [...monthlyStudents] : [];
  rows.sort((a, b) => String(a?.student_name || '').localeCompare(String(b?.student_name || ''), 'pt-BR'));
  if (!rows.length) {
    monthlyTableBody.innerHTML = '<tr><td colspan="5">Nenhum mensalista cadastrado.</td></tr>';
    return;
  }
  monthlyTableBody.innerHTML = rows
    .map((row) => {
      const updatedAt = row?.updated_at ? formatDateTimeBR(row.updated_at) : '-';
      const days = Number(row?.weekly_days || 0);
      return `
        <tr data-student-id="${escapeHtml(row?.student_id || '')}">
          <td>${escapeHtml(row?.student_name || '-')}</td>
          <td>${escapeHtml(row?.enrollment || '-')}</td>
          <td>${escapeHtml(days || '-')} dias/semana</td>
          <td>${escapeHtml(updatedAt)}</td>
          <td>${escapeHtml(row?.updated_by || '-')}</td>
        </tr>
      `;
    })
    .join('');
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

function renderCashflowSummary(totals, period, monthlyAdjustment = null) {
  if (!cashflowSummary) return;
  if (!totals) {
    cashflowSummary.innerHTML = '';
    return;
  }
  const monthlyCount = Number(monthlyAdjustment?.count || 0);
  const monthlyAmount = Number(monthlyAdjustment?.amount || 0);
  const monthlyLabel = monthlyCount > 0
    ? `<span class="cashflow-pill">Subtraído mensalistas: ${formatCurrency(monthlyAmount)} (${monthlyCount} registro(s) • Aluno mensalista)</span>`
    : '';
  cashflowSummary.innerHTML = `
    <span class="cashflow-pill">Período: ${formatDateBR(period?.from)} até ${formatDateBR(period?.to)}</span>
    <span class="cashflow-pill">Registros: ${totals.count || 0}</span>
    <span class="cashflow-pill">Total geral: ${formatCurrency(totals.amount || 0)}</span>
    <span class="cashflow-pill">Total pago: ${formatCurrency(totals.paid_amount || 0)}</span>
    ${monthlyLabel}
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
    !cashflowMonthlyModeInput ||
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
  if (cashflowMonthlyModeInput.value) params.set('monthly_mode', cashflowMonthlyModeInput.value);
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
      renderCashflowSummary(null, null, null);
      return;
    }
    renderCashflowRows(data.items || []);
    renderCashflowSummary(data.totals || null, data.period || null, data.monthly_adjustment || null);
    setCashflowMessage('');
    cashflowLoaded = true;
  } catch {
    setCashflowMessage('Falha ao carregar fluxo de caixa.', true);
    renderCashflowRows([]);
    renderCashflowSummary(null, null, null);
  } finally {
    cashflowSearchButton.removeAttribute('disabled');
  }
}

function setAsaasDataMessage(text, isError = false) {
  if (!asaasDataMessage) return;
  asaasDataMessage.textContent = text;
  asaasDataMessage.className = `charge-message ${isError ? 'error' : ''}`.trim();
}

function formatDateTimeBR(value) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('pt-BR');
}

function renderAsaasGroupRows(tbody, items) {
  if (!tbody) return;
  const list = Array.isArray(items) ? items : [];
  if (!list.length) {
    tbody.innerHTML = '<tr><td colspan="9">Nenhum registro.</td></tr>';
    return;
  }
  tbody.innerHTML = list
    .map((item) => {
      const link = item.invoice_url
        ? `<a href="${escapeHtml(item.invoice_url)}" target="_blank" rel="noopener">Abrir</a>`
        : '-';
      const customer = [item.customer_name, item.customer_id].filter(Boolean).join(' • ') || '-';
      return `
      <tr>
        <td>${escapeHtml(item.id || '-')}</td>
        <td>${escapeHtml(item.status || '-')}</td>
        <td>${escapeHtml(customer)}</td>
        <td>${escapeHtml(item.description || '-')}</td>
        <td>${formatDateBR(item.due_date)}</td>
        <td>${formatDateTimeBR(item.paid_at)}</td>
        <td>${escapeHtml(item.billing_type || '-')}</td>
        <td>${formatCurrency(item.value)}</td>
        <td>${link}</td>
      </tr>
      `;
    })
    .join('');
}

function renderAsaasSummary(groups, generatedAt, warnings) {
  if (!asaasDataSummary) return;
  const pagos = groups?.pagos || {};
  const pendentes = groups?.pendentes || {};
  const vencidos = groups?.vencidos || {};
  const warnCount = Array.isArray(warnings) ? warnings.length : 0;
  asaasDataSummary.innerHTML = `
    <span class="cashflow-pill">Atualizado em: ${formatDateTimeBR(generatedAt)}</span>
    <span class="cashflow-pill">Pagos: ${pagos.count || 0} (${formatCurrency(pagos.total_value || 0)})</span>
    <span class="cashflow-pill">Pendentes: ${pendentes.count || 0} (${formatCurrency(pendentes.total_value || 0)})</span>
    <span class="cashflow-pill">Vencidos: ${vencidos.count || 0} (${formatCurrency(vencidos.total_value || 0)})</span>
    <span class="cashflow-pill">Avisos: ${warnCount}</span>
  `;
}

async function loadAsaasData(force = false) {
  if (!asaasPaidTbody || !asaasPendingTbody || !asaasOverdueTbody) {
    return;
  }
  if (asaasDataLoaded && !force) {
    return;
  }

  if (asaasDataRefreshButton) asaasDataRefreshButton.setAttribute('disabled', 'disabled');
  setAsaasDataMessage('Buscando dados diretamente do Asaas...');
  renderAsaasGroupRows(asaasPaidTbody, []);
  renderAsaasGroupRows(asaasPendingTbody, []);
  renderAsaasGroupRows(asaasOverdueTbody, []);

  try {
    const res = await fetch(`/api/admin-asaas-data.php?ts=${Date.now()}`);
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      const warningsText = Array.isArray(data?.warnings) ? ` ${data.warnings.join(' | ')}` : '';
      setAsaasDataMessage((data?.error || 'Falha ao carregar dados do Asaas.') + warningsText, true);
      return;
    }

    const groups = data.groups || {};
    renderAsaasGroupRows(asaasPaidTbody, groups?.pagos?.items || []);
    renderAsaasGroupRows(asaasPendingTbody, groups?.pendentes?.items || []);
    renderAsaasGroupRows(asaasOverdueTbody, groups?.vencidos?.items || []);
    renderAsaasSummary(groups, data.generated_at, data.warnings || []);
    const warningsText = Array.isArray(data.warnings) && data.warnings.length
      ? ` (com avisos: ${data.warnings.join(' | ')})`
      : '';
    setAsaasDataMessage('Dados carregados diretamente do Asaas.' + warningsText);
    asaasDataLoaded = true;
  } catch {
    setAsaasDataMessage('Falha ao carregar dados do Asaas.', true);
  } finally {
    if (asaasDataRefreshButton) asaasDataRefreshButton.removeAttribute('disabled');
  }
}

async function addChargeItem(studentName) {
  if (!studentName || selectedStudents.has(studentName)) return;
  const monthlyPlan = getMonthlyPlanForStudent(studentName);
  if (monthlyPlan && Number(monthlyPlan.weekly_days || 0) > 0) {
    await showAdminAlert(
      `Aluno ${studentName} é mensalista de ${monthlyPlan.weekly_days} dias por semana.`,
      { title: 'Atenção: aluno mensalista' },
    );
  }
  selectedStudents.add(studentName);
  const studentRecord = getStudentByName(studentName);

  const wrapper = document.createElement('div');
  wrapper.className = 'charge-item';
  wrapper.dataset.student = studentName;
  wrapper.dataset.studentId = studentRecord?.id ? String(studentRecord.id) : '';
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

function applyStudentsToLists(students) {
  adminStudents = Array.isArray(students) ? students : [];
  if (studentList) studentList.innerHTML = '';
  if (viewUserStudentsList) viewUserStudentsList.innerHTML = '';
  if (pendenciaStudentsList) pendenciaStudentsList.innerHTML = '';
  if (attendanceStudentsList) attendanceStudentsList.innerHTML = '';
  studentNames.clear();
  studentLookupByLabel.clear();
  adminStudents.forEach((student) => {
    const studentName = (student.name || '').trim();
    if (!studentName) return;
    const option = document.createElement('option');
    option.value = studentName;
    if (studentList) studentList.appendChild(option);
    if (attendanceStudentsList) {
      const optionAttendance = document.createElement('option');
      optionAttendance.value = studentName;
      attendanceStudentsList.appendChild(optionAttendance);
    }
    if (pendenciaStudentsList) {
      const gradeLabel = student.grade ? `${student.grade}º ano` : '';
      const classLabel = (student.class_name || '').trim();
      const enrollmentLabel = (student.enrollment || '').trim();
      const details = [gradeLabel, classLabel, enrollmentLabel ? `Matrícula ${enrollmentLabel}` : '']
        .filter(Boolean)
        .join(' • ');
      const lookupLabel = details ? `${studentName} • ${details}` : studentName;
      const optionPendencia = document.createElement('option');
      optionPendencia.value = lookupLabel;
      pendenciaStudentsList.appendChild(optionPendencia);
      if (student.id) {
        studentLookupByLabel.set(lookupLabel, student);
      }
    }
    studentNames.add(studentName);
  });
  updateViewUserAutocompleteOptions('');
}

async function loadStudents() {
  if (!studentList && !viewUserStudentsList && !pendenciaStudentsList && !attendanceStudentsList) return;

  const bootStudents = Array.isArray(window.__adminStudents) ? window.__adminStudents : null;
  if (bootStudents && bootStudents.length) {
    applyStudentsToLists(bootStudents);
    return;
  }

  let data = null;
  try {
    const res = await fetch('/api/students.php', {
      headers: { Accept: 'application/json' },
    });
    try {
      data = await res.json();
    } catch {
      data = null;
    }
    if (!res.ok || !data?.ok) {
      console.error('[admin-dashboard] loadStudents falhou', {
        status: res.status,
        payload: data,
      });
      return;
    }
  } catch (error) {
    console.error('[admin-dashboard] loadStudents erro de rede', error);
    return;
  }

  applyStudentsToLists(data.students);
}

async function syncMonthlyStudents(action) {
  if (!monthlyStudentInput) return;
  const resolved = resolveStudentNameForAdmin(monthlyStudentInput.value);
  if (!resolved.ok) {
    setMonthlyMessage(resolved.error || 'Selecione um aluno válido.', true);
    return;
  }
  const studentName = resolved.name;
  monthlyStudentInput.value = studentName;
  const student = getStudentByName(studentName);
  if (!student?.id) {
    setMonthlyMessage('Aluno não encontrado no banco.', true);
    return;
  }

  let weeklyDays = null;
  if (action === 'set') {
    const checked = document.querySelector('input[name="monthly-days"]:checked');
    weeklyDays = checked ? Number(checked.value || 0) : 0;
    if (![2, 3, 4, 5].includes(weeklyDays)) {
      setMonthlyMessage('Selecione 2, 3, 4 ou 5 dias por semana.', true);
      return;
    }
  }

  const targetButton = action === 'set' ? monthlySaveButton : monthlyRemoveButton;
  const originalLabel = targetButton?.textContent || '';
  if (targetButton) {
    targetButton.setAttribute('disabled', 'disabled');
    targetButton.textContent = action === 'set' ? 'Salvando...' : 'Removendo...';
  }
  setMonthlyMessage(action === 'set' ? 'Salvando mensalista...' : 'Removendo mensalista...');

  try {
    const res = await fetch('/api/admin-mensalistas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action,
        student_id: student.id,
        weekly_days: weeklyDays,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      setMonthlyMessage(data?.error || 'Falha ao atualizar mensalistas.', true);
      return;
    }
    monthlyStudents = Array.isArray(data.items) ? data.items : [];
    rebuildMonthlyMaps();
    renderMonthlyTable();
    setMonthlyMessage(
      action === 'set'
        ? `Aluno ${studentName} definido como mensalista de ${weeklyDays} dias/semana.`
        : `Aluno ${studentName} removido da lista de mensalistas.`,
      false,
    );
  } catch {
    setMonthlyMessage('Falha ao atualizar mensalistas.', true);
  } finally {
    if (targetButton) {
      targetButton.removeAttribute('disabled');
      targetButton.textContent = originalLabel;
    }
  }
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
    student_id: item.dataset.studentId || '',
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

function setAttendanceMessage(text, isError = false) {
  if (!attendanceMessage) return;
  attendanceMessage.textContent = text;
  attendanceMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
}

function attendanceStatusLabel(status) {
  const map = {
    em_revisao: 'Em revisão',
    autorizada_cobranca: 'Autorizada (cobrança na fila)',
    rejeitada: 'Rejeitada',
    aluno_mensalista: 'Aluno mensalista',
    bloqueada_ja_paga: 'Bloqueada: já paga',
    bloqueada_duplicidade: 'Bloqueada: cobrança existente',
    erro_cobranca: 'Erro ao lançar cobrança',
  };
  return map[String(status || '').trim()] || status || '-';
}

function compareByStudentName(a, b) {
  const aName = normalizeSearchText(a?.student_name || a?.name || '');
  const bName = normalizeSearchText(b?.student_name || b?.name || '');
  const byName = aName.localeCompare(bName, 'pt-BR');
  if (byName !== 0) return byName;
  const aDate = String(a?.attendance_date || '');
  const bDate = String(b?.attendance_date || '');
  return bDate.localeCompare(aDate);
}

function getAttendanceFilterParams() {
  const params = new URLSearchParams();
  const from = String(attendanceFilterFromInput?.value || '').trim();
  const to = String(attendanceFilterToInput?.value || '').trim();
  if (from) params.set('from', from);
  if (to) params.set('to', to);
  return params;
}

function resolveAttendanceOffice(inputValue) {
  const raw = String(inputValue || '').trim();
  if (!raw) return { ok: true, officeId: '', officeName: '' };
  const byLabel = attendanceOfficeByLabel.get(raw);
  if (byLabel) {
    return {
      ok: true,
      officeId: String(byLabel.id || '').trim(),
      officeName: String(byLabel.name || '').trim(),
    };
  }
  for (const office of attendanceOfficeById.values()) {
    if (normalizeSearchText(office.name) === normalizeSearchText(raw)) {
      return {
        ok: true,
        officeId: String(office.id || '').trim(),
        officeName: String(office.name || '').trim(),
      };
    }
  }
  return { ok: false, error: 'Selecione uma oficina válida da lista do mês corrente.' };
}

function renderAttendanceRows(items) {
  if (!attendanceTbody) return;
  const rows = Array.isArray(items) ? [...items] : [];
  rows.sort(compareByStudentName);
  if (!rows.length) {
    attendanceTbody.innerHTML = '<tr><td colspan="8">Nenhuma chamada lançada.</td></tr>';
    return;
  }
  attendanceTbody.innerHTML = rows
    .map((item) => {
      const office = item.office_name
        ? `${item.office_name}${item.office_code ? ` (${item.office_code})` : ''}`
        : '-';
      const reviewParts = [];
      if (item.review_note) reviewParts.push(String(item.review_note));
      if (item.reviewed_at) reviewParts.push(formatDateTimeBR(item.reviewed_at));
      const reviewText = reviewParts.length ? reviewParts.join(' • ') : '-';
      const canReview = adminCanApproveAttendance && String(item.status || '') === 'em_revisao';
      const actionParts = [];
      if (canReview) {
        actionParts.push(`<button class="btn btn-primary btn-sm js-attendance-approve" type="button" data-id="${escapeHtml(item.id || '')}">Autorizar</button>`);
        actionParts.push(`<button class="btn btn-danger btn-sm js-attendance-reject" type="button" data-id="${escapeHtml(item.id || '')}">Rejeitar</button>`);
      }
      actionParts.push(`<button class="btn btn-primary btn-sm js-attendance-edit" type="button" data-id="${escapeHtml(item.id || '')}" data-date="${escapeHtml(item.attendance_date || '')}">Editar</button>`);
      const actions = actionParts.join('');
      return `
        <tr data-attendance-id="${escapeHtml(item.id || '')}" data-attendance-date="${escapeHtml(item.attendance_date || '')}">
          <td>${escapeHtml(formatDateBR(item.attendance_date || '-'))}</td>
          <td>${escapeHtml(item.student_name || '-')}</td>
          <td>${escapeHtml(office)}</td>
          <td>${escapeHtml(attendanceStatusLabel(item.status || ''))}</td>
          <td>${escapeHtml(item.created_by_user || item.created_by_role || '-')}</td>
          <td>${escapeHtml(formatDateTimeBR(item.created_at || ''))}</td>
          <td>${escapeHtml(reviewText)}</td>
          <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">${actions}</td>
        </tr>
      `;
    })
    .join('');
}

async function loadAttendanceOffices() {
  if (attendanceOfficesLoaded) return;
  if (!attendanceOfficesList) return;
  try {
    const res = await fetch(`/api/admin-oficinas-current-month.php?ts=${Date.now()}`);
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      attendanceOfficesList.innerHTML = '';
      attendanceOfficesLoaded = true;
      return;
    }
    attendanceOfficeById.clear();
    attendanceOfficeByLabel.clear();
    attendanceOfficesList.innerHTML = '';
    (Array.isArray(data.offices) ? data.offices : []).forEach((office) => {
      const id = String(office.id || '').trim();
      const name = String(office.name || '').trim();
      const label = String(office.label || name).trim();
      if (!id || !name) return;
      const normalized = { id, name, label };
      attendanceOfficeById.set(id, normalized);
      attendanceOfficeByLabel.set(label, normalized);
      const option = document.createElement('option');
      option.value = label;
      attendanceOfficesList.appendChild(option);
    });
    attendanceOfficesLoaded = true;
  } catch {
    attendanceOfficesLoaded = true;
  }
}

async function loadAttendanceCalls(force = false) {
  if (!attendanceTbody) return;
  setAttendanceMessage('Carregando chamadas...');
  try {
    const params = getAttendanceFilterParams();
    params.set('ts', Date.now().toString());
    const res = await fetch(`/api/admin-attendance.php?${params.toString()}`);
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      setAttendanceMessage(data?.error || 'Falha ao carregar chamadas.', true);
      renderAttendanceRows([]);
      return;
    }
    renderAttendanceRows(Array.isArray(data.items) ? data.items : []);
    setAttendanceMessage('');
    attendanceLoaded = true;
  } catch {
    setAttendanceMessage('Falha ao carregar chamadas.', true);
    renderAttendanceRows([]);
  }
}

async function postAttendanceAction(payload) {
  const res = await fetch('/api/admin-attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload || {}),
  });
  const data = await res.json();
  return { res, data };
}

function renderAttendanceDayQueue() {
  if (!attendanceDayList) return;
  if (!attendanceDayQueue.length) {
    attendanceDayList.innerHTML = '<tr><td colspan="4">Nenhum aluno adicionado para o fechamento do dia.</td></tr>';
    return;
  }
  attendanceDayQueue.sort(compareByStudentName);
  attendanceDayList.innerHTML = attendanceDayQueue
    .map((entry, index) => {
      const officeLabel = entry.office_name
        ? `${entry.office_name}${entry.office_code ? ` (${entry.office_code})` : ''}`
        : '-';
      return `
        <tr>
          <td>${escapeHtml(formatDateBR(entry.attendance_date || '-'))}</td>
          <td>${escapeHtml(entry.student_name || '-')}</td>
          <td>${escapeHtml(officeLabel)}</td>
          <td>
            <button class="btn btn-danger btn-sm js-attendance-queue-remove" type="button" data-index="${index}">Remover</button>
          </td>
        </tr>
      `;
    })
    .join('');
}

function addAttendanceEntryToQueue() {
  if (!attendanceDateInput || !attendanceStudentInput) return;
  const attendanceDate = String(attendanceDateInput.value || '').trim();
  if (!attendanceDate) {
    setAttendanceMessage('Informe a data da chamada.', true);
    return;
  }
  const resolvedStudent = resolveStudentNameForAdmin(attendanceStudentInput.value);
  if (!resolvedStudent.ok) {
    setAttendanceMessage(resolvedStudent.error || 'Selecione um aluno válido.', true);
    return;
  }
  const studentName = resolvedStudent.name;
  attendanceStudentInput.value = studentName;
  const student = getStudentByName(studentName);
  if (!student?.id) {
    setAttendanceMessage('Aluno não encontrado no banco.', true);
    return;
  }

  const officeResolved = resolveAttendanceOffice(attendanceOfficeInput?.value || '');
  if (!officeResolved.ok) {
    setAttendanceMessage(officeResolved.error || 'Oficina inválida.', true);
    return;
  }
  const office = officeResolved.officeId ? attendanceOfficeById.get(officeResolved.officeId) : null;

  const alreadyInQueue = attendanceDayQueue.some(
    (entry) =>
      String(entry.attendance_date || '') === attendanceDate && String(entry.student_id || '') === String(student.id || ''),
  );
  if (alreadyInQueue) {
    setAttendanceMessage('Aluno já adicionado na lista deste dia.', true);
    return;
  }

  attendanceDayQueue.push({
    attendance_date: attendanceDate,
    student_id: String(student.id || ''),
    student_name: String(student.name || studentName),
    office_id: String(officeResolved.officeId || ''),
    office_name: String(officeResolved.officeName || ''),
    office_code: String(office?.code || ''),
  });
  attendanceDayQueue.sort(compareByStudentName);
  renderAttendanceDayQueue();
  attendanceStudentInput.value = '';
  if (attendanceOfficeInput) attendanceOfficeInput.value = '';
  setAttendanceMessage('Aluno adicionado na lista do dia.');
}

async function closeAttendanceDay() {
  if (!attendanceDateInput) return;
  const attendanceDate = String(attendanceDateInput.value || '').trim();
  if (!attendanceDate) {
    setAttendanceMessage('Informe a data da chamada.', true);
    return;
  }
  if (!attendanceDayQueue.length) {
    setAttendanceMessage('Adicione pelo menos um aluno antes de fechar o dia.', true);
    return;
  }

  const mixedDate = attendanceDayQueue.find(
    (entry) => String(entry.attendance_date || '') !== attendanceDate,
  );
  if (mixedDate) {
    setAttendanceMessage('A lista contém alunos de outra data. Ajuste a data ou remova os itens.', true);
    return;
  }

  const confirmed = await showAdminConfirm(
    `Fechar dia de chamada com ${attendanceDayQueue.length} aluno(s) para ${formatDateBR(attendanceDate)}?`,
    { title: 'Fechar dia de chamada', confirmText: 'Fechar dia' },
  );
  if (!confirmed) return;

  if (attendanceCloseDayButton) attendanceCloseDayButton.setAttribute('disabled', 'disabled');
  if (attendanceAddButton) attendanceAddButton.setAttribute('disabled', 'disabled');
  setAttendanceMessage('Fechando dia de chamada...');
  try {
    const { res, data } = await postAttendanceAction({
      action: 'close_day',
      attendance_date: attendanceDate,
      entries: attendanceDayQueue.map((entry) => ({
        student_id: entry.student_id,
        student_name: entry.student_name,
        office_id: entry.office_id,
        office_name: entry.office_name,
      })),
    });
    if (!res.ok || !data?.ok) {
      setAttendanceMessage(data?.error || 'Falha ao fechar dia de chamada.', true);
      return;
    }
    const created = Number(data.created_count || 0);
    const blocked = Number(data.blocked_count || 0);
    const skipped = Number(data.skipped_count || 0);
    const emailWarning = data.email_warning ? ` Aviso: ${data.email_warning}` : '';
    setAttendanceMessage(
      `Dia fechado. Criadas: ${created}. Bloqueadas: ${blocked}. Ignoradas: ${skipped}.${emailWarning}`,
      blocked > 0,
    );
    attendanceDayQueue = [];
    renderAttendanceDayQueue();
    await loadAttendanceCalls(true);
  } catch {
    setAttendanceMessage('Falha ao fechar dia de chamada.', true);
  } finally {
    if (attendanceCloseDayButton) attendanceCloseDayButton.removeAttribute('disabled');
    if (attendanceAddButton) attendanceAddButton.removeAttribute('disabled');
  }
}

async function handleAttendanceAction(event) {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;
  const approveButton = target.closest('.js-attendance-approve');
  const rejectButton = target.closest('.js-attendance-reject');
  const editButton = target.closest('.js-attendance-edit');
  if (!approveButton && !rejectButton && !editButton) return;

  const actionButton = approveButton || rejectButton || editButton;
  const id = actionButton?.getAttribute('data-id') || '';
  if (!id) return;

  if (editButton) {
    const currentRaw = String(editButton.getAttribute('data-date') || '').trim();
    const promptResult = await showAdminDateInput(currentRaw);
    if (!promptResult?.ok) return;
    const newDate = String(promptResult.value || '').trim();
    if (!newDate) {
      setAttendanceMessage('Informe uma data válida para edição.', true);
      return;
    }

    actionButton.setAttribute('disabled', 'disabled');
    setAttendanceMessage('Atualizando Data Day Use...');
    try {
      const { res, data } = await postAttendanceAction({ action: 'edit', id, attendance_date: newDate });
      if (!res.ok || !data?.ok) {
        setAttendanceMessage(data?.error || 'Falha ao editar Data Day Use.', true);
        return;
      }
      setAttendanceMessage(data?.message || 'Data Day Use atualizada.');
      await loadAttendanceCalls(true);
    } catch {
      setAttendanceMessage('Falha ao editar Data Day Use.', true);
    } finally {
      actionButton.removeAttribute('disabled');
    }
    return;
  }

  if (approveButton) {
    actionButton.setAttribute('disabled', 'disabled');
    setAttendanceMessage('Autorizando chamada...');
    try {
      const { res, data } = await postAttendanceAction({ action: 'approve', id });
      if (!res.ok || !data?.ok) {
        setAttendanceMessage(data?.error || 'Falha ao autorizar chamada.', true);
        return;
      }
      if (data?.blocked) {
        const blockedReason = String(data?.blocked_reason || '');
        if (blockedReason === 'monthly_covered') {
          const monthly = data?.monthly || {};
          const usedDates = Array.isArray(monthly.used_dates) ? monthly.used_dates : [];
          const usedLabel = usedDates.length
            ? usedDates.map((d) => formatDateBR(d)).join(', ')
            : 'Nenhuma';
          await showAdminAlert(
            `Aluno mensalista (${monthly.weekly_days || '?'} dias/semana).\n` +
              `Data da chamada: ${formatDateBR(monthly.attendance_date || '')}\n` +
              `Datas registradas na semana: ${usedLabel}\n` +
              `Saldo restante na semana: ${monthly.remaining_days ?? '?'} dia(s).\n\n` +
              'Resultado: sem cobrança em Cobranças em aberto.',
            { title: 'Aviso de mensalista' },
          );
        } else if (blockedReason === 'already_paid') {
          await showAdminAlert(
            'Esta data já está paga para o aluno. Nenhuma cobrança foi gerada.',
            { title: 'Bloqueio: diária já paga' },
          );
        } else if (blockedReason === 'already_open') {
          await showAdminAlert(
            'Já existe cobrança em aberto para esta data. Nenhuma nova cobrança foi gerada.',
            { title: 'Bloqueio: cobrança já existente' },
          );
        }
      }
      setAttendanceMessage(data?.message || 'Chamada autorizada.');
      await loadAttendanceCalls(true);
    } catch {
      setAttendanceMessage('Falha ao autorizar chamada.', true);
    } finally {
      actionButton.removeAttribute('disabled');
    }
    return;
  }

  const confirmed = await showAdminConfirm(
    'Confirmar rejeição desta chamada? A cobrança não será gerada.',
    { title: 'Rejeitar chamada', confirmText: 'Rejeitar' },
  );
  if (!confirmed) return;

  actionButton.setAttribute('disabled', 'disabled');
  setAttendanceMessage('Rejeitando chamada...');
  try {
    const { res, data } = await postAttendanceAction({ action: 'reject', id });
    if (!res.ok || !data?.ok) {
      setAttendanceMessage(data?.error || 'Falha ao rejeitar chamada.', true);
      return;
    }
    setAttendanceMessage(data?.message || 'Chamada rejeitada.');
    await loadAttendanceCalls(true);
  } catch {
    setAttendanceMessage('Falha ao rejeitar chamada.', true);
  } finally {
    actionButton.removeAttribute('disabled');
  }
}

tabs.forEach((btn) => {
  btn.addEventListener('click', () => {
    setActiveTab(btn.dataset.tab);
    if (btn.dataset.tab === 'inadimplentes') {
      maybeAlertInadimplentesDuplicates();
      maybeAlertInadimplentesMonthly();
    }
    if (btn.dataset.tab === 'chamada') {
      loadAttendanceOffices();
      loadAttendanceCalls(true);
    }
    if (btn.dataset.tab === 'fluxo-caixa' && !cashflowLoaded) {
      loadCashflow();
    }
    if (btn.dataset.tab === 'dados-asaas' && !asaasDataLoaded) {
      loadAsaasData();
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

if (attendanceAddButton) {
  attendanceAddButton.addEventListener('click', () => {
    addAttendanceEntryToQueue();
  });
}

if (attendanceStudentInput) {
  attendanceStudentInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      addAttendanceEntryToQueue();
    }
  });
}

if (attendanceCloseDayButton) {
  attendanceCloseDayButton.addEventListener('click', () => {
    closeAttendanceDay();
  });
}

if (attendanceFilterButton) {
  attendanceFilterButton.addEventListener('click', () => {
    loadAttendanceCalls(true);
  });
}

if (attendanceClearButton) {
  attendanceClearButton.addEventListener('click', () => {
    if (attendanceFilterFromInput) attendanceFilterFromInput.value = '';
    if (attendanceFilterToInput) attendanceFilterToInput.value = '';
    loadAttendanceCalls(true);
  });
}

if (attendanceExportButton) {
  attendanceExportButton.addEventListener('click', () => {
    const params = getAttendanceFilterParams();
    params.set('ts', Date.now().toString());
    window.location.href = `/api/admin-attendance-export.php?${params.toString()}`;
  });
}

if (attendanceDayList) {
  attendanceDayList.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const removeButton = target.closest('.js-attendance-queue-remove');
    if (!removeButton) return;
    const index = Number(removeButton.getAttribute('data-index') || -1);
    if (Number.isNaN(index) || index < 0 || index >= attendanceDayQueue.length) return;
    attendanceDayQueue.splice(index, 1);
    renderAttendanceDayQueue();
    setAttendanceMessage('Aluno removido da lista do dia.');
  });
}

if (attendanceTbody) {
  attendanceTbody.addEventListener('click', (event) => {
    handleAttendanceAction(event);
  });
}

if (monthlySaveButton) {
  monthlySaveButton.addEventListener('click', () => {
    syncMonthlyStudents('set');
  });
}

if (monthlyRemoveButton) {
  monthlyRemoveButton.addEventListener('click', () => {
    syncMonthlyStudents('remove');
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
    sendChargesButton.textContent = 'Registrando...';
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
        const wantsToContinue = await showAdminConfirm(
          buildDuplicatesPopupMessage(duplicates),
          { title: 'Possíveis coincidências de cobrança', confirmText: 'Continuar assim' },
        );
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
      let data = null;
      try {
        data = await res.json();
      } catch {
        data = null;
      }
      if (!data?.ok) {
        const statusInfo = !res.ok ? ` (HTTP ${res.status})` : '';
        showChargeMessage((data?.error || 'Falha ao registrar cobranças manuais.') + statusInfo, true);
      } else {
        const results = Array.isArray(data.results) ? data.results : [];
        const failures = results.filter((item) => !item.ok);
        const monthlyCoveredOnly = results.filter((item) => item?.ok && item?.monthly_covered);
        const monthlyPartial = results.filter(
          (item) =>
            item?.ok &&
            !item?.monthly_covered &&
            Array.isArray(item?.covered_dates) &&
            item.covered_dates.length > 0 &&
            Array.isArray(item?.overflow_dates) &&
            item.overflow_dates.length > 0,
        );
        if (monthlyCoveredOnly.length || monthlyPartial.length) {
          const lines = [];
          monthlyCoveredOnly.forEach((item) => {
            lines.push(
              `- ${item.student_name}: dentro da franquia mensalista (${item.monthly_days || '?'} dias/semana), sem cobrança.`,
            );
          });
          monthlyPartial.forEach((item) => {
            lines.push(
              `- ${item.student_name}: mensalista (${item.monthly_days || '?'} dias). Cobrança criada só para excedente.`,
            );
          });
          await showAdminAlert(lines.join('\n'), { title: 'Regra de mensalistas aplicada' });
        }
        if (failures.length) {
          showChargeMessage('Algumas cobranças manuais não foram registradas. Verifique os dados.', true);
        } else if (results.length > 0 && results.every((item) => item?.ok && item?.monthly_covered)) {
          showChargeMessage('Nenhuma cobrança gerada: todas as datas estão dentro da franquia de mensalistas.');
          resetChargeForm();
        } else {
          showChargeMessage('Cobranças manuais registradas na fila (sem envio). Abrindo Cobranças em aberto...');
          resetChargeForm();
          setTimeout(() => {
            window.location.href = '/admin/dashboard.php?tab=inadimplentes';
          }, 350);
        }
      }
    } catch (err) {
      showChargeMessage('Falha ao registrar cobranças manuais.', true);
    } finally {
      sendChargesButton.disabled = false;
      sendChargesButton.textContent = 'Registrar cobranças manuais (sem envio)';
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
      showSendPendingMessage('Selecione ao menos uma cobrança da fila de envio.', true);
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
      const results = Array.isArray(data?.results) ? data.results : [];
      const successIds = results
        .filter((row) => row && row.ok && row.id)
        .map((row) => String(row.id));
      const failures = results.filter((row) => row && !row.ok);

      if (!res.ok && !successIds.length) {
        showSendPendingMessage(data?.error || 'Falha ao enviar cobranças da fila.', true);
        return;
      }

      successIds.forEach((paymentId) => {
        const row = document.querySelector(`.inadimplente-row[data-payment-id="${paymentId}"]`);
        if (!row) return;

        const firstCell = row.querySelector('td');
        if (firstCell) {
          firstCell.textContent = '-';
        }
        const statusCell = row.children?.[6];
        if (statusCell) {
          statusCell.textContent = 'Aguardando pagamento';
        }
      });

      if (selectAllPendingInput) {
        selectAllPendingInput.checked = false;
      }

      if (successIds.length && failures.length) {
        const firstError = failures[0]?.error || 'Erro em parte dos envios.';
        showSendPendingMessage(
          `${successIds.length} cobrança(s) enviada(s). ${failures.length} com erro: ${firstError}`,
          true,
        );
        return;
      }

      if (successIds.length) {
        showSendPendingMessage('Cobranças da fila enviadas com sucesso. Tabela atualizada sem recarregar a página.');
        return;
      }

      showSendPendingMessage(data?.error || 'Falha ao enviar cobranças da fila.', true);
    } catch {
      showSendPendingMessage('Falha ao enviar cobranças da fila.', true);
    } finally {
      sendSelectedPendingButton.removeAttribute('disabled');
      sendSelectedPendingButton.textContent = originalText;
    }
  });
}

function normalizeDuplicateKey(value) {
  return String(value || '').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z0-9]+/g, '');
}

function detectInadimplentesDuplicates() {
  const rows = [...document.querySelectorAll('.inadimplente-row')];
  const groups = new Map();
  rows.forEach((row) => {
    const student = normalizeDuplicateKey(row.getAttribute('data-student') || '');
    const dayUseDates = normalizeDuplicateKey(row.getAttribute('data-dayuse-date') || '');
    if (!student || !dayUseDates) return;
    const key = `${student}|${dayUseDates}`;
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(row);
  });

  const duplicates = [];
  groups.forEach((groupRows) => {
    if (groupRows.length <= 1) return;
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
}

function highlightInadimplentesDuplicates(duplicates) {
  document.querySelectorAll('.inadimplente-row').forEach((row) => {
    row.style.background = '';
  });
  duplicates.forEach((dup) => {
    dup.rows.forEach((row) => {
      row.style.background = '#FEF2F2';
    });
  });
}

function maybeAlertInadimplentesDuplicates(force = false) {
  if (inadimplentesDuplicatesPopupShown && !force) return;
  const duplicates = detectInadimplentesDuplicates();
  if (!duplicates.length) return;
  inadimplentesDuplicatesPopupShown = true;
  highlightInadimplentesDuplicates(duplicates);

  const lines = [
    'ATENÇÃO: existem cobranças em duplicidade na aba Cobranças em aberto.',
    'Verifique os casos abaixo e exclua uma das cobranças duplicadas.',
    '',
  ];
  duplicates.slice(0, 12).forEach((dup) => {
    lines.push(`- ${dup.student_name} | Datas: ${dup.day_use_dates} | Duplicadas: ${dup.count}`);
  });
  if (duplicates.length > 12) {
    lines.push(`... e mais ${duplicates.length - 12} grupo(s) duplicado(s).`);
  }
  lines.push('');
  lines.push('Use o botão Excluir e selecione o motivo: COBRANÇA EM DUPLICIDADE.');
  showAdminAlert(lines.join('\n'), { title: 'Cobranças em duplicidade' });
}

function maybeAlertInadimplentesMonthly(force = false) {
  if (inadimplentesMonthlyPopupShown && !force) return;
  const rows = [...document.querySelectorAll('.inadimplente-row[data-monthly="1"]')];
  if (!rows.length) return;
  inadimplentesMonthlyPopupShown = true;

  const lines = ['Alunos mensalistas encontrados em Cobranças em aberto:'];
  rows.slice(0, 12).forEach((row) => {
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
  showAdminAlert(lines.join('\n'), { title: 'Atenção: alunos mensalistas' });
}

pendingDeleteButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const paymentId = button.dataset.id;
    const row = button.closest('tr');
    if (!paymentId || !row) return;

    const student = row.getAttribute('data-student') || 'Aluno';
    const dayUseDates = row.getAttribute('data-dayuse-date') || '-';
    const amountRaw = Number(row.getAttribute('data-amount') || 0);
    const amount = formatCurrency(amountRaw);

    const chooseReason = await showAdminConfirm(
      `Excluir cobrança?\n\nAluno: ${student}\nDatas do day-use: ${dayUseDates}\nValor: ${amount}\n\nMotivo: COBRANÇA EM DUPLICIDADE`,
      { title: 'Excluir cobrança' },
    );
    if (!chooseReason) return;

    const confirmDelete = await showAdminConfirm(
      'Confirmar exclusão desta cobrança em duplicidade?',
      { title: 'Confirmação final', confirmText: 'Excluir cobrança' },
    );
    if (!confirmDelete) return;

    button.setAttribute('disabled', 'disabled');
    showSendPendingMessage('Excluindo cobrança...', false);
    try {
      const res = await fetch('/api/admin-delete-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: paymentId, reason: 'COBRANCA_EM_DUPLICIDADE' }),
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) {
        showSendPendingMessage(data?.error || 'Falha ao excluir cobrança.', true);
        return;
      }
      row.remove();
      showSendPendingMessage('Cobrança excluída com motivo: Cobrança em duplicidade.');
      maybeAlertInadimplentesDuplicates(true);
    } catch {
      showSendPendingMessage('Falha ao excluir cobrança.', true);
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

if (syncRecebidasButton) {
  syncRecebidasButton.addEventListener('click', async () => {
    syncRecebidasButton.setAttribute('disabled', 'disabled');
    const originalText = syncRecebidasButton.textContent;
    syncRecebidasButton.textContent = 'Atualizando...';
    if (syncRecebidasMessage) {
      syncRecebidasMessage.textContent = 'Buscando cobranças RECEIVED/CONFIRMED no Asaas...';
      syncRecebidasMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-sync-recebidas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) {
        if (syncRecebidasMessage) {
          syncRecebidasMessage.textContent = data?.error || 'Falha ao atualizar cobrancas recebidas.';
          syncRecebidasMessage.className = 'charge-message error';
        }
        return;
      }
      const summary = data.summary || {};
      if (syncRecebidasMessage) {
        syncRecebidasMessage.textContent = `Atualização concluída. Locais promovidos para pago: ${summary.payments_promoted_paid || 0}. Pendências locais movidas para pagas: ${summary.pendencias_promoted_paid || 0}. Asaas varrido: ${summary.asaas_scanned_total || 0}. Pagos encontrados: ${summary.asaas_paid_found || 0}. Importados em payments: ${summary.asaas_paid_imported_payments || 0}. Importados em recebidas (pendências pagas): ${summary.asaas_paid_imported_pendencias || 0}. Não mapeados: ${summary.asaas_paid_unmapped || 0}. Recarregue a página quando quiser refletir tudo na tabela.`;
        syncRecebidasMessage.className = 'charge-message success';
      }
    } catch {
      if (syncRecebidasMessage) {
        syncRecebidasMessage.textContent = 'Falha ao atualizar cobrancas recebidas.';
        syncRecebidasMessage.className = 'charge-message error';
      }
    } finally {
      syncRecebidasButton.removeAttribute('disabled');
      syncRecebidasButton.textContent = originalText;
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
const pendenciaDeleteButtons = document.querySelectorAll('.js-delete-pendencia');
const pendenciaLinkStudentButtons = document.querySelectorAll('.js-pendencia-link-student');
const pendenciaCreateStudentButtons = document.querySelectorAll('.js-pendencia-create-student');
const syncChargesPaymentsButton = document.querySelector('#sync-charges-payments-btn');
const syncChargesPaymentsMessage = document.querySelector('#sync-charges-payments-message');

function normalizeCpf(value) {
  return value.replace(/\D/g, '').slice(0, 11);
}

function findPendenciaRow(id) {
  if (!id) return null;
  return document.querySelector(`[data-pendencia-id="${id}"]`);
}

function ensurePendenciasEmptyState() {
  const tbody = document.querySelector('#tab-pendencias tbody');
  if (!tbody) return;
  const rows = tbody.querySelectorAll('tr[data-pendencia-id]');
  if (rows.length > 0) return;
  tbody.innerHTML = '<tr><td colspan="11">Nenhuma pendência registrada.</td></tr>';
}

function removePendenciaRowsByIds(ids) {
  const uniqueIds = Array.from(new Set((Array.isArray(ids) ? ids : []).map((id) => String(id || '').trim()).filter(Boolean)));
  let removed = 0;
  uniqueIds.forEach((id) => {
    const row = findPendenciaRow(id);
    if (row) {
      row.remove();
      removed += 1;
    }
  });
  if (removed > 0) {
    ensurePendenciasEmptyState();
  }
  return removed;
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

function findStudentFromLookup(lookupValue) {
  const value = String(lookupValue || '').trim();
  if (!value) {
    return { student: null, error: 'Informe o aluno existente para fazer a mesclagem.' };
  }
  const byLabel = studentLookupByLabel.get(value);
  if (byLabel && byLabel.id) {
    return { student: byLabel, error: '' };
  }

  const byName = adminStudents.filter(
    (student) => String(student.name || '').trim().toLowerCase() === value.toLowerCase(),
  );
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
}

function renderPendenciaStudentLink(row, student) {
  if (!row || !student) return;
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
    studentLinkCell.innerHTML = `<div class="pendencia-student-link">${escapeHtml(label)}</div>`;
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
    const confirmSettle = await showAdminConfirm(
      'Confirmar baixa manual desta pendência?',
      { title: 'Baixa manual', confirmText: 'Confirmar baixa' },
    );
    if (!confirmSettle) return;

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

pendenciaDeleteButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const pendenciaId = button.dataset.id;
    const row = button.closest('tr');
    if (!pendenciaId || !row) return;

    const student = row.children?.[0]?.textContent?.trim() || 'Aluno';
    const guardian = row.children?.[1]?.textContent?.trim() || 'Responsável';
    const dayUseDate = row.children?.[4]?.textContent?.trim() || '-';

    const chooseReason = await showAdminConfirm(
      `Excluir pendência?\n\nAluno: ${student}\nResponsável: ${guardian}\nData do day-use: ${dayUseDate}\n\nOpção: DIÁRIA NÃO USADA`,
      { title: 'Excluir pendência' },
    );
    if (!chooseReason) return;

    const confirmDelete = await showAdminConfirm(
      'CONFIRMAR EXCLUSÃO DA PENDÊNCIA?\n\nLEMBRETE: EXCLUA TAMBÉM A COBRANÇA NO ASAAS.',
      { title: 'Confirmação final', confirmText: 'Excluir pendência' },
    );
    if (!confirmDelete) return;

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Excluindo pendência...';
      pendenciaMessage.className = 'charge-message';
    }

    try {
      const res = await fetch('/api/admin-delete-pendencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: pendenciaId, reason: 'DIARIA_NAO_USADA' }),
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data?.error || 'Falha ao excluir pendência.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }

      row.remove();
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Pendência excluída. LEMBRETE: EXCLUA TAMBÉM A COBRANÇA NO ASAAS.';
        pendenciaMessage.className = 'charge-message success';
      }
      await showAdminAlert(
        'PENDÊNCIA EXCLUÍDA.\nLEMBRETE: EXCLUA TAMBÉM A COBRANÇA NO ASAAS.',
        { title: 'Pendência excluída' },
      );
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao excluir pendência.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

async function postPendenciaStudentReconcile(payload) {
  const res = await fetch('/api/admin-reconcile-pendencia-student.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  return { res, data };
}

pendenciaLinkStudentButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const pendenciaId = button.dataset.id;
    const row = button.closest('tr');
    const lookupInput = row ? row.querySelector('.pendencia-student-lookup') : null;
    const lookupValue = lookupInput ? lookupInput.value : '';
    if (!pendenciaId || !row) return;

    const found = findStudentFromLookup(lookupValue);
    if (!found.student) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = found.error;
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Mesclando pendência com aluno existente...';
      pendenciaMessage.className = 'charge-message';
    }
    try {
      const { res, data } = await postPendenciaStudentReconcile({
        pendencia_id: pendenciaId,
        action: 'link_existing',
        student_id: found.student.id,
      });
      if (!res.ok || !data?.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data?.error || 'Falha ao mesclar pendência com aluno existente.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }
      const linkedIds = Array.isArray(data?.linked_pendencia_ids) && data.linked_pendencia_ids.length
        ? data.linked_pendencia_ids
        : [pendenciaId];
      const removedRows = removePendenciaRowsByIds(linkedIds);
      if (removedRows === 0) {
        renderPendenciaStudentLink(row, data.student || found.student);
      }
      if (lookupInput) lookupInput.value = '';
      if (pendenciaMessage) {
        pendenciaMessage.textContent = removedRows > 1
          ? `Pendências vinculadas e removidas da lista: ${removedRows}.`
          : 'Pendência mesclada com aluno existente e removida da lista.';
        pendenciaMessage.className = 'charge-message success';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao mesclar pendência com aluno existente.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

pendenciaCreateStudentButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    if (!(button instanceof HTMLElement)) return;
    const pendenciaId = button.dataset.id;
    const row = button.closest('tr');
    if (!pendenciaId || !row) return;

    const rowStudentName = row.querySelector('[data-col="student-name"]')?.textContent?.trim() || '';
    const studentName = window.prompt('Nome do aluno para incluir no banco:', rowStudentName || '');
    if (studentName === null) return;
    const studentNameTrimmed = studentName.trim();
    if (!studentNameTrimmed) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Informe o nome do aluno para incluir no banco.';
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    const gradeRaw = window.prompt('Série do aluno (6, 7 ou 8):', '6');
    if (gradeRaw === null) return;
    const gradeDigits = String(gradeRaw).replace(/\D/g, '');
    if (!['6', '7', '8'].includes(gradeDigits)) {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Série inválida. Use 6, 7 ou 8.';
        pendenciaMessage.className = 'charge-message error';
      }
      return;
    }

    const classNameRaw = window.prompt('Turma (opcional, ex: 6º Ano - A):', '');
    if (classNameRaw === null) return;
    const enrollmentRaw = window.prompt('Matrícula (opcional):', '');
    if (enrollmentRaw === null) return;

    button.setAttribute('disabled', 'disabled');
    if (pendenciaMessage) {
      pendenciaMessage.textContent = 'Incluindo aluno no banco e vinculando pendência...';
      pendenciaMessage.className = 'charge-message';
    }
    try {
      const { res, data } = await postPendenciaStudentReconcile({
        pendencia_id: pendenciaId,
        action: 'create_student',
        student_name: studentNameTrimmed,
        grade: Number(gradeDigits),
        class_name: String(classNameRaw || '').trim(),
        enrollment: String(enrollmentRaw || '').trim(),
      });
      if (!res.ok || !data?.ok) {
        if (pendenciaMessage) {
          pendenciaMessage.textContent = data?.error || 'Falha ao incluir aluno no banco.';
          pendenciaMessage.className = 'charge-message error';
        }
        return;
      }

      const student = data.student || null;
      if (student) {
        const linkedIds = Array.isArray(data?.linked_pendencia_ids) && data.linked_pendencia_ids.length
          ? data.linked_pendencia_ids
          : [pendenciaId];
        const removedRows = removePendenciaRowsByIds(linkedIds);
        if (removedRows === 0) {
          renderPendenciaStudentLink(row, student);
        }
        const newName = String(student.name || '').trim();
        if (newName && !studentNames.has(newName)) {
          studentNames.add(newName);
          adminStudents.push(student);
          if (studentList) {
            const option = document.createElement('option');
            option.value = newName;
            studentList.appendChild(option);
          }
          if (viewUserStudentsList) {
            const optionView = document.createElement('option');
            optionView.value = newName;
            viewUserStudentsList.appendChild(optionView);
          }
          if (pendenciaStudentsList) {
            const gradeLabel = student.grade ? `${student.grade}º ano` : '';
            const classLabel = String(student.class_name || '').trim();
            const enrollmentLabel = String(student.enrollment || '').trim();
            const details = [gradeLabel, classLabel, enrollmentLabel ? `Matrícula ${enrollmentLabel}` : '']
              .filter(Boolean)
              .join(' • ');
            const lookupLabel = details ? `${newName} • ${details}` : newName;
            const optionPendencia = document.createElement('option');
            optionPendencia.value = lookupLabel;
            pendenciaStudentsList.appendChild(optionPendencia);
            if (student.id) {
              studentLookupByLabel.set(lookupLabel, student);
            }
          }
        }
      }

      if (pendenciaMessage) {
        const linkedCount = Array.isArray(data?.linked_pendencia_ids) ? data.linked_pendencia_ids.length : 1;
        if (data.created_student) {
          pendenciaMessage.textContent = linkedCount > 1
            ? `Aluno incluído e ${linkedCount} pendências vinculadas/removidas da lista.`
            : 'Aluno incluído no banco e pendência vinculada/removida da lista.';
        } else {
          pendenciaMessage.textContent = linkedCount > 1
            ? `${linkedCount} pendências vinculadas ao aluno existente e removidas da lista.`
            : 'Aluno já existia no banco e a pendência foi vinculada/removida da lista.';
        }
        pendenciaMessage.className = 'charge-message success';
      }
    } catch {
      if (pendenciaMessage) {
        pendenciaMessage.textContent = 'Falha ao incluir aluno no banco.';
        pendenciaMessage.className = 'charge-message error';
      }
    } finally {
      button.removeAttribute('disabled');
    }
  });
});

function buildSyncDuplicateDayUsePopupMessage(duplicates) {
  const sourceLabel = (source) => {
    if (source === 'payments_paid') return 'Cobrança paga (payments)';
    if (source === 'pendencia_paid') return 'Pendência já paga';
    return source || '-';
  };
  const lines = [
    'Atenção: encontramos pendências duplicadas no SAAS para o MESMO dia de day-use.',
    'Essas pendências já possuem uma cobrança paga para o mesmo aluno/data do day-use.',
    '',
    'Revise abaixo antes de confirmar a remoção:',
  ];
  duplicates.slice(0, 12).forEach((item) => {
    const student = item.student_name || '-';
    const guardian = item.guardian_name || '-';
    const date = formatIsoDateBr(item.payment_date || '-');
    const paidSource = sourceLabel(item.paid_source);
    const paidDate = formatIsoDateBr(item.paid_payment_date || item.payment_date || '-');
    const paidAt = formatDateTimeBR(item.paid_at || '-');
    const paidAmount = formatCurrency(item.paid_amount || 0);
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
}

function buildSyncDuplicatePaymentsPopupMessage(duplicates) {
  const lines = [
    'ATENÇÃO: encontramos cobranças duplicadas para o MESMO dia de day-use.',
    'O aluno não deve ter duas cobranças para o mesmo dia.',
    '',
    'Cobranças duplicadas detectadas (serão removidas as extras):',
  ];
  duplicates.slice(0, 12).forEach((item) => {
    const student = item.student_name || '-';
    const guardian = item.guardian_name || '-';
    const date = formatIsoDateBr(item.payment_date || '-');
    const amount = formatCurrency(item.remove_amount || 0);
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
}

async function postSyncChargesPayments(payload) {
  const res = await fetch('/api/admin-sync-charges-payments.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload || {}),
  });
  const data = await res.json();
  return { res, data };
}

if (syncChargesPaymentsButton) {
  syncChargesPaymentsButton.addEventListener('click', async () => {
    syncChargesPaymentsButton.setAttribute('disabled', 'disabled');
    const originalText = syncChargesPaymentsButton.textContent;
    syncChargesPaymentsButton.textContent = 'Analisando duplicidades...';
    if (syncChargesPaymentsMessage) {
      syncChargesPaymentsMessage.textContent = 'Checando duplicidade de dia (mesmo aluno + mesmo day-use)...';
      syncChargesPaymentsMessage.className = 'charge-message';
    }

    try {
      const previewResult = await postSyncChargesPayments({ preview_duplicate_dayuse: true });
      const previewRes = previewResult.res;
      const previewData = previewResult.data;
      if (!previewRes.ok || !previewData?.ok) {
        if (syncChargesPaymentsMessage) {
          syncChargesPaymentsMessage.textContent =
            previewData?.error || 'Falha ao checar duplicidades de day-use.';
          syncChargesPaymentsMessage.className = 'charge-message error';
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
        const wantsToRemove = await showAdminConfirm(
          buildSyncDuplicateDayUsePopupMessage(duplicateItems),
          { title: 'Duplicidades em pendências', confirmText: 'Remover duplicidades' },
        );
        if (!wantsToRemove) {
          if (syncChargesPaymentsMessage) {
            syncChargesPaymentsMessage.textContent =
              'Atualização cancelada. Revise as pendências duplicadas de day-use.';
            syncChargesPaymentsMessage.className = 'charge-message';
          }
          return;
        }
        syncPayload = { confirm_remove_duplicate_dayuse: true };
      }
      if (duplicatePaymentItems.length > 0) {
        const wantsToRemovePayments = await showAdminConfirm(
          buildSyncDuplicatePaymentsPopupMessage(duplicatePaymentItems),
          { title: 'Duplicidades em cobranças', confirmText: 'Excluir extras' },
        );
        if (!wantsToRemovePayments) {
          if (syncChargesPaymentsMessage) {
            syncChargesPaymentsMessage.textContent =
              'Atualização cancelada. Revise as cobranças duplicadas de day-use.';
            syncChargesPaymentsMessage.className = 'charge-message';
          }
          return;
        }
        syncPayload = {
          ...syncPayload,
          confirm_remove_duplicate_payments: true,
        };
      }

      syncChargesPaymentsButton.textContent = 'Sincronizando...';
      if (syncChargesPaymentsMessage) {
        syncChargesPaymentsMessage.textContent = 'Executando varredura de cobranças/pagamentos no Asaas...';
        syncChargesPaymentsMessage.className = 'charge-message';
      }

      const syncResult = await postSyncChargesPayments(syncPayload);
      const res = syncResult.res;
      const data = syncResult.data;
      if (!res.ok || !data?.ok) {
        if (syncChargesPaymentsMessage) {
          syncChargesPaymentsMessage.textContent = data?.error || 'Falha ao sincronizar cobranças e pagamentos.';
          syncChargesPaymentsMessage.className = 'charge-message error';
        }
        return;
      }

      const summary = data.summary || {};
      if (syncChargesPaymentsMessage) {
        syncChargesPaymentsMessage.textContent = `Sincronização concluída. Duplicidades em pendências (mesmo dia): ${summary.duplicate_dayuse_detected || 0}, removidas: ${summary.pendencias_removed_duplicate_dayuse || 0}. Duplicidades em cobranças: ${summary.duplicate_payments_detected || 0}, removidas: ${summary.duplicate_payments_removed || 0}. Payments verificados: ${summary.payments_checked || 0}, atualizados para pago: ${summary.payments_paid_updated || 0}, cancelados: ${summary.payments_canceled_updated || 0}, não encontrados: ${summary.payments_not_found || 0}. Pendências verificadas: ${summary.pendencias_checked || 0}, pagas: ${summary.pendencias_paid_updated || 0}, removidas sem cobrança no Asaas: ${summary.pendencias_removed_no_charge || 0}, desvinculadas: ${summary.pendencias_unlinked || 0}. Recarregue a página quando quiser refletir tudo na tabela.`;
        syncChargesPaymentsMessage.className = 'charge-message success';
      }
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
        const row = button.closest('tr');
        if (row) {
          const tbody = row.parentElement;
          row.remove();
          if (tbody && !tbody.querySelector('.js-merge-duplicates')) {
            tbody.innerHTML = '<tr><td colspan="6">Nenhum duplicado encontrado.</td></tr>';
          }
        }
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
    if (cashflowMonthlyModeInput) cashflowMonthlyModeInput.value = 'subtract';
    if (cashflowExcludeStudentInput) cashflowExcludeStudentInput.value = '';
    if (cashflowExcludeTermInput) cashflowExcludeTermInput.value = '';
    loadCashflow();
  });
}

if (asaasDataRefreshButton) {
  asaasDataRefreshButton.addEventListener('click', () => {
    loadAsaasData(true);
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

  viewUserStudentInput.addEventListener('input', () => {
    updateViewUserAutocompleteOptions(viewUserStudentInput.value);
  });
  viewUserStudentInput.addEventListener('focus', () => {
    updateViewUserAutocompleteOptions(viewUserStudentInput.value);
  });

  viewUserButton.addEventListener('click', async () => {
    const resolved = resolveStudentNameForAdmin(viewUserStudentInput.value);
    if (!resolved.ok) {
      await showAdminAlert(resolved.error || 'Aluno não encontrado na lista.');
      return;
    }
    const studentName = resolved.name;
    viewUserStudentInput.value = studentName;

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
        await showAdminAlert(data.error || 'Falha ao abrir visão de usuário.');
        return;
      }
      const url = data.url || '/dashboard.php';
      const win = window.open(url, '_blank', 'noopener');
      if (!win) {
        window.location.href = url;
      }
    } catch {
      await showAdminAlert('Falha ao abrir visão de usuário.');
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
    addGuardianButton.addEventListener('click', async () => {
      const resolved = resolveStudentNameForAdmin(viewUserStudentInput.value);
      if (!resolved.ok) {
        await showAdminAlert(resolved.error || 'Aluno não encontrado na lista.');
        return;
      }
      const studentName = resolved.name;
      viewUserStudentInput.value = studentName;
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

const initialTab = document.body?.dataset?.activeTab || 'charges';
rebuildMonthlyMaps();
renderMonthlyTable();
renderAttendanceDayQueue();
setActiveTab(initialTab);
if (initialTab === 'inadimplentes') {
  maybeAlertInadimplentesDuplicates();
  maybeAlertInadimplentesMonthly();
}
if (initialTab === 'chamada') {
  loadAttendanceOffices();
  loadAttendanceCalls();
}
if (initialTab === 'dados-asaas') {
  loadAsaasData();
}
loadStudents();
