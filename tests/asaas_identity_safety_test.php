<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\AsaasCustomerIdentity;

$root = dirname(__DIR__);
$files = [
    'checkout' => $root . '/public/api/create-payment.php',
    'view_user' => $root . '/public/api/admin-view-as-user.php',
    'guardian_upsert' => $root . '/public/api/admin-upsert-guardian-for-student.php',
    'dashboard_js' => $root . '/public/assets/js/admin-dashboard.js',
    'dashboard_php' => $root . '/public/admin/dashboard.php',
    'batch_v1' => $root . '/public/api/admin-send-pending-charges.php',
    'batch_v2' => $root . '/public/api/admin-send-pending-charges-v2.php',
    'attendance' => $root . '/public/api/admin-attendance.php',
    'finance' => $root . '/public/api/financeiro-pay.php',
    'resend' => $root . '/public/api/admin-resend-feb-charge.php',
];

$failures = [];

function test_read(string $path, array &$failures): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $failures[] = 'Arquivo ausente ou ilegível: ' . $path;
        return '';
    }
    return $content;
}

function test_contains(string $label, string $content, string $needle, array &$failures): void
{
    if (strpos($content, $needle) === false) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
}

function test_not_contains(string $label, string $content, string $needle, array &$failures): void
{
    if (strpos($content, $needle) !== false) {
        $failures[] = $label . ' não deveria conter: ' . $needle;
    }
}

function test_order(string $label, string $content, string $first, string $second, array &$failures): void
{
    $firstPosition = strpos($content, $first);
    $secondPosition = strpos($content, $second);
    if ($firstPosition === false || $secondPosition === false || $firstPosition > $secondPosition) {
        $failures[] = $label . ' deveria validar "' . $first . '" antes de "' . $second . '".';
    }
}

$contents = [];
foreach ($files as $name => $path) {
    $contents[$name] = test_read($path, $failures);
}

if (!AsaasCustomerIdentity::isValidCpfOrCnpj('006.246.111-75')) {
    $failures[] = 'CPF válido com máscara deveria ser aceito.';
}
if (AsaasCustomerIdentity::isValidCpfOrCnpj('006.246.111-74')) {
    $failures[] = 'CPF com dígito verificador incorreto deveria ser recusado.';
}
if (AsaasCustomerIdentity::isValidCpfOrCnpj('111.111.111-11')) {
    $failures[] = 'CPF com todos os dígitos repetidos deveria ser recusado.';
}

test_contains('checkout usa resolvedor de identidade', $contents['checkout'], 'new AsaasCustomerIdentity(', $failures);
test_contains('checkout bloqueia cobrança existente', $contents['checkout'], "'PAYMENT_ALREADY_EXISTS'", $failures);
test_contains('checkout cancela órfã se persistência falhar', $contents['checkout'], '$asaas->deletePayment($createdAsaasPaymentId);', $failures);
test_not_contains('checkout não altera somente CPF', $contents['checkout'], "updateCustomer(\$guardianData['asaas_customer_id']", $failures);
test_order('checkout checa idempotência antes de cobrar', $contents['checkout'], "'PAYMENT_ALREADY_EXISTS'", '$asaas->createPayment(', $failures);

test_contains('visão exige student_id', $contents['view_user'], "\$payload['student_id']", $failures);
test_contains('visão aceita guardian_id explícito', $contents['view_user'], "\$payload['guardian_id']", $failures);
test_contains('visão exige escolha com múltiplos responsáveis', $contents['view_user'], "'GUARDIAN_SELECTION_REQUIRED'", $failures);
test_not_contains('visão não busca por nome', $contents['view_user'], '&name=eq.', $failures);

test_contains('cadastro de responsável exige student_id', $contents['guardian_upsert'], "\$payload['student_id']", $failures);
test_not_contains('cadastro não busca aluno por nome', $contents['guardian_upsert'], '&name=eq.', $failures);

test_contains('UI mostra matrícula', $contents['dashboard_js'], 'Matrícula ${enrollment}', $failures);
test_contains('UI envia student_id', $contents['dashboard_js'], 'student_id: resolved.id', $failures);
test_contains('UI envia guardian_id', $contents['dashboard_js'], 'guardian_id: selectedGuardianId', $failures);
test_contains('dashboard possui seletor de responsável', $contents['dashboard_php'], 'id="admin-view-user-guardian"', $failures);
test_contains('dashboard atualizou cache do JS', $contents['dashboard_php'], '/assets/js/admin-dashboard.js?v=74', $failures);

foreach (['batch_v1', 'batch_v2', 'attendance', 'finance', 'resend'] as $flow) {
    test_contains($flow . ' valida identidade Asaas', $contents[$flow], 'new \\App\\AsaasCustomerIdentity(', $failures);
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas na proteção de identidade Asaas:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: identidade Asaas, seleção explícita e idempotência validadas.\n";
