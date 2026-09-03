<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}

$activeTab = (string) $dashboardContext['activeTab'];
$adminCsrfToken = (string) $dashboardContext['adminCsrfToken'];
$allowedTabs = $dashboardContext['allowedTabs'];
$assets = $dashboardContext['assets'];
$canViewAsUser = (bool) $dashboardContext['canViewAsUser'];
$dashboardTabs = $dashboardContext['dashboardTabs'];
$isAdminPrincipal = (bool) $dashboardContext['isAdminPrincipal'];
$monthlyRowsForJs = $dashboardContext['monthlyRowsForJs'];
$studentsForJs = $dashboardContext['studentsForJs'];
$context = $dashboardContext;
$partialsRoot = __DIR__ . '/partials';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="admin-csrf-token" content="<?php echo htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
  <title>Admin - Entradas</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <?php foreach ($assets['styles'] as $stylesheet): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endforeach; ?>
</head>
<body data-active-tab="<?php echo htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="admin-wrap">
    <header class="admin-header">
      <div class="admin-title">DIÁRIAS VILLAGE • ADMIN</div>
      <div class="cta">
        <?php if ($canViewAsUser): ?>
          <div class="admin-view-user">
            <input id="admin-view-user-student" list="admin-students-list" placeholder="Aluno ou matrícula" autocomplete="off" />
            <datalist id="admin-students-list"></datalist>
            <select id="admin-view-user-guardian" class="hidden" aria-label="Responsável selecionado">
              <option value="">Selecione o responsável</option>
            </select>
            <button id="admin-view-user-btn" class="btn btn-ghost btn-sm" type="button">Ver como usuário</button>
            <button id="admin-add-guardian-btn" class="btn btn-ghost btn-sm" type="button">Criar mais um responsável</button>
          </div>
        <?php endif; ?>
        <?php if ($isAdminPrincipal): ?>
          <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=fluxo-caixa" data-tab="fluxo-caixa">Fluxo de Caixa</a>
          <a class="btn btn-danger btn-sm" href="/admin/settle-pendencia.php">Baixa manual</a>
          <a class="btn btn-ghost btn-sm" href="/admin/import.php">Importar alunos</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="/logout.php?context=admin">Sair</a>
      </div>
    </header>

    <?php if ($canViewAsUser): ?>
      <div id="admin-view-user-form" class="view-user-form hidden">
        <div class="charge-fields">
          <div class="form-group">
            <label>Aluno</label>
            <input id="view-user-student-name" type="text" readonly />
            <input id="view-user-student-id" type="hidden" />
          </div>
          <div class="form-group">
            <label>Nome do responsável</label>
            <input id="view-user-parent-name" type="text" placeholder="Nome completo" />
          </div>
          <div class="form-group">
            <label>E-mail do responsável</label>
            <input id="view-user-parent-email" type="email" placeholder="email@exemplo.com" />
          </div>
          <div class="form-group">
            <label>Telefone</label>
            <input id="view-user-parent-phone" type="text" placeholder="(DDD) 99999-9999" />
          </div>
          <div class="form-group">
            <label>CPF/CNPJ</label>
            <input id="view-user-parent-document" type="text" placeholder="Somente números" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <label style="display:flex;gap:8px;align-items:center;">
              <input id="view-user-force-create" type="checkbox" />
              Salvar como novo responsável
            </label>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button id="view-user-save-guardian" class="btn btn-danger btn-sm" type="button">Salvar responsável</button>
            <button id="view-user-cancel-guardian" class="btn btn-ghost btn-sm" type="button">Cancelar</button>
          </div>
        </div>
        <div id="view-user-form-message" class="charge-message"></div>
      </div>
    <?php endif; ?>

    <div class="admin-card">
      <div class="admin-tabs">
        <?php foreach ($allowedTabs as $tabName): ?>
          <?php $tabDefinition = $dashboardTabs[$tabName]; ?>
          <a class="btn btn-primary btn-sm" href="/admin/dashboard.php?tab=<?php echo rawurlencode($tabName); ?>" data-tab="<?php echo htmlspecialchars($tabName, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $tabDefinition['label'], ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
      </div>

      <?php foreach ($allowedTabs as $tabName): ?>
        <?php
        $partialName = (string) $dashboardTabs[$tabName]['partial'];
        require $partialsRoot . '/' . $partialName;
        ?>
      <?php endforeach; ?>
    </div>

    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>

  <script>
    window.__adminDashboardBooted = false;
    window.__adminStudents = <?php echo json_encode($studentsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.__monthlyStudents = <?php echo json_encode($monthlyRowsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.__adminCanApproveAttendance = <?php echo $dashboardContext['canAttendanceApprove'] ? 'true' : 'false'; ?>;
  </script>
  <script type="module" src="<?php echo htmlspecialchars((string) $assets['script'], ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script>
    (function () {
      function activateTab(name) {
        var mapping = {
          entries: 'tab-entries',
          charges: 'tab-charges',
          chamada: 'tab-chamada',
          familias: 'tab-familias',
          inadimplentes: 'tab-inadimplentes',
          recebidas: 'tab-recebidas',
          'sem-whatsapp': 'tab-sem-whatsapp',
          pendencias: 'tab-pendencias',
          mensalistas: 'tab-mensalistas',
          'oficinas-modulares': 'tab-oficinas-modulares',
          exclusoes: 'tab-exclusoes',
          duplicados: 'tab-duplicados',
          'reset-senha': 'tab-reset-senha',
          'acesso-secretaria': 'tab-acesso-secretaria',
          'fluxo-caixa': 'tab-fluxo-caixa',
          'dados-asaas': 'tab-dados-asaas',
          'email-massa': 'tab-email-massa'
        };
        Object.keys(mapping).forEach(function (key) {
          var section = document.getElementById(mapping[key]);
          if (section) section.classList.toggle('hidden', key !== name);
        });
      }

      setTimeout(function () {
        if (window.__adminDashboardBooted) {
          return;
        }
        console.error('[admin-dashboard] JS externo não inicializou; fallback inline ativado.');
        var tabs = document.querySelectorAll('[data-tab]');
        tabs.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-tab') || 'entries';
            activateTab(tab);
          });
        });
      }, 700);
    })();
  </script>
</body>
</html>
