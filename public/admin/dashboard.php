<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/');
    exit;
}

$client = new SupabaseClient(new HttpClient());
$paymentsResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email)&status=eq.paid&order=paid_at.desc&limit=200'
);
$payments = $paymentsResult['data'] ?? [];

$manualPendingResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)&billing_type=eq.PIX_MANUAL&status=eq.pending&order=created_at.desc&limit=200'
);
$manualPending = $manualPendingResult['data'] ?? [];

$manualPaidResult = $client->select(
    'payments',
    'select=*,students(name,enrollment),guardians(parent_name,email,parent_phone)&billing_type=eq.PIX_MANUAL&status=eq.paid&order=paid_at.desc&limit=200'
);
$manualPaid = $manualPaidResult['data'] ?? [];

$missingWhatsappResult = $client->select(
    'guardians',
    'select=parent_name,email,parent_phone,parent_document,students(name,enrollment)&or=(parent_phone.is.null,parent_phone.eq.)&order=created_at.desc&limit=500'
);
$missingWhatsapp = $missingWhatsappResult['data'] ?? [];

$pendenciasResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,student_name,guardian_name,guardian_cpf,guardian_email,created_at,paid_at&order=created_at.desc&limit=500'
);
$pendencias = $pendenciasResult['data'] ?? [];

$studentsResult = $client->select('students', 'select=id,name,enrollment,created_at,active&limit=10000');
$students = $studentsResult['data'] ?? [];
$duplicateGroups = [];
$duplicateEnrollmentGroups = [];
$cpfDuplicateGroups = [];
if ($students) {
    $normalizeName = static function (string $name): string {
        $name = mb_strtoupper($name, 'UTF-8');
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        if ($translit !== false) {
            $name = $translit;
        }
        $name = preg_replace('/[^A-Z0-9]+/', '', $name) ?? '';
        return trim($name);
    };

    $groups = [];
    foreach ($students as $student) {
        $key = $normalizeName($student['name'] ?? '');
        if ($key === '') {
            continue;
        }
        $groups[$key][] = $student;
    }
    foreach ($groups as $group) {
        if (count($group) > 1) {
            usort($group, static function ($a, $b) {
                return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
            });
            $duplicateGroups[] = $group;
        }
    }

    $enrollmentGroups = [];
    foreach ($students as $student) {
        $enrollment = trim((string) ($student['enrollment'] ?? ''));
        if ($enrollment === '' || $enrollment === '-') {
            continue;
        }
        $enrollmentGroups[$enrollment][] = $student;
    }
    foreach ($enrollmentGroups as $group) {
        if (count($group) > 1) {
            usort($group, static function ($a, $b) {
                return strtotime($a['created_at'] ?? '') <=> strtotime($b['created_at'] ?? '');
            });
            $duplicateEnrollmentGroups[] = $group;
        }
    }
}

