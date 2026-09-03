<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $path) use (&$failures): string {
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $failures[] = 'Arquivo ausente ou ilegível: ' . $path;
        return '';
    }
    return $content;
};
$contains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (!str_contains($content, $needle)) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
};
$notContains = static function (string $label, string $content, string $needle) use (&$failures): void {
    if (str_contains($content, $needle)) {
        $failures[] = $label . ' não deveria conter: ' . $needle;
    }
};
$order = static function (string $label, string $content, string $first, string $second) use (&$failures): void {
    $firstPosition = strpos($content, $first);
    $secondPosition = strpos($content, $second);
    if ($firstPosition === false || $secondPosition === false || $firstPosition >= $secondPosition) {
        $failures[] = $label . ' está fora da ordem segura.';
    }
};

$workflow = $read($root . '/.github/workflows/deploy-hostinger.yml');
$dispatcher = $read($root . '/ops/hostinger-root.htaccess');
$health = $read($root . '/public/health.php');
$bulkEmail = $read($root . '/public/api/admin-bulk-email.php');
$bootstrap = $read($root . '/src/Bootstrap.php');

$contains('workflow', $workflow, 'cancel-in-progress: false');
$contains('workflow', $workflow, '${{ github.run_attempt }}');
$contains('workflow', $workflow, 'target: "${{ secrets.SSH_TARGET_DIR }}/.releases/${{ env.RELEASE_ID }}"');
$contains('workflow', $workflow, 'mv -Tf "$NEXT_LINK" "$CURRENT"');
$contains('workflow', $workflow, 'Reverter ativação após falha');
$contains('workflow', $workflow, 'mv -Tf "$ROLLBACK_LINK" "$CURRENT"');
$contains('workflow', $workflow, 'ln -s "../../storage" "$RELEASE/storage"');
$contains('workflow', $workflow, 'ln -s "../../../.env" "$RELEASE/.env"');
$contains('workflow', $workflow, 'test ! -e "$RELEASE/storage"');
$contains('workflow', $workflow, 'test ! -e "$RELEASE/.env"');
$contains('workflow', $workflow, 'Verificar produção pela Internet');
$contains('workflow', $workflow, 'php tests/deploy_release_security_test.php');
$contains('workflow', $workflow, 'mv -f "$TARGET/.htaccess.rollback" "$TARGET/.htaccess"');
$contains('workflow', $workflow, "-name '*.bak.*'");
$notContains('workflow', $workflow, 'cp .env.example deploy/');
$order(
    'release deve ser validada antes da troca do symlink',
    $workflow,
    'HEALTH_OUTPUT="$(php "$RELEASE/health.php")"',
    'mv -Tf "$NEXT_LINK" "$CURRENT"'
);
$order(
    'marcador de rollback deve anteceder a troca',
    $workflow,
    'touch "$RELEASE/.activation-started"',
    'mv -Tf "$NEXT_LINK" "$CURRENT"'
);

$contains('dispatcher', $dispatcher, 'RewriteCond %{THE_REQUEST} "\s/+[^?\s]*%" [NC]');
$contains(
    'dispatcher',
    $dispatcher,
    'RewriteCond %{THE_REQUEST} "\s/+\.releases(?:[/\s?]|$)" [NC,OR]'
);
$contains(
    'dispatcher',
    $dispatcher,
    'RewriteCond %{THE_REQUEST} "\s/+storage(?:[/\s?]|$)" [NC]'
);
$contains('dispatcher', $dispatcher, 'RewriteRule ^(.*)$ .releases/current/$1 [L]');
$contains('dispatcher', $dispatcher, 'RewriteRule ^\.well-known(?:/|$) - [L]');

$contains('health', $health, "'release_manifest_missing'");
$contains('health', $health, "'release_incomplete'");
$contains('health', $health, "define('APP_HEALTH_CHECK', true)");
$contains('health', $health, "require_once __DIR__ . '/src/Bootstrap.php'");
$contains('health', $health, "'SUPABASE_SERVICE_ROLE_KEY'");
$contains('health', $health, "'environment_incomplete'");
$contains('health', $health, "header('Cache-Control: no-store, max-age=0')");
$notContains('health', $health, 'file_put_contents');
$notContains('health', $health, "ini_set('display_errors'");
$contains('bootstrap', $bootstrap, "!defined('APP_HEALTH_CHECK') && session_status()");

$contains(
    'estado persistente do e-mail',
    $bulkEmail,
    "return dirname(__DIR__) . '/storage/admin_bulk_email_templates.json';"
);
$notContains(
    'estado persistente do e-mail',
    $bulkEmail,
    "dirname(__DIR__, 2) . '/storage/admin_bulk_email_templates.json'"
);

$blockedArtifactPattern = '/\.(?:bak|bkp|old|orig|save|swp|sql|zip|tar|gz|7z)(?:\.|$)/i';
$publicFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $root . '/public',
        FilesystemIterator::SKIP_DOTS
    )
);
foreach ($publicFiles as $publicFile) {
    if ($publicFile->isFile() && preg_match($blockedArtifactPattern, $publicFile->getFilename()) === 1) {
        $failures[] = 'Artefato proibido seria incluído na release: ' . $publicFile->getPathname();
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas de segurança do deploy:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: release atômica, rollback e estado persistente protegidos.\n";
