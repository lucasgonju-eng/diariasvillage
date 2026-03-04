const profileForm = document.querySelector('#profile-form');
const profileMessage = document.querySelector('#profile-message');
const addGuardianForm = document.querySelector('#add-guardian-form');
const addGuardianMessage = document.querySelector('#add-guardian-message');

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

if (addGuardianForm) {
  addGuardianForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (addGuardianMessage) {
      addGuardianMessage.textContent = '';
      addGuardianMessage.className = '';
    }

    const payload = {
      parent_name: document.querySelector('#extra-parent-name').value.trim(),
      email: document.querySelector('#extra-parent-email').value.trim(),
      parent_phone: document.querySelector('#extra-parent-phone').value.trim(),
      parent_document: document.querySelector('#extra-parent-document').value.trim(),
    };

    let data = null;
    try {
      const res = await fetch('/api/profile-add-guardian.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const raw = await res.text();
      data = raw ? JSON.parse(raw) : null;
      if (!res.ok || !data || !data.ok) {
        const message = data?.error || 'Não foi possível adicionar responsável.';
        if (addGuardianMessage) {
          addGuardianMessage.textContent = message;
          addGuardianMessage.className = 'error';
        }
        return;
      }
    } catch (err) {
      if (addGuardianMessage) {
        addGuardianMessage.textContent = 'Erro inesperado ao adicionar responsável.';
        addGuardianMessage.className = 'error';
      }
      return;
    }

    if (addGuardianMessage) {
      addGuardianMessage.textContent = 'Responsável adicionado com sucesso.';
      addGuardianMessage.className = 'success';
    }
    addGuardianForm.reset();
  });
}
