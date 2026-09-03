<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/diarias-vite-release-' . bin2hex(random_bytes(6));

$copyTree = static function (string $source, string $destination) use (&$copyTree): void {
    if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException('Não foi possível criar diretório temporário.');
    }
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $target = $destination . DIRECTORY_SEPARATOR . $item->getFilename();
        if ($item->isDir()) {
            $copyTree($item->getPathname(), $target);
            continue;
        }
        if (!copy($item->getPathname(), $target)) {
            throw new RuntimeException('Não foi possível copiar asset temporário.');
        }
    }
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot()) {
            continue;
        }
        if ($item->isDir()) {
            $removeTree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
};

try {
    mkdir($tempRoot . '/src', 0700, true);
    copy($root . '/src/ViteAssets.php', $tempRoot . '/src/ViteAssets.php');
    $copyTree($root . '/public/assets/admin-dist', $tempRoot . '/assets/admin-dist');

    require $tempRoot . '/src/ViteAssets.php';
    $assets = \App\ViteAssets::adminDashboard();
    if (empty($assets['script']) || empty($assets['styles'])) {
        throw new RuntimeException('Resolvedor não encontrou assets na release achatada.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha na simulação da release achatada: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $removeTree($tempRoot);
}

echo "OK: release achatada resolve os assets Vite gerados.\n";
