const profileForm = document.querySelector('#profile-form');
const profileMessage = document.querySelector('#profile-message');

if (profileForm) {
  profileForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    profileMessage.textContent = '';

    const payload = {
      parent_name: document.querySelector('#parent-name').value.trim(),
      parent_phone: document.querySelector('#parent-phone').value.trim(),
      parent_document: document.querySelector('#parent-document').value.trim(),
      password: document.querySelector('#new-password').value,
      password_confirm: document.querySelector('#new-password-confirm').value,
    };

    const res = await fetch('/api/profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.ok) {
      profileMessage.textContent = data.error || 'Não foi possível atualizar.';
      profileMessage.className = 'error';
      return;
    }

    profileMessage.textContent = 'Dados atualizados com sucesso.';
    profileMessage.className = 'success';
  });
}
