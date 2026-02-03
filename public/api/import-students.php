<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;
use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;
use PhpOffice\PhpSpreadsheet\IOFactory;

$key = $_GET['key'] ?? '';
if ($key !== Env::get('ADMIN_SECRET', '')) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

if (empty($_FILES['file']['tmp_name'])) {
    echo 'Arquivo nao enviado.';
    exit;
}

$path = $_FILES['file']['tmp_name'];
$original = $_FILES['file']['name'] ?? '';
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

$rows = [];

if ($ext === 'csv') {
    if (($handle = fopen($path, 'r')) !== false) {
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    }
} else {
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
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

if ($nameIndex === false || $gradeIndex === false) {
    echo 'Cabecalho invalido. Use colunas nome e serie.';
    exit;
}

$client = new SupabaseClient(new HttpClient());
$payload = [];

for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    $name = trim($row[$nameIndex] ?? '');
    $grade = (int) ($row[$gradeIndex] ?? 0);

    if ($name === '' || $grade < 6 || $grade > 8) {
        continue;
    }

    $payload[] = ['name' => $name, 'grade' => $grade, 'active' => true];

    if (count($payload) >= 200) {
        $client->insert('students', $payload);
        $payload = [];
    }
}

if (!empty($payload)) {
    $client->insert('students', $payload);
}

header('Location: /admin/import.php?key=' . urlencode($key));
exit;
