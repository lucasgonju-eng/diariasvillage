<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'endpoint' => $root . '/public/api/admin-settle-payment.php',
    'cashflow' => $root . '/public/api/admin-cashflow.php',
    'dashboard_js' => $root . '/public/assets/js/admin-dashboard.js',
    'dashboard_php' => $root . '/public/admin/dashboard.php',
];

$failures = [];

function read_test_file(string $path, array &$failures): string
{
    if (!is_file($path)) {
        $failures[] = 'Arquivo ausente: ' . $path;
        return '';
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        $failures[] = 'Falha ao ler: ' . $path;
        return '';
    }

    return $content;
}

function assert_contains(string $label, string $haystack, string $needle, array &$failures): void
{
    if (strpos($haystack, $needle) === false) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
}

function assert_order(string $label, string $haystack, string $first, string $second, array &$failures): void
{
    $firstPos = strpos($haystack, $first);
    $secondPos = strpos($haystack, $second);
    if ($firstPos === false || $secondPos === false || $firstPos > $secondPos) {
        $failures[] = $label . ' deveria validar "' . $first . '" antes de "' . $second . '".';
    }
}

$endpoint = read_test_file($files['endpoint'], $failures);
$cashflow = read_test_file($files['cashflow'], $failures);
$dashboardJs = read_test_file($files['dashboard_js'], $failures);
$dashboardPhp = read_test_file($files['dashboard_php'], $failures);

assert_contains('endpoint autenticação admin', $endpoint, 'Helpers::requireAdminRole(', $failures);
assert_contains('endpoint admin principal', $endpoint, 'AdminAuth::ROLE_ADMIN', $failures);
assert_contains('endpoint exige POST', $endpoint, 'Helpers::requirePost();', $failures);
assert_contains('endpoint exige id', $endpoint, 'ID da cobrança inválido.', $failures);
assert_contains('endpoint exige observação', $endpoint, 'Informe a observação/motivo da baixa.', $failures);
assert_contains('endpoint limita observação', $endpoint, '$noteLength > 500', $failures);
assert_contains('endpoint busca payment por id', $endpoint, "'payments'", $failures);
assert_contains('endpoint bloqueia pago', $endpoint, 'Esta cobrança já está baixada/paga.', $failures);
assert_contains('endpoint restringe a cobranças PIX', $endpoint, "in_array(\$billingType, ['PIX', 'PIX_MANUAL'], true)", $failures);
assert_contains('endpoint restringe status pendentes', $endpoint, "['pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis']", $failures);
assert_contains('endpoint marca paid', $endpoint, "'status' => 'paid'", $failures);
assert_contains('endpoint registra paid_at', $endpoint, "'paid_at' => \$settledAt", $failures);
assert_contains('endpoint auditoria log', $endpoint, 'append_payment_settlement_log', $failures);
assert_contains('endpoint auditoria observação', $endpoint, "'note' => \$note", $failures);
assert_contains('endpoint auditoria usuário', $endpoint, "'settled_by' =>", $failures);
assert_order('endpoint ordem auth/post', $endpoint, 'Helpers::requireAdminRole(', 'Helpers::requirePost();', $failures);
assert_order('endpoint ordem validação/update', $endpoint, "in_array(\$billingType, ['PIX', 'PIX_MANUAL'], true)", "\$client->update('payments'", $failures);

assert_contains('cashflow habilita ação explicitamente', $cashflow, "'can_manual_settle' =>", $failures);
assert_contains('cashflow restringe ação a cobranças PIX', $cashflow, "in_array(strtoupper(\$billingType), ['PIX', 'PIX_MANUAL'], true)", $failures);
assert_contains('cashflow restringe ação a pendentes', $cashflow, "in_array(strtolower(\$status), ['pending', 'pending_asaas', 'overdue', 'awaiting_risk_analysis'], true)", $failures);
assert_contains('cashflow bloqueia pendencia_de_cadastro', $cashflow, "'can_manual_settle' => false", $failures);

assert_contains('js modal baixa manual', $dashboardJs, 'showManualSettlementInput', $failures);
assert_contains('js exige observação antes do POST', $dashboardJs, 'Observação obrigatória', $failures);
assert_contains('js botão depende de can_manual_settle', $dashboardJs, "item?.can_manual_settle === true", $failures);
assert_contains('js endpoint baixa manual', $dashboardJs, '/api/admin-settle-payment.php', $failures);
assert_contains('js envia observação', $dashboardJs, 'note: noteResult.value', $failures);

assert_contains('dashboard coluna ação', $dashboardPhp, '<th>Ação</th>', $failures);
assert_contains('dashboard cache bust js', $dashboardPhp, '/assets/js/admin-dashboard.js?v=81', $failures);

if ($failures !== []) {
    fwrite(STDERR, "Falhas no teste de segurança da baixa manual:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: teste de segurança da baixa manual validou endpoint, API e UI.\n";
