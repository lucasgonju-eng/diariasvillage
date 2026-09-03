import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsViewAsUser(runtime: AdminRuntime): void {
  if (runtime.viewUserButton && runtime.viewUserStudentInput) {
      let viewUserSaveMode = 'open_user';
      const resetViewUserGuardianSelection = () => {
          if (!runtime.viewUserGuardianSelect)
              return;
          runtime.viewUserGuardianSelect.innerHTML = '<option value="">Selecione o responsável</option>';
          runtime.viewUserGuardianSelect.value = '';
          runtime.viewUserGuardianSelect.classList.add('hidden');
      };
      const showViewUserForm = (student: RuntimeValue, mode: RuntimeValue = 'open_user') => {
          const studentName = String(student?.name || '').trim();
          const studentId = String(student?.id || '').trim();
          viewUserSaveMode = mode;
          if (!runtime.viewUserForm)
              return;
          runtime.viewUserForm.classList.remove('hidden');
          if (runtime.viewUserStudentNameInput)
              runtime.viewUserStudentNameInput.value = studentName || '';
          if (runtime.viewUserStudentIdInput)
              runtime.viewUserStudentIdInput.value = studentId || '';
          if (runtime.viewUserParentNameInput)
              runtime.viewUserParentNameInput.value = '';
          if (runtime.viewUserParentEmailInput)
              runtime.viewUserParentEmailInput.value = '';
          if (runtime.viewUserParentPhoneInput)
              runtime.viewUserParentPhoneInput.value = '';
          if (runtime.viewUserParentDocumentInput)
              runtime.viewUserParentDocumentInput.value = '';
          if (runtime.viewUserForceCreateInput)
              runtime.viewUserForceCreateInput.checked = mode === 'create_more';
          if (runtime.viewUserFormMessage) {
              runtime.viewUserFormMessage.textContent = mode === 'create_more'
                  ? 'Cadastre um novo responsável para o aluno selecionado.'
                  : 'Este aluno ainda não tem responsável. Preencha os dados para cadastrar automaticamente.';
              runtime.viewUserFormMessage.className = 'charge-message';
          }
      };
      const hideViewUserForm = () => {
          if (!runtime.viewUserForm)
              return;
          runtime.viewUserForm.classList.add('hidden');
          if (runtime.viewUserFormMessage) {
              runtime.viewUserFormMessage.textContent = '';
              runtime.viewUserFormMessage.className = 'charge-message';
          }
          if (runtime.viewUserStudentIdInput)
              runtime.viewUserStudentIdInput.value = '';
      };
      runtime.viewUserStudentInput.addEventListener('input', () => {
          resetViewUserGuardianSelection();
          runtime.updateViewUserAutocompleteOptions(runtime.viewUserStudentInput.value);
      });
      runtime.viewUserStudentInput.addEventListener('focus', () => {
          runtime.updateViewUserAutocompleteOptions(runtime.viewUserStudentInput.value);
      });
      runtime.viewUserButton.addEventListener('click', async () => {
          const resolved = runtime.resolveStudentIdentityForAdmin(runtime.viewUserStudentInput.value);
          if (!resolved.ok) {
              await runtime.showAdminAlert(resolved.error || 'Aluno não encontrado na lista.');
              return;
          }
          const studentName = resolved.name;
          runtime.viewUserStudentInput.value = resolved.label;
          const selectedGuardianId = String(runtime.viewUserGuardianSelect?.value || '').trim();
          runtime.viewUserButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.viewUserButton.textContent;
          runtime.viewUserButton.textContent = 'Abrindo...';
          try {
              const res = await fetch('/api/admin-view-as-user.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      student_id: resolved.id,
                      guardian_id: selectedGuardianId || null,
                      csrf_token: runtime.adminCsrfToken,
                  }),
              });
              const data = await res.json();
              if (!data.ok) {
                  if (data.code === 'GUARDIAN_SELECTION_REQUIRED'
                      && runtime.viewUserGuardianSelect
                      && Array.isArray(data.guardians)) {
                      resetViewUserGuardianSelection();
                      data.guardians.forEach((guardian: RuntimeValue) => {
                          const option = document.createElement('option');
                          option.value = String(guardian?.id || '');
                          option.textContent = [
                              String(guardian?.name || 'Responsável'),
                              String(guardian?.email_masked || ''),
                              String(guardian?.document_masked || ''),
                          ].filter(Boolean).join(' • ');
                          runtime.viewUserGuardianSelect.appendChild(option);
                      });
                      runtime.viewUserGuardianSelect.classList.remove('hidden');
                      runtime.viewUserGuardianSelect.focus();
                      await runtime.showAdminAlert('Este aluno possui mais de um responsável. Selecione explicitamente o responsável e clique novamente em “Ver como usuário”.', { title: 'Escolha o responsável' });
                      return;
                  }
                  if (data.code === 'GUARDIAN_NOT_FOUND') {
                      showViewUserForm({
                          id: data.student?.id || resolved.id,
                          name: data.student?.name || studentName,
                      }, 'open_user');
                      return;
                  }
                  await runtime.showAdminAlert(data.error || 'Falha ao abrir visão de usuário.');
                  return;
              }
              const url = runtime.safeSameOriginUrl(data.url || '/dashboard.php');
              const win = window.open(url, '_blank', 'noopener');
              if (!win) {
                  window.location.href = url;
              }
          }
          catch {
              await runtime.showAdminAlert('Falha ao abrir visão de usuário.');
          }
          finally {
              runtime.viewUserButton.removeAttribute('disabled');
              runtime.viewUserButton.textContent = originalText;
          }
      });
      if (runtime.viewUserCancelGuardianButton) {
          runtime.viewUserCancelGuardianButton.addEventListener('click', () => {
              hideViewUserForm();
          });
      }
      if (runtime.addGuardianButton) {
          runtime.addGuardianButton.addEventListener('click', async () => {
              const resolved = runtime.resolveStudentIdentityForAdmin(runtime.viewUserStudentInput.value);
              if (!resolved.ok) {
                  await runtime.showAdminAlert(resolved.error || 'Aluno não encontrado na lista.');
                  return;
              }
              runtime.viewUserStudentInput.value = resolved.label;
              showViewUserForm(resolved.student, 'create_more');
          });
      }
      if (runtime.viewUserSaveGuardianButton) {
          runtime.viewUserSaveGuardianButton.addEventListener('click', async () => {
              const studentName = (runtime.viewUserStudentNameInput && runtime.viewUserStudentNameInput.value.trim()) ||
                  '';
              const studentId = (runtime.viewUserStudentIdInput && runtime.viewUserStudentIdInput.value.trim()) || '';
              const parentName = (runtime.viewUserParentNameInput && runtime.viewUserParentNameInput.value.trim()) || '';
              const email = (runtime.viewUserParentEmailInput && runtime.viewUserParentEmailInput.value.trim()) || '';
              const parentPhone = (runtime.viewUserParentPhoneInput && runtime.viewUserParentPhoneInput.value.trim()) || '';
              const parentDocument = (runtime.viewUserParentDocumentInput && runtime.viewUserParentDocumentInput.value.trim()) || '';
              if (!studentId || !studentName || !parentName) {
                  if (runtime.viewUserFormMessage) {
                      runtime.viewUserFormMessage.textContent = 'Informe aluno e nome do responsável.';
                      runtime.viewUserFormMessage.className = 'charge-message error';
                  }
                  return;
              }
              runtime.viewUserSaveGuardianButton.setAttribute('disabled', 'disabled');
              const originalText = runtime.viewUserSaveGuardianButton.textContent;
              runtime.viewUserSaveGuardianButton.textContent = 'Salvando...';
              if (runtime.viewUserFormMessage) {
                  runtime.viewUserFormMessage.textContent = 'Salvando responsável no banco...';
                  runtime.viewUserFormMessage.className = 'charge-message';
              }
              try {
                  const res = await fetch('/api/admin-upsert-guardian-for-student.php', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({
                          student_id: studentId,
                          parent_name: parentName,
                          email,
                          parent_phone: parentPhone,
                          parent_document: parentDocument,
                          force_create: !!(runtime.viewUserForceCreateInput && runtime.viewUserForceCreateInput.checked),
                      }),
                  });
                  const data = await res.json();
                  if (!data.ok) {
                      if (runtime.viewUserFormMessage) {
                          runtime.viewUserFormMessage.textContent = data.error || 'Falha ao salvar responsável.';
                          runtime.viewUserFormMessage.className = 'charge-message error';
                      }
                      return;
                  }
                  if (runtime.viewUserFormMessage) {
                      runtime.viewUserFormMessage.textContent = viewUserSaveMode === 'open_user'
                          ? 'Responsável salvo. Abrindo visão do usuário em nova aba...'
                          : 'Responsável salvo com sucesso. Você pode cadastrar outro.';
                      runtime.viewUserFormMessage.className = 'charge-message success';
                  }
                  const selectedStudent = runtime.adminStudents.find((student: RuntimeValue) => String(student?.id || '') === studentId);
                  if (runtime.viewUserStudentInput) {
                      runtime.viewUserStudentInput.value = selectedStudent
                          ? runtime.formatStudentIdentityLabel(selectedStudent)
                          : studentName;
                  }
                  if (viewUserSaveMode === 'open_user') {
                      hideViewUserForm();
                      runtime.viewUserButton.click();
                  }
                  else {
                      if (runtime.viewUserParentNameInput)
                          runtime.viewUserParentNameInput.value = '';
                      if (runtime.viewUserParentEmailInput)
                          runtime.viewUserParentEmailInput.value = '';
                      if (runtime.viewUserParentPhoneInput)
                          runtime.viewUserParentPhoneInput.value = '';
                      if (runtime.viewUserParentDocumentInput)
                          runtime.viewUserParentDocumentInput.value = '';
                      if (runtime.viewUserForceCreateInput)
                          runtime.viewUserForceCreateInput.checked = true;
                  }
              }
              catch {
                  if (runtime.viewUserFormMessage) {
                      runtime.viewUserFormMessage.textContent = 'Falha ao salvar responsável.';
                      runtime.viewUserFormMessage.className = 'charge-message error';
                  }
              }
              finally {
                  runtime.viewUserSaveGuardianButton.removeAttribute('disabled');
                  runtime.viewUserSaveGuardianButton.textContent = originalText;
              }
          });
      }
  }
}
