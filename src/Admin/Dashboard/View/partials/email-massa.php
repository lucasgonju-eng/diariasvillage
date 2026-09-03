<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('email-massa', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-email-massa" class="<?php echo $activeTab === 'email-massa' ? '' : 'hidden'; ?>">
        <h2>Enviar E-mails em Massa</h2>
        <p class="muted">Envio disponível apenas para admin principal. Selecione os alunos, monte o HTML e envie para os responsáveis dos alunos marcados.</p>

        <div class="bulk-mail-filters">
          <div class="form-group">
            <label>Filtrar alunos</label>
            <input id="bulk-mail-filter" type="text" placeholder="Digite nome ou matrícula" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Série</label>
            <select id="bulk-mail-grade-filter">
              <option value="all">Todas</option>
            </select>
          </div>
          <div class="form-group">
            <label>Perfil</label>
            <select id="bulk-mail-type-filter">
              <option value="all">Todos</option>
              <option value="diaristas">Diaristas (já fizeram pelo menos uma diária)</option>
              <option value="mensalistas">Mensalistas</option>
              <option value="inadimplentes">Inadimplentes (cobranças em aberto)</option>
            </select>
          </div>
        </div>

        <div class="bulk-mail-toolbar">
          <label style="display:flex;gap:6px;align-items:center;font-size:13px;">
            <input id="bulk-mail-select-all" type="checkbox" />
            Marcar todos (filtro atual)
          </label>
          <div class="bulk-mail-meta" id="bulk-mail-counter">0 selecionado(s)</div>
        </div>

        <div class="bulk-mail-table-wrap">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Selecionar</th>
                <th>Aluno</th>
                <th>Matrícula</th>
                <th>Tipo</th>
                <th>E-mail(s) de destino</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="bulk-mail-recipients-body">
              <tr><td colspan="6">Carregando alunos...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="bulk-mail-template-row">
          <div class="form-group" style="min-width:280px;">
            <label>Template salvo</label>
            <select id="bulk-mail-template-select"></select>
          </div>
          <button id="bulk-mail-template-load" class="btn btn-ghost btn-sm" type="button">Carregar template</button>
          <button id="bulk-mail-template-save" class="btn btn-primary btn-sm" type="button">Salvar template atual</button>
        </div>

        <div class="charge-fields">
          <div class="form-group">
            <label>Assunto</label>
            <input id="bulk-mail-subject" type="text" placeholder="Assunto do e-mail" />
          </div>
        </div>
        <div class="bulk-mail-meta">Placeholders disponíveis: {{NOME_ALUNO}}, {{MATRICULA}}, {{NOME_RESPONSAVEL}}</div>

        <div class="bulk-mail-compose">
          <div class="form-group">
            <label>HTML puro</label>
            <textarea id="bulk-mail-html" class="bulk-mail-editor" placeholder="Cole ou escreva o HTML completo do e-mail"></textarea>
          </div>
          <div class="form-group">
            <label for="bulk-mail-visual">Visão do usuário (editável, sanitizada e isolada)</label>
            <iframe
              id="bulk-mail-visual"
              class="bulk-mail-visual"
              sandbox="allow-same-origin"
              title="Prévia segura e editável do e-mail"
            ></iframe>
          </div>
        </div>

        <div class="bulk-mail-toolbar">
          <button id="bulk-mail-send" class="btn btn-danger btn-sm" type="button">Enviar para selecionados</button>
          <button id="bulk-mail-send-test" class="btn btn-ghost btn-sm bulk-mail-edit-emails" type="button">Enviar teste</button>
          <div id="bulk-mail-message" class="charge-message"></div>
        </div>
      </section>
