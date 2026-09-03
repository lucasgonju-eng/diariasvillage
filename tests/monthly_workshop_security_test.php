<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root . '/' . $relative;
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $failures[] = 'Arquivo ausente ou ilegível: ' . $relative;
        return '';
    }
    return $content;
};
$contains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
};

$schema = $read('supabase/migrations/20260903001157_add_monthly_workshop_confirmations.sql');
$seed = $read('supabase/migrations/20260903003154_seed_legacy_monthly_student_plans.sql');
$codeFix = $read('supabase/migrations/20260903003357_fix_monthly_entry_code_generation.sql');
$service = $read('src/Services/MonthlyWorkshopService.php');
$monthlyStudents = $read('src/MonthlyStudents.php');
$adminPlans = $read('public/api/admin-monthly-students.php');
$dashboardDefinition = $read('src/Admin/Dashboard/Data/DashboardDefinition.php');

$contains('quota mensal é duas oficinas por dia', $schema, 'required_slots = weekly_days_snapshot * 2');
$contains('dias mensais são exatos', $schema, 'v_day_count <> v_plan.weekly_days');
$contains('cada dia possui dois encontros', $schema, 'having count(*) <> 2');
$contains('oficina recorrente exige todos os encontros', $schema, "o.monthly_selection_mode = 'ALL_MEETINGS'");
$contains('Trilhas seleciona encontro isolado', $schema, "monthly_selection_mode = 'SINGLE_MEETING'");
$contains('Orientadora é persistida', $schema, 'orientadora boolean not null default false');
$contains('confirmação ativa é única', $schema, 'uq_monthly_submission_confirmed');
$contains('entradas mensais são geradas', $schema, 'insert into public.monthly_workshop_entries');
$contains('alteração exige desbloqueio', $schema, 'unlock_monthly_workshops');
$contains('tabelas mensais usam RLS', $schema, 'alter table public.monthly_workshop_entries enable row level security');
$contains('RPC não é pública', $schema, 'revoke execute on function public.confirm_monthly_workshops');
$contains('função criptográfica tem schema disponível', $codeFix, 'set search_path = public, extensions');

if (preg_match_all("/\\('[0-9a-f-]{36}'::uuid, [2-5]\\)/i", $seed) !== 60) {
    $failures[] = 'A migration deve preservar exatamente os 60 planos mensalistas legados.';
}

$contains('serviço limita ao mês corrente', $service, '$referenceMonth !== self::currentMonth()');
$contains('consulta de plano falha fechada', $service, "throw new \\RuntimeException('Não foi possível confirmar o plano mensalista");
$contains('lista mensalista falha fechada', $monthlyStudents, "throw new \\RuntimeException('Não foi possível confirmar o cadastro");
$contains('plano confirmado bloqueia desativação', $adminPlans, "'MONTHLY_SUBMISSION_LOCKED'");
$contains(
    'secretaria recebe somente abas operacionais',
    $dashboardDefinition,
    "return ['chamada', 'familias', 'sem-whatsapp', 'mensalistas', 'entries'];"
);

$paymentEmitters = [
    'public/api/create-payment.php',
    'public/api/admin-send-pending-charges.php',
    'public/api/admin-send-pending-charges-v2.php',
    'public/api/admin-resend-feb-charge.php',
    'public/api/financeiro-pay.php',
    'public/api/diaria-iniciar.php',
    'public/pendencia-verify.php',
];
foreach ($paymentEmitters as $emitter) {
    $contains(
        'emissor bloqueia PIX mensalista (' . $emitter . ')',
        $read($emitter),
        'MonthlyWorkshopService'
    );
}
foreach (['public/api/admin-charge.php', 'public/api/admin-attendance.php'] as $emitter) {
    $contains(
        'emissor consulta cadastro mensalista por UUID (' . $emitter . ')',
        $read($emitter),
        'MonthlyStudents::resolvePlan'
    );
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas no fluxo mensalista:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: fluxo mensalista, migração legada e bloqueios de cobrança validados.\n";
