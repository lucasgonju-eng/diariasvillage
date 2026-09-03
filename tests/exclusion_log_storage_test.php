<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/diarias-exclusion-log-' . bin2hex(random_bytes(6));

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
    copy($root . '/src/ExclusionLog.php', $tempRoot . '/src/ExclusionLog.php');
    file_put_contents(
        $tempRoot . '/exclusions_log.jsonl',
        json_encode(['deleted_at' => '2026-09-01T10:00:00-03:00', 'entity_id' => 'legacy']) . PHP_EOL
    );

    require $tempRoot . '/src/ExclusionLog.php';
    if (!\App\ExclusionLog::append([
        'deleted_at' => '2026-09-03T10:00:00-03:00',
        'entity_id' => 'current',
    ])) {
        throw new RuntimeException('Não foi possível gravar no storage persistente.');
    }

    $preferred = $tempRoot . '/storage/exclusions_log.jsonl';
    if (!is_file($preferred)) {
        throw new RuntimeException('Log novo não foi gravado dentro de storage.');
    }

    $rows = \App\ExclusionLog::load();
    if (array_column($rows, 'entity_id') !== ['current', 'legacy']) {
        throw new RuntimeException('Leitura não conciliou storage e histórico legado em ordem.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha no log persistente de exclusões: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $removeTree($tempRoot);
}

echo "OK: exclusões usam storage persistente e preservam o histórico legado.\n";
