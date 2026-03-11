<?php
$bootstrapCandidates = [
    __DIR__ . '/../src/Bootstrap.php',
    dirname(__DIR__, 2) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\MonthlyStudents;
use App\SupabaseClient;

function ensureAdminPrincipal(): void
{
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
    }
    if (($_SESSION['admin_user'] ?? '') !== 'admin') {
        Helpers::json(['ok' => false, 'error' => 'Apenas admin principal pode usar este recurso.'], 403);
    }
}

function defaultTemplate(): array
{
    $subject = 'Diárias Village — atualização importante';
    $baseUrl = rtrim(Helpers::baseUrl(), '/');
    $mascotUrl = ($baseUrl !== '' ? $baseUrl : 'https://diarias.village.einsteinhub.co') . '/assets/img/mascote-village.png';
    $html = '<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <title>Diárias Village — atualização importante</title>
</head>
<body style="margin:0; padding:0; background:#F6F8FC;">
  <div style="display:none; font-size:1px; line-height:1px; max-height:0px; max-width:0px; opacity:0; overflow:hidden;">
    ipsis lorem.
  </div>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F6F8FC; padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
          style="width:100%; max-width:600px; background:#FFFFFF; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.06);">
          <tr>
            <td style="padding:22px 22px 16px; background:linear-gradient(135deg,#0A2A6A 0%, #0E4AA8 55%, #FFC700 130%);">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="left" style="vertical-align:middle;">
                    <div style="font-family:Arial, Helvetica, sans-serif; color:#FFFFFF; font-size:18px; font-weight:700; letter-spacing:0.2px;">
                      Diárias Village
                    </div>
                    <div style="font-family:Arial, Helvetica, sans-serif; color:#EAF1FF; font-size:13px; line-height:1.4; margin-top:4px;">
                      Mais praticidade, com a segurança em primeiro lugar.
                    </div>
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <span style="display:inline-block; font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:700; color:#0A2A6A; background:#FFC700; padding:8px 10px; border-radius:999px;">
                      IMPORTANTE
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 22px 8px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="vertical-align:top; padding-right:14px;">
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:800; color:#0A2A6A; line-height:1.25;">
                      Olá, {{NOME}}! 😊
                    </div>
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#2A2F3A; line-height:1.7; margin-top:10px;">
                      ipsis lorem. Ipsis lorem dolor sit amet, consectetur adipiscing elit. Ipsis lorem sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                    </div>
                  </td>
                  <td align="right" style="vertical-align:top; width:160px;">
                    <img src="' . htmlspecialchars($mascotUrl, ENT_QUOTES, 'UTF-8') . '" width="150" alt="Mascote Village" style="display:block; width:150px; max-width:150px; height:auto; border:0; border-radius:14px;">
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:10px 22px 0;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F2F6FF; border:1px solid #DCE6FF; border-radius:16px;">
                <tr>
                  <td style="padding:16px 16px 14px;">
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0A2A6A; font-weight:800;">
                      ✅ ipsis lorem
                    </div>
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:13.5px; color:#2A2F3A; line-height:1.75; margin-top:8px;">
                      ipsis lorem. Ipsis lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore.
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 22px 0;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#FFF8E1; border:1px solid #FFE08A; border-radius:16px;">
                <tr>
                  <td style="padding:16px 16px 14px;">
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#8A5A00; font-weight:900;">
                      🌟 ipsis lorem
                    </div>
                    <div style="font-family:Arial, Helvetica, sans-serif; font-size:13.5px; color:#2A2F3A; line-height:1.75; margin-top:8px;">
                      ipsis lorem. Ipsis lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 22px 0;">
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#0A2A6A; font-weight:900;">
                Pontos importantes (por favor, leia com atenção)
              </div>
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:13.5px; color:#2A2F3A; line-height:1.75; margin-top:10px;">
                <ul style="margin:0; padding-left:18px;">
                  <li style="margin:0 0 10px 0;">ipsis lorem dolor sit amet, consectetur adipiscing elit.</li>
                  <li style="margin:0 0 10px 0;">ipsis lorem sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</li>
                  <li style="margin:0 0 0 0;">ipsis lorem ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</li>
                </ul>
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 22px 6px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="padding:10px 0 6px;">
                    <a href="{{LINK_PAGAMENTO}}"
                       style="display:inline-block; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:900; text-decoration:none; background:#FFC700; color:#0A2A6A; padding:12px 18px; border-radius:12px; border:1px solid #E6B800;">
                      Acessar o Diárias Village e regularizar
                    </a>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding:6px 0 0;">
                    <a href="{{LINK_SUPORTE}}"
                       style="display:inline-block; font-family:Arial, Helvetica, sans-serif; font-size:13px; font-weight:700; text-decoration:none; color:#0E4AA8; padding:10px 12px;">
                      Preciso de ajuda / enviar comprovante
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:0 22px;">
              <div style="height:1px; background:#E9EEF6;"></div>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 22px 18px;">
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:13.5px; color:#2A2F3A; line-height:1.75;">
                ipsis lorem. Ipsis lorem ipsum dolor sit amet, consectetur adipiscing elit.
              </div>
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:13.5px; color:#2A2F3A; line-height:1.75; margin-top:10px;">
                Com carinho e à disposição,<br>
                <strong>Equipe Diárias Village</strong>
              </div>
            </td>
          </tr>

          <tr>
            <td style="background:#0A2A6A; padding:14px 22px;">
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#CFE0FF; line-height:1.6;">
                ipsis lorem.
              </div>
              <div style="font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#CFE0FF; line-height:1.6; margin-top:8px;">
                © Diárias Village
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

    return [
        'id' => 'default-original',
        'name' => 'Template original sugerido',
        'subject' => $subject,
        'html' => $html,
        'is_default' => true,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];
}

