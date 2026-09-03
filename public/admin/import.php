<?php
require_once __DIR__ . '/../src/Bootstrap.php';
use App\AdminAuth;
use App\Helpers;

$debug = ($_GET['debug'] ?? '') === '1';
$adminSession = Helpers::requireAdminRoleWeb(AdminAuth::ROLE_ADMIN);

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
$success = ($_GET['success'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Importar alunos</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diárias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/admin/dashboard.php?tab=entries">Entradas</a>
        <a class="button secondary" href="/logout.php">Sair</a>
      </nav>
    </header>

    <div class="card">
      <h2>Importar alunos</h2>
      <p class="subtitle">Envie CSV, XLS ou XLSX com colunas: nome, matrícula, série / turma, nascimento.</p>
      <?php if ($success): ?>
        <div class="success">Importação concluída com sucesso.</div>
      <?php endif; ?>

      <form action="/api/import-students.php" method="post" enctype="multipart/form-data">
        <div class="form-group">
          <input type="file" name="file" required />
        </div>
        <button class="button" type="submit">Importar</button>
      </form>
    </div>

    <div class="card" style="margin-top:18px;">
      <h2>Criar aluno manualmente</h2>
      <p class="subtitle">Use quando precisar cadastrar um aluno novo sem planilha. O aluno já fica ativo e vinculado ao responsável principal.</p>

      <form id="manual-student-form">
        <div class="grid-2">
          <div class="form-group">
            <label for="manual-student-name">Nome do aluno</label>
            <input id="manual-student-name" name="student_name" type="text" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-student-enrollment">Matrícula</label>
            <input id="manual-student-enrollment" name="enrollment" type="text" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-student-grade">Série</label>
            <select id="manual-student-grade" name="grade" required>
              <option value="">Selecione</option>
              <option value="6">6º ano</option>
              <option value="7">7º ano</option>
              <option value="8">8º ano</option>
            </select>
          </div>
          <div class="form-group">
            <label for="manual-student-class">Turma</label>
            <input id="manual-student-class" name="class_name" type="text" placeholder="Ex.: 6º A" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-student-birth">Nascimento</label>
            <input id="manual-student-birth" name="birth_date" type="date" />
          </div>
        </div>

        <h3 style="margin:8px 0 12px;">Responsável principal</h3>
        <div class="grid-2">
          <div class="form-group">
            <label for="manual-guardian-name">Nome do responsável</label>
            <input id="manual-guardian-name" name="guardian_name" type="text" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-guardian-email">E-mail do responsável</label>
            <input id="manual-guardian-email" name="guardian_email" type="email" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-guardian-phone">WhatsApp</label>
            <input id="manual-guardian-phone" name="guardian_phone" type="text" inputmode="tel" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label for="manual-guardian-document">CPF</label>
            <input id="manual-guardian-document" name="guardian_document" type="text" inputmode="numeric" autocomplete="off" required />
          </div>
        </div>

        <button id="manual-student-submit" class="button" type="submit">Criar aluno manualmente</button>
        <div id="manual-student-message" aria-live="polite"></div>
      </form>
    </div>

    <div class="card" style="margin-top:18px;">
      <h2>Importar responsáveis</h2>
      <p class="subtitle">Envie PDF ou JSON com colunas: student_name, guardian_name, guardian_email, guardian_phone, guardian_cpf.</p>

      <form action="/api/import-guardians.php?return=html" method="post" enctype="multipart/form-data">
        <div class="form-group">
          <label for="guardians-file">Arquivo (PDF ou JSON)</label>
          <input id="guardians-file" type="file" name="file" accept=".pdf,.json" />
        </div>
        <div class="form-group">
          <label for="guardians-json">Ou cole o JSON abaixo</label>
          <textarea id="guardians-json" name="json" rows="6" placeholder='[{"student_name":"ALUNO","guardian_name":"RESPONSAVEL","guardian_email":"email@exemplo.com","guardian_phone":"6299999999","guardian_cpf":"12345678900"}]'></textarea>
        </div>
        <button class="button" type="submit">Importar responsáveis</button>
      </form>
    </div>
    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>
  <script>
    const manualStudentForm = document.getElementById('manual-student-form');
    const manualStudentSubmit = document.getElementById('manual-student-submit');
    const manualStudentMessage = document.getElementById('manual-student-message');

    function setManualStudentMessage(message, isError = false) {
      manualStudentMessage.textContent = message || '';
      manualStudentMessage.className = message ? (isError ? 'error' : 'success') : '';
    }

    manualStudentForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      setManualStudentMessage('');
      manualStudentSubmit.disabled = true;
      manualStudentSubmit.textContent = 'Criando...';

      const formData = new FormData(manualStudentForm);
      const payload = Object.fromEntries(formData.entries());

      try {
        const response = await fetch('/api/admin-create-student.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
          throw new Error(data.error || 'Não foi possível criar o aluno.');
        }

        manualStudentForm.reset();
        setManualStudentMessage('Aluno criado com sucesso.');
      } catch (error) {
        setManualStudentMessage(error.message || 'Não foi possível criar o aluno.', true);
      } finally {
        manualStudentSubmit.disabled = false;
        manualStudentSubmit.textContent = 'Criar aluno manualmente';
      }
    });
  </script>
</body>
</html>
