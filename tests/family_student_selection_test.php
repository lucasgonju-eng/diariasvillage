<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use (&$failures): string {
    $content = file_get_contents($path);
    if ($content === false) {
        $failures[] = 'Não foi possível ler ' . $path;
        return '';
    }
    return $content;
};
$contains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $label;
    }
};
$notContains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (str_contains($content, $needle)) {
        $failures[] = $label;
    }
};
$ordered = static function (string $label, string $content, string $first, string $second) use (&$failures): void {
    $firstAt = strpos($content, $first);
    $secondAt = strpos($content, $second);
    if ($firstAt === false || $secondAt === false || $firstAt >= $secondAt) {
        $failures[] = $label;
    }
};

$loginApi = $read($root . '/public/api/login.php');
$loginJs = $read($root . '/public/assets/js/login.js');
$loginPage = $read($root . '/public/login.php');
$selector = $read($root . '/public/selecionar-aluno.php');
$selectStudent = $read($root . '/public/api/select-student.php');
$helpers = $read($root . '/src/Helpers.php');
$dashboard = $read($root . '/public/dashboard.php');
$mobileLogin = $read($root . '/public/mobile/app/login.php');
$mobileRouter = $read($root . '/public/mobile/index.php');
$adminView = $read($root . '/public/api/admin-view-as-user.php');
$requestPage = $read($root . '/public/vincular-filho.php');
$requestApi = $read($root . '/public/api/family-link-request.php');
$reviewApi = $read($root . '/public/api/admin-review-family-link.php');
$adminDashboard = $read($root . '/src/Admin/Dashboard/View/layout.php')
    . $read($root . '/src/Admin/Dashboard/View/partials/familias.php');
$adminDashboardJs = $read($root . '/frontend/admin/domains/family-links.ts');
$adminDashboardDefinition = $read($root . '/src/Admin/Dashboard/Data/DashboardDefinition.php');
$profilePage = $read($root . '/public/profile.php');
$profileApi = $read($root . '/public/api/profile.php');
$profileAddGuardianApi = $read($root . '/public/api/profile-add-guardian.php');
$profileGuardiansApi = $read($root . '/public/api/profile-guardians.php');
$migration = $read($root . '/supabase/migrations/20260903051032_secure_family_link_requests.sql');

$contains(
    'login deve carregar todos os vínculos pelo auth_user_id',
    $loginApi,
    "'select=*&auth_user_id=eq.'"
);
$contains(
    'login deve contar student_id únicos',
    $loginApi,
    '$requiresSelection = count($studentIds) > 1'
);
$contains(
    'login deve marcar seleção obrigatória na sessão',
    $loginApi,
    "\$_SESSION['family_student_selection_required'] = \$requiresSelection"
);
$contains(
    'login deve marcar confirmação somente para conta de um aluno',
    $loginApi,
    "\$_SESSION['family_student_selection_confirmed'] = !\$requiresSelection"
);
$contains(
    'login deve direcionar famílias para a escolha',
    $loginApi,
    "'/selecionar-aluno.php'"
);
$ordered(
    'login deve validar a família antes de criar a sessão',
    $loginApi,
    'GuardianAccountIdentity::analyze($familyGuardians)',
    'Helpers::establishUserSession($user)'
);
$contains('frontend deve obedecer ao redirect do servidor', $loginJs, "data.redirect || '/dashboard.php'");
$contains('cache do login deve ser atualizado', $loginPage, '/assets/js/login.js?v=3');
$contains('mobile deve obedecer ao redirect canônico', $mobileLogin, "data.redirect||'/dashboard.php'");

$contains(
    'APIs devem bloquear enquanto a escolha estiver pendente',
    $helpers,
    "'code' => 'STUDENT_SELECTION_REQUIRED'"
);
$contains(
    'páginas devem redirecionar enquanto a escolha estiver pendente',
    $helpers,
    "header('Location: /selecionar-aluno.php')"
);
$contains(
    'mobile deve bloquear rotas operacionais antes da escolha',
    $mobileRouter,
    "\$_SESSION['family_student_selection_confirmed']"
);

