import type { AdminRuntime, RuntimeValue } from '../core/runtime';

export function initializeDomainsFamilyLinks(runtime: AdminRuntime): void {
  document.querySelectorAll('.js-family-link-review').forEach((button: RuntimeValue) => {
      button.addEventListener('click', async () => {
          const requestId = String(button.dataset.requestId || '');
          const decision = String(button.dataset.decision || '').toUpperCase();
          const row = button.closest('tr');
          const targetStudent = row?.children?.[2]?.textContent?.trim() || 'o aluno solicitado';
          const confirmationWord = decision === 'APPROVE' ? 'APROVAR' : 'REJEITAR';
          const typed = window.prompt(`${decision === 'APPROVE' ? 'Confirme que o responsável possui vínculo oficial com' : 'Rejeitar o vínculo com'} ${targetStudent}.\n\nDigite ${confirmationWord} para continuar.`);
          if (String(typed || '').trim().toUpperCase() !== confirmationWord) {
              return;
          }
          const note = window.prompt('Observação da análise (opcional):') || '';
          const message = document.querySelector('#family-link-review-message');
          document.querySelectorAll('.js-family-link-review').forEach((item: RuntimeValue) => {
              item.setAttribute('disabled', 'disabled');
          });
          if (message) {
              message.textContent = decision === 'APPROVE'
                  ? 'Validando identidade e criando o vínculo...'
                  : 'Registrando rejeição...';
              message.className = 'charge-message';
          }
          try {
              const response = await fetch('/api/admin-review-family-link.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                      request_id: requestId,
                      decision,
                      note,
                      csrf_token: runtime.adminCsrfToken,
                  }),
              });
              const data = await response.json();
              if (!response.ok || !data.ok) {
                  throw new Error(data.error || 'Não foi possível revisar o vínculo.');
              }
              row?.remove();
              if (message) {
                  message.textContent = data.message;
                  message.className = 'charge-message success';
              }
          }
          catch (error: RuntimeValue) {
              if (message) {
                  message.textContent = error?.message || 'Não foi possível revisar o vínculo.';
                  message.className = 'charge-message error';
              }
          }
          finally {
              document.querySelectorAll('.js-family-link-review').forEach((item: RuntimeValue) => {
                  item.removeAttribute('disabled');
              });
          }
      });
  });
}
