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
if (($_SESSION['admin_user'] ?? '') !== 'admin') {
    Helpers::json(['ok' => false, 'error' => 'Somente admin pode criar e editar oficinas modulares.'], 403);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    Helpers::json(['ok' => false, 'error' => 'Método inválido.'], 405);
}

$client = new SupabaseClient(new HttpClient());
$catalogTag = '[CATALOGO_OM_MENSAL]';

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

$extractTeacherFromDescription = static function (string $description): string {
    if ($description === '') {
        return '';
    }
    if (preg_match('/^\[PROFESSOR\]\s*(.+)$/mi', $description, $match)) {
        return trim((string) ($match[1] ?? ''));
    }
    return '';
};

$hasCatalogTag = static function (string $description) use ($catalogTag): bool {
    if ($description === '') {
        return false;
    }
    $normalizedDescription = function_exists('mb_strtoupper')
        ? mb_strtoupper($description, 'UTF-8')
        : strtoupper($description);
    $normalizedTag = function_exists('mb_strtoupper')
        ? mb_strtoupper($catalogTag, 'UTF-8')
        : strtoupper($catalogTag);
    return str_contains($normalizedDescription, $normalizedTag);
};

$stripMetaFromDescription = static function (string $description) use ($catalogTag): string {
    if ($description === '') {
        return '';
    }
    $withoutTeacher = preg_replace('/^\[PROFESSOR\].*(\R|$)/mi', '', $description);
    $withoutCatalog = preg_replace('/^\[CATALOGO_OM_MENSAL\].*(\R|$)/mi', '', (string) $withoutTeacher);
    return trim((string) $withoutCatalog);
};

$buildDescription = static function (string $teacherName, string $existingDescription = '') use ($stripMetaFromDescription, $catalogTag): string {
    $cleanTeacher = trim($teacherName);
    $baseDescription = $stripMetaFromDescription($existingDescription);
    $parts = [
        $catalogTag,
        '[PROFESSOR] ' . $cleanTeacher,
    ];
    if ($baseDescription !== '') {
        $parts[] = $baseDescription;
    }
    return implode("\n\n", $parts);
};

$isAvailableThisMonth = static function (array $office, string $monthStart, string $monthEnd): bool {
    $active = ($office['ativa'] ?? false) === true || (string) ($office['ativa'] ?? '') === 'true';
    if (!$active) {
        return false;
    }
    $tipo = strtoupper(trim((string) ($office['tipo'] ?? '')));
    $start = trim((string) ($office['data_inicio_validade'] ?? ''));
    $end = trim((string) ($office['data_fim_validade'] ?? ''));
    if ($start === '' || $end === '') {
        return false;
    }
    return $start <= $monthEnd && $end >= $monthStart;
};

