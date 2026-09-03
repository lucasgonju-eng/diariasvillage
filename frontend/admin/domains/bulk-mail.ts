import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsBulkMail(runtime: AdminRuntime): void {
  runtime.setBulkMailMessage = function setBulkMailMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.bulkMailMessage)
          return;
      runtime.bulkMailMessage.textContent = String(text || '');
      runtime.bulkMailMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
  };
  
  runtime.updateBulkMailCounter = function updateBulkMailCounter() {
      if (!runtime.bulkMailCounter)
          return;
      runtime.bulkMailCounter.textContent = `${runtime.bulkMailSelectedIds.size} selecionado(s)`;
  };
  
  runtime.getBulkMailTypeLabel = function getBulkMailTypeLabel(student: RuntimeValue) {
      if (student?.is_inadimplente)
          return 'Inadimplente';
      if (student?.is_mensalista)
          return 'Mensalista';
      if (student?.is_diarista)
          return 'Diarista';
      return 'Sem diária';
  };
  
  runtime.getBulkMailGradeKey = function getBulkMailGradeKey(student: RuntimeValue) {
      const grade = Number(student?.grade || 0);
      if (!Number.isInteger(grade) || grade <= 0)
          return '';
      return String(grade);
  };
  
  runtime.renderBulkMailGradeFilterOptions = function renderBulkMailGradeFilterOptions() {
      if (!runtime.bulkMailGradeFilterInput)
          return;
      const previousValue = String(runtime.bulkMailGradeFilterInput.value || 'all');
      const grades = new Set();
      runtime.bulkMailStudents.forEach((student: RuntimeValue) => {
          const key = runtime.getBulkMailGradeKey(student);
          if (key)
              grades.add(key);
      });
      const sortedGrades = Array.from(grades).sort((a: RuntimeValue, b: RuntimeValue) => Number(a) - Number(b));
      runtime.bulkMailGradeFilterInput.innerHTML = [
          '<option value="all">Todas</option>',
          ...sortedGrades.map((grade: RuntimeValue) => `<option value="${runtime.escapeHtml(grade)}">${runtime.escapeHtml(grade)}º ano</option>`),
      ].join('');
      const nextValue = sortedGrades.includes(previousValue) ? previousValue : 'all';
      runtime.bulkMailGradeFilterInput.value = nextValue;
  };
  
  runtime.getFilteredBulkMailStudents = function getFilteredBulkMailStudents() {
      const query = runtime.normalizeSearchText(runtime.bulkMailFilterInput?.value || '');
      const grade = String(runtime.bulkMailGradeFilterInput?.value || 'all');
      const type = String(runtime.bulkMailTypeFilterInput?.value || 'all');
      return runtime.bulkMailStudents.filter((student: RuntimeValue) => {
          if (grade !== 'all' && runtime.getBulkMailGradeKey(student) !== grade)
              return false;
          if (type === 'diaristas' && !student?.is_diarista)
              return false;
          if (type === 'mensalistas' && !student?.is_mensalista)
              return false;
          if (type === 'inadimplentes' && !student?.is_inadimplente)
              return false;
          if (!query)
              return true;
          const name = runtime.normalizeSearchText(student?.name || '');
          const enrollment = runtime.normalizeSearchText(student?.enrollment || '');
          return name.includes(query) || enrollment.includes(query);
      });
  };
  
  runtime.getBulkMailStudentById = function getBulkMailStudentById(studentId: RuntimeValue) {
      const target = String(studentId || '').trim();
      if (!target)
          return null;
      return runtime.bulkMailStudents.find((row: RuntimeValue) => String(row?.id || '').trim() === target) || null;
  };
  
  runtime.renderBulkMailTemplates = function renderBulkMailTemplates() {
      if (!runtime.bulkMailTemplateSelect)
          return;
      if (!Array.isArray(runtime.bulkMailTemplates) || !runtime.bulkMailTemplates.length) {
          runtime.bulkMailTemplateSelect.innerHTML = '<option value="">Nenhum template salvo</option>';
          return;
      }
      runtime.bulkMailTemplateSelect.innerHTML = runtime.bulkMailTemplates.map((tpl: RuntimeValue) => `<option value="${runtime.escapeHtml(tpl.id || '')}">${runtime.escapeHtml(tpl.name || 'Template sem nome')}</option>`)
          .join('');
  };
  
  runtime.applyTemplateToBulkMail = function applyTemplateToBulkMail(templateId: RuntimeValue) {
      const targetId = String(templateId || '').trim();
      if (!targetId)
          return;
      const template = runtime.bulkMailTemplates.find((tpl: RuntimeValue) => String(tpl?.id || '') === targetId);
      if (!template)
          return;
      if (runtime.bulkMailSubjectInput)
          runtime.bulkMailSubjectInput.value = String(template.subject || '');
      if (runtime.bulkMailHtmlInput)
          runtime.bulkMailHtmlInput.value = String(template.html || '');
      runtime.syncBulkMailVisualFromHtml();
  };
  
  runtime.renderBulkMailRecipients = function renderBulkMailRecipients() {
      if (!runtime.bulkMailRecipientsBody)
          return;
      const rows = runtime.getFilteredBulkMailStudents();
      if (!rows.length) {
          runtime.bulkMailRecipientsBody.innerHTML = '<tr><td colspan="6">Nenhum aluno para o filtro aplicado.</td></tr>';
          runtime.updateBulkMailCounter();
          if (runtime.bulkMailSelectAllInput) {
              runtime.bulkMailSelectAllInput.checked = false;
              runtime.bulkMailSelectAllInput.indeterminate = false;
          }
          return;
      }
      runtime.bulkMailRecipientsBody.innerHTML = rows
          .map((student: RuntimeValue) => {
          const studentId = String(student?.id || '');
          const emails = Array.isArray(student?.emails) ? student.emails.filter(Boolean) : [];
          const hasEmail = emails.length > 0;
          const checked = runtime.bulkMailSelectedIds.has(studentId) ? 'checked' : '';
          const disabled = hasEmail ? '' : 'disabled';
          return `
          <tr>
            <td>
              <input class="bulk-mail-student-checkbox" type="checkbox" data-id="${runtime.escapeHtml(studentId)}" ${checked} ${disabled} />
            </td>
            <td>${runtime.escapeHtml(student?.name || '-')}</td>
            <td>${runtime.escapeHtml(student?.enrollment || '-')}</td>
            <td>${runtime.escapeHtml(runtime.getBulkMailTypeLabel(student))}</td>
            <td>${emails.length ? runtime.escapeHtml(emails.join(', ')) : '<span style="color:#B91C1C;">Sem e-mail válido</span>'}</td>
            <td>
              <button class="btn btn-ghost btn-sm bulk-mail-edit-emails" type="button" data-id="${runtime.escapeHtml(studentId)}">Editar e-mails</button>
            </td>
          </tr>
        `;
      })
          .join('');
      const renderedCheckboxes = [
          ...runtime.bulkMailRecipientsBody.querySelectorAll('.bulk-mail-student-checkbox'),
      ].filter((el: RuntimeValue) => el instanceof HTMLInputElement && !el.disabled);
      renderedCheckboxes.forEach((checkbox: RuntimeValue) => {
          checkbox.addEventListener('change', () => {
              const id = String(checkbox.dataset.id || '').trim();
              if (!id)
                  return;
              if (checkbox.checked) {
                  runtime.bulkMailSelectedIds.add(id);
              }
              else {
                  runtime.bulkMailSelectedIds.delete(id);
              }
              runtime.updateBulkMailCounter();
              const selectedInView = renderedCheckboxes.filter((cb: RuntimeValue) => cb.checked).length;
              if (runtime.bulkMailSelectAllInput) {
                  runtime.bulkMailSelectAllInput.checked = selectedInView > 0 && selectedInView === renderedCheckboxes.length;
                  runtime.bulkMailSelectAllInput.indeterminate =
                      selectedInView > 0 && selectedInView < renderedCheckboxes.length;
              }
          });
      });
      [...runtime.bulkMailRecipientsBody.querySelectorAll('.bulk-mail-edit-emails')].forEach((button: RuntimeValue) => {
          button.addEventListener('click', async () => {
              if (!(button instanceof HTMLElement))
                  return;
              const studentId = String(button.dataset.id || '').trim();
              if (!studentId)
                  return;
              const student = runtime.getBulkMailStudentById(studentId);
              if (!student) {
                  runtime.setBulkMailMessage('Aluno não encontrado para edição de e-mails.', true);
                  return;
              }
              const guardians = Array.isArray(student.guardians) ? student.guardians : [];
              if (!guardians.length) {
                  await runtime.showAdminAlert('Este aluno não possui responsáveis para edição de e-mail.');
                  return;
              }
              const updates = [];
              for (const guardian of guardians) {
                  const guardianId = String(guardian?.id || '').trim();
                  if (!guardianId)
                      continue;
                  const guardianName = String(guardian?.name || 'Responsável').trim();
                  const currentEmail = String(guardian?.email || '').trim();
                  const edited = window.prompt(`E-mail do responsável: ${guardianName}`, currentEmail);
                  if (edited === null) {
                      return;
                  }
                  updates.push({ id: guardianId, email: String(edited || '').trim() });
              }
              if (!updates.length) {
                  runtime.setBulkMailMessage('Nenhum responsável válido para atualização.', true);
                  return;
              }
              const originalText = button.textContent;
              button.setAttribute('disabled', 'disabled');
              button.textContent = 'Salvando...';
              runtime.setBulkMailMessage('Salvando e-mails no banco...');
              try {
                  const res = await fetch('/admin/bulk-email.php', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({
                          action: 'update_guardians_emails',
                          student_id: studentId,
                          guardians: updates,
                      }),
                  });
                  const data = await res.json();
                  if (!res.ok || !data?.ok) {
                      runtime.setBulkMailMessage(data?.error || 'Falha ao salvar e-mails dos responsáveis.', true);
                      return;
                  }
                  student.guardians = Array.isArray(data.guardians) ? data.guardians : student.guardians;
                  student.emails = Array.isArray(data.emails) ? data.emails : student.emails;
                  runtime.renderBulkMailRecipients();
                  runtime.setBulkMailMessage('E-mails atualizados com sucesso.');
              }
              catch {
                  runtime.setBulkMailMessage('Falha ao salvar e-mails dos responsáveis.', true);
              }
              finally {
                  button.removeAttribute('disabled');
                  button.textContent = originalText;
              }
          });
      });
      const selectedInView = renderedCheckboxes.filter((cb: RuntimeValue) => cb.checked).length;
      if (runtime.bulkMailSelectAllInput) {
          runtime.bulkMailSelectAllInput.checked = selectedInView > 0 && selectedInView === renderedCheckboxes.length;
          runtime.bulkMailSelectAllInput.indeterminate = selectedInView > 0 && selectedInView < renderedCheckboxes.length;
      }
      runtime.updateBulkMailCounter();
  };
  
  runtime.syncBulkMailVisualFromHtml = function syncBulkMailVisualFromHtml() {
      if (!runtime.bulkMailHtmlInput || !runtime.bulkMailVisualInput || runtime.bulkMailSyncingEditors)
          return;
      runtime.bulkMailSyncingEditors = true;
      runtime.bulkMailVisualInput.srcdoc = runtime.buildBulkMailPreviewHtml(runtime.bulkMailHtmlInput.value || '');
      runtime.bulkMailSyncingEditors = false;
  };
  
  runtime.syncBulkMailHtmlFromVisual = function syncBulkMailHtmlFromVisual() {
      if (!runtime.bulkMailHtmlInput || !runtime.bulkMailVisualInput || runtime.bulkMailSyncingEditors)
          return;
      const visualDocument = runtime.getBulkMailVisualDocument();
      if (!visualDocument?.documentElement)
          return;
      runtime.bulkMailSyncingEditors = true;
      const clone = visualDocument.documentElement.cloneNode(true);
      clone.querySelector('meta[data-bulk-mail-preview-security="true"]')?.remove();
      const body = clone.querySelector('body');
      body?.removeAttribute('contenteditable');
      body?.removeAttribute('spellcheck');
      runtime.bulkMailHtmlInput.value = runtime.sanitizeBulkMailHtml(`<!doctype html>\n${clone.outerHTML}`);
      runtime.bulkMailSyncingEditors = false;
  };
  
  runtime.bindBulkMailVisualEditor = function bindBulkMailVisualEditor() {
      const visualDocument = runtime.getBulkMailVisualDocument();
      if (!visualDocument?.body || runtime.bulkMailVisualBoundDocuments.has(visualDocument))
          return;
      runtime.bulkMailVisualBoundDocuments.add(visualDocument);
      visualDocument.body.addEventListener('paste', (event: RuntimeValue) => {
          event.preventDefault();
          const plainText = event.clipboardData?.getData('text/plain') || '';
          visualDocument.execCommand('insertText', false, plainText);
      });
      visualDocument.body.addEventListener('drop', (event: RuntimeValue) => {
          event.preventDefault();
      });
      visualDocument.body.addEventListener('input', () => {
          runtime.syncBulkMailHtmlFromVisual();
      });
      visualDocument.body.addEventListener('blur', () => {
          runtime.syncBulkMailHtmlFromVisual();
          runtime.syncBulkMailVisualFromHtml();
      });
  };
  
  runtime.loadBulkMailData = async function loadBulkMailData(force: RuntimeValue = false) {
      if (!runtime.tabEmailMassa || !runtime.bulkMailRecipientsBody)
          return;
      if (runtime.bulkMailLoaded && !force)
          return;
      runtime.bulkMailRecipientsBody.innerHTML = '<tr><td colspan="6">Carregando alunos...</td></tr>';
      runtime.setBulkMailMessage('');
      try {
          const res = await fetch('/admin/bulk-email.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'init' }),
          });
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.setBulkMailMessage(data?.error || 'Falha ao carregar dados de e-mail em massa.', true);
              runtime.bulkMailRecipientsBody.innerHTML = '<tr><td colspan="6">Falha ao carregar alunos.</td></tr>';
              return;
          }
          runtime.bulkMailStudents = Array.isArray(data.students) ? data.students : [];
          runtime.bulkMailTemplates = Array.isArray(data.templates) ? data.templates : [];
          runtime.renderBulkMailGradeFilterOptions();
          runtime.renderBulkMailTemplates();
          const suggested = data?.suggested_template_id || runtime.bulkMailTemplates[0]?.id;
          if (runtime.bulkMailTemplateSelect && suggested) {
              runtime.bulkMailTemplateSelect.value = String(suggested);
          }
          runtime.applyTemplateToBulkMail(suggested);
          runtime.renderBulkMailRecipients();
          runtime.bulkMailLoaded = true;
      }
      catch {
          runtime.setBulkMailMessage('Falha ao carregar dados de e-mail em massa.', true);
          runtime.bulkMailRecipientsBody.innerHTML = '<tr><td colspan="6">Falha ao carregar alunos.</td></tr>';
      }
  };
  
  if (runtime.bulkMailFilterInput) {
      runtime.bulkMailFilterInput.addEventListener('input', () => {
          runtime.renderBulkMailRecipients();
      });
  }
  
  if (runtime.bulkMailTypeFilterInput) {
      runtime.bulkMailTypeFilterInput.addEventListener('change', () => {
          runtime.renderBulkMailRecipients();
      });
  }
  
  if (runtime.bulkMailGradeFilterInput) {
      runtime.bulkMailGradeFilterInput.addEventListener('change', () => {
          runtime.renderBulkMailRecipients();
      });
  }
  
  if (runtime.bulkMailSelectAllInput) {
      runtime.bulkMailSelectAllInput.addEventListener('change', () => {
          const visibleCheckboxes = [...document.querySelectorAll('.bulk-mail-student-checkbox')].filter((el: RuntimeValue) => el instanceof HTMLInputElement && !el.disabled);
          const checked = !!runtime.bulkMailSelectAllInput.checked;
          visibleCheckboxes.forEach((checkbox: RuntimeValue) => {
              checkbox.checked = checked;
              const id = String(checkbox.dataset.id || '').trim();
              if (!id)
                  return;
              if (checked)
                  runtime.bulkMailSelectedIds.add(id);
              else
                  runtime.bulkMailSelectedIds.delete(id);
          });
          runtime.updateBulkMailCounter();
      });
  }
  
  if (runtime.bulkMailHtmlInput) {
      runtime.bulkMailHtmlInput.addEventListener('input', () => {
          runtime.syncBulkMailVisualFromHtml();
      });
  }
  
  if (runtime.bulkMailVisualInput) {
      runtime.bulkMailVisualInput.addEventListener('load', runtime.bindBulkMailVisualEditor);
  }
  
  if (runtime.bulkMailTemplateLoadButton) {
      runtime.bulkMailTemplateLoadButton.addEventListener('click', () => {
          const templateId = String(runtime.bulkMailTemplateSelect?.value || '').trim();
          if (!templateId) {
              runtime.setBulkMailMessage('Selecione um template para carregar.', true);
              return;
          }
          runtime.applyTemplateToBulkMail(templateId);
          runtime.setBulkMailMessage('Template carregado.');
      });
  }
  
  if (runtime.bulkMailTemplateSaveButton) {
      runtime.bulkMailTemplateSaveButton.addEventListener('click', async () => {
          const subject = String(runtime.bulkMailSubjectInput?.value || '').trim();
          const html = String(runtime.bulkMailHtmlInput?.value || '').trim();
          if (!subject || !html) {
              runtime.setBulkMailMessage('Preencha assunto e HTML antes de salvar template.', true);
              return;
          }
          const templateName = window.prompt('Nome do template:', 'Novo template');
          if (templateName === null)
              return;
          const finalName = String(templateName || '').trim();
          if (!finalName) {
              runtime.setBulkMailMessage('Informe um nome válido para o template.', true);
              return;
          }
          runtime.bulkMailTemplateSaveButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.bulkMailTemplateSaveButton.textContent;
          runtime.bulkMailTemplateSaveButton.textContent = 'Salvando...';
          try {
              const res = await fetch('/admin/bulk-email.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      action: 'save_template',
                      name: finalName,
                      subject,
                      html,
                  }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.setBulkMailMessage(data?.error || 'Falha ao salvar template.', true);
                  return;
              }
              runtime.bulkMailTemplates = Array.isArray(data.templates) ? data.templates : runtime.bulkMailTemplates;
              runtime.renderBulkMailTemplates();
              if (runtime.bulkMailTemplateSelect && data?.saved_template_id) {
                  runtime.bulkMailTemplateSelect.value = String(data.saved_template_id);
              }
              runtime.setBulkMailMessage('Template salvo com sucesso.');
          }
          catch {
              runtime.setBulkMailMessage('Falha ao salvar template.', true);
          }
          finally {
              runtime.bulkMailTemplateSaveButton.removeAttribute('disabled');
              runtime.bulkMailTemplateSaveButton.textContent = originalText;
          }
      });
  }
  
  if (runtime.bulkMailSendButton) {
      runtime.bulkMailSendButton.addEventListener('click', async () => {
          const selectedIds = Array.from(runtime.bulkMailSelectedIds);
          const subject = String(runtime.bulkMailSubjectInput?.value || '').trim();
          const html = String(runtime.bulkMailHtmlInput?.value || '').trim();
          if (!selectedIds.length) {
              runtime.setBulkMailMessage('Selecione ao menos um aluno para envio.', true);
              return;
          }
          if (!subject || !html) {
              runtime.setBulkMailMessage('Preencha assunto e HTML antes de enviar.', true);
              return;
          }
          const confirmed = await runtime.showAdminConfirm(`Enviar e-mail para ${selectedIds.length} aluno(s) selecionado(s)?`, { title: 'Confirmação de envio', confirmText: 'Enviar e-mails' });
          if (!confirmed)
              return;
          runtime.bulkMailSendButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.bulkMailSendButton.textContent;
          runtime.bulkMailSendButton.textContent = 'Enviando...';
          runtime.setBulkMailMessage('Enviando e-mails...');
          try {
              const res = await fetch('/admin/bulk-email.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      action: 'send',
                      student_ids: selectedIds,
                      subject,
                      html,
                  }),
              });
              const data = await res.json();
              const summary = data?.summary || {};
              if (!res.ok || !data?.ok) {
                  runtime.setBulkMailMessage(data?.error
                      || `Falha parcial no envio. Sucesso: ${summary.sent_students || 0}, falha: ${summary.failed_students || 0}.`, true);
                  return;
              }
              runtime.setBulkMailMessage(`Envio concluído. Alunos com sucesso: ${summary.sent_students || 0}. E-mails enviados: ${summary.sent_emails || 0}.`);
          }
          catch {
              runtime.setBulkMailMessage('Falha de rede ao enviar e-mails.', true);
          }
          finally {
              runtime.bulkMailSendButton.removeAttribute('disabled');
              runtime.bulkMailSendButton.textContent = originalText;
          }
      });
  }
  
  if (runtime.bulkMailSendTestButton) {
      runtime.bulkMailSendTestButton.addEventListener('click', async () => {
          const subject = String(runtime.bulkMailSubjectInput?.value || '').trim();
          const html = String(runtime.bulkMailHtmlInput?.value || '').trim();
          if (!subject || !html) {
              runtime.setBulkMailMessage('Preencha assunto e HTML antes de enviar teste.', true);
              return;
          }
          const selectedIds = Array.from(runtime.bulkMailSelectedIds);
          const sampleStudentId = selectedIds[0] || '';
          const confirmed = await runtime.showAdminConfirm('Enviar e-mail de teste para lucasgonju@gmail.com com o template atual?', { title: 'Enviar teste', confirmText: 'Enviar teste' });
          if (!confirmed)
              return;
          runtime.bulkMailSendTestButton.setAttribute('disabled', 'disabled');
          const originalText = runtime.bulkMailSendTestButton.textContent;
          runtime.bulkMailSendTestButton.textContent = 'Enviando teste...';
          runtime.setBulkMailMessage('Enviando teste para lucasgonju@gmail.com...');
          try {
              const res = await fetch('/admin/bulk-email.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      action: 'send_test',
                      subject,
                      html,
                      student_id: sampleStudentId,
                  }),
              });
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                  runtime.setBulkMailMessage(data?.error || 'Falha ao enviar e-mail de teste.', true);
                  return;
              }
              runtime.setBulkMailMessage('Teste enviado para lucasgonju@gmail.com com sucesso.');
          }
          catch {
              runtime.setBulkMailMessage('Falha ao enviar e-mail de teste.', true);
          }
          finally {
              runtime.bulkMailSendTestButton.removeAttribute('disabled');
              runtime.bulkMailSendTestButton.textContent = originalText;
          }
      });
  }
}