$contains('seletor deve permitir somente sessão pendente autenticada', $selector, 'Helpers::requireAuthWeb(true)');
$contains('seletor deve revalidar a conta composta', $selector, 'GuardianAccountIdentity::analyze($guardians, $currentGuardianId)');
$contains('seletor deve falhar se algum aluno não carregar', $selector, 'array_diff_key($studentIds, $loadedIds)');
$contains('seletor deve consultar planos ativos', $selector, "'monthly_student_plans'");
$contains('seletor deve distinguir mensalista', $selector, 'Mensalista •');
$contains('seletor deve distinguir day-use', $selector, '>Day-use<');
$contains('seletor deve explicar que não há escolha automática', $selector, 'Nenhum filho é escolhido automaticamente.');
$contains('seletor deve usar cartões grandes', $selector, 'family-choice-card');
$contains('seletor deve enviar student_id UUID', $selector, 'name="student_id"');
$contains('seletor deve enviar CSRF', $selector, 'name="csrf_token"');

$contains('troca deve aceitar sessão ainda pendente', $selectStudent, 'Helpers::requireAuth(true)');
$contains('troca deve reutilizar guardian revalidado pela sessão', $selectStudent, "\$sessionAuthUserId = trim((string) (\$user['auth_user_id']");
$contains('troca deve validar CSRF', $selectStudent, 'hash_equals($expectedCsrfToken, $csrfToken)');
$contains('troca deve exigir mesma conta Auth', $selectStudent, '&auth_user_id=eq.');
$contains('troca deve exigir student_id escolhido', $selectStudent, '&student_id=eq.');
$contains('troca deve exigir mesma versão de sessão', $selectStudent, '&account_session_version=eq.');
$contains(
    'troca deve liberar fluxos somente após validar',
    $selectStudent,
    "\$_SESSION['family_student_selection_required'] = false"
);
$contains(
    'troca deve registrar confirmação explícita',
    $selectStudent,
    "\$_SESSION['family_student_selection_confirmed'] = true"
);
$contains('troca deve consumir o CSRF', $selectStudent, "unset(\$_SESSION['family_selection_csrf'])");
$notContains('troca não pode autorizar por CPF', $selectStudent, 'parent_document');
$notContains('troca não pode autorizar por nome', $selectStudent, 'parent_name');

$contains('dashboard deve destacar o aluno ativo', $dashboard, 'VOCÊ ESTÁ ORGANIZANDO PARA');
$contains('dashboard deve oferecer troca clara', $dashboard, '>Trocar filho<');
$contains(
    'dashboard deve redirecionar se não encontrar a seleção exata',
    $dashboard,
    'empty($studentRow) && count($studentRows) > 1'
);
$notContains('dashboard não deve trocar silenciosamente em select', $dashboard, 'id="family-student"');

$contains(
    'impersonação administrativa deve registrar seleção explícita concluída',
    $adminView,
    "\$_SESSION['family_student_selection_required'] = false"
);

$contains('dashboard familiar deve permitir solicitar outro filho', $dashboard, '/vincular-filho.php');
$contains('pedido deve exigir sessão com aluno já escolhido', $requestApi, 'Helpers::requireAuth()');
$contains('pedido deve validar CSRF', $requestApi, 'hash_equals($expectedCsrfToken, $csrfToken)');
$contains('pedido deve resolver conta somente por auth_user_id', $requestApi, "'select=*&auth_user_id=eq.'");
$contains('pedido deve registrar matrícula para revisão humana', $requestApi, "'requested_enrollment' => \$enrollment");
$notContains('pedido não deve confirmar existência do aluno', $requestApi, "select=id,active&enrollment=eq.");
$contains('pedido deve criar somente solicitação pendente', $requestApi, "'family_link_requests'");
$contains('pedido deve limitar fila por conta', $requestApi, 'count($pendingRows) >= 10');
$notContains('pedido do pai não pode criar vínculo diretamente', $requestApi, "insert('guardians'");
$notContains('pedido do pai não pode alterar responsável', $requestApi, "update('guardians'");
$contains('página deve explicar aprovação humana', $requestPage, 'A solicitação não concede acesso automaticamente.');
$contains('página só deve revelar aluno após aprovação', $requestPage, "\$status === 'APPROVED'");

$contains('secretaria deve poder revisar vínculo', $reviewApi, 'AdminAuth::ROLE_SECRETARIA');
$contains('admin deve poder revisar vínculo', $reviewApi, 'AdminAuth::ROLE_ADMIN');
$contains('revisão administrativa deve validar CSRF', $reviewApi, 'hash_equals($expectedCsrfToken, $csrfToken)');
$contains('revisão deve ocorrer em RPC transacional', $reviewApi, "rpc('review_family_link_request'");
$notContains('endpoint de revisão não cria guardian fora da RPC', $reviewApi, "insert('guardians'");
$contains('dashboard admin deve ter aba Famílias', $adminDashboardDefinition, "'familias' =>");
$contains('dashboard deve mostrar aluno de origem', $adminDashboard, 'Aluno já vinculado');
$contains('dashboard deve mostrar aluno solicitado', $adminDashboard, 'Aluno solicitado');
$contains('aprovação deve exigir digitação explícita', $adminDashboardJs, 'Digite ${confirmationWord}');
$contains('revisão deve enviar CSRF', $adminDashboardJs, 'csrf_token: runtime.adminCsrfToken');
$contains('asset administrativo deve usar manifest', $adminDashboard, '$assets[\'script\']');

