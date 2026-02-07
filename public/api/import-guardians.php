<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$key = $_GET['key'] ?? '';
$returnHtml = ($_GET['return'] ?? '') === 'html';

$sessionOk = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
$keyOk = $key !== '' && $key === Env::get('ADMIN_SECRET', '');

if (!$sessionOk && !$keyOk) {
    Helpers::json(['ok' => false, 'error' => 'Nao autorizado.'], 401);
}

Helpers::requirePost();

function normalize_text(string $value): string
{
    $value = mb_strtoupper($value, 'UTF-8');
    $translit = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
    if ($translit !== false) {
        $value = $translit;
    }
    $value = preg_replace('/[^A-Z0-9]/', '', $value);
    return $value ?? '';
}

function is_student_line(string $line): bool
{
    $norm = normalize_text($line);
    return str_contains($line, ' - ')
        && str_contains($norm, 'ANO')
        && str_contains($norm, 'ENSINOFUNDAMENTAL')
        && str_contains($norm, '6');
}

function is_responsavel_line(string $line): bool
{
    return str_starts_with(normalize_text($line), 'RESPONS');
}

function is_label(string $line): bool
{
    $labels = [
        'RESPONSAVEL',
        'TIPODERESPONSAVEL',
        'DTNASCIMENTO',
        'RG',
        'CPFCNPJ',
        'TELEFONES',
        'EMAIL',
        'RESIDECOMALUNO',
        'PROFISSAO',
        'EMPRESA',
        'ENDERECO',
        'ENDERECOCOMERCIAL',
        'TELEFONECOMERCIAL',
        'CIDADEUF',
        'CEP',
        'NACIONALIDADE',
        'ESTADOCIVIL',
        'SEXO',
    ];
    $norm = normalize_text($line);
    return in_array($norm, $labels, true) || str_contains($norm, 'CPFCNPJ') || str_contains($norm, 'TELEFONES');
}

function extract_email(string $value): string
{
    $parts = preg_split('/[\s\/]+/', $value) ?: [];
    foreach ($parts as $part) {
        if (str_contains($part, '@')) {
            return trim($part);
        }
    }
    return '';
}

