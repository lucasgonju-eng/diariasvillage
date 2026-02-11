const form = document.querySelector('#register-form');
const studentsInput = document.querySelector('#student-name');
const message = document.querySelector('#form-message');
const openPendingButton = document.querySelector('#open-pending');
const pendingForm = document.querySelector('#pending-form');
const pendingMessage = document.querySelector('#pending-message');
const cpfInput = document.querySelector('#cpf');
const pendingCpfInput = document.querySelector('#pending-cpf');

function applyCpfMask(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  let masked = digits;
  if (digits.length > 3) masked = `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length > 6) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  if (digits.length > 9) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
  return masked;
}

if (cpfInput) {
  cpfInput.addEventListener('input', (event) => {
    event.target.value = applyCpfMask(event.target.value);
  });
}

if (pendingCpfInput) {
  pendingCpfInput.addEventListener('input', (event) => {
    event.target.value = applyCpfMask(event.target.value);
  });
}

if (form) {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.textContent = '';
    const payload = {
      student_name: studentsInput.value.trim(),
      cpf: document.querySelector('#cpf').value.trim(),
      email: document.querySelector('#email').value.trim(),
      password: document.querySelector('#password').value,
      password_confirm: document.querySelector('#password-confirm').value,
    };

    const res = await fetch('/api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      message.textContent =
        data.error ||
        'Nao foi possivel cadastrar. Verifique CPF, nome do aluno e e-mail.';
      message.className = 'error';
      return;
    }

    message.textContent = 'Cadastro enviado. Confirme o e-mail para continuar.';
    message.className = 'success';
    form.reset();
  });
}

if (openPendingButton && pendingForm) {
  openPendingButton.addEventListener('click', () => {
    pendingForm.style.display = pendingForm.style.display === 'none' ? 'block' : 'none';
  });
}

if (pendingForm) {
  pendingForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (pendingMessage) {
      pendingMessage.textContent = '';
      pendingMessage.className = '';
    }

    const payload = {
      student_name: document.querySelector('#pending-student').value.trim(),
      guardian_name: document.querySelector('#pending-guardian').value.trim(),
      guardian_cpf: document.querySelector('#pending-cpf').value.trim(),
      guardian_email: document.querySelector('#pending-email').value.trim(),
    };

    const res = await fetch('/api/pendencia-cadastro.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      if (pendingMessage) {
        pendingMessage.textContent = data.error || 'Falha ao enviar pendencia.';
        pendingMessage.className = 'error';
      }
      return;
    }

    if (pendingMessage) {
      pendingMessage.textContent = 'Pendencia enviada. Nossa equipe vai ajustar seu cadastro.';
      pendingMessage.className = 'success';
    }
    pendingForm.reset();
  });
}
