<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('acesso-secretaria', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
$secretariaAccount = $context['secretariaAccount'];
?>
<section id="tab-acesso-secretaria" class="<?php echo $activeTab === 'acesso-secretaria' ? '' : 'hidden'; ?>">
        <h2>Acesso da Secretaria</h2>
        <p class="muted">
          Crie ou troque a senha operacional da secretaria. A senha não é exibida nem registrada em logs.
          Ao salvar, qualquer sessão anterior da secretaria será encerrada.
        </p>
        <div class="info-note" style="margin-bottom:14px;">
          <?php if ($secretariaAccount === null || ($secretariaAccount['requires_password_setup'] ?? false)): ?>
            <strong>Ação necessária:</strong> a nova senha segura ainda não foi configurada.
            O acesso da secretaria permanece bloqueado até o admin principal salvar uma senha.
          <?php elseif (!($secretariaAccount['active'] ?? false)): ?>
            <strong>Conta desativada.</strong> Salvar uma nova senha reativará o acesso da secretaria.
          <?php else: ?>
            <strong>Conta segura configurada.</strong> Salvar novamente troca a senha e revoga sessões anteriores.
          <?php endif; ?>
        </div>
        <div class="charge-fields" style="margin-bottom:12px;">
          <div class="form-group">
            <label>Nova senha da secretaria</label>
            <input
              id="secretaria-password"
              type="password"
              minlength="12"
              maxlength="128"
              autocomplete="new-password"
              placeholder="12+ caracteres, maiúscula, minúscula, número e símbolo"
            />
          </div>
          <div class="form-group">
            <label>Confirmar nova senha</label>
            <input
              id="secretaria-password-confirm"
              type="password"
              minlength="12"
              maxlength="128"
              autocomplete="new-password"
              placeholder="Repita a nova senha"
            />
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;">
            <button id="secretaria-password-save" class="btn btn-danger btn-sm" type="button">
              Salvar acesso da secretaria
            </button>
          </div>
        </div>
        <div id="secretaria-password-message" class="charge-message" aria-live="polite"></div>
      </section>