function extract_phone(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function find_value(array $block, string $labelKey): string
{
    foreach ($block as $index => $line) {
        if (normalize_text($line) === $labelKey) {
            for ($j = $index + 1; $j < count($block); $j++) {
                $candidate = trim($block[$j]);
                if ($candidate === '') {
                    continue;
                }
                if (is_label($candidate)) {
                    return '';
                }
                return $candidate;
            }
        }
    }
    return '';
}

function placeholder_email(string $name, string $cpf, string $phone, string $student, array &$counter): string
{
    $base = $cpf !== '' ? $cpf : ($phone !== '' ? $phone : substr(md5($name . '-' . $student), 0, 10));
    $base = preg_replace('/\W+/', '', $base) ?? '';
    $email = 'sem-email+' . $base . '@diariasvillage.local';
    $count = $counter[$email] ?? 0;
    if ($count > 0) {
        $email = 'sem-email+' . $base . '-' . ($count + 1) . '@diariasvillage.local';
    }
    $counter[$email] = $count + 1;
    return $email;
}

function parse_guardians_from_text(string $text): array
{
    $rawLines = preg_split('/\R/u', $text) ?: [];
    $lines = array_values(array_filter(array_map('trim', $rawLines)));

    $skipPrefixes = ['COL', 'RESOL', 'Relat', 'Escolar Manager', 'LUCAS JR,', '-- '];

    $guardiansByStudent = [];
    $currentStudent = null;

    $i = 0;
    while ($i < count($lines)) {
        $line = $lines[$i];

        $skip = false;
        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($line, $prefix)) {
                $skip = true;
                break;
            }
        }
        if ($skip || preg_match('/^\d{2}\/\d{2}\/\d{4}/', $line)) {
            $i++;
            continue;
        }

        if (is_student_line($line)) {
            $currentStudent = trim(explode(' - ', $line)[0] ?? '');
            if ($currentStudent !== '') {
                $guardiansByStudent[$currentStudent] = $guardiansByStudent[$currentStudent] ?? [];
            }
            $i++;
            continue;
        }

        if (is_responsavel_line($line)) {
            if (!$currentStudent) {
                $i++;
                continue;
            }
            $nameIdx = $i + 1;
            while ($nameIdx < count($lines) && trim($lines[$nameIdx]) === '') {
                $nameIdx++;
            }
            if ($nameIdx >= count($lines)) {
                break;
            }
            $guardianName = trim($lines[$nameIdx]);

            $blockStart = $nameIdx + 1;
            $blockEnd = $blockStart;
            while ($blockEnd < count($lines)) {
                if (is_student_line($lines[$blockEnd]) || is_responsavel_line($lines[$blockEnd])) {
                    break;
                }
                $blockEnd++;
            }

            $block = array_slice($lines, $blockStart, $blockEnd - $blockStart);
            $emailValue = extract_email(find_value($block, 'EMAIL'));
            $cpfValue = preg_replace('/\D+/', '', find_value($block, 'CPFCNPJ')) ?? '';
            $phoneValue = extract_phone(find_value($block, 'TELEFONES'));

            $guardiansByStudent[$currentStudent][] = [
                'name' => $guardianName,
                'email' => $emailValue,
                'phone' => $phoneValue,
                'cpf' => $cpfValue,
            ];

            $i = $blockEnd;
            continue;
        }

        $i++;
    }

    $selected = [];
    $missingEmail = [];
    $placeholderCounter = [];

    foreach ($guardiansByStudent as $student => $guardians) {
        if (!$guardians) {
            continue;
        }
        $withEmail = array_values(array_filter($guardians, static fn($g) => !empty($g['email'])));
        $chosen = $withEmail[0] ?? $guardians[0];
        if (empty($chosen['email'])) {
            $missingEmail[] = $student;
            $chosen['email'] = placeholder_email($chosen['name'] ?? '', $chosen['cpf'] ?? '', $chosen['phone'] ?? '', $student, $placeholderCounter);
        }
        $selected[] = [
            'student_name' => $student,
            'guardian_name' => $chosen['name'] ?? '',
            'guardian_email' => $chosen['email'] ?? '',
            'guardian_phone' => $chosen['phone'] ?? '',
            'guardian_cpf' => $chosen['cpf'] ?? '',
        ];
    }

    return [
        'entries' => $selected,
        'missing_email_students' => $missingEmail,
        'total_students' => count($guardiansByStudent),
    ];
}

function extract_text_from_pdf(string $pdfPath): string
{
    if (!function_exists('shell_exec')) {
        return '';
    }
    $check = @shell_exec('pdftotext -v 2>&1');
    if (!$check || str_contains($check, 'not recognized') || str_contains($check, 'command not found')) {
        return '';
    }
    $tmpTxt = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'responsaveis_' . uniqid('', true) . '.txt';
    $cmd = 'pdftotext -layout ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($tmpTxt);
    @shell_exec($cmd);
    if (!file_exists($tmpTxt)) {
        return '';
    }
    $text = file_get_contents($tmpTxt) ?: '';
    @unlink($tmpTxt);
    return $text;
}

function normalize_entry(array $entry, array &$placeholderCounter): array
{
    $student = trim($entry['student_name'] ?? '');
    $name = trim($entry['guardian_name'] ?? '');
    $email = trim($entry['guardian_email'] ?? '');
    $phone = preg_replace('/\D+/', '', $entry['guardian_phone'] ?? '') ?? '';
    $cpf = preg_replace('/\D+/', '', $entry['guardian_cpf'] ?? '') ?? '';

    if ($email === '') {
        $email = placeholder_email($name, $cpf, $phone, $student, $placeholderCounter);
    }

    return [
        'student_name' => $student,
        'guardian_name' => $name,
        'guardian_email' => $email,
        'guardian_phone' => $phone,
        'guardian_cpf' => $cpf,
    ];
}

$entries = [];
$meta = [];

