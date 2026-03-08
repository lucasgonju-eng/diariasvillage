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
use App\SupabaseClient;

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    Helpers::json(['ok' => false, 'error' => 'Não autorizado.'], 401);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$client = new SupabaseClient(new HttpClient());

$normalizeSortName = static function (string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    if (function_exists('mb_strtoupper')) {
        $name = mb_strtoupper($name, 'UTF-8');
    } else {
        $name = strtoupper($name);
    }
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    if ($translit !== false) {
        $name = $translit;
    }
    $name = preg_replace('/[^A-Z0-9 ]+/', '', $name) ?? '';
    return trim($name);
};

$normalizeTime = static function (string $raw): string {
    $value = trim($raw);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^\d{4}$/', $value)) {
        $value = substr($value, 0, 2) . ':' . substr($value, 2, 2);
    }
    if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $value)) {
        return substr($value, 0, 5);
    }
    return '';
};

$extractTeacherFromDescription = static function (string $description): string {
    if ($description === '') {
        return '';
    }
    if (preg_match('/^\[PROFESSOR\]\s*(.+)$/mi', $description, $match)) {
        return trim((string) ($match[1] ?? ''));
    }
    return '';
};

$stripTeacherFromDescription = static function (string $description): string {
    if ($description === '') {
        return '';
    }
    $withoutTag = preg_replace('/^\[PROFESSOR\].*(\R|$)/mi', '', $description);
    return trim((string) $withoutTag);
};

$buildDescription = static function (string $teacherName, string $existingDescription = '') use ($stripTeacherFromDescription): string {
    $cleanTeacher = trim($teacherName);
    $baseDescription = $stripTeacherFromDescription($existingDescription);
    if ($cleanTeacher === '') {
        return $baseDescription;
    }
    $tag = '[PROFESSOR] ' . $cleanTeacher;
    if ($baseDescription === '') {
        return $tag;
    }
    return $tag . "\n\n" . $baseDescription;
};

$isAvailableThisMonth = static function (array $office, string $monthStart, string $monthEnd): bool {
    $active = ($office['ativa'] ?? false) === true || (string) ($office['ativa'] ?? '') === 'true';
    if (!$active) {
        return false;
    }
    $tipo = strtoupper(trim((string) ($office['tipo'] ?? '')));
    if ($tipo !== 'OCASIONAL_30D') {
        return true;
    }
    $start = trim((string) ($office['data_inicio_validade'] ?? ''));
    $end = trim((string) ($office['data_fim_validade'] ?? ''));
    if ($start === '' || $end === '') {
        return false;
    }
    return $start <= $monthEnd && $end >= $monthStart;
};

$loadItems = static function () use ($client, $extractTeacherFromDescription, $normalizeSortName, $isAvailableThisMonth): array {
    $officesResult = $client->select(
        'oficina_modular',
        'select=id,nome,codigo,descricao,ativa,status_quorum,tipo,capacidade,data_inicio_validade,data_fim_validade,created_at,updated_at'
        . '&order=nome.asc&limit=1000'
    );
    if (!($officesResult['ok'] ?? false) || !is_array($officesResult['data'] ?? null)) {
        return [];
    }

    $schedulesResult = $client->select(
        'oficina_modular_horarios',
        'select=id,oficina_modular_id,dia_semana,hora_inicio,hora_fim&order=oficina_modular_id.asc,dia_semana.asc,hora_inicio.asc&limit=5000'
    );
    $scheduleRows = is_array($schedulesResult['data'] ?? null) ? $schedulesResult['data'] : [];
    $schedulesByOffice = [];
    foreach ($scheduleRows as $schedule) {
        if (!is_array($schedule)) {
            continue;
        }
        $officeId = trim((string) ($schedule['oficina_modular_id'] ?? ''));
        if ($officeId === '') {
            continue;
        }
        $dayOfWeek = (int) ($schedule['dia_semana'] ?? 0);
        $start = substr((string) ($schedule['hora_inicio'] ?? ''), 0, 5);
        $end = substr((string) ($schedule['hora_fim'] ?? ''), 0, 5);
        if ($dayOfWeek < 1 || $dayOfWeek > 7 || $start === '' || $end === '') {
            continue;
        }
        $schedulesByOffice[$officeId][] = [
            'day_of_week' => $dayOfWeek,
            'start' => $start,
            'end' => $end,
        ];
    }

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $items = [];
    foreach ($officesResult['data'] as $office) {
        if (!is_array($office)) {
            continue;
        }
        $officeId = trim((string) ($office['id'] ?? ''));
        $name = trim((string) ($office['nome'] ?? ''));
        if ($officeId === '' || $name === '') {
            continue;
        }
        $schedules = $schedulesByOffice[$officeId] ?? [];
        if (!empty($schedules)) {
            usort($schedules, static function (array $a, array $b): int {
                if ((int) $a['day_of_week'] !== (int) $b['day_of_week']) {
                    return (int) $a['day_of_week'] <=> (int) $b['day_of_week'];
                }
                return strcmp((string) $a['start'], (string) $b['start']);
            });
        }

        $days = [];
        foreach ($schedules as $schedule) {
            $days[(int) $schedule['day_of_week']] = true;
        }

        $description = trim((string) ($office['descricao'] ?? ''));
        $items[] = [
            'id' => $officeId,
            'name' => $name,
            'code' => trim((string) ($office['codigo'] ?? '')),
            'teacher_name' => $extractTeacherFromDescription($description),
            'description' => $description,
            'active' => ($office['ativa'] ?? false) === true || (string) ($office['ativa'] ?? '') === 'true',
            'status_quorum' => trim((string) ($office['status_quorum'] ?? '')),
            'tipo' => trim((string) ($office['tipo'] ?? '')),
            'capacity' => (int) ($office['capacidade'] ?? 0),
            'validity_start' => trim((string) ($office['data_inicio_validade'] ?? '')),
            'validity_end' => trim((string) ($office['data_fim_validade'] ?? '')),
            'available_this_month' => $isAvailableThisMonth($office, $monthStart, $monthEnd),
            'schedules' => $schedules,
            'days_of_week' => array_map('intval', array_keys($days)),
            'created_at' => trim((string) ($office['created_at'] ?? '')),
            'updated_at' => trim((string) ($office['updated_at'] ?? '')),
        ];
    }

    usort($items, static function (array $a, array $b) use ($normalizeSortName): int {
        return strcmp($normalizeSortName((string) ($a['name'] ?? '')), $normalizeSortName((string) ($b['name'] ?? '')));
    });

    return $items;
};

