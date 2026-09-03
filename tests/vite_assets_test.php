<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\ViteAssets;

$failures = [];
$resolverSource = file_get_contents(dirname(__DIR__) . '/src/ViteAssets.php') ?: '';
if (substr_count($resolverSource, 'is_file($assetRoot .') < 2) {
    $failures[] = 'Resolvedor deve falhar fechado quando JS ou CSS declarado estiver ausente.';
}
if (!str_contains($resolverSource, "is_dir(\$appRoot . '/public')")) {
    $failures[] = 'Resolvedor deve suportar a árvore local e a release achatada.';
}

try {
    $assets = ViteAssets::adminDashboard();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao resolver assets Vite: ' . $exception->getMessage() . "\n");
    exit(1);
}

$paths = array_merge([$assets['script'] ?? ''], $assets['styles'] ?? []);
if (count($paths) < 2) {
    $failures[] = 'O dashboard deve possuir bundle JavaScript e CSS gerados pelo Vite.';
}

foreach ($paths as $publicPath) {
    if (!is_string($publicPath) || !preg_match('#^/assets/admin-dist/assets/[a-zA-Z0-9_.-]+$#', $publicPath)) {
        $failures[] = 'Caminho de asset inválido ou sem hash: ' . var_export($publicPath, true);
        continue;
    }
    $diskPath = dirname(__DIR__) . '/public' . $publicPath;
    if (!is_file($diskPath) || filesize($diskPath) === 0) {
        $failures[] = 'Asset declarado no manifest não existe ou está vazio: ' . $publicPath;
    }
}

if (!str_ends_with((string) ($assets['script'] ?? ''), '.js')) {
    $failures[] = 'Entrada principal do dashboard deve ser JavaScript.';
}
if (count(array_filter(
    $assets['styles'] ?? [],
    static fn(string $path): bool => str_ends_with($path, '.css')
)) !== 1) {
    $failures[] = 'O manifest deve declarar exatamente um CSS administrativo.';
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas nos assets Vite:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: manifest Vite referencia bundles administrativos versionados e existentes.\n";
