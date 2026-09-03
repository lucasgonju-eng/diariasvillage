<?php

namespace App;

use RuntimeException;

final class ViteAssets
{
    private const PUBLIC_PREFIX = '/assets/admin-dist/';
    private const MANIFEST_ENTRY = 'frontend/admin/main.ts';

    /**
     * @return array{script:string,styles:list<string>}
     */
    public static function adminDashboard(): array
    {
        $appRoot = dirname(__DIR__);
        $publicRoot = is_dir($appRoot . '/public') ? $appRoot . '/public' : $appRoot;
        $manifestPath = $publicRoot . '/assets/admin-dist/manifest.json';
        $assetRoot = dirname($manifestPath) . '/';
        $raw = @file_get_contents($manifestPath);
        if (!is_string($raw) || trim($raw) === '') {
            throw new RuntimeException('Manifest dos assets administrativos não encontrado.');
        }

        $manifest = json_decode($raw, true);
        $entry = is_array($manifest) ? ($manifest[self::MANIFEST_ENTRY] ?? null) : null;
        if (!is_array($entry) || empty($entry['file'])) {
            throw new RuntimeException('Entrada administrativa ausente no manifest de assets.');
        }

        $cssFiles = $entry['css'] ?? null;
        if (!is_array($cssFiles) || $cssFiles === []) {
            throw new RuntimeException('Bundle CSS administrativo ausente no manifest.');
        }

        $styles = [];
        foreach ($cssFiles as $cssFile) {
            if (
                !is_string($cssFile)
                || !str_ends_with($cssFile, '.css')
                || !self::isSafeAssetPath($cssFile)
                || !is_file($assetRoot . $cssFile)
                || filesize($assetRoot . $cssFile) < 1
            ) {
                throw new RuntimeException('Bundle CSS administrativo ausente ou inválido.');
            }
            $styles[] = self::PUBLIC_PREFIX . $cssFile;
        }

        $scriptFile = (string) $entry['file'];
        if (
            !str_ends_with($scriptFile, '.js')
            || !self::isSafeAssetPath($scriptFile)
            || !is_file($assetRoot . $scriptFile)
            || filesize($assetRoot . $scriptFile) < 1
        ) {
            throw new RuntimeException('Bundle JavaScript administrativo ausente ou inválido.');
        }

        return [
            'script' => self::PUBLIC_PREFIX . $scriptFile,
            'styles' => $styles,
        ];
    }

    private static function isSafeAssetPath(string $path): bool
    {
        return $path !== ''
            && !str_contains($path, '..')
            && preg_match('/^[a-zA-Z0-9_\/.-]+$/', $path) === 1;
    }
}
