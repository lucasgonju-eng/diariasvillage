<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);
$pendenciaId = trim($payload['id'] ?? '');
$paymentDate = trim($payload['payment_date'] ?? '');

if ($pendenciaId === '') {
    Helpers::json(['ok' => false, 'error' => 'ID inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$pendenciaResult = $client->select(
    'pendencia_de_cadastro',
    'select=id,paid_at,student_name,guardian_name,guardian_cpf,guardian_email&id=eq.' . urlencode($pendenciaId)
);
if (!$pendenciaResult['ok'] || empty($pendenciaResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pendência não encontrada.'], 404);
}

$pendenciaRow = $pendenciaResult['data'][0];
if (!empty($pendenciaRow['paid_at'])) {
    Helpers::json(['ok' => true, 'paid_at' => $pendenciaRow['paid_at']]);
}

$accessCode = Helpers::randomNumericCode(6);
$dayUseDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) ? $paymentDate : date('Y-m-d');

$guardianByCpf = $client->select(
    'guardians',
    'select=student_id&parent_document=eq.' . urlencode($pendenciaRow['guardian_cpf'] ?? '') . '&limit=1'
);
$enrollment = null;
$studentId = null;
if ($guardianByCpf['ok'] && !empty($guardianByCpf['data'])) {
    $studentId = $guardianByCpf['data'][0]['student_id'] ?? null;
    if ($studentId) {
        $studentRes = $client->select('students', 'select=enrollment&id=eq.' . urlencode($studentId));
        if ($studentRes['ok'] && !empty($studentRes['data'])) {
            $enrollment = $studentRes['data'][0]['enrollment'] ?? null;
        }
    }
}

$updatePayload = [
    'paid_at' => date('c'),
    'access_code' => $accessCode,
    'payment_date' => $dayUseDate,
];
if ($studentId) {
    $updatePayload['student_id'] = $studentId;
}
if ($enrollment !== null) {
    $updatePayload['enrollment'] = $enrollment;
}

$update = $client->update('pendencia_de_cadastro', 'id=eq.' . urlencode($pendenciaId), $updatePayload);
if (!$update['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao dar baixa.'], 500);
}

$guardianEmail = $pendenciaRow['guardian_email'] ?? '';
if ($guardianEmail) {
    $studentName = $pendenciaRow['student_name'] ?? 'Aluno';
    $paymentDateFormatted = date('d/m/Y', strtotime($dayUseDate));
    $portalLink = Helpers::baseUrl() ?: 'https://village.einsteinhub.co';

    $template = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pagamento confirmado • Diárias Village</title></head>
<body style="margin:0;padding:0;background:#EEF2F7;">
<table role="presentation" width="100%" style="background:#EEF2F7;padding:24px;">
<tr><td align="center">
<table role="presentation" width="600" style="background:#FFF;border-radius:18px;box-shadow:0 10px 30px rgba(11,16,32,.14);">
<tr><td style="padding:26px 28px;background: linear-gradient(135deg,#163A7A,#0A1B4D);color:#fff;">
<div style="font-weight:800;font-size:14px;">DIÁRIAS VILLAGE</div>
<div style="font-size:13px;opacity:.9;margin-top:4px;">Pagamento confirmado</div>
</td></tr>
<tr><td style="padding:28px;">
<div style="font-size:26px;font-weight:800;">Pagamento confirmado ✅</div>
<div style="margin-top:10px;font-size:15px;line-height:1.65;">
Tudo certo! Recebemos o pagamento da diária e o acesso foi <b>liberado automaticamente</b>.
</div>
<div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
<div style="font-size:16px;font-weight:800;margin-bottom:10px;">Resumo da sua compra</div>
<div style="font-size:14px;line-height:1.7;">
Aluno: <b>{{nome_aluno}}</b><br>
Data da diária: <b>{{data_diaria}}</b><br>
Tipo: <b>Planejada</b><br>
Valor pago: <b>R$ {{valor}}</b><br>
Código de acesso: <b>{{codigo_acesso}}</b>
</div>
</div>
{{extra_dados}}
<div style="margin-top:18px;font-size:15px;">A secretaria já foi avisada automaticamente.</div>
<div style="margin-top:22px;"><a href="{{link_portal}}" style="display:inline-block;background:#D6B25E;color:#0B1020;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:14px;">Acessar Diárias Village</a></div>
</td></tr>
<tr><td style="padding:18px 28px;background:#F3F6FB;border-top:1px solid #E6E9F2;font-size:12px;color:#556070;text-align:center;">Diárias Village • Sistema oficial</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $replace = [
        '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
        '{{data_diaria}}' => $paymentDateFormatted,
        '{{valor}}' => '77,00',
        '{{codigo_acesso}}' => htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8'),
        '{{link_portal}}' => htmlspecialchars($portalLink, ENT_QUOTES, 'UTF-8'),
        '{{extra_dados}}' => '',
    ];
    $mailer = new Mailer();
    $mailer->send($guardianEmail, 'Pagamento confirmado • Diárias Village', strtr($template, $replace));

    $secretaria = App\Env::get('EMAIL_SECRETARIA', '');
    $copia = App\Env::get('EMAIL_COPIA', '');
    if ($secretaria) {
        $replace['{{extra_dados}}'] = '<div style="margin-top:16px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:16px;">
          <div style="font-size:14px;font-weight:800;margin-bottom:8px;">Dados do responsável</div>
          <div style="font-size:13px;line-height:1.6;">CPF: <b>' . htmlspecialchars($pendenciaRow['guardian_cpf'] ?? '', ENT_QUOTES, 'UTF-8') . '</b><br>
          E-mail: <b>' . htmlspecialchars($guardianEmail, ENT_QUOTES, 'UTF-8') . '</b><br>
          Código: <b>' . htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8') . '</b><br>
          Matrícula: <b>' . htmlspecialchars($enrollment ?? '(CPF não vinculado ao aluno)', ENT_QUOTES, 'UTF-8') . '</b></div>
        </div>';
        $mailer->send($secretaria, 'Pagamento confirmado - liberar estudante (pendência)', strtr($template, $replace), $copia ? [$copia] : []);
    }
}

$paidAt = $updatePayload['paid_at'];
Helpers::json(['ok' => true, 'paid_at' => $paidAt]);
