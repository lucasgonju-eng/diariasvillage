<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('reset-senha', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-reset-senha" class="<?php echo $activeTab === 'reset-senha' ? '' : 'hidden'; ?>">
        <h2>Resetar senha do usuário</h2>
        <p class="muted">Busque o CPF, confira explicitamente o responsável e o aluno, e só então defina a nova senha. CPFs com identidade ou conta divergente ficam bloqueados para revisão.</p>

        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>CPF do responsável</label>
            <input id="reset-cpf" type="text" placeholder="Digite o CPF (apenas números)" inputmode="numeric" maxlength="14" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="reset-lookup-btn" class="btn btn-primary btn-sm" type="button">Buscar conta</button>
          </div>
          <div class="form-group">
            <label>Vínculo confirmado</label>
            <select id="reset-guardian" disabled>
              <option value="">Busque um CPF válido</option>
            </select>
          </div>
          <div class="form-group">
            <label>Nova senha</label>
            <input id="reset-senha-nova" type="password" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password" />
          </div>
          <div class="form-group">
            <label>Confirmar nova senha</label>
            <input id="reset-senha-confirm" type="password" placeholder="Repita a nova senha" minlength="6" autocomplete="new-password" />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="reset-senha-btn" class="btn btn-danger btn-sm" type="button" disabled>Resetar senha</button>
          </div>
        </div>
        <div id="reset-senha-message" class="charge-message"></div>
      </section>