if ($method === 'GET') {
    $items = $loadItems();
    Helpers::json([
        'ok' => true,
        'items' => $items,
        'month_start' => date('Y-m-01'),
        'month_end' => date('Y-m-t'),
    ]);
}

Helpers::requirePost();
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$action = trim((string) ($payload['action'] ?? 'create'));
if ($action !== 'create') {
    Helpers::json(['ok' => false, 'error' => 'Ação inválida.'], 422);
}

$name = trim((string) ($payload['name'] ?? ''));
if ($name === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe o nome da oficina modular.'], 422);
}

$teacherName = trim((string) ($payload['teacher_name'] ?? ''));
if ($teacherName === '') {
    Helpers::json(['ok' => false, 'error' => 'Informe o nome do(a) professor(a).'], 422);
}

$daysInput = $payload['days_of_week'] ?? [];
if (!is_array($daysInput)) {
    Helpers::json(['ok' => false, 'error' => 'Dias da semana inválidos.'], 422);
}
$days = [];
foreach ($daysInput as $dayRaw) {
    $day = (int) $dayRaw;
    if ($day < 1 || $day > 5) {
        continue;
    }
    $days[$day] = true;
}
$days = array_map('intval', array_keys($days));
sort($days);
if (empty($days)) {
    Helpers::json(['ok' => false, 'error' => 'Selecione pelo menos um dia útil (Seg a Sex).'], 422);
}

$timeStart = $normalizeTime((string) ($payload['time_start'] ?? ''));
$timeEnd = $normalizeTime((string) ($payload['time_end'] ?? ''));
$isAllowedTime = ($timeStart === '14:00' && $timeEnd === '15:00')
    || ($timeStart === '15:40' && $timeEnd === '16:40');
if (!$isAllowedTime) {
    Helpers::json([
        'ok' => false,
        'error' => 'Horário inválido. Pelas regras fixas da grade, use apenas 14:00-15:00 ou 15:40-16:40.',
    ], 422);
}

$createdOfficeId = '';
$createdCode = '';
$description = $buildDescription($teacherName, '');
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $createdCode = 'OM' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
    $insertOffice = $client->insert('oficina_modular', [[
        'nome' => $name,
        'codigo' => $createdCode,
        'descricao' => $description,
        'ativa' => true,
        'capacidade' => 0,
        'status_quorum' => 'LIVRE',
        'tipo' => 'RECORRENTE',
        'data_inicio_validade' => null,
        'data_fim_validade' => null,
    ]]);
    if (($insertOffice['ok'] ?? false) && !empty($insertOffice['data'][0]['id'])) {
        $createdOfficeId = (string) $insertOffice['data'][0]['id'];
        break;
    }
    $errorText = strtolower((string) ($insertOffice['error'] ?? ''));
    $errorData = strtolower(json_encode($insertOffice['data'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');
    if (!str_contains($errorText, 'duplicate') && !str_contains($errorData, 'uq_oficina_modular_codigo')) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao criar oficina modular.'], 500);
    }
}

if ($createdOfficeId === '') {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível gerar código único para a oficina.'], 500);
}

$schedulePayload = [];
foreach ($days as $day) {
    $schedulePayload[] = [
        'oficina_modular_id' => $createdOfficeId,
        'dia_semana' => $day,
        'hora_inicio' => $timeStart . ':00',
        'hora_fim' => $timeEnd . ':00',
    ];
}

$insertSchedules = $client->insert('oficina_modular_horarios', $schedulePayload);
if (!($insertSchedules['ok'] ?? false)) {
    $client->delete('oficina_modular', 'id=eq.' . rawurlencode($createdOfficeId));
    Helpers::json(['ok' => false, 'error' => 'Falha ao salvar horários da oficina.'], 500);
}

Helpers::json([
    'ok' => true,
    'message' => 'Oficina modular criada com sucesso.',
    'created_id' => $createdOfficeId,
    'created_code' => $createdCode,
    'items' => $loadItems(),
]);
