<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('oficinas-modulares', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-oficinas-modulares" class="<?php echo $activeTab === 'oficinas-modulares' ? '' : 'hidden'; ?>">
        <h2>Criação de Oficinas Modulares</h2>
        <p class="muted">Somente admin cria a grade mensal. As regras fixas da grade são preservadas: horários 14:00-15:00 e 15:40-16:40, com seleção por dia da semana útil.</p>
        <datalist id="modular-catalog-list"></datalist>
        <datalist id="modular-teachers-list"></datalist>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>Mês da grade</label>
            <select id="modular-create-month">
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo ((int) date('n') === $m) ? 'selected' : ''; ?>>
                  <?php echo str_pad((string) $m, 2, '0', STR_PAD_LEFT); ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Ano da grade</label>
            <input id="modular-create-year" type="number" min="2025" max="2099" value="<?php echo date('Y'); ?>" />
          </div>
          <div class="form-group">
            <label>Nome da Oficina Modular</label>
            <input id="modular-create-name" list="modular-catalog-list" type="text" placeholder="Digite nova oficina ou selecione do catálogo" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Professor(a)</label>
            <input id="modular-create-teacher" list="modular-teachers-list" type="text" placeholder="Digite novo professor(a) ou selecione" autocomplete="off" />
          </div>
          <div class="form-group">
            <label>Grade semanal (dias e horários fixos)</label>
            <div class="office-days-wrap">
              <div class="office-day-row">
                <span class="office-day-label">Segunda-feira</span>
                <div class="office-day-slots">
                  <label><input type="checkbox" name="modular-create-week-slot" value="1_1" /> 1º horário (14h)</label>
                  <label><input type="checkbox" name="modular-create-week-slot" value="1_2" /> 2º horário (15h40)</label>
                </div>
              </div>
              <div class="office-day-row">
                <span class="office-day-label">Terça-feira</span>
                <div class="office-day-slots">
                  <label><input type="checkbox" name="modular-create-week-slot" value="2_1" /> 1º horário (14h)</label>
                  <label><input type="checkbox" name="modular-create-week-slot" value="2_2" /> 2º horário (15h40)</label>
                </div>
              </div>
              <div class="office-day-row">
                <span class="office-day-label">Quarta-feira</span>
                <div class="office-day-slots">
                  <label><input type="checkbox" name="modular-create-week-slot" value="3_1" /> 1º horário (14h)</label>
                  <label><input type="checkbox" name="modular-create-week-slot" value="3_2" /> 2º horário (15h40)</label>
                </div>
              </div>
              <div class="office-day-row">
                <span class="office-day-label">Quinta-feira</span>
                <div class="office-day-slots">
                  <label><input type="checkbox" name="modular-create-week-slot" value="4_1" /> 1º horário (14h)</label>
                  <label><input type="checkbox" name="modular-create-week-slot" value="4_2" /> 2º horário (15h40)</label>
                </div>
              </div>
              <div class="office-day-row">
                <span class="office-day-label">Sexta-feira</span>
                <div class="office-day-slots">
                  <label><input type="checkbox" name="modular-create-week-slot" value="5_1" /> 1º horário (14h)</label>
                  <label><input type="checkbox" name="modular-create-week-slot" value="5_2" /> 2º horário (15h40)</label>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="modular-create-btn" class="btn btn-primary btn-sm" type="button">Criar oficina modular</button>
          </div>
        </div>
        <div id="modular-create-message" class="charge-message"></div>

        <h3 style="margin-top:14px;">Prévia para teste</h3>
        <p class="muted">Sem matrícula de alunos. Aqui você valida como as oficinas aparecem para aluno, secretaria e admin no mês/ano selecionado.</p>
        <div class="form-group" style="max-width:260px;">
          <label>Dia de teste (visão do aluno)</label>
          <select id="modular-preview-day">
            <option value="1">Segunda-feira</option>
            <option value="2">Terça-feira</option>
            <option value="3">Quarta-feira</option>
            <option value="4">Quinta-feira</option>
            <option value="5">Sexta-feira</option>
          </select>
        </div>

        <div class="office-preview-grid">
          <div class="office-preview-card">
            <h4>Prévia Aluno • 14:00-15:00</h4>
            <div id="modular-preview-aluno-1400" class="office-preview-list">
              <div class="muted">Carregando oficinas...</div>
            </div>
          </div>
          <div class="office-preview-card">
            <h4>Prévia Aluno • 15:40-16:40</h4>
            <div id="modular-preview-aluno-1540" class="office-preview-list">
              <div class="muted">Carregando oficinas...</div>
            </div>
          </div>
        </div>

        <h3 style="margin-top:16px;">Prévia Secretaria</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Oficina</th>
                <th>Professor(a)</th>
                <th>Dias</th>
                <th>Horários</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="modular-preview-secretaria-body">
              <tr><td colspan="5">Carregando oficinas...</td></tr>
            </tbody>
          </table>
        </div>

        <h3 style="margin-top:16px;">Prévia Admin</h3>
        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Código</th>
                <th>Oficina</th>
                <th>Tipo</th>
                <th>Capacidade</th>
                <th>Dias/Horários</th>
                <th>Visível no mês</th>
              </tr>
            </thead>
            <tbody id="modular-preview-admin-body">
              <tr><td colspan="6">Carregando oficinas...</td></tr>
            </tbody>
          </table>
        </div>
      </section>
