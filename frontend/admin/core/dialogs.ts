import type { AdminRuntime, RuntimeValue } from './runtime';

export function initializeCoreDialogs(runtime: AdminRuntime): void {
  runtime.ensureAdminDialog = function ensureAdminDialog() {
      if (runtime.adminDialogInstance)
          return runtime.adminDialogInstance;
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
        .admin-dialog-form{
          display:grid;
          grid-template-columns:1fr;
          gap:10px;
        }
        .admin-dialog-form-row{
          display:grid;
          grid-template-columns:1fr;
          gap:6px;
        }
        .admin-dialog-form label{
          font-size:12px;
          font-weight:700;
          color:#334155;
        }
        .admin-dialog-form input,
        .admin-dialog-form select{
          width:100%;
          padding:10px 12px;
          border:1px solid #CBD5E1;
          border-radius:10px;
          font-size:14px;
          line-height:1.2;
          background:#fff;
          color:#0F172A;
        }
        .admin-dialog-form small{
          display:block;
          font-size:12px;
          color:#64748B;
        }
        .admin-dialog-form-error{
          min-height:18px;
          font-size:12px;
          color:#B91C1C;
        }
      `;
          document.head.appendChild(style);
      }
      const overlay = document.createElement('div');
      overlay.className = 'admin-dialog-overlay hidden';
      overlay.innerHTML = `
      <div class="admin-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="admin-dialog-title">
        <h3 id="admin-dialog-title" class="admin-dialog-title"></h3>
        <div class="admin-dialog-message"></div>
        <input class="admin-dialog-input hidden" type="text" inputmode="numeric" />
        <div class="admin-dialog-actions">
          <button type="button" class="btn btn-ghost btn-sm admin-dialog-cancel">Cancelar</button>
          <button type="button" class="btn btn-primary btn-sm admin-dialog-confirm">Confirmar</button>
        </div>
      </div>
    `;
      document.body.appendChild(overlay);
      runtime.adminDialogInstance = {
          overlay,
          panel: overlay.querySelector('.admin-dialog-panel'),
          title: overlay.querySelector('.admin-dialog-title'),
          message: overlay.querySelector('.admin-dialog-message'),
          input: overlay.querySelector('.admin-dialog-input'),
          cancel: overlay.querySelector('.admin-dialog-cancel'),
          confirm: overlay.querySelector('.admin-dialog-confirm'),
      };
      return runtime.adminDialogInstance;
  };
  
  runtime.openAdminDialog = function openAdminDialog({ title = 'Confirmação', message = '', confirmText = 'Confirmar', cancelText = 'Cancelar', showCancel = true, }: RuntimeValue) {
      const ui = runtime.ensureAdminDialog();
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
      return new Promise((resolve: RuntimeValue) => {
          let settled = false;
          const settle = (result: RuntimeValue) => {
              if (settled)
                  return;
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
          const onOverlayClick = (event: RuntimeValue) => {
              if (event.target === ui.overlay) {
                  settle(showCancel ? false : true);
              }
          };
          const onKeyDown = (event: RuntimeValue) => {
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
  };
  
  runtime.showAdminConfirm = function showAdminConfirm(message: RuntimeValue, options: RuntimeValue = {}) {
      return runtime.openAdminDialog({
          title: options.title || 'Confirmar ação',
          message,
          confirmText: options.confirmText || 'Confirmar',
          cancelText: options.cancelText || 'Cancelar',
          showCancel: true,
      });
  };
  
  runtime.showAdminAlert = function showAdminAlert(message: RuntimeValue, options: RuntimeValue = {}) {
      return runtime.openAdminDialog({
          title: options.title || 'Atenção',
          message,
          confirmText: options.confirmText || 'OK',
          cancelText: 'Cancelar',
          showCancel: false,
      });
  };
  
  runtime.toShortMaskedDate = function toShortMaskedDate(value: RuntimeValue) {
      const raw = String(value || '').trim();
      if (!raw)
          return '';
      const isoMatch = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
      if (isoMatch) {
          return `${isoMatch[3]}/${isoMatch[2]}/${isoMatch[1]?.slice(-2)}`;
      }
      const brMatch = /^(\d{2})\/(\d{2})\/(\d{2,4})$/.exec(raw);
      if (brMatch) {
          return `${brMatch[1]}/${brMatch[2]}/${String(brMatch[3]).slice(-2)}`;
      }
      return raw;
  };
  
  runtime.applyShortDateMask = function applyShortDateMask(input: RuntimeValue) {
      const digits = String(input.value || '').replace(/\D/g, '').slice(0, 6);
      let value = digits;
      if (digits.length > 2)
          value = `${digits.slice(0, 2)}/${digits.slice(2)}`;
      if (digits.length > 4)
          value = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
      input.value = value;
  };
  
  runtime.showAdminDateInput = function showAdminDateInput(initialValue: RuntimeValue = '') {
      const ui = runtime.ensureAdminDialog();
      ui.title.textContent = 'Editar Data Day Use';
      ui.message.textContent = 'Informe a nova Data Day Use (DD/MM/AA):';
      ui.confirm.textContent = 'Salvar';
      ui.cancel.textContent = 'Cancelar';
      ui.cancel.classList.remove('hidden');
      if (ui.input) {
          ui.input.classList.remove('hidden');
          ui.input.placeholder = 'DD/MM/AA';
          ui.input.value = runtime.toShortMaskedDate(initialValue);
      }
      ui.overlay.classList.remove('hidden');
      return new Promise((resolve: RuntimeValue) => {
          let settled = false;
          const settle = (result: RuntimeValue) => {
              if (settled)
                  return;
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
              if (!ui.input)
                  return;
              runtime.applyShortDateMask(ui.input);
          };
          const onConfirm = () => {
              const value = String(ui.input?.value || '').trim();
              if (!/^\d{2}\/\d{2}\/\d{2}$/.test(value)) {
                  ui.message.textContent = 'Data inválida. Use exatamente DD/MM/AA.';
                  if (ui.input)
                      ui.input.focus();
                  return;
              }
              settle({ ok: true, value });
          };
          const onCancel = () => settle({ ok: false, value: '' });
          const onOverlayClick = (event: RuntimeValue) => {
              if (event.target === ui.overlay) {
                  settle({ ok: false, value: '' });
              }
          };
          const onKeyDown = (event: RuntimeValue) => {
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
              runtime.applyShortDateMask(ui.input);
              ui.input.focus();
          }
          else {
              ui.confirm.focus();
          }
      });
  };
  
  runtime.parseDiscountInput = function parseDiscountInput(value: RuntimeValue) {
      const raw = String(value || '').trim();
      if (!raw)
          return { ok: true, value: null };
      const normalized = raw.replace(/\s+/g, '').replace(/^R\$/i, '').replace(/\./g, '').replace(',', '.');
      if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
          return { ok: false, error: 'Desconto inválido. Use formato como 10,00.' };
      }
      const parsed = Number(normalized);
      if (!Number.isFinite(parsed) || parsed < 0) {
          return { ok: false, error: 'Desconto inválido.' };
      }
      if (parsed === 0)
          return { ok: true, value: null };
      return { ok: true, value: Math.round(parsed * 100) / 100 };
  };
  
  runtime.formatDiscountInputValue = function formatDiscountInputValue(value: RuntimeValue) {
      const amount = Number(value);
      if (!Number.isFinite(amount) || amount <= 0)
          return '';
      return amount.toFixed(2).replace('.', ',');
  };
  
  runtime.normalizeDayUseTypeValue = function normalizeDayUseTypeValue(value: RuntimeValue) {
      const normalized = String(value || '').trim().toLowerCase();
      if (normalized === 'planejada' || normalized === 'emergencial')
          return normalized;
      return 'emergencial';
  };
  
  runtime.showAdminAttendanceEditInput = function showAdminAttendanceEditInput(initialValue: RuntimeValue = {}) {
      const ui = runtime.ensureAdminDialog();
      ui.title.textContent = 'Editar Day Use';
      ui.confirm.textContent = 'Salvar';
      ui.cancel.textContent = 'Cancelar';
      ui.cancel.classList.remove('hidden');
      if (ui.input) {
          ui.input.classList.add('hidden');
          ui.input.value = '';
          ui.input.placeholder = '';
      }
      const initialDate = runtime.toShortMaskedDate(initialValue.attendance_date || '');
      const initialType = runtime.normalizeDayUseTypeValue(initialValue.day_use_type || '');
      const initialDiscount = runtime.formatDiscountInputValue(initialValue.discount_amount);
      const initialReason = String(initialValue.discount_reason || '').trim();
      ui.message.innerHTML = `
      <div class="admin-dialog-form">
        <div class="admin-dialog-form-row">
          <label for="admin-attendance-edit-date">Data Day Use (DD/MM/AA)</label>
          <input id="admin-attendance-edit-date" type="text" inputmode="numeric" placeholder="DD/MM/AA" value="${runtime.escapeHtml(initialDate)}" />
        </div>
        <div class="admin-dialog-form-row">
          <label for="admin-attendance-edit-type">Tipo de day use</label>
          <select id="admin-attendance-edit-type">
            <option value="planejada" ${initialType === 'planejada' ? 'selected' : ''}>Planejada</option>
            <option value="emergencial" ${initialType === 'emergencial' ? 'selected' : ''}>Emergencial</option>
          </select>
        </div>
        <div class="admin-dialog-form-row">
          <label for="admin-attendance-edit-discount">Desconto (R$)</label>
          <input id="admin-attendance-edit-discount" type="text" inputmode="decimal" placeholder="Ex.: 10,00" value="${runtime.escapeHtml(initialDiscount)}" />
          <small>Deixe em branco para não aplicar desconto.</small>
        </div>
        <div class="admin-dialog-form-row">
          <label for="admin-attendance-edit-reason">Motivo do desconto</label>
          <input id="admin-attendance-edit-reason" type="text" placeholder="Ex.: Fidelização / ajuste comercial" value="${runtime.escapeHtml(initialReason)}" />
        </div>
        <div class="admin-dialog-form-error" id="admin-attendance-edit-error"></div>
      </div>
    `;
      ui.overlay.classList.remove('hidden');
      const dateInput = ui.message.querySelector('#admin-attendance-edit-date');
      const typeInput = ui.message.querySelector('#admin-attendance-edit-type');
      const discountInput = ui.message.querySelector('#admin-attendance-edit-discount');
      const reasonInput = ui.message.querySelector('#admin-attendance-edit-reason');
      const errorBox = ui.message.querySelector('#admin-attendance-edit-error');
      return new Promise((resolve: RuntimeValue) => {
          let settled = false;
          const settle = (result: RuntimeValue) => {
              if (settled)
                  return;
              settled = true;
              ui.overlay.classList.add('hidden');
              ui.confirm.removeEventListener('click', onConfirm);
              ui.cancel.removeEventListener('click', onCancel);
              ui.overlay.removeEventListener('click', onOverlayClick);
              document.removeEventListener('keydown', onKeyDown);
              if (dateInput)
                  dateInput.removeEventListener('input', onDateInput);
              resolve(result);
          };
          const setError = (message: RuntimeValue) => {
              if (errorBox)
                  errorBox.textContent = String(message || '');
          };
          const onDateInput = () => {
              if (!dateInput)
                  return;
              runtime.applyShortDateMask(dateInput);
          };
          const onConfirm = () => {
              const dateValue = String(dateInput?.value || '').trim();
              if (!/^\d{2}\/\d{2}\/\d{2}$/.test(dateValue)) {
                  setError('Data inválida. Use exatamente DD/MM/AA.');
                  dateInput?.focus();
                  return;
              }
              const dayUseType = runtime.normalizeDayUseTypeValue(typeInput?.value || '');
              const discountResult = runtime.parseDiscountInput(String(discountInput?.value || ''));
              if (!discountResult.ok) {
                  setError(discountResult.error || 'Desconto inválido.');
                  discountInput?.focus();
                  return;
              }
              const discountAmount = discountResult.value;
              const discountReason = String(reasonInput?.value || '').trim();
              if (discountAmount !== null && discountAmount > 0 && !discountReason) {
                  setError('Informe o motivo quando houver desconto.');
                  reasonInput?.focus();
                  return;
              }
              setError('');
              settle({
                  ok: true,
                  value: {
                      attendance_date: dateValue,
                      day_use_type: dayUseType,
                      discount_amount: discountAmount,
                      discount_reason: discountReason,
                  },
              });
          };
          const onCancel = () => settle({ ok: false, value: null });
          const onOverlayClick = (event: RuntimeValue) => {
              if (event.target === ui.overlay) {
                  settle({ ok: false, value: null });
              }
          };
          const onKeyDown = (event: RuntimeValue) => {
              if (event.key === 'Escape') {
                  event.preventDefault();
                  settle({ ok: false, value: null });
                  return;
              }
              if (event.key === 'Enter') {
                  const tagName = String(event.target?.tagName || '').toLowerCase();
                  if (tagName !== 'button') {
                      event.preventDefault();
                      onConfirm();
                  }
              }
          };
          ui.confirm.addEventListener('click', onConfirm);
          ui.cancel.addEventListener('click', onCancel);
          ui.overlay.addEventListener('click', onOverlayClick);
          document.addEventListener('keydown', onKeyDown);
          if (dateInput) {
              dateInput.addEventListener('input', onDateInput);
              runtime.applyShortDateMask(dateInput);
              dateInput.focus();
          }
          else {
              ui.confirm.focus();
          }
      });
  };
  
  runtime.showAdminDiscountInput = function showAdminDiscountInput() {
      const ui = runtime.ensureAdminDialog();
      ui.title.textContent = 'Criar desconto (opcional)';
      ui.message.textContent =
          'Informe o desconto em R$ para esta cobrança.\nDeixe em branco para autorizar sem desconto.';
      ui.confirm.textContent = 'Autorizar e cobrar';
      ui.cancel.textContent = 'Cancelar';
      ui.cancel.classList.remove('hidden');
      if (ui.input) {
          ui.input.classList.remove('hidden');
          ui.input.placeholder = 'Ex.: 10,00';
          ui.input.value = '';
          ui.input.setAttribute('inputmode', 'decimal');
      }
      ui.overlay.classList.remove('hidden');
      return new Promise((resolve: RuntimeValue) => {
          let settled = false;
          const settle = (result: RuntimeValue) => {
              if (settled)
                  return;
              settled = true;
              ui.overlay.classList.add('hidden');
              ui.confirm.removeEventListener('click', onConfirm);
              ui.cancel.removeEventListener('click', onCancel);
              ui.overlay.removeEventListener('click', onOverlayClick);
              document.removeEventListener('keydown', onKeyDown);
              if (ui.input) {
                  ui.input.classList.add('hidden');
                  ui.input.placeholder = '';
                  ui.input.setAttribute('inputmode', 'numeric');
              }
              resolve(result);
          };
          const onConfirm = () => settle({ ok: true, value: String(ui.input?.value || '').trim() });
          const onCancel = () => settle({ ok: false, value: '' });
          const onOverlayClick = (event: RuntimeValue) => {
              if (event.target === ui.overlay)
                  settle({ ok: false, value: '' });
          };
          const onKeyDown = (event: RuntimeValue) => {
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
          if (ui.input)
              ui.input.focus();
      });
  };
  
  runtime.showManualSettlementInput = function showManualSettlementInput(details: RuntimeValue = {}) {
      const ui = runtime.ensureAdminDialog();
      ui.title.textContent = 'Dar baixa manual';
      ui.message.textContent =
          `Aluno: ${details.student || '-'}\n` +
              `Data: ${details.date || '-'}\n` +
              `Valor: ${details.amount || '-'}\n\n` +
              'Escreva a observação/motivo da baixa. Ex.: PIX recebido na conta Inter.';
      ui.confirm.textContent = 'Confirmar baixa';
      ui.cancel.textContent = 'Cancelar';
      ui.cancel.classList.remove('hidden');
      if (ui.input) {
          ui.input.classList.remove('hidden');
          ui.input.placeholder = 'Ex.: PIX recebido na conta Inter em 26/05';
          ui.input.value = '';
          ui.input.removeAttribute('inputmode');
      }
      ui.overlay.classList.remove('hidden');
      return new Promise((resolve: RuntimeValue) => {
          let settled = false;
          const settle = (result: RuntimeValue) => {
              if (settled)
                  return;
              settled = true;
              ui.overlay.classList.add('hidden');
              ui.confirm.removeEventListener('click', onConfirm);
              ui.cancel.removeEventListener('click', onCancel);
              ui.overlay.removeEventListener('click', onOverlayClick);
              document.removeEventListener('keydown', onKeyDown);
              if (ui.input) {
                  ui.input.classList.add('hidden');
                  ui.input.placeholder = '';
                  ui.input.setAttribute('inputmode', 'numeric');
              }
              resolve(result);
          };
          const onConfirm = () => {
              const value = String(ui.input?.value || '').trim();
              if (!value) {
                  if (ui.input) {
                      ui.input.placeholder = 'Observação obrigatória';
                      ui.input.focus();
                  }
                  return;
              }
              settle({ ok: true, value });
          };
          const onCancel = () => settle({ ok: false, value: '' });
          const onOverlayClick = (event: RuntimeValue) => {
              if (event.target === ui.overlay)
                  settle({ ok: false, value: '' });
          };
          const onKeyDown = (event: RuntimeValue) => {
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
          if (ui.input)
              ui.input.focus();
      });
  };
}