$contains('migration deve remover unicidade de e-mail por linha', $migration, 'drop constraint if exists guardians_email_key');
$contains('migration deve proteger identidade de e-mail', $migration, 'trg_guardians_email_identity');
$contains('migration deve impedir conta duplicada no mesmo aluno', $migration, 'uq_guardians_auth_user_student');
$contains('migration deve garantir matrícula normalizada única', $migration, 'uq_students_enrollment_normalized');
$contains('migration deve proteger documento por identidade composta', $migration, 'trg_guardians_document_identity');
$contains('RPC deve serializar revisões da matrícula alvo', $migration, "'family-target:'");
$contains('migration deve ativar RLS nas solicitações', $migration, 'alter table public.family_link_requests enable row level security');
$contains('service role não deve atualizar solicitações diretamente', $migration, 'grant select, insert on table public.family_link_requests to service_role');
$contains('insert direto deve ser forçado para PENDING', $migration, 'trg_family_link_request_insert');
$contains('migration deve bloquear RPC para anon', $migration, 'review_family_link_request(uuid, uuid, text, text, text)');
$contains('RPC deve travar solicitação', $migration, 'for update;');
$contains('RPC deve conferir identidade da conta', $migration, 'REQUESTER_ACCOUNT_IDENTITY_CONFLICT');
$contains('RPC deve validar dígitos do documento', $migration, 'public.is_valid_cpf_cnpj_digits(v_document)');
$contains('service role deve executar validador no primeiro acesso', $migration, 'grant execute on function public.is_valid_cpf_cnpj_digits(text)');
$contains('RPC deve bloquear mutações concorrentes de guardian', $migration, 'lock table public.guardians in share row exclusive mode');
$contains('RPC deve bloquear e-mail divergente no alvo', $migration, 'TARGET_GUARDIAN_EMAIL_CONFLICT');
$contains('RPC deve bloquear conflito Asaas', $migration, 'TARGET_GUARDIAN_ASAAS_CONFLICT');
$notContains('RPC não deve propagar cliente Asaas sem validação remota', $migration, 'asaas_customer_id = coalesce');
$contains('RPC deve auditar aprovação', $migration, 'FAMILY_LINK_REQUEST_APPROVED');
$contains('primeiro acesso não deve agrupar irmãos automaticamente', $migration, "'related_guardians_updated', 0");

$notContains('perfil não pode expandir família por CPF parcial', $profilePage, 'parent_document=ilike.');
$notContains('perfil não pode expandir família por e-mail', $profilePage, 'guardiansByEmail');
$contains('perfil deve usar auth_user_id', $profilePage, '&auth_user_id=eq.');
$contains('perfil deve tornar identidade somente leitura', $profilePage, 'id="parent-document"');
$contains('perfil deve enviar CSRF', $profilePage, 'name="profile-csrf-token"');
$notContains('perfil não pode procurar Auth por e-mail', $profileApi, 'listUsers(');
$contains('perfil deve atualizar Auth pelo UUID vinculado', $profileApi, '$auth->updateUser($authUserId');
$contains('perfil deve validar CSRF', $profileApi, 'hash_equals($expectedCsrfToken, $csrfToken)');
$contains('sessão antiga sem Auth deve exigir novo login', $selector, "header('Location: /login.php?reauth=1')");
$contains('seletor deve revalidar guardian atual', $selector, 'GuardianAccountIdentity::analyze($guardians, $currentGuardianId)');
$contains('mobile legado deve exigir seleção', $mobileRouter, '$legacyAuthRequired');
$contains('novo responsável deve validar CPF/CNPJ', $profileAddGuardianApi, 'AsaasCustomerIdentity::isValidCpfOrCnpj');
$notContains('novo responsável não pode procurar aluno por e-mail', $profileAddGuardianApi, 'currentByEmail');
$notContains('lista de responsáveis não pode procurar aluno por e-mail', $profileGuardiansApi, 'currentByEmail');

if ($failures !== []) {
    fwrite(STDERR, "Falhas na seleção familiar obrigatória:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: famílias escolhem explicitamente o filho antes de qualquer fluxo operacional.\n";
