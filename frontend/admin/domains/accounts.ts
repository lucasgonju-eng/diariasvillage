import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsAccounts(runtime: AdminRuntime): void {
  if (runtime.inadimplentesStudentFilterInput) {
      runtime.inadimplentesStudentFilterInput.addEventListener('input', () => {
          runtime.applyInadimplentesStudentFilter();
      });
  }
  
  if (runtime.inadimplentesStudentFilterClearButton) {
      runtime.inadimplentesStudentFilterClearButton.addEventListener('click', () => {
          if (runtime.inadimplentesStudentFilterInput) {
              runtime.inadimplentesStudentFilterInput.value = '';
          }
          runtime.applyInadimplentesStudentFilter();
      });
  }
  
  runtime.mergeButtons.forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          if (!(button instanceof HTMLElement))
              return;
          const primaryId = button.dataset.primary;
          const duplicatesRaw = button.dataset.duplicates || '[]';
          let duplicates = [];
          try {
              duplicates = JSON.parse(duplicatesRaw);
          }
          catch {
              duplicates = [];
          }
          if (!primaryId || !duplicates.length)
              return;
          button.setAttribute('disabled', 'disabled');
          if (runtime.mergeMessage) {
              runtime.mergeMessage.textContent = 'Mesclando duplicados...';
              runtime.mergeMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-merge-duplicates.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ primary_id: primaryId, duplicate_ids: duplicates }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (runtime.mergeMessage) {
                      runtime.mergeMessage.textContent = data.error || 'Falha ao mesclar duplicados.';
                      runtime.mergeMessage.className = 'charge-message error';
                  }
              }
              else if (runtime.mergeMessage) {
                  runtime.mergeMessage.textContent = 'Duplicados mesclados com sucesso.';
                  runtime.mergeMessage.className = 'charge-message success';
                  const row = button.closest('tr');
                  if (row) {
                      const tbody = row.parentElement;
                      row.remove();
                      if (tbody && !tbody.querySelector('.js-merge-duplicates')) {
                          tbody.innerHTML = '<tr><td colspan="6">Nenhum duplicado encontrado.</td></tr>';
                      }
                  }
              }
          }
          catch {
              if (runtime.mergeMessage) {
                  runtime.mergeMessage.textContent = 'Falha ao mesclar duplicados.';
                  runtime.mergeMessage.className = 'charge-message error';
              }
          }
          finally {
              button.removeAttribute('disabled');
          }
      });
  });
  
  if (runtime.resetSenhaBtn && runtime.resetLookupBtn && runtime.resetGuardianSelect && runtime.resetCpfInput && runtime.resetSenhaNovaInput && runtime.resetSenhaConfirmInput) {
      const clearResetAccountSelection = () => {
          runtime.resetGuardianSelect.replaceChildren();
          const option = document.createElement('option');
          option.value = '';
          option.textContent = 'Busque um CPF válido';
          runtime.resetGuardianSelect.appendChild(option);
          runtime.resetGuardianSelect.setAttribute('disabled', 'disabled');
          runtime.resetSenhaBtn.setAttribute('disabled', 'disabled');
      };
      runtime.resetCpfInput.addEventListener('input', (event: RuntimeValue) => {
          event.target.value = runtime.normalizeCpf(event.target.value);
          clearResetAccountSelection();
      });
      runtime.resetGuardianSelect.addEventListener('change', () => {
          if (runtime.resetGuardianSelect.value) {
              runtime.resetSenhaBtn.removeAttribute('disabled');
          }
          else {
              runtime.resetSenhaBtn.setAttribute('disabled', 'disabled');
          }
      });
      runtime.resetLookupBtn.addEventListener('click', async () => {
          const cpf = runtime.normalizeCpf(runtime.resetCpfInput.value || '');
          clearResetAccountSelection();
          if (cpf.length !== 11) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'Informe um CPF válido (11 dígitos).';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
              return;
          }
          runtime.resetLookupBtn.setAttribute('disabled', 'disabled');
          if (runtime.resetSenhaMessage) {
              runtime.resetSenhaMessage.textContent = 'Validando identidade e conta...';
              runtime.resetSenhaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-reset-password.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'lookup', cpf, csrf_token: runtime.adminCsrfToken }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  throw new Error(data?.error || 'Falha ao buscar a conta.');
              }
              const candidates = Array.isArray(data.candidates) ? data.candidates : [];
              if (data.blocked) {
                  if (runtime.resetSenhaMessage) {
                      runtime.resetSenhaMessage.textContent =
                          `CPF bloqueado por conflito de identidade ou conta (${data.code || 'revisão necessária'}). ` +
                              `${candidates.length} vínculo(s) encontrado(s); corrija o cadastro antes do reset.`;
                      runtime.resetSenhaMessage.className = 'charge-message error';
                  }
                  return;
              }
              const placeholder = document.createElement('option');
              placeholder.value = '';
              placeholder.textContent = 'Selecione e confirme o vínculo';
              runtime.resetGuardianSelect.replaceChildren(placeholder);
              candidates.forEach((candidate: RuntimeValue) => {
                  const option = document.createElement('option');
                  option.value = String(candidate.guardian_id || '');
                  const studentLabel = candidate.student_name
                      ? `${candidate.student_name}${candidate.enrollment ? ` • ${candidate.enrollment}` : ''}`
                      : 'Aluno não identificado';
                  option.textContent =
                      `${candidate.guardian_name || 'Responsável'} • ${studentLabel} • ` +
                          `${candidate.email_masked || 'sem e-mail'}`;
                  runtime.resetGuardianSelect.appendChild(option);
              });
              runtime.resetGuardianSelect.removeAttribute('disabled');
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'Confira o vínculo e selecione-o antes de redefinir a senha.';
                  runtime.resetSenhaMessage.className = 'charge-message';
              }
          }
          catch (error: RuntimeValue) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = error?.message || 'Falha ao buscar a conta.';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
          }
          finally {
              runtime.resetLookupBtn.removeAttribute('disabled');
          }
      });
      runtime.resetSenhaBtn.addEventListener('click', async () => {
          const cpf = runtime.normalizeCpf(runtime.resetCpfInput.value || '');
          const guardianId = String(runtime.resetGuardianSelect.value || '').trim();
          const novaSenha = (runtime.resetSenhaNovaInput.value || '').trim();
          const confirmSenha = (runtime.resetSenhaConfirmInput.value || '').trim();
          if (cpf.length !== 11) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'Informe um CPF válido (11 dígitos).';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
              return;
          }
          if (!guardianId) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'Busque o CPF e selecione explicitamente o vínculo.';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
              return;
          }
          if (novaSenha.length < 6) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'A nova senha deve ter pelo menos 6 caracteres.';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
              return;
          }
          if (novaSenha !== confirmSenha) {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'As senhas não conferem.';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
              return;
          }
          runtime.resetSenhaBtn.setAttribute('disabled', 'disabled');
          if (runtime.resetSenhaMessage) {
              runtime.resetSenhaMessage.textContent = 'Alterando senha...';
              runtime.resetSenhaMessage.className = 'charge-message';
          }
          try {
              const res = await fetch('/api/admin-reset-password.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      action: 'reset',
                      cpf,
                      guardian_id: guardianId,
                      nova_senha: novaSenha,
                      csrf_token: runtime.adminCsrfToken,
                  }),
              });
              let data;
              try {
                  data = await res.json();
              }
              catch {
                  if (runtime.resetSenhaMessage) {
                      runtime.resetSenhaMessage.textContent = `Erro no servidor (${res.status || 'sem resposta'}). Tente novamente.`;
                      runtime.resetSenhaMessage.className = 'charge-message error';
                  }
                  return;
              }
              if (!data.ok) {
                  const errMsg = data.error ||
                      data.error_description ||
                      data.message ||
                      data.msg ||
                      'Falha ao resetar senha.';
                  if (runtime.resetSenhaMessage) {
                      runtime.resetSenhaMessage.textContent = errMsg;
                      runtime.resetSenhaMessage.className = 'charge-message error';
                  }
              }
              else {
                  if (runtime.resetSenhaMessage) {
                      const name = data.guardian_name ? ` (${data.guardian_name})` : '';
                      runtime.resetSenhaMessage.textContent = data.message + name;
                      runtime.resetSenhaMessage.className = 'charge-message success';
                  }
                  runtime.resetCpfInput.value = '';
                  runtime.resetSenhaNovaInput.value = '';
                  runtime.resetSenhaConfirmInput.value = '';
                  clearResetAccountSelection();
              }
          }
          catch {
              if (runtime.resetSenhaMessage) {
                  runtime.resetSenhaMessage.textContent = 'Falha ao resetar senha.';
                  runtime.resetSenhaMessage.className = 'charge-message error';
              }
          }
          finally {
              if (runtime.resetGuardianSelect.value) {
                  runtime.resetSenhaBtn.removeAttribute('disabled');
              }
          }
      });
  }
  
  if (runtime.secretariaPasswordInput && runtime.secretariaPasswordConfirmInput && runtime.secretariaPasswordSaveButton) {
      runtime.secretariaPasswordSaveButton.addEventListener('click', async () => {
          const password = String(runtime.secretariaPasswordInput.value || '');
          const confirmation = String(runtime.secretariaPasswordConfirmInput.value || '');
          const strongEnough = password.length >= 12 &&
              password.length <= 128 &&
              /[a-z]/.test(password) &&
              /[A-Z]/.test(password) &&
              /[0-9]/.test(password) &&
              /[^a-zA-Z0-9]/.test(password);
          if (!strongEnough) {
              if (runtime.secretariaPasswordMessage) {
                  runtime.secretariaPasswordMessage.textContent =
                      'Use ao menos 12 caracteres, com maiúscula, minúscula, número e símbolo.';
                  runtime.secretariaPasswordMessage.className = 'charge-message error';
              }
              return;
          }
          if (password !== confirmation) {
              if (runtime.secretariaPasswordMessage) {
                  runtime.secretariaPasswordMessage.textContent = 'As senhas não conferem.';
                  runtime.secretariaPasswordMessage.className = 'charge-message error';
              }
              return;
          }
          runtime.secretariaPasswordSaveButton.setAttribute('disabled', 'disabled');
          if (runtime.secretariaPasswordMessage) {
              runtime.secretariaPasswordMessage.textContent = 'Protegendo e salvando o novo acesso...';
              runtime.secretariaPasswordMessage.className = 'charge-message';
          }
          try {
              const response = await fetch('/api/admin-secretaria-access.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      password,
                      password_confirmation: confirmation,
                  }),
              });
              const data = await response.json();
              if (!response.ok || !data?.ok) {
                  throw new Error(data?.error || 'Falha ao salvar o acesso da secretaria.');
              }
              runtime.secretariaPasswordInput.value = '';
              runtime.secretariaPasswordConfirmInput.value = '';
              if (runtime.secretariaPasswordMessage) {
                  runtime.secretariaPasswordMessage.textContent = data.message;
                  runtime.secretariaPasswordMessage.className = 'charge-message success';
              }
          }
          catch (error: RuntimeValue) {
              if (runtime.secretariaPasswordMessage) {
                  runtime.secretariaPasswordMessage.textContent =
                      error instanceof Error ? error.message : 'Falha ao salvar o acesso da secretaria.';
                  runtime.secretariaPasswordMessage.className = 'charge-message error';
              }
          }
          finally {
              runtime.secretariaPasswordInput.value = '';
              runtime.secretariaPasswordConfirmInput.value = '';
              runtime.secretariaPasswordSaveButton.removeAttribute('disabled');
          }
      });
  }
}
