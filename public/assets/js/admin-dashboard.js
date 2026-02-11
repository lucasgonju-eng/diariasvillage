const tabs = document.querySelectorAll('[data-tab]');
const tabEntries = document.querySelector('#tab-entries');
const tabCharges = document.querySelector('#tab-charges');
const tabInadimplentes = document.querySelector('#tab-inadimplentes');
const tabRecebidas = document.querySelector('#tab-recebidas');
const tabSemWhatsapp = document.querySelector('#tab-sem-whatsapp');
const tabDuplicados = document.querySelector('#tab-duplicados');
const tabPendencias = document.querySelector('#tab-pendencias');
const tabResetSenha = document.querySelector('#tab-reset-senha');
const studentInput = document.querySelector('#charge-student');
const studentList = document.querySelector('#students-list');
const chargeList = document.querySelector('#charge-list');
const sendChargesButton = document.querySelector('#send-charges');
const chargeMessage = document.querySelector('#charge-message');

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
  tabs.forEach((btn) => {
    const isActive = btn.dataset.tab === name;
    btn.classList.toggle('btn-primary', isActive);
    btn.classList.toggle('admin-tab', !isActive);
    btn.style.opacity = isActive ? '1' : '0.95';
  });
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

  if (guardianCache.has(studentName)) {
    const cached = guardianCache.get(studentName);
    if (cached) {
      const nameInput = wrapper.querySelector('[name="guardian_name"]');
      const emailInput = wrapper.querySelector('[name="guardian_email"]');
      const phoneInput = wrapper.querySelector('[name="guardian_whatsapp"]');
      const docInput = wrapper.querySelector('[name="guardian_document"]');
      if (nameInput && !nameInput.value) nameInput.value = cached.parent_name || '';
      if (emailInput && !emailInput.value) emailInput.value = cached.email || '';
      if (phoneInput && !phoneInput.value) phoneInput.value = cached.parent_phone || '';
      if (docInput && !docInput.value) docInput.value = cached.parent_document || '';
    }
    return;
  }

  try {
    const res = await fetch(`/api/guardian-by-student.php?name=${encodeURIComponent(studentName)}`);
    const data = await res.json();
    const guardian = data.ok ? data.guardian : null;
    guardianCache.set(studentName, guardian);
    if (!guardian) return;
    const nameInput = wrapper.querySelector('[name="guardian_name"]');
    const emailInput = wrapper.querySelector('[name="guardian_email"]');
    const phoneInput = wrapper.querySelector('[name="guardian_whatsapp"]');
    const docInput = wrapper.querySelector('[name="guardian_document"]');
    if (nameInput && !nameInput.value) nameInput.value = guardian.parent_name || '';
    if (emailInput && !emailInput.value) emailInput.value = guardian.email || '';
    if (phoneInput && !phoneInput.value) phoneInput.value = guardian.parent_phone || '';
    if (docInput && !docInput.value) docInput.value = guardian.parent_document || '';
  } catch (err) {
    guardianCache.set(studentName, null);
  }
}

async function loadStudents() {
  if (!studentList) return;
  const res = await fetch('/api/students.php');
  const data = await res.json();
  if (!data.ok) return;
  studentList.innerHTML = '';
  studentNames.clear();
  data.students.forEach((student) => {
    const option = document.createElement('option');
    option.value = student.name;
    studentList.appendChild(option);
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
  btn.addEventListener('click', () => setActiveTab(btn.dataset.tab));
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
          showChargeMessage('Algumas cobranças falharam. Verifique os dados.', true);
        } else {
          showChargeMessage('Cobrança feita!');
          resetChargeForm();
          window.location.reload();
        }
      }
    } catch (err) {
      showChargeMessage('Falha ao enviar cobranças.', true);
    } finally {
      sendChargesButton.disabled = false;
      sendChargesButton.textContent = 'Enviar cobranças';
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
      const data = await res.json();
      if (!data.ok) {
        if (resetSenhaMessage) {
          resetSenhaMessage.textContent = data.error || 'Falha ao resetar senha.';
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

setActiveTab('charges');
loadStudents();
