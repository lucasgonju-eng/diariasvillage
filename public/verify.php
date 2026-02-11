<?php
require_once __DIR__ . '/src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$token = $_GET['token'] ?? '';

$client = new SupabaseClient(new HttpClient());
$result = $client->select('verification_tokens', 'token=eq.' . urlencode($token) . '&select=*');

if (!$result['ok'] || empty($result['data'])) {
    $status = 'Token inválido.';
} else {
    $record = $result['data'][0];
    $expiresAt = strtotime($record['expires_at']);

    if ($expiresAt < time()) {
        $status = 'Token expirado.';
    } else {
        $client->update('guardians', 'id=eq.' . $record['guardian_id'], ['verified_at' => date('c')]);
        $client->update('verification_tokens', 'id=eq.' . $record['id'], ['expires_at' => date('c')]);
        $status = 'E-mail confirmado! Agora voce pode entrar.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Confirmação</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diárias Village</div>
    </header>
    <div class="card">
      <h2><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></h2>
      <a class="button" href="/login.php">Ir para login</a>
    </div>
    <div class="footer">Desenvolvido por Lucas Gonçalves Junior - 2026</div>
  </div>
</body>
</html>
