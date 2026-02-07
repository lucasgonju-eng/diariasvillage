const tabs = document.querySelectorAll('[data-tab]');
const tabEntries = document.querySelector('#tab-entries');
const tabCharges = document.querySelector('#tab-charges');
const studentInput = document.querySelector('#charge-student');
const studentList = document.querySelector('#students-list');
const chargeList = document.querySelector('#charge-list');
const sendChargesButton = document.querySelector('#send-charges');
const chargeMessage = document.querySelector('#charge-message');

const selectedStudents = new Set();

function setActiveTab(name) {
  if (!tabEntries || !tabCharges) return;
  tabEntries.classList.toggle('hidden', name !== 'entries');
  tabCharges.classList.toggle('hidden', name !== 'charges');
  tabs.forEach((btn) => {
    const isActive = btn.dataset.tab === name;
    btn.classList.toggle('btn-primary', isActive);
    btn.classList.toggle('admin-tab', !isActive);
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
    </div>
  `;

  wrapper.querySelector('button').addEventListener('click', () => {
    selectedStudents.delete(studentName);
    wrapper.remove();
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
  }));
}

function showChargeMessage(text, isError = false) {
  if (!chargeMessage) return;
  chargeMessage.textContent = text;
  chargeMessage.className = `charge-message ${isError ? 'error' : 'success'}`;
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
          showChargeMessage('Cobranças enviadas com sucesso!');
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

setActiveTab('entries');
loadStudents();
