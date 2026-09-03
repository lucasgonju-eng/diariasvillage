<?php
require_once dirname(__DIR__, 2) . '/src/Bootstrap.php';
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;
use App\UploadSecurity;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

Helpers::requireAdminRole(\App\AdminAuth::ROLE_ADMIN);
Helpers::requirePost();

try {
    $upload = UploadSecurity::validate(
        is_array($_FILES['file'] ?? null) ? $_FILES['file'] : [],
        [
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        5 * 1024 * 1024
    );
} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$path = $upload['path'];
$ext = $upload['extension'];

$rows = [];

if ($ext === 'csv') {
    if (($handle = fopen($path, 'r')) !== false) {
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $data;
            if (count($rows) > 5001) {
                fclose($handle);
                http_response_code(422);
                echo 'A planilha excede o limite de 5.000 alunos.';
                exit;
            }
        }
        fclose($handle);
    }
} else {
    try {
        $reader = $ext === 'xls' ? new Xls() : new Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        if (
            $sheet->getHighestDataRow() > 5001
            || Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 50
        ) {
            http_response_code(422);
            echo 'A planilha excede o limite de 5.000 alunos ou 50 colunas.';
            exit;
        }
        $rows = $sheet->toArray();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    } catch (\Throwable $e) {
        error_log('[import-students] Falha ao ler planilha: ' . $e->getMessage());
        http_response_code(422);
        echo 'Não foi possível ler a planilha.';
        exit;
    }
}

if (count($rows) < 2) {
    echo 'Arquivo sem dados.';
    exit;
}

$header = array_map('strtolower', array_map('trim', $rows[0]));
$nameIndex = array_search('nome', $header, true);
if ($nameIndex === false) {
    $nameIndex = array_search('name', $header, true);
}
$gradeIndex = array_search('serie', $header, true);
if ($gradeIndex === false) {
    $gradeIndex = array_search('grade', $header, true);
}
$classIndex = array_search('serie / turma', $header, true);
if ($classIndex === false) {
    $classIndex = array_search('série / turma', $header, true);
}
$enrollmentIndex = array_search('matricula', $header, true);
if ($enrollmentIndex === false) {
    $enrollmentIndex = array_search('matrícula', $header, true);
}
$birthIndex = array_search('nascimento', $header, true);
if ($birthIndex === false) {
    $birthIndex = array_search('data de nascimento', $header, true);
}

if ($nameIndex === false || ($gradeIndex === false && $classIndex === false)) {
    echo 'Cabeçalho inválido. Use colunas nome e série ou série / turma.';
    exit;
}

$client = new SupabaseClient(new HttpClient());
$payload = [];

for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    $name = trim($row[$nameIndex] ?? '');
    $gradeRaw = $gradeIndex !== false ? ($row[$gradeIndex] ?? '') : ($row[$classIndex] ?? '');
    $grade = (int) preg_replace('/[^0-9]/', '', (string) $gradeRaw);
    $className = $classIndex !== false ? trim((string) ($row[$classIndex] ?? '')) : null;
    $enrollment = $enrollmentIndex !== false ? trim((string) ($row[$enrollmentIndex] ?? '')) : null;
    $birth = $birthIndex !== false ? trim((string) ($row[$birthIndex] ?? '')) : null;
    $birthDate = null;
    if ($birth !== '') {
        $parts = preg_split('/[\\/-]/', $birth);
        if (count($parts) === 3) {
            $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $year = $parts[2];
            if (strlen($year) === 4) {
                $birthDate = $year . '-' . $month . '-' . $day;
            }
        }
    }

    if ($name === '' || $grade < 6 || $grade > 8) {
        continue;
    }

    $payload[] = [
        'name' => $name,
        'enrollment' => $enrollment,
        'grade' => $grade,
        'class_name' => $className,
        'birth_date' => $birthDate,
        'active' => true,
    ];

    if (count($payload) >= 200) {
        $insert = $client->insert('students', $payload);
        if (!($insert['ok'] ?? false)) {
            http_response_code(503);
            echo 'Falha ao gravar um lote de alunos.';
            exit;
        }
        $payload = [];
    }
}

if (!empty($payload)) {
    $insert = $client->insert('students', $payload);
    if (!($insert['ok'] ?? false)) {
        http_response_code(503);
        echo 'Falha ao gravar os alunos.';
        exit;
    }
}

header('Location: /admin/import.php?success=1');
exit;
