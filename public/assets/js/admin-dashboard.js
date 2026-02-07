const tabs = document.querySelectorAll('[data-tab]');
const tabEntries = document.querySelector('#tab-entries');
const tabCharges = document.querySelector('#tab-charges');
const tabInadimplentes = document.querySelector('#tab-inadimplentes');
const tabRecebidas = document.querySelector('#tab-recebidas');
const studentInput = document.querySelector('#charge-student');
const studentList = document.querySelector('#students-list');
const chargeList = document.querySelector('#charge-list');
const sendChargesButton = document.querySelector('#send-charges');
const chargeMessage = document.querySelector('#charge-message');

const selectedStudents = new Set();

function setActiveTab(name) {
  if (!tabEntries || !tabCharges || !tabInadimplentes || !tabRecebidas) return;
  tabEntries.classList.toggle('hidden', name !== 'entries');
  tabCharges.classList.toggle('hidden', name !== 'charges');
  tabInadimplentes.classList.toggle('hidden', name !== 'inadimplentes');
  tabRecebidas.classList.toggle('hidden', name !== 'recebidas');
  tabs.forEach((btn) => {
    const isActive = btn.dataset.tab === name;
    btn.classList.toggle('btn-primary', isActive);
    btn.classList.toggle('admin-tab', !isActive);
    btn.style.opacity = isActive ? '1' : '0.95';
  });
}

function addChargeItem(studentName) {
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
}

async function loadStudents() {
  if (!studentList) return;
  const res = await fetch('/api/students.php');
  const data = await res.json();
  if (!data.ok) return;
  studentList.innerHTML = '';
  data.students.forEach((student) => {
    const option = document.createElement('option');
    option.value = student.name;
    studentList.appendChild(option);
  });
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
  studentInput.addEventListener('change', () => {
    const value = studentInput.value.trim();
    if (value) {
      addChargeItem(value);
      studentInput.value = '';
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

setActiveTab('charges');
loadStudents();
