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
$publicRoot = dirname(__DIR__) . '/public';
$htaccess = file_get_contents($publicRoot . '/.htaccess');
$deployWorkflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy-hostinger.yml');

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

$blockedExtensions = ['bak', 'bkp', 'old', 'orig', 'save', 'swp', 'sql', 'zip', 'tar', 'gz', '7z'];
$publishedBackups = [];
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($files as $file) {
    $blockedPattern = '/\.(' . implode('|', $blockedExtensions) . ')(\.|$)/i';
    if ($file->isFile() && preg_match($blockedPattern, $file->getFilename()) === 1) {
        $publishedBackups[] = $file->getPathname();
    }
}

check_upload(
    $publishedBackups === [],
    'public não deve conter backups ou arquivos de dados publicáveis'
);
check_upload(
    is_string($htaccess)
        && str_contains($htaccess, '\.(bak|bkp|old|orig|save|swp|sql|zip|tar|gz|7z)(\.|$)'),
    '.htaccess deve negar extensões de backup e dados'
);
check_upload(
    is_string($deployWorkflow)
        && str_contains($deployWorkflow, 'BLOCKED_ARTIFACTS')
        && str_contains($deployWorkflow, 'Remover backups legados do servidor'),
    'deploy deve rejeitar novos backups e remover os legados do servidor'
);

if ($failures) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Upload security tests passed.\n";
