const form = document.querySelector('#register-form');
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
  if (message) {
    message.textContent = '';
    message.className = '';
  }
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    message.textContent = '';
    const payload = {
      cpf: document.querySelector('#cpf').value.trim(),
      password: document.querySelector('#password').value,
      password_confirm: document.querySelector('#password-confirm').value,
    };

    const res = await fetch('/api/register-primeiro-acesso.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      message.textContent = data.error || 'Não foi possível cadastrar. Tente novamente.';
      message.className = 'error';
      if (
        data.error &&
        (data.error.toLowerCase().includes('não encontrado') ||
          data.error.toLowerCase().includes('nao encontrado'))
      ) {
        if (pendingForm && openPendingButton) {
          pendingForm.style.display = 'block';
          openPendingButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
          const pendingCpf = document.querySelector('#pending-cpf');
          if (pendingCpf && cpfInput && !pendingCpf.value) {
            pendingCpf.value = cpfInput.value.trim();
          }
        }
      }
      return;
    }

    message.textContent = 'Conta criada! Você receberá um e-mail de validação (confirmação). Confira sua caixa de entrada e a pasta de spam.';
    message.className = 'success';
    form.reset();
  });
}

if (openPendingButton && pendingForm) {
  openPendingButton.addEventListener('click', () => {
    pendingForm.style.display = pendingForm.style.display === 'none' ? 'block' : 'none';
    if (pendingForm.style.display === 'block') {
      const pendingCpf = document.querySelector('#pending-cpf');
      if (pendingCpf && cpfInput && !pendingCpf.value) {
        pendingCpf.value = cpfInput.value.trim();
      }
      const pendingGuardian = document.querySelector('#pending-guardian');
      if (pendingGuardian && !pendingGuardian.value) {
        pendingGuardian.focus();
      }
    }
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
    if (
      !payload.student_name ||
      !payload.guardian_name ||
      !payload.guardian_cpf ||
      !payload.guardian_email
    ) {
      if (pendingMessage) {
        pendingMessage.textContent = 'Preencha nome do aluno, responsável, CPF e e-mail.';
        pendingMessage.className = 'error';
      }
      return;
    }

    try {
      const res = await fetch('/api/pendencia-cadastro.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!data.ok) {
        if (pendingMessage) {
          pendingMessage.textContent = data.error || 'Falha ao enviar pendência.';
          pendingMessage.className = 'error';
        }
        return;
      }

      if (pendingMessage) {
        pendingMessage.textContent = 'Pendência enviada. Verifique seu e-mail para confirmar.';
        pendingMessage.className = 'success';
      }
      pendingForm.reset();
    } catch (error) {
      if (pendingMessage) {
        pendingMessage.textContent = 'Falha ao enviar pendência. Tente novamente.';
        pendingMessage.className = 'error';
      }
    }
  });
}