$loadItems = static function () use ($client, $extractTeacherFromDescription, $normalizeSortName, $isAvailableThisMonth, $hasCatalogTag): array {
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
        $description = trim((string) ($office['descricao'] ?? ''));
        if (!$hasCatalogTag($description)) {
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
    $teacherSet = [];
    $catalogNameSet = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $teacher = trim((string) ($item['teacher_name'] ?? ''));
        $name = trim((string) ($item['name'] ?? ''));
        if ($teacher !== '') {
            $teacherSet[$teacher] = true;
        }
        if ($name !== '') {
            $catalogNameSet[$name] = true;
        }
    }
    $teachers = array_keys($teacherSet);
    $catalogNames = array_keys($catalogNameSet);
    usort($teachers, static function (string $a, string $b) use ($normalizeSortName): int {
        return strcmp($normalizeSortName($a), $normalizeSortName($b));
    });
    usort($catalogNames, static function (string $a, string $b) use ($normalizeSortName): int {
        return strcmp($normalizeSortName($a), $normalizeSortName($b));
    });
    Helpers::json([
        'ok' => true,
        'items' => $items,
        'teachers' => $teachers,
        'catalog_names' => $catalogNames,
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
$month = (int) ($payload['month'] ?? 0);
$year = (int) ($payload['year'] ?? 0);
if ($month < 1 || $month > 12 || $year < 2025 || $year > 2099) {
    Helpers::json(['ok' => false, 'error' => 'Informe mês e ano válidos da grade mensal.'], 422);
}
$periodStartDate = \DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
if (!$periodStartDate instanceof \DateTimeImmutable) {
    Helpers::json(['ok' => false, 'error' => 'Período mensal inválido.'], 422);
}
$periodStart = $periodStartDate->format('Y-m-01');
$periodEnd = $periodStartDate->format('Y-m-t');

$weekSlotsInput = $payload['week_slots'] ?? [];
if (!is_array($weekSlotsInput)) {
    Helpers::json(['ok' => false, 'error' => 'Grade semanal inválida.'], 422);
}
$selectedSlots = [];
foreach ($weekSlotsInput as $slotRaw) {
    $slotValue = trim((string) $slotRaw);
    if (!preg_match('/^([1-5])_([12])$/', $slotValue, $match)) {
        continue;
    }
    $dayOfWeek = (int) ($match[1] ?? 0);
    $slotIndex = (int) ($match[2] ?? 0);
    if ($dayOfWeek < 1 || $dayOfWeek > 5 || ($slotIndex !== 1 && $slotIndex !== 2)) {
        continue;
    }
    $selectedSlots[$dayOfWeek . '_' . $slotIndex] = [
        'day' => $dayOfWeek,
        'slot' => $slotIndex,
    ];
}
$selectedSlots = array_values($selectedSlots);
if (empty($selectedSlots)) {
    Helpers::json([
        'ok' => false,
        'error' => 'Selecione pelo menos um item da grade semanal (dia + 1º/2º horário).',
    ], 422);
}

$description = $buildDescription($teacherName, '');
$allCatalogItems = $loadItems();
$normalizedName = $normalizeSortName($name);
$existingCatalog = null;
foreach ($allCatalogItems as $item) {
    if (!is_array($item)) {
        continue;
    }
    if ($normalizeSortName((string) ($item['name'] ?? '')) === $normalizedName) {
        $existingCatalog = $item;
        break;
    }
}

$createdOfficeId = '';
$createdCode = '';
$createdNewCatalog = false;
if (is_array($existingCatalog) && !empty($existingCatalog['id'])) {
    $createdOfficeId = (string) $existingCatalog['id'];
    $createdCode = (string) ($existingCatalog['code'] ?? '');
    $updateOffice = $client->update(
        'oficina_modular',
        'id=eq.' . rawurlencode($createdOfficeId),
        [
            'nome' => $name,
            'descricao' => $description,
            'ativa' => true,
            'status_quorum' => 'LIVRE',
            'tipo' => 'OCASIONAL_30D',
            'data_inicio_validade' => $periodStart,
            'data_fim_validade' => $periodEnd,
            'updated_at' => date('c'),
        ]
    );
    if (!($updateOffice['ok'] ?? false)) {
        Helpers::json(['ok' => false, 'error' => 'Falha ao atualizar catálogo da oficina modular.'], 500);
    }
} else {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $createdCode = 'OM' . date('ymdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $insertOffice = $client->insert('oficina_modular', [[
            'nome' => $name,
            'codigo' => $createdCode,
            'descricao' => $description,
            'ativa' => true,
            'capacidade' => 0,
            'status_quorum' => 'LIVRE',
            'tipo' => 'OCASIONAL_30D',
            'data_inicio_validade' => $periodStart,
            'data_fim_validade' => $periodEnd,
        ]]);
        if (($insertOffice['ok'] ?? false) && !empty($insertOffice['data'][0]['id'])) {
            $createdOfficeId = (string) $insertOffice['data'][0]['id'];
            $createdNewCatalog = true;
            break;
        }
        $errorText = strtolower((string) ($insertOffice['error'] ?? ''));
        $errorData = strtolower(json_encode($insertOffice['data'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');
        if (!str_contains($errorText, 'duplicate') && !str_contains($errorData, 'uq_oficina_modular_codigo')) {
            Helpers::json(['ok' => false, 'error' => 'Falha ao criar oficina modular.'], 500);
        }
    }
}

if ($createdOfficeId === '') {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível gerar código único para a oficina.'], 500);
}

$deleteOldSchedules = $client->delete(
    'oficina_modular_horarios',
    'oficina_modular_id=eq.' . rawurlencode($createdOfficeId)
);
if (!($deleteOldSchedules['ok'] ?? false)) {
    Helpers::json(['ok' => false, 'error' => 'Falha ao limpar horários anteriores da oficina.'], 500);
}

$schedulePayload = [];
foreach ($selectedSlots as $slotInfo) {
    $day = (int) ($slotInfo['day'] ?? 0);
    $slot = (int) ($slotInfo['slot'] ?? 0);
    $start = $slot === 1 ? '14:00:00' : '15:40:00';
    $end = $slot === 1 ? '15:00:00' : '16:40:00';
    $schedulePayload[] = [
        'oficina_modular_id' => $createdOfficeId,
        'dia_semana' => $day,
        'hora_inicio' => $start,
        'hora_fim' => $end,
    ];
}

$insertSchedules = $client->insert('oficina_modular_horarios', $schedulePayload);
if (!($insertSchedules['ok'] ?? false)) {
    if ($createdNewCatalog) {
        $client->delete('oficina_modular', 'id=eq.' . rawurlencode($createdOfficeId));
    }
    Helpers::json(['ok' => false, 'error' => 'Falha ao salvar horários da oficina.'], 500);
}

$updatedItems = $loadItems();
$teachers = array_values(array_unique(array_filter(array_map(
    static fn($item): string => trim((string) ($item['teacher_name'] ?? '')),
    $updatedItems
))));
$catalogNames = array_values(array_unique(array_filter(array_map(
    static fn($item): string => trim((string) ($item['name'] ?? '')),
    $updatedItems
))));
usort($teachers, static function (string $a, string $b) use ($normalizeSortName): int {
    return strcmp($normalizeSortName($a), $normalizeSortName($b));
});
usort($catalogNames, static function (string $a, string $b) use ($normalizeSortName): int {
    return strcmp($normalizeSortName($a), $normalizeSortName($b));
});

Helpers::json([
    'ok' => true,
    'message' => 'Grade mensal da oficina salva com sucesso para ' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '/' . $year . '.',
    'created_id' => $createdOfficeId,
    'created_code' => $createdCode,
    'items' => $updatedItems,
    'teachers' => $teachers,
    'catalog_names' => $catalogNames,
]);
