<?php
declare(strict_types=1);

namespace App;

final class ExclusionLog
{
    public static function append(array $entry): bool
    {
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return false;
        }

        $path = self::preferredPath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function load(int $limit = 500): array
    {
        $rows = [];
        $seenLines = [];
        foreach (self::readPaths() as $path) {
            if (!is_file($path)) {
                continue;
            }
            foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '' || isset($seenLines[$line])) {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (!is_array($decoded)) {
                    continue;
                }
                $seenLines[$line] = true;
                $rows[] = $decoded;
            }
        }

        usort($rows, static fn(array $a, array $b): int =>
            (strtotime((string) ($b['deleted_at'] ?? '')) ?: 0)
            <=>
            (strtotime((string) ($a['deleted_at'] ?? '')) ?: 0)
        );

        return array_slice($rows, 0, max(1, $limit));
    }

    private static function preferredPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl';
    }

    /**
     * @return list<string>
     */
    private static function readPaths(): array
    {
        $projectRoot = dirname(__DIR__);
        return array_values(array_unique([
            self::preferredPath(),
            $projectRoot . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl',
            dirname($projectRoot) . DIRECTORY_SEPARATOR . 'exclusions_log.jsonl',
        ]));
    }
}
