<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Helpers;
use App\HttpClient;
use App\Mailer;
use App\SupabaseClient;

$token = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
$expected = App\Env::get('ASAAS_WEBHOOK_TOKEN', '');

if ($expected && $token !== $expected) {
    Helpers::json(['ok' => false, 'error' => 'Token invalido.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
$event = $payload['event'] ?? '';
$payment = $payload['payment'] ?? [];

if (!$event || empty($payment['id'])) {
    Helpers::json(['ok' => false, 'error' => 'Payload invalido.'], 400);
}

if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
    Helpers::json(['ok' => true]);
}

$client = new SupabaseClient(new HttpClient());
$paymentResult = $client->select('payments', 'select=*&asaas_payment_id=eq.' . urlencode($payment['id']));

if (!$paymentResult['ok'] || empty($paymentResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'Pagamento nao encontrado.'], 404);
}

$paymentRow = $paymentResult['data'][0];

$accessCode = $paymentRow['access_code'] ?: Helpers::randomNumericCode(6);
$client->update('payments', 'id=eq.' . $paymentRow['id'], [
    'status' => 'paid',
    'paid_at' => date('c'),
    'access_code' => $accessCode,
]);

$studentResult = $client->select('students', 'select=name,enrollment&' . 'id=eq.' . $paymentRow['student_id']);
$student = $studentResult['data'][0] ?? null;

$guardianResult = $client->select('guardians', 'select=*&id=eq.' . $paymentRow['guardian_id']);
$guardian = $guardianResult['data'][0] ?? null;

if ($guardian) {
    $studentName = $student['name'] ?? 'Aluno';
    $enrollment = $student['enrollment'] ?? '-';
    $amount = number_format((float) $paymentRow['amount'], 2, ',', '.');
    $paymentDate = date('d/m/Y', strtotime($paymentRow['payment_date']));
    $dailyLabel = $paymentRow['daily_type'] === 'emergencial' ? 'Emergencial' : 'Planejada';

    $html = '<p>Pagamento confirmado!</p>'
        . '<p><strong>Aluno:</strong> ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Matricula:</strong> ' . htmlspecialchars($enrollment, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Diaria:</strong> ' . $dailyLabel . ' - ' . $paymentDate . '</p>'
        . '<p><strong>Valor:</strong> R$ ' . $amount . '</p>'
        . '<p><strong>Codigo de acesso:</strong> ' . $accessCode . '</p>'
        . '<p>Bem-vindo ao Day-Use do Einstein Village! '
        . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . ' (matricula '
        . htmlspecialchars($enrollment, ENT_QUOTES, 'UTF-8')
        . ') pode se dirigir ao Einstein Village e ser recebido por nossa equipe.</p>'
        . '<p>Ja conhece as atividades de hoje? Em breve enviaremos o PDF com a programacao.</p>'
        . '<p>Obrigado por escolher o Einstein Village.</p>';

    $mailer = new Mailer();
    $mailer->send(
        $guardian['email'],
        'Pagamento confirmado - Diarias Village',
        $html
    );

    $secretaria = App\Env::get('EMAIL_SECRETARIA', '');
    $copia = App\Env::get('EMAIL_COPIA', '');

    if ($secretaria) {
        $mailer->send(
            $secretaria,
            'Pagamento confirmado - liberar estudante',
            $html,
            $copia ? [$copia] : []
        );
    }
}

Helpers::json(['ok' => true]);
