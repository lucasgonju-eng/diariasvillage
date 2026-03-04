const profileForm = document.querySelector('#profile-form');
const profileMessage = document.querySelector('#profile-message');
const addGuardianForm = document.querySelector('#add-guardian-form');
const addGuardianMessage = document.querySelector('#add-guardian-message');
const guardiansList = document.querySelector('#guardians-list');

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatDocument(value) {
  const digits = String(value ?? '').replace(/\D+/g, '');
  if (digits.length === 11) {
    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9, 11)}`;
  }
  if (digits.length === 14) {
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12, 14)}`;
  }
  return value || '-';
}

async function loadGuardiansList() {
  if (!guardiansList) return;
  guardiansList.textContent = 'Carregando...';

  try {
    const res = await fetch('/api/profile-guardians.php');
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      guardiansList.textContent = data?.error || 'Não foi possível carregar os responsáveis.';
      return;
    }

    const items = Array.isArray(data.guardians) ? data.guardians : [];
    if (!items.length) {
      guardiansList.textContent = 'Nenhum responsável encontrado.';
      return;
    }

    guardiansList.innerHTML = items.map((guardian) => {
      const name = escapeHtml(guardian.parent_name || 'Sem nome');
      const email = escapeHtml(guardian.email || '-');
      const phone = escapeHtml(guardian.parent_phone || '-');
      const document = escapeHtml(formatDocument(guardian.parent_document || ''));
      return `<div style="padding:8px 10px;border:1px solid #E6E9F2;border-radius:10px;margin-bottom:8px;">
        <strong>${name}</strong><br>
        <span style="font-size:12px;">E-mail: ${email} | Telefone: ${phone} | CPF/CNPJ: ${document}</span>
      </div>`;
    }).join('');
  } catch (err) {
    guardiansList.textContent = 'Não foi possível carregar os responsáveis.';
  }
}

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
    await loadGuardiansList();
  });
}

loadGuardiansList();
