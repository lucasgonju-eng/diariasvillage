const loginForm = document.querySelector('#login-form');
const loginMessage = document.querySelector('#login-message');

if (loginForm) {
  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    loginMessage.textContent = '';

    const payload = {
      email: document.querySelector('#login-email').value.trim(),
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
