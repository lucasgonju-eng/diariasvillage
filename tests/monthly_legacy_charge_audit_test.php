<?php
declare(strict_types=1);

$auditPath = dirname(__DIR__) . '/public/api/admin-monthly-legacy-charge-audit.php';
$audit = file_get_contents($auditPath);
if ($audit === false) {
    fwrite(STDERR, "Não foi possível ler o auditor de cobranças legadas.\n");
    exit(1);
}

$failures = [];

$contains = static function (string $label, string $needle) use (&$failures, $audit): void {
    if (!str_contains($audit, $needle)) {
        $failures[] = $label;
    }
};

$notContains = static function (string $label, string $needle) use (&$failures, $audit): void {
    if (str_contains($audit, $needle)) {
        $failures[] = $label;
    }
};

$contains('acesso exclusivo do admin principal', 'Helpers::requireAdminRole(AdminAuth::ROLE_ADMIN)');
$contains('auditoria aceita somente GET', "REQUEST_METHOD'] ?? 'GET') !== 'GET'");
$contains('corte histórico anterior a setembro', "LEGACY_MONTHLY_CUTOFF = '2026-09-01'");
$contains('consulta somente planos ativos', "'select=student_id,weekly_days&active=eq.true&order=student_id.asc'");
$contains('paginação explícita não permite truncamento silencioso', 'function legacy_monthly_select_all(');
$contains('paginação usa offset determinístico', "'&offset=' . " . '$offset');
$contains('consulta somente cobranças não pagas', ". '&paid_at=is.null'");
$contains('consulta somente cobranças anteriores ao corte', ". '&payment_date=lt.'");
$contains('consulta cobrança remota pelo ID exato', '$asaas->getPayment($asaasPaymentId)');
$contains('consulta cliente remoto pelo ID exato', '$asaas->getCustomer($remoteCustomerId)');
$contains('valida vínculo responsável-aluno', "'guardian_link_match'");
$contains('valida vínculo do cliente Asaas', "'customer_link_match'");
$contains('valida identidade composta remota', 'AsaasCustomerIdentity::matchesRemoteCustomer(');
$contains('exige e-mail local válido', 'filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)');
$contains('exige documento local válido', 'AsaasCustomerIdentity::isValidCpfOrCnpj($guardianDocument)');
$contains('valida valor remoto', 'legacy_monthly_amount_matches($amount, $remoteAmount)');
$contains('pagamento remoto nunca vira candidato de cancelamento', "'REMOTE_PAID_REQUIRES_RECONCILIATION'");
$contains('estado remoto desconhecido permanece bloqueado', "'REMOTE_STATUS_UNKNOWN'");
$contains('fila sem ID permanece para revisão', "'LOCAL_QUEUE_WITHOUT_REMOTE_ID'");
$contains('resultado declara modo somente leitura', "'read_only' => true");

foreach ([
    '->insert(',
    '->update(',
    '->delete(',
    '->rpc(',
    'createPayment(',
    'deletePayment(',
    'updateCustomer(',
    'createCustomer(',
] as $mutation) {
    $notContains('auditor não pode conter mutação ' . $mutation, $mutation);
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas no auditor legado mensalista:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: auditor legado mensalista é individual, fail-closed e somente leitura.\n";
