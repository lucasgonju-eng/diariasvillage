<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseAuth;
use App\SupabaseClient;

Helpers::requirePost();
$payload = json_decode(file_get_contents('php://input'), true);

$cpf = trim($payload['cpf'] ?? '');
$password = $payload['password'] ?? '';
$passwordConfirm = $payload['password_confirm'] ?? '';

if ($cpf === '' || $password === '') {
    Helpers::json(['ok' => false, 'error' => 'Preencha CPF e senha.'], 422);
}

if ($password !== $passwordConfirm) {
    Helpers::json(['ok' => false, 'error' => 'As senhas não conferem.'], 422);
}

$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$guardianResult = $client->select(
    'guardians',
    'select=id,email,parent_name,parent_document&parent_document=eq.' . urlencode($cpfDigits) . '&limit=1'
);

if (!$guardianResult['ok'] || empty($guardianResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'CPF não encontrado no cadastro. Entre em contato com a secretaria ou use o formulário de pendência.'], 404);
}

$guardian = $guardianResult['data'][0];
$email = trim($guardian['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Helpers::json(['ok' => false, 'error' => 'E-mail inválido no cadastro. Entre em contato com a secretaria.'], 422);
}

$auth = new SupabaseAuth(new HttpClient());
$signUpResult = $auth->signUp($email, $password, [
    'data' => ['cpf' => $cpfDigits],
    'email_redirect_to' => Helpers::baseUrl() . '/login.php',
]);

if (!$signUpResult['ok']) {
    $errorMsg = $signUpResult['error'] ?? '';
    $data = $signUpResult['data'] ?? [];
    if (is_array($data) && !empty($data['msg'])) {
        $errorMsg = $data['msg'];
    }
    if (stripos($errorMsg, 'already') !== false || stripos($errorMsg, 'registered') !== false) {
        Helpers::json(['ok' => false, 'error' => 'Este CPF já possui cadastro. Use "Já tem cadastro?" para entrar.'], 409);
    }
    Helpers::json(['ok' => false, 'error' => $errorMsg ?: 'Falha ao criar conta. Tente novamente.'], 500);
}

$guardianName = $guardian['parent_name'] ?? 'Responsável';

$template = <<<'HTML'
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Valide seu e-mail • Diárias Village</title>
</head>
<body style="margin:0;padding:0;background:#EEF2F7;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Valide seu e-mail para acessar o Diárias Village.
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
                      DIÁRIAS VILLAGE
                    </div>
                    <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:13px;opacity:.90;margin-top:4px;">
                      Pagamento rápido do Day use Village
                    </div>
                  </td>
                </tr>
              </table>
              <div style="margin-top:14px;display:inline-block;padding:8px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.18);background:rgba(8,22,54,.35);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:12px;color:#EAF0FF;">
                Validação de e-mail
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 10px 28px;">
              <div style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B1020;">
                <div style="font-size:26px;font-weight:800;line-height:1.15;">Valide seu e-mail</div>
                <div style="margin-top:10px;font-size:15px;line-height:1.65;color:#1B2333;">
                  Olá, {{nome}}! Seu cadastro foi criado com sucesso.
                </div>
                <div style="margin-top:20px;background:#F6F8FC;border:1px solid #E6E9F2;border-radius:14px;padding:18px;">
                  <div style="font-size:16px;font-weight:800;margin-bottom:10px;color:#0B1020;">Próximo passo</div>
                  <div style="font-size:14px;line-height:1.7;color:#1B2333;">
                    Você receberá em instantes um e-mail com o link para validar seu cadastro.
                    Confira sua caixa de entrada e a pasta de spam.
                  </div>
                </div>
                <div style="margin-top:22px;font-size:14px;line-height:1.6;color:#556070;">
                  Após validar o e-mail, você poderá fazer login com seu CPF e senha para agendar e pagar as diárias.
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
    '{{nome}}' => htmlspecialchars($guardianName, ENT_QUOTES, 'UTF-8'),
]);

$mailer = new Mailer();
$mailer->send(
    $email,
    'Valide seu e-mail • Diárias Village',
    $html
);

Helpers::json(['ok' => true]);
