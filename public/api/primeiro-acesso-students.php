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

Helpers::requirePost();
$payload = json_decode((string) file_get_contents('php://input'), true);

$cpf = trim((string) ($payload['cpf'] ?? ''));
$studentNameQuery = trim((string) ($payload['student_name'] ?? ''));
$cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';

if (strlen($cpfDigits) !== 11) {
    Helpers::json(['ok' => false, 'error' => 'CPF inválido.'], 422);
}
if ($studentNameQuery === '' || mb_strlen($studentNameQuery, 'UTF-8') < 3) {
    Helpers::json(['ok' => false, 'error' => 'Digite pelo menos 3 letras do nome do aluno(a).'], 422);
}

$normalize = static function (string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = str_replace(
        ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'],
        ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'],
        $value
    );
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
};

$scoreCandidate = static function (string $query, string $name) use ($normalize): float {
    $q = $normalize($query);
    $n = $normalize($name);
    if ($q === '' || $n === '') {
        return 0.0;
    }
    if ($q === $n) {
        return 100.0;
    }

    similar_text($q, $n, $percent);
    $score = (float) $percent;

    if (str_starts_with($n, $q)) {
        $score += 15.0;
    }
    if (str_contains($n, $q)) {
        $score += 10.0;
    }

    $tokens = array_values(array_filter(explode(' ', $q)));
    if (!empty($tokens)) {
        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($n, $token)) {
                $hits++;
            }
        }
        $score += (20.0 * ($hits / count($tokens)));
    }

    return min($score, 100.0);
};

$client = new SupabaseClient(new HttpClient());
$guardiansResult = $client->select(
    'guardians',
    'select=student_id,parent_document&parent_document=eq.' . urlencode($cpfDigits) . '&limit=200'
);

if (!$guardiansResult['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível consultar o vínculo do CPF.'], 500);
}
if (empty($guardiansResult['data'])) {
    Helpers::json(['ok' => false, 'error' => 'CPF não encontrado no cadastro.'], 404);
}

$studentIds = [];
foreach ($guardiansResult['data'] as $guardian) {
    $sid = trim((string) ($guardian['student_id'] ?? ''));
    if ($sid !== '') {
        $studentIds[$sid] = true;
    }
}
if (empty($studentIds)) {
    Helpers::json(['ok' => false, 'error' => 'CPF sem vínculo de aluno ativo. Procure a secretaria.'], 404);
}

$quotedIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', array_keys($studentIds));
$studentsResult = $client->select(
    'students',
    'select=id,name,grade,class_name,enrollment,active&id=in.(' . implode(',', $quotedIds) . ')&active=eq.true&limit=200'
);

if (!$studentsResult['ok']) {
    Helpers::json(['ok' => false, 'error' => 'Não foi possível buscar alunos vinculados.'], 500);
}

$candidates = [];
foreach (($studentsResult['data'] ?? []) as $student) {
    $name = (string) ($student['name'] ?? '');
    $score = $scoreCandidate($studentNameQuery, $name);
    if ($score < 35.0) {
        continue;
    }
    $candidates[] = [
        'id' => (string) ($student['id'] ?? ''),
        'name' => $name,
        'grade' => isset($student['grade']) ? (int) $student['grade'] : null,
        'class_name' => (string) ($student['class_name'] ?? ''),
        'enrollment' => (string) ($student['enrollment'] ?? ''),
        'score' => round($score, 2),
    ];
}

usort($candidates, static function (array $a, array $b): int {
    if (($b['score'] ?? 0) === ($a['score'] ?? 0)) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    }
    return (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
});

$candidates = array_slice($candidates, 0, 10);
Helpers::json(['ok' => true, 'candidates' => $candidates]);