$guardiansResult = $client->select(
    'guardians',
    'select=student_id,parent_document,students(id,name,enrollment)&parent_document=not.is.null&order=created_at.desc&limit=10000'
);
$guardians = $guardiansResult['data'] ?? [];
if ($guardians) {
    $cpfGroups = [];
    foreach ($guardians as $guardian) {
        $cpf = trim((string) ($guardian['parent_document'] ?? ''));
        if ($cpf === '') {
            continue;
        }
        $cpfGroups[$cpf][] = $guardian;
    }
    foreach ($cpfGroups as $cpf => $group) {
        $studentIds = array_unique(array_filter(array_map(static fn($g) => $g['student_id'] ?? '', $group)));
        if (count($studentIds) > 1) {
            $cpfDuplicateGroups[] = $group;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Entradas</title>
  <link rel="stylesheet" href="/assets/style.css?v=5" />
  <style>
    .admin-wrap{max-width:1120px;margin:0 auto;padding:28px 20px}
    .admin-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    .admin-title{font-weight:800;letter-spacing:.08em;font-size:12px;color:#1a2133}
    .admin-card{background:#fff;border-radius:16px;padding:18px;border:1px solid var(--line);box-shadow:var(--shadow-soft)}
    .admin-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
    .admin-tab{background:#EFF3FA;border-color:#D5DCE8;color:#1a2133}
    .admin-card{background:#FDFDFE;border-radius:16px;padding:18px;border:1px solid #E6EAF2;box-shadow:var(--shadow-soft)}
    .admin-table{width:100%;border-collapse:collapse}
    .admin-table th,.admin-table td{padding:10px 8px}
    .admin-table tr{border-top:1px solid #f1f5f9}
    .charge-list{display:grid;gap:12px;margin-top:12px}
    .charge-item{background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:14px}
    .charge-header{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
    .charge-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
    .date-list{display:grid;gap:8px}
    .date-row{display:flex;gap:8px;align-items:center}
    .date-row input{flex:1}
    .date-row .btn{
      color:#0A1B4D;
      border-color:#BFD0EE;
      background:#E8F0FF;
      font-weight:800;
    }
    .btn-danger{
      color:#B91C1C;
      border-color:#FCA5A5;
      background:#FEE2E2;
      font-weight:800;
    }
    .btn-danger:hover{filter:brightness(.97)}
    .date-actions{display:flex;gap:6px}
    .charge-message{margin-top:12px;font-size:13px}
    .hidden{display:none}
  </style>
</head>
<body>
  <div class="admin-wrap">
    <header class="admin-header">
      <div class="admin-title">DIÁRIAS VILLAGE • ADMIN</div>
      <div class="cta">
        <a class="btn btn-ghost btn-sm" href="/admin/import.php">Importar alunos</a>
        <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
      </div>
    </header>

    <div class="admin-card">
      <div class="admin-tabs">
        <button class="btn btn-primary btn-sm" type="button" data-tab="charges">Cobrança</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="inadimplentes">Inadimplentes</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="recebidas">Cobranças recebidas</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="sem-whatsapp">Sem WhatsApp</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="pendencias">Pendências</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="duplicados">Duplicados</button>
        <button class="btn btn-primary btn-sm" type="button" data-tab="entries">Entradas confirmadas</button>
      </div>

      <section id="tab-entries">
        <h2>Entradas confirmadas</h2>
        <p class="muted">Pagamentos confirmados e liberados para entrada.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Matricula</th>
                <th>Pagamento</th>
                <th>Tipo</th>
                <th>Data do day-use</th>
                <th>Confirmado em</th>
                <th>Valor</th>
                <th>Codigo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr>
                  <td colspan="8">Nenhuma entrada confirmada ainda.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $billing = in_array($payment['billing_type'], ['PIX', 'PIX_MANUAL'], true) ? 'PIX' : 'Debito';
                    $dailyRaw = $payment['daily_type'] ?? '';
                    $dailyBase = explode('|', $dailyRaw, 2)[0] ?? $dailyRaw;
                    $dailyLabel = $dailyBase === 'emergencial' ? 'Emergencial' : 'Planejada';
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $dayUse = date('d/m/Y', strtotime($payment['payment_date']));
                    $confirmed = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($student['enrollment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $billing; ?></td>
                    <td><?php echo $dailyLabel; ?></td>
                    <td><?php echo $dayUse; ?></td>
                    <td><?php echo $confirmed; ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo htmlspecialchars($payment['access_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-charges" class="hidden">
        <h2>Cobrança</h2>
        <p class="muted">Selecione alunos e informe os dados do responsável para enviar as cobranças em massa.</p>

        <div class="form-group">
          <label>Aluno</label>
          <input id="charge-student" list="students-list" placeholder="Digite o nome do aluno" autocomplete="off" />
          <datalist id="students-list"></datalist>
        </div>

        <div id="charge-list" class="charge-list"></div>

        <button class="btn btn-primary" id="send-charges" type="button">Enviar cobranças</button>
        <div id="charge-message" class="charge-message"></div>
      </section>

      <section id="tab-inadimplentes" class="hidden">
        <h2>Inadimplentes</h2>
        <p class="muted">Cobranças pendentes de alunos que frequentaram sem pagamento.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Criado em</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($manualPending)): ?>
                <tr>
                  <td colspan="6">Nenhuma cobrança pendente.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($manualPending as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $created = $payment['created_at'] ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $created; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-recebidas" class="hidden">
        <h2>Cobranças recebidas</h2>
        <p class="muted">Cobranças pagas e regularizadas.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>Datas do day-use</th>
                <th>Valor</th>
                <th>Recebido em</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($manualPaid)): ?>
                <tr>
                  <td colspan="6">Nenhuma cobrança recebida.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($manualPaid as $payment): ?>
                  <?php
                    $student = $payment['students'] ?? [];
                    $guardian = $payment['guardians'] ?? [];
                    $amount = number_format((float) $payment['amount'], 2, ',', '.');
                    $paidAt = $payment['paid_at'] ? date('d/m/Y H:i', strtotime($payment['paid_at'])) : '-';
                    $dailyParts = explode('|', $payment['daily_type'] ?? '', 2);
                    $datesLabel = $dailyParts[1] ?? date('d/m/Y', strtotime($payment['payment_date']));
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($datesLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>R$ <?php echo $amount; ?></td>
                    <td><?php echo $paidAt; ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-sem-whatsapp" class="hidden">
        <h2>Responsáveis sem WhatsApp</h2>
        <p class="muted">Lista de responsáveis sem celular cadastrado.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>E-mail</th>
                <th>CPF/CNPJ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($missingWhatsapp)): ?>
                <tr>
                  <td colspan="4">Nenhum responsável pendente.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($missingWhatsapp as $guardian): ?>
                  <?php $student = $guardian['students'] ?? []; ?>
                  <tr>
                    <td><?php echo htmlspecialchars($student['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($guardian['parent_document'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-pendencias" class="hidden">
        <h2>Pendências de cadastro</h2>
        <p class="muted">Solicitações registradas para ajuste manual no cadastro.</p>
        <div id="pendencia-message" class="charge-message"></div>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Aluno</th>
                <th>Responsável</th>
                <th>CPF</th>
                <th>E-mail</th>
                <th>Registrado em</th>
                <th>Pago em</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($pendencias)): ?>
                <tr>
                  <td colspan="7">Nenhuma pendência registrada.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($pendencias as $pendencia): ?>
                  <?php
                    $created = $pendencia['created_at'] ? date('d/m/Y H:i', strtotime($pendencia['created_at'])) : '-';
                    $paidAt = $pendencia['paid_at'] ? date('d/m/Y H:i', strtotime($pendencia['paid_at'])) : '-';
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($pendencia['student_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_cpf'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($pendencia['guardian_email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $created; ?></td>
                    <td data-col="paid-at"><?php echo $paidAt; ?></td>
                    <td data-col="action">
                      <?php if (!empty($pendencia['paid_at'])): ?>
                        -
                      <?php else: ?>
                        <button
                          class="btn btn-danger btn-sm js-check-pendencia"
                          type="button"
                          data-id="<?php echo htmlspecialchars($pendencia['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                          Checar de novo
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="tab-duplicados" class="hidden">
        <h2>Alunos duplicados</h2>
        <p class="muted">Mescla automática por nome ou matrícula (mantém o registro mais antigo). Abaixo listamos possíveis duplicados por CPF do responsável.</p>

        <div style="overflow-x:auto;">
          <table class="admin-table">
            <thead>
              <tr style="text-align:left;">
                <th>Critério</th>
                <th>Aluno</th>
                <th>IDs</th>
                <th>Matrículas</th>
                <th>Ativo</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($duplicateGroups) && empty($duplicateEnrollmentGroups)): ?>
                <tr>
                  <td colspan="6">Nenhum duplicado encontrado.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($duplicateGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Nome</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($duplicateEnrollmentGroups as $group): ?>
                  <?php
                    $primary = $group[0];
                    $duplicateIds = array_map(static fn($s) => $s['id'], array_slice($group, 1));
                    $ids = array_map(static fn($s) => $s['id'], $group);
                    $enrollments = array_map(static fn($s) => $s['enrollment'] ?? '-', $group);
                    $actives = array_map(static fn($s) => ($s['active'] ? 'Sim' : 'Não'), $group);
                  ?>
                  <tr>
                    <td>Matrícula</td>
                    <td><?php echo htmlspecialchars($primary['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $actives), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button class="btn btn-primary btn-sm js-merge-duplicates"
                        type="button"
                        data-primary="<?php echo htmlspecialchars($primary['id'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-duplicates="<?php echo htmlspecialchars(json_encode($duplicateIds), ENT_QUOTES, 'UTF-8'); ?>">
                        Mesclar duplicados
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($cpfDuplicateGroups)): ?>
          <div style="margin-top:18px;overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr style="text-align:left;">
                  <th>Possível duplicado (CPF do responsável)</th>
                  <th>CPF</th>
                  <th>IDs</th>
                  <th>Matrículas</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cpfDuplicateGroups as $group): ?>
                  <?php
                    $cpf = $group[0]['parent_document'] ?? '-';
                    $names = array_map(static fn($g) => ($g['students']['name'] ?? '-'), $group);
                    $ids = array_map(static fn($g) => ($g['students']['id'] ?? '-'), $group);
                    $enrollments = array_map(static fn($g) => ($g['students']['enrollment'] ?? '-'), $group);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars(implode(' | ', $names), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($cpf, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $ids), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $enrollments), ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <div class="charge-message" id="merge-message"></div>
      </section>
    </div>

    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>

  <script src="/assets/js/admin-dashboard.js?v=13"></script>
</body>
</html>
