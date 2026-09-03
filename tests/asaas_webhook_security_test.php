<?php

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\Services\AsaasWebhookInbox;
use App\SupabaseClient;

$failures = [];

function check_webhook(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class FakeWebhookSupabaseClient extends SupabaseClient
{
    public array $calls = [];
    public array $responses = [];

    public function __construct()
    {
    }

    public function rpc(string $functionName, array $payload = []): array
    {
        $this->calls[] = ['function' => $functionName, 'payload' => $payload];
        return array_shift($this->responses) ?? ['ok' => false, 'data' => null];
    }
}

$fake = new FakeWebhookSupabaseClient();
$fake->responses[] = [
    'ok' => true,
    'data' => ['ok' => true, 'claimed' => true, 'attempt_count' => 1],
];
$raw = '{"id":"evt_abc123","event":"PAYMENT_RECEIVED","payment":{"id":"pay_abc123"}}';
$inbox = new AsaasWebhookInbox($fake);
$claim = $inbox->claim(
    'evt_abc123',
    'PAYMENT_RECEIVED',
    'pay_abc123',
    json_decode($raw, true),
    $raw
);

check_webhook(($claim['claimed'] ?? false) === true, 'caixa deve adquirir evento novo');
check_webhook(
    ($fake->calls[0]['payload']['p_payload_sha256'] ?? '') === hash('sha256', $raw),
    'caixa deve persistir hash SHA-256 do payload recebido'
);

$fake->responses[] = ['ok' => true, 'data' => ['ok' => true, 'updated' => true]];
check_webhook($inbox->complete('evt_abc123'), 'caixa deve concluir evento adquirido');

$fake->responses[] = ['ok' => true, 'data' => ['ok' => true, 'updated' => true]];
check_webhook($inbox->fail('evt_abc123', 'falha controlada'), 'caixa deve registrar falha controlada');

$endpoint = file_get_contents(dirname(__DIR__) . '/public/api/asaas-webhook.php');
$httpClient = file_get_contents(dirname(__DIR__) . '/src/HttpClient.php');
$mailer = file_get_contents(dirname(__DIR__) . '/src/Mailer.php');
$migration = file_get_contents(
    dirname(__DIR__) . '/supabase/migrations/20260903010136_secure_asaas_webhook_inbox.sql'
);
$blockedMigration = file_get_contents(
    dirname(__DIR__) . '/supabase/migrations/20260903010831_add_blocked_asaas_webhook_status.sql'
);
$terminalMigration = file_get_contents(
    dirname(__DIR__) . '/supabase/migrations/20260903010926_keep_blocked_webhooks_terminal.sql'
);

check_webhook(
    str_contains($endpoint, "\$expected === ''") && str_contains($endpoint, 'Webhook indisponível.'),
    'webhook deve falhar fechado quando o segredo não estiver configurado'
);
check_webhook(
    str_contains($endpoint, 'hash_equals($expected, $token)'),
    'webhook deve comparar o token em tempo constante'
);
check_webhook(
    str_contains($endpoint, "HTTP_ASAAS_ACCESS_TOKEN")
        && !str_contains($endpoint, "HTTP_AUTHORIZATION")
        && !str_contains($endpoint, "HTTP_X_WEBHOOK_TOKEN"),
    'webhook deve aceitar somente o cabeçalho oficial asaas-access-token'
);
check_webhook(
    str_contains($endpoint, '$payload[\'id\']')
        && str_contains($endpoint, '$inbox->claim(')
        && str_contains($endpoint, '$completeWebhook('),
    'webhook deve usar o ID do evento para idempotência'
);
check_webhook(
    str_contains($endpoint, "(\$claim['status'] ?? '') === 'PROCESSING'")
        && str_contains($endpoint, 'Evento ainda em processamento.'),
    'webhook não deve confirmar duplicata enquanto a primeira entrega ainda processa'
);
check_webhook(
    str_contains($endpoint, "\$inbox->complete(\$eventId, 'BLOCKED')")
        && str_contains($endpoint, "['ok' => true, 'blocked' => true]"),
    'conflito permanente deve ser registrado sem pausar a fila com tentativas inúteis'
);
check_webhook(
    str_contains($endpoint, 'getPayment($paymentId)')
        && str_contains($endpoint, "['CONFIRMED', 'RECEIVED']")
        && str_contains($endpoint, 'matchesRemoteCustomer('),
    'webhook deve validar pagamento e identidade diretamente no Asaas'
);
check_webhook(
    !str_contains($endpoint, 'error_log_custom.txt')
        && !str_contains($endpoint, 'file_put_contents('),
    'webhook não deve gravar payload ou token em arquivo público local'
);
check_webhook(
    !str_contains($endpoint, 'asaas_invoice_url=eq.')
        && !str_contains($endpoint, 'parent_document=eq.'),
    'webhook não deve conciliar pendência por URL ou CPF isolado'
);
check_webhook(
    str_contains($httpClient, 'CURLOPT_CONNECTTIMEOUT')
        && str_contains($httpClient, 'CURLOPT_TIMEOUT')
        && str_contains($mailer, '$mail->Timeout = 20;'),
    'chamadas HTTP e SMTP do webhook devem possuir timeouts explícitos'
);
check_webhook(
    str_contains($migration, 'create table if not exists public.asaas_webhook_events')
        && str_contains($migration, 'event_id text primary key')
        && str_contains($migration, 'claim_asaas_webhook_event')
        && str_contains($migration, 'complete_asaas_webhook_event')
        && str_contains($migration, 'fail_asaas_webhook_event'),
    'migration deve criar caixa e ciclo idempotente do evento'
);
check_webhook(
    str_contains($blockedMigration, "'BLOCKED'")
        && str_contains($terminalMigration, "'PROCESSED', 'IGNORED', 'BLOCKED'"),
    'migrations devem tornar eventos bloqueados terminais'
);
check_webhook(
    str_contains($migration, 'enable row level security')
        && str_contains($migration, 'from public, anon, authenticated')
        && str_contains($migration, 'to service_role'),
    'caixa do webhook deve ser service-only com RLS'
);

if ($failures) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: autenticação, idempotência e validação remota do webhook Asaas.\n";
