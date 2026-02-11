const loginForm = document.querySelector('#login-form');
const loginMessage = document.querySelector('#login-message');
const loginCpfInput = document.querySelector('#login-cpf');

function applyCpfMask(value) {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  let masked = digits;
  if (digits.length > 3) masked = `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length > 6) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  if (digits.length > 9) masked = `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
  return masked;
}

if (loginCpfInput) {
  loginCpfInput.addEventListener('input', (event) => {
    event.target.value = applyCpfMask(event.target.value);
  });
}

if (loginForm) {
  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginMessage.textContent = '';

    const payload = {
      cpf: document.querySelector('#login-cpf').value.trim(),
      password: document.querySelector('#login-password').value,
    };

    const res = await fetch('/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      loginMessage.textContent = data.error || 'Falha ao entrar.';
      loginMessage.className = 'error';
      return;
    }

    window.location.href = '/dashboard.php';
  });
}
