<?php

require_once dirname(__DIR__) . '/src/Bootstrap.php';

use App\UploadSecurity;

$failures = [];

function check_upload(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function expect_upload_rejection(callable $callback, string $message): void
{
    try {
        $callback();
        check_upload(false, $message);
    } catch (InvalidArgumentException $e) {
        check_upload(true, $message);
    }
}

$tmp = tempnam(sys_get_temp_dir(), 'upload_security_');
file_put_contents($tmp, "nome,serie\nAluno,6\n");

$csvTypes = [
    'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
];

$validated = UploadSecurity::validate([
    'error' => UPLOAD_ERR_OK,
    'tmp_name' => $tmp,
    'name' => 'alunos.csv',
    'size' => filesize($tmp),
], $csvTypes, 1024);

check_upload($validated['extension'] === 'csv', 'deve aceitar CSV válido');
check_upload($validated['size'] === filesize($tmp), 'deve medir o tamanho real no servidor');

expect_upload_rejection(
    fn () => UploadSecurity::validate([
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $tmp,
        'name' => 'alunos.php',
        'size' => filesize($tmp),
    ], $csvTypes, 1024),
    'deve rejeitar extensão fora da allowlist'
);

expect_upload_rejection(
    fn () => UploadSecurity::validate([
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $tmp,
        'name' => 'alunos.csv',
        'size' => 1,
    ], $csvTypes, 5),
    'deve rejeitar pelo tamanho real, mesmo com tamanho declarado menor'
);

expect_upload_rejection(
    fn () => UploadSecurity::validate([
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $tmp,
        'name' => 'responsaveis.pdf',
        'size' => filesize($tmp),
    ], ['pdf' => ['application/pdf']], 1024),
    'deve rejeitar conteúdo incompatível com a extensão'
);

@unlink($tmp);

$studentImporter = file_get_contents(dirname(__DIR__) . '/public/api/import-students.php');
$lock = file_get_contents(dirname(__DIR__) . '/composer.lock');

check_upload(
    str_contains($studentImporter, 'new Xls()') && str_contains($studentImporter, 'new Xlsx()'),
    'importação deve selecionar leitor de planilha explicitamente'
);
check_upload(
    !str_contains($studentImporter, 'IOFactory::load'),
    'importação não deve autodetectar leitor com IOFactory::load'
);
check_upload(
    str_contains($lock, '"version": "1.30.6"'),
    'composer.lock deve fixar PhpSpreadsheet 1.30.6'
);

if ($failures) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Upload security tests passed.\n";