function templatesFilePath(): string
{
    return dirname(__DIR__, 2) . '/storage/admin_bulk_email_templates.json';
}

function loadTemplates(): array
{
    $path = templatesFilePath();
    $default = defaultTemplate();
    if (!is_file($path)) {
        return [$default];
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [$default];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [$default];
    }

    $templates = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $subject = (string) ($row['subject'] ?? '');
        $html = (string) ($row['html'] ?? '');
        if ($id === '' || $name === '' || $subject === '' || $html === '') {
            continue;
        }
        $templates[] = [
            'id' => $id,
            'name' => $name,
            'subject' => $subject,
            'html' => $html,
            'is_default' => false,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return array_merge([$default], $templates);
}

function persistTemplates(array $templates): bool
{
    $path = templatesFilePath();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $rowsToSave = array_values(array_filter(array_map(static function (array $row): ?array {
        if (($row['id'] ?? '') === 'default-original') {
            return null;
        }
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'html' => (string) ($row['html'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }, $templates)));

    return @file_put_contents(
        $path,
        json_encode($rowsToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function replacePlaceholders(string $text, array $context): string
{
    $studentName = (string) ($context['student_name'] ?? '');
    return strtr($text, [
        '{{NOME_ALUNO}}' => (string) ($context['student_name'] ?? ''),
        '{{NOME}}' => $studentName !== '' ? $studentName : (string) ($context['guardian_name'] ?? 'Responsável'),
        '{{MATRICULA}}' => (string) ($context['enrollment'] ?? ''),
        '{{NOME_RESPONSAVEL}}' => (string) ($context['guardian_name'] ?? 'Responsável'),
        '{{LINK_PAGAMENTO}}' => (string) ($context['link_pagamento'] ?? '#'),
        '{{LINK_SUPORTE}}' => (string) ($context['link_suporte'] ?? '#'),
        '{{URL_MASCOTE}}' => (string) ($context['url_mascote'] ?? ''),
    ]);
}

ensureAdminPrincipal();
Helpers::requirePost();

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $action = trim((string) ($payload['action'] ?? 'init'));
    $client = new SupabaseClient(new HttpClient());

if ($action === 'init') {
    $studentsQueries = [
        'select=id,name,enrollment,active&order=name.asc&limit=10000',
        'select=id,name,enrollment&order=name.asc&limit=10000',
        'select=id,name,enrollment,grade,class_name&order=name.asc&limit=10000',
    ];
    $studentsResult = null;
    foreach ($studentsQueries as $query) {
        $attempt = $client->select('students', $query);
        if ($attempt['ok'] ?? false) {
            $studentsResult = $attempt;
            break;
        }
    }
    if (!is_array($studentsResult) || !($studentsResult['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao carregar alunos no banco.'], 500);
    }
    $studentsRows = is_array($studentsResult['data'] ?? null) ? $studentsResult['data'] : [];

    $guardiansResult = $client->select(
        'guardians',
        'select=student_id,parent_name,email&limit=8000'
    );
    if (!($guardiansResult['ok'] ?? false)) {
        $guardiansResult = $client->select(
            'guardians',
            'select=student_id,parent_name,email&limit=4000'
        );
    }
    $guardiansRows = is_array($guardiansResult['data'] ?? null) ? $guardiansResult['data'] : [];

    // Consultas leves para não travar o carregamento do Admin.
    $paidPaymentsResult = $client->select(
        'payments',
        'select=student_id,status&status=eq.paid&limit=5000'
    );
    if (!($paidPaymentsResult['ok'] ?? false)) {
        $paidPaymentsResult = $client->select('payments', 'select=student_id,payment_date&paid_at=not.is.null&limit=5000');
    }
    $paidPaymentsRows = is_array($paidPaymentsResult['data'] ?? null) ? $paidPaymentsResult['data'] : [];

    $openPaymentsResult = $client->select(
        'payments',
        'select=student_id,status,paid_at&paid_at=is.null&status=in.(pending,overdue,awaiting_risk_analysis,queued)&limit=5000'
    );
    if (!($openPaymentsResult['ok'] ?? false)) {
        $openPaymentsResult = $client->select('payments', 'select=student_id,status,paid_at&paid_at=is.null&limit=5000');
    }
    $openPaymentsRows = is_array($openPaymentsResult['data'] ?? null) ? $openPaymentsResult['data'] : [];

    $monthlyRows = MonthlyStudents::load();
    $monthlyByStudentId = MonthlyStudents::mapByStudentId($monthlyRows);

    $diaristaStudentIds = [];
    foreach ($paidPaymentsRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $studentId = trim((string) ($row['student_id'] ?? ''));
        if ($studentId !== '') {
            $diaristaStudentIds[$studentId] = true;
        }
    }
    $inadimplenteStudentIds = [];
    foreach ($openPaymentsRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if (in_array($status, ['paid', 'canceled', 'refunded', 'deleted'], true)) {
            continue;
        }
        $studentId = trim((string) ($row['student_id'] ?? ''));
        if ($studentId !== '') {
            $inadimplenteStudentIds[$studentId] = true;
        }
    }

    $emailsByStudentId = [];
    $guardianNamesByStudentId = [];
    foreach ($guardiansRows as $guardian) {
        if (!is_array($guardian)) {
            continue;
        }
        $studentId = trim((string) ($guardian['student_id'] ?? ''));
        if ($studentId === '') {
            continue;
        }
        $email = trim((string) ($guardian['email'] ?? ''));
        $name = trim((string) ($guardian['parent_name'] ?? 'Responsável'));
        if ($name !== '') {
            $guardianNamesByStudentId[$studentId][$name] = true;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailsByStudentId[$studentId][strtolower($email)] = $email;
        }
    }

    $students = [];
    foreach ($studentsRows as $student) {
        if (!is_array($student)) {
            continue;
        }
        $id = trim((string) ($student['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $emails = array_values($emailsByStudentId[$id] ?? []);
        $guardianNames = array_values(array_keys($guardianNamesByStudentId[$id] ?? []));
        $students[] = [
            'id' => $id,
            'name' => trim((string) ($student['name'] ?? '')),
            'enrollment' => trim((string) ($student['enrollment'] ?? '')),
            'is_diarista' => isset($diaristaStudentIds[$id]),
            'is_mensalista' => isset($monthlyByStudentId[$id]),
            'is_inadimplente' => isset($inadimplenteStudentIds[$id]),
            'emails' => $emails,
            'guardian_names' => $guardianNames,
        ];
    }

    Helpers::json([
        'ok' => true,
        'students' => $students,
        'templates' => loadTemplates(),
        'suggested_template_id' => 'default-original',
    ]);
}

if ($action === 'save_template') {
    $name = trim((string) ($payload['name'] ?? ''));
    $subject = trim((string) ($payload['subject'] ?? ''));
    $html = trim((string) ($payload['html'] ?? ''));

    if ($name === '' || $subject === '' || $html === '') {
        Helpers::json(['ok' => false, 'error' => 'Informe nome, assunto e HTML para salvar template.'], 422);
    }

    $templates = loadTemplates();
    $templateId = 'tpl-' . bin2hex(random_bytes(6));
    $templates[] = [
        'id' => $templateId,
        'name' => $name,
        'subject' => $subject,
        'html' => $html,
        'is_default' => false,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ];

    if (!persistTemplates($templates)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao salvar template no servidor.'], 500);
    }

    Helpers::json(['ok' => true, 'templates' => loadTemplates(), 'saved_template_id' => $templateId]);
}

if ($action === 'send') {
    $studentIds = $payload['student_ids'] ?? [];
    $subject = trim((string) ($payload['subject'] ?? ''));
    $html = trim((string) ($payload['html'] ?? ''));

    if (!is_array($studentIds) || empty($studentIds)) {
        Helpers::json(['ok' => false, 'error' => 'Selecione ao menos um aluno.'], 422);
    }
    if ($subject === '' || $html === '') {
        Helpers::json(['ok' => false, 'error' => 'Assunto e HTML são obrigatórios para envio.'], 422);
    }

    $studentIds = array_values(array_unique(array_filter(array_map(static fn($id) => trim((string) $id), $studentIds))));
    if (empty($studentIds)) {
        Helpers::json(['ok' => false, 'error' => 'IDs de alunos inválidos.'], 422);
    }

    $mailer = new Mailer();
    $baseUrl = rtrim(Helpers::baseUrl(), '/');
    if ($baseUrl === '') {
        $baseUrl = 'https://diarias.village.einsteinhub.co';
    }
    $results = [];

    foreach ($studentIds as $studentId) {
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment&id=eq.' . urlencode($studentId) . '&limit=1'
        );
        $student = $studentResult['data'][0] ?? null;
        if (!is_array($student)) {
            $results[] = ['student_id' => $studentId, 'ok' => false, 'error' => 'Aluno não encontrado.'];
            continue;
        }

        $guardianResult = $client->select(
            'guardians',
            'select=parent_name,email&student_id=eq.' . urlencode($studentId) . '&limit=100'
        );
        $guardians = is_array($guardianResult['data'] ?? null) ? $guardianResult['data'] : [];
        $validGuardians = array_values(array_filter($guardians, static function ($g): bool {
            if (!is_array($g)) {
                return false;
            }
            $email = trim((string) ($g['email'] ?? ''));
            return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        }));

        if (empty($validGuardians)) {
            $results[] = [
                'student_id' => $studentId,
                'student_name' => (string) ($student['name'] ?? ''),
                'ok' => false,
                'error' => 'Aluno sem e-mail válido de responsável.',
            ];
            continue;
        }

        $sentForStudent = 0;
        $failedForStudent = 0;
        $sentEmails = [];
        foreach ($validGuardians as $guardian) {
            $email = trim((string) ($guardian['email'] ?? ''));
            $emailKey = strtolower($email);
            if (isset($sentEmails[$emailKey])) {
                continue;
            }

            $context = [
                'student_name' => (string) ($student['name'] ?? ''),
                'enrollment' => (string) ($student['enrollment'] ?? ''),
                'guardian_name' => (string) ($guardian['parent_name'] ?? 'Responsável'),
                'link_pagamento' => $baseUrl . '/login.php',
                'link_suporte' => $baseUrl . '/login.php',
                'url_mascote' => $baseUrl . '/assets/img/mascote-village.png',
            ];
            $resolvedSubject = replacePlaceholders($subject, $context);
            $resolvedHtml = replacePlaceholders($html, $context);

            $mailResult = $mailer->send($email, $resolvedSubject, $resolvedHtml);
            if ($mailResult['ok'] ?? false) {
                $sentForStudent++;
                $sentEmails[$emailKey] = true;
            } else {
                $failedForStudent++;
            }
        }

        $results[] = [
            'student_id' => $studentId,
            'student_name' => (string) ($student['name'] ?? ''),
            'ok' => $sentForStudent > 0 && $failedForStudent === 0,
            'sent' => $sentForStudent,
            'failed' => $failedForStudent,
            'error' => ($sentForStudent === 0)
                ? 'Falha ao enviar para os responsáveis deste aluno.'
                : null,
        ];
    }

    $sentStudents = 0;
    $failedStudents = 0;
    $sentEmailsTotal = 0;
    foreach ($results as $row) {
        $sentEmailsTotal += (int) ($row['sent'] ?? 0);
        if ($row['ok'] ?? false) {
            $sentStudents++;
        } else {
            $failedStudents++;
        }
    }

    Helpers::json([
        'ok' => $failedStudents === 0,
        'results' => $results,
        'summary' => [
            'sent_students' => $sentStudents,
            'failed_students' => $failedStudents,
            'sent_emails' => $sentEmailsTotal,
        ],
        'error' => $failedStudents > 0 ? 'Parte dos envios falhou.' : null,
    ]);
}

Helpers::json(['ok' => false, 'error' => 'Ação inválida.'], 422);
} catch (\Throwable $e) {
    error_log('admin-bulk-email.php fatal: ' . $e->getMessage());
    Helpers::json([
        'ok' => false,
        'error' => 'Falha interna ao processar e-mails em massa.',
        'debug' => (string) $e->getMessage(),
    ], 500);
}
