<?php
declare(strict_types=1);

if (!defined('ADMIN_DASHBOARD_COMPOSING')) {
    http_response_code(404);
    exit;
}
if (!in_array('charges', $context['allowedTabs'], true)) {
    return;
}
$activeTab = $context['activeTab'];
?>
<section id="tab-charges" class="<?php echo $activeTab === 'charges' ? '' : 'hidden'; ?>">
        <h2>Cobrança manual pós-chamada</h2>
        <p class="muted">Use quando o aluno frequentou sem pagamento antecipado. Registre a cobrança manual para revisão antes do envio.</p>

        <div class="form-group">
          <label>Aluno</label>
          <input id="charge-student" list="students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
          <datalist id="students-list"></datalist>
        </div>

        <div id="charge-list" class="charge-list"></div>

        <button class="btn btn-primary" id="send-charges" type="button">Registrar cobranças manuais (sem envio)</button>
        <div id="charge-message" class="charge-message"></div>
      </section>
