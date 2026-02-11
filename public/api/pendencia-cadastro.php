<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$studentName = trim($payload['student_name'] ?? '');
$guardianName = trim($payload['guardian_name'] ?? '');
$guardianCpf = trim($payload['guardian_cpf'] ?? '');
$guardianEmail = trim($payload['guardian_email'] ?? '');

if ($studentName === '' || $guardianName === '' || $guardianCpf === '' || $guardianEmail === '') {
    Helpers::json(['ok' => false, 'error' => 'Preencha nome do aluno, responsavel, CPF e e-mail.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $guardianCpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF invalido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$insert = $client->insert('pendencia_de_cadastro', [[
    'student_name' => $studentName,
    'guardian_name' => $guardianName,
    'guardian_cpf' => $cpfDigits,
    'guardian_email' => $guardianEmail ?: null,
]]);

if (!$insert['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar pendencia.'], 500);
}

$pendenciaId = $insert['data'][0]['id'] ?? null;
if (!$pendenciaId) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao registrar pendencia.'], 500);
}

$token = bin2hex(random_bytes(16));
$expiresAt = date('c', strtotime('+24 hours'));
$client->insert('pendencia_tokens', [[
    'pendencia_id' => $pendenciaId,
    'token' => $token,
    'expires_at' => $expiresAt,
]]);

$verifyLink = Helpers::baseUrl() . '/pendencia-verify.php?token=' . $token;

$template = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Confirme seu e-mail • Diárias Village</title>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Confirme seu e-mail para garantir a diária planejada.
  </div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#EEF2F7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
               style="width:600px;max-width:600px;background:#FFFFFF;border-radius:18px;overflow:hidden; box-shadow:0 10px 30px rgba(11,16,32,.14);">
          <tr>
            <td style="padding:26px 28px;background: radial-gradient(1100px 380px at 25% 0%, #163A7A 0%, #0A1B4D 40%, #081636 100%); color:#FFFFFF;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td valign="middle" style="padding-right:12px;">
                    <span style="display:inline-block;width:34px;height:34px;border-radius:12px;background:#D6B25E;"></span>
                  </td>
                  <td valign="middle">
                    <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-weight:800;letter-spacing:.06em;font-size:14px;line-height:1;">
                      DIARIAS VILLAGE
                    </div>
                    <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;opacity:.90;margin-top:4px;">
                      Pagamento rápido do Day use Village
                    </div>
                  </td>
                </tr>
              </table>
              <div style="margin-top:14px;display:inline-block;padding:8px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.18);background:rgba(8,22,54,.35);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#EAF0FF;">
                Confirmação de e-mail
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">Confirme seu e-mail</div>
                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Para garantir a diária planejada, confirme seu e-mail clicando no botão abaixo.
                </div>
                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">Resumo do cadastro</div>
                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Aluno: <b>{{nome_aluno}}</b><br>
                    Responsável: <b>{{nome_responsavel}}</b>
                  </div>
                </div>
                <div style="margin-top:22px;">
                  <a href="{{link_verificacao}}" style="display:inline-block;background:#D6B25E;color:#0B1020;text-decoration:none;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-weight:800;padding:12px 16px;border-radius:14px;">
                    Confirmar e-mail
                  </a>
                  <div style="margin-top:10px;font-size:12px;line-height:1.5;color:#556070;">
                    Se o botão não funcionar, copie e cole este link no navegador:<br>
                    <span style="color:#0A1B4D;">{{link_verificacao}}</span>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;background:#F3F6FB;border-top:1px solid #E6E9F2;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:12px;line-height:1.5;color:#556070;text-align:center;">
                Diárias Village • Sistema oficial de pagamento e controle de acesso<br>
                Em caso de dúvidas, entre em contato com a secretaria.
              </div>
            </td>
          </tr>
        </table>
        <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:11px;color:#8A94A6;margin-top:10px;text-align:center;">
          © Diárias Village
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$html = strtr($template, [
    '{{nome_aluno}}' => htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'),
    '{{nome_responsavel}}' => htmlspecialchars($guardianName, ENT_QUOTES, 'UTF-8'),
    '{{link_verificacao}}' => htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8'),
]);

$mailer = new Mailer();
$mailer->send(
    $guardianEmail,
    'Confirme seu e-mail • Diárias Village',
    $html
);

Helpers::json(['ok' => true]);
