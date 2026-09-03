import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsMonthly(runtime: AdminRuntime): void {
  runtime.rebuildMonthlyMaps = function rebuildMonthlyMaps() {
      runtime.monthlyByStudentId.clear();
      const rows = Array.isArray(runtime.monthlyStudents) ? runtime.monthlyStudents : [];
      rows.forEach((row: RuntimeValue) => {
          if (!row || row.active === false)
              return;
          const studentId = String(row.student_id || '').trim();
          const weeklyDays = Number(row.weekly_days || 0);
          if (!studentId || ![2, 3, 4, 5].includes(weeklyDays))
              return;
          runtime.monthlyByStudentId.set(studentId, row);
      });
  };
  
  runtime.getMonthlyPlanForStudent = function getMonthlyPlanForStudent(student: RuntimeValue) {
      const studentId = String(student?.id || '').trim();
      return studentId ? runtime.monthlyByStudentId.get(studentId) || null : null;
  };
  
  runtime.setMonthlyMessage = function setMonthlyMessage(text: RuntimeValue, isError: RuntimeValue = false) {
      if (!runtime.monthlyMessage)
          return;
      runtime.monthlyMessage.textContent = text;
      runtime.monthlyMessage.className = `charge-message ${isError ? 'error' : 'success'}`.trim();
  };
  
  runtime.renderMonthlyTable = function renderMonthlyTable() {
      if (!runtime.monthlyTableBody)
          return;
      const rows = Array.isArray(runtime.monthlyStudents) ? [...runtime.monthlyStudents] : [];
      rows.sort((a: RuntimeValue, b: RuntimeValue) => String(a?.student_name || '').localeCompare(String(b?.student_name || ''), 'pt-BR'));
      if (!rows.length) {
          runtime.monthlyTableBody.innerHTML = '<tr><td colspan="5">Nenhum mensalista cadastrado.</td></tr>';
          return;
      }
      runtime.monthlyTableBody.innerHTML = rows
          .map((row: RuntimeValue) => {
          const updatedAt = row?.updated_at ? runtime.formatDateTimeBR(row.updated_at) : '-';
          const days = Number(row?.weekly_days || 0);
          return `
          <tr data-student-id="${runtime.escapeHtml(row?.student_id || '')}">
            <td>${runtime.escapeHtml(row?.student_name || '-')}</td>
            <td>${runtime.escapeHtml(row?.enrollment || '-')}</td>
            <td>${runtime.escapeHtml(days || '-')} dias/semana</td>
            <td>${runtime.escapeHtml(updatedAt)}</td>
            <td>${runtime.escapeHtml(row?.updated_by || '-')}</td>
          </tr>
        `;
      })
          .join('');
  };
  
  runtime.syncMonthlyStudents = async function syncMonthlyStudents(action: RuntimeValue) {
      if (!runtime.monthlyStudentInput)
          return;
      const resolved = runtime.resolveStudentIdentityForAdmin(runtime.monthlyStudentInput.value);
      if (!resolved.ok || !resolved.student) {
          runtime.setMonthlyMessage(resolved.error || 'Selecione um aluno válido.', true);
          return;
      }
      const student = resolved.student;
      const studentName = String(student.name || '').trim();
      runtime.monthlyStudentInput.value = resolved.label || runtime.formatStudentIdentityLabel(student);
      let weeklyDays = null;
      if (action === 'set') {
          const checked = document.querySelector<HTMLInputElement>('input[name="monthly-days"]:checked');
          weeklyDays = checked ? Number(checked.value || 0) : 0;
          if (![2, 3, 4, 5].includes(weeklyDays)) {
              runtime.setMonthlyMessage('Selecione 2, 3, 4 ou 5 dias por semana.', true);
              return;
          }
      }
      const targetButton = action === 'set' ? runtime.monthlySaveButton : runtime.monthlyRemoveButton;
      const originalLabel = targetButton?.textContent || '';
      if (targetButton) {
          targetButton.setAttribute('disabled', 'disabled');
          targetButton.textContent = action === 'set' ? 'Salvando...' : 'Removendo...';
      }
      runtime.setMonthlyMessage(action === 'set' ? 'Salvando mensalista...' : 'Removendo mensalista...');
      try {
          const res = await fetch('/api/admin-mensalistas.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                  action,
                  student_id: student.id,
                  weekly_days: weeklyDays,
              }),
          });
          const data = await res.json();
          if (!res.ok || !data?.ok) {
              runtime.setMonthlyMessage(data?.error || 'Falha ao atualizar mensalistas.', true);
              return;
          }
          runtime.monthlyStudents = Array.isArray(data.items) ? data.items : [];
          runtime.rebuildMonthlyMaps();
          runtime.renderMonthlyTable();
          runtime.setMonthlyMessage(action === 'set'
              ? `Aluno ${studentName} definido como mensalista de ${weeklyDays} dias/semana.`
              : `Aluno ${studentName} removido da lista de mensalistas.`, false);
      }
      catch {
          runtime.setMonthlyMessage('Falha ao atualizar mensalistas.', true);
      }
      finally {
          if (targetButton) {
              targetButton.removeAttribute('disabled');
              targetButton.textContent = originalLabel;
          }
      }
  };
  
  if (runtime.monthlySaveButton) {
      runtime.monthlySaveButton.addEventListener('click', () => {
          runtime.syncMonthlyStudents('set');
      });
  }
  
  if (runtime.monthlyRemoveButton) {
      runtime.monthlyRemoveButton.addEventListener('click', () => {
          runtime.syncMonthlyStudents('remove');
      });
  }
  
  document.querySelectorAll('.monthly-unlock-btn').forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          const submissionId = String(button.getAttribute('data-submission-id') || '').trim();
          if (!submissionId)
              return;
          const confirmed = await runtime.showAdminConfirm('Desbloquear esta confirmação? As entradas recorrentes do mês serão canceladas até o responsável confirmar novamente.', { title: 'Desbloquear oficinas mensalistas', confirmText: 'Desbloquear', cancelText: 'Cancelar' });
          if (!confirmed)
              return;
          const message = document.querySelector('#monthly-confirmations-message');
          const original = button.textContent;
          button.disabled = true;
          button.textContent = 'Desbloqueando...';
          if (message) {
              message.textContent = '';
              message.className = 'charge-message';
          }
          try {
              const response = await fetch('/api/admin-monthly-workshops.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ action: 'unlock', submission_id: submissionId }),
              });
              const data = await response.json();
              if (!response.ok || !data?.ok) {
                  throw new Error(data?.error || 'Não foi possível desbloquear a confirmação.');
              }
              if (message) {
                  message.textContent = 'Confirmação desbloqueada. Atualizando a lista...';
                  message.className = 'charge-message success';
              }
              window.location.reload();
          }
          catch (error: RuntimeValue) {
              if (message) {
                  message.textContent = error instanceof Error ? error.message : 'Não foi possível desbloquear a confirmação.';
                  message.className = 'charge-message error';
              }
              button.disabled = false;
              button.textContent = original;
          }
      });
  });
}