if (!empty($_FILES['file']['tmp_name'])) {
    $fileName = $_FILES['file']['name'] ?? '';
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $tmpPath = $_FILES['file']['tmp_name'];

    if ($ext === 'json') {
        $payload = json_decode(file_get_contents($tmpPath), true);
        if (!is_array($payload)) {
            Helpers::json(['ok' => false, 'error' => 'JSON invalido.'], 422);
        }
        $placeholderCounter = [];
        foreach ($payload as $entry) {
            $entries[] = normalize_entry((array) $entry, $placeholderCounter);
        }
    } elseif ($ext === 'pdf') {
        $text = extract_text_from_pdf($tmpPath);
        if ($text === '') {
            Helpers::json(['ok' => false, 'error' => 'Nao foi possivel ler o PDF. Envie um JSON.'], 422);
        }
        $parsed = parse_guardians_from_text($text);
        $meta = [
            'total_students' => $parsed['total_students'],
            'students_without_email' => $parsed['missing_email_students'],
        ];
        $entries = $parsed['entries'];
    } else {
        Helpers::json(['ok' => false, 'error' => 'Envie um arquivo PDF ou JSON.'], 422);
    }
} elseif (!empty($_POST['json'])) {
    $payload = json_decode((string) $_POST['json'], true);
    if (!is_array($payload)) {
        Helpers::json(['ok' => false, 'error' => 'JSON invalido.'], 422);
    }
    $placeholderCounter = [];
    foreach ($payload as $entry) {
        $entries[] = normalize_entry((array) $entry, $placeholderCounter);
    }
} else {
    Helpers::json(['ok' => false, 'error' => 'Nenhum arquivo enviado.'], 422);
}

$client = new SupabaseClient(new HttpClient());
$studentsResult = $client->select('students', 'select=id,name');
$students = $studentsResult['data'] ?? [];
$studentsByNorm = [];
foreach ($students as $student) {
    $norm = normalize_text($student['name'] ?? '');
    if ($norm === '') {
        continue;
    }
    $studentsByNorm[$norm][] = $student;
}

$result = [
    'total' => count($entries),
    'inserted' => 0,
    'updated' => 0,
    'skipped_missing_student' => [],
    'skipped_ambiguous_student' => [],
    'skipped_conflict_email' => [],
    'errors' => [],
    'meta' => $meta,
];

foreach ($entries as $entry) {
    $studentName = $entry['student_name'] ?? '';
    $guardianName = $entry['guardian_name'] ?? '';
    $guardianEmail = $entry['guardian_email'] ?? '';
    $guardianPhone = $entry['guardian_phone'] ?? '';
    $guardianCpf = $entry['guardian_cpf'] ?? '';

    if ($studentName === '' || $guardianEmail === '') {
        $result['errors'][] = ['student' => $studentName, 'error' => 'Dados incompletos.'];
        continue;
    }

    $studentNorm = normalize_text($studentName);
    $matches = $studentsByNorm[$studentNorm] ?? [];
    if (!$matches) {
        $result['skipped_missing_student'][] = $studentName;
        continue;
    }
    if (count($matches) > 1) {
        $result['skipped_ambiguous_student'][] = $studentName;
        continue;
    }
    $studentRow = $matches[0];

    $guardianResult = $client->select('guardians', 'select=id,student_id,email&email=eq.' . urlencode($guardianEmail));
    $guardianRow = $guardianResult['data'][0] ?? null;

    if ($guardianRow) {
        if ($guardianRow['student_id'] !== $studentRow['id']) {
            $result['skipped_conflict_email'][] = [
                'email' => $guardianEmail,
                'existing_student_id' => $guardianRow['student_id'],
                'new_student' => $studentName,
            ];
            continue;
        }

        $update = $client->update('guardians', 'id=eq.' . $guardianRow['id'], [
            'parent_name' => $guardianName,
            'parent_phone' => $guardianPhone ?: null,
            'parent_document' => $guardianCpf ?: null,
        ]);
        if (!$update['ok']) {
            $result['errors'][] = ['student' => $studentName, 'error' => 'Falha ao atualizar responsavel.'];
            continue;
        }
        $result['updated']++;
        continue;
    }

    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert = $client->insert('guardians', [[
        'student_id' => $studentRow['id'],
        'email' => $guardianEmail,
        'password_hash' => $passwordHash,
        'parent_name' => $guardianName,
        'parent_phone' => $guardianPhone ?: null,
        'parent_document' => $guardianCpf ?: null,
    ]]);

    if (!$insert['ok']) {
        $result['errors'][] = ['student' => $studentName, 'error' => 'Falha ao inserir responsavel.'];
        continue;
    }

    $result['inserted']++;
}

if ($returnHtml) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<pre>' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

Helpers::json(['ok' => true, 'result' => $result]);
