<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Admin\Dashboard\Data\ConfirmedEntriesLoader;
use App\Admin\Dashboard\Data\DashboardDataLoader;
use App\Admin\Dashboard\Data\DashboardDomainLoader;
use App\Admin\Dashboard\Data\FamiliesLoader;
use App\Admin\Dashboard\Data\MissingWhatsappLoader;
use App\Admin\Dashboard\Data\MonthlyPlansLoader;
use App\Admin\Dashboard\Data\PrincipalFinancialLoader;
use App\Admin\Dashboard\Data\PrincipalGovernanceLoader;

$failures = [];
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message
            . "\n  esperado: " . var_export($expected, true)
            . "\n  recebido: " . var_export($actual, true);
    }
};
$assertTrue = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$sharedKeys = [
    MonthlyPlansLoader::KEY,
    FamiliesLoader::KEY,
    MissingWhatsappLoader::KEY,
    ConfirmedEntriesLoader::KEY,
];
$principalOnlyKeys = [
    PrincipalFinancialLoader::KEY,
    PrincipalGovernanceLoader::KEY,
];

$assertSame(
    $sharedKeys,
    DashboardDataLoader::loaderKeysForRole('secretaria'),
    'A matriz da secretaria deve conter somente loaders das cinco views permitidas.'
);
$assertSame(
    array_merge($sharedKeys, $principalOnlyKeys),
    DashboardDataLoader::loaderKeysForRole('admin_principal'),
    'O administrador principal deve manter loaders compartilhados e restritos.'
);

$makeLoader = static function (string $key, array &$calls): DashboardDomainLoader {
    return new class($key, static function (string $calledKey) use (&$calls): void {
        $calls[] = $calledKey;
    }) implements DashboardDomainLoader {
        public function __construct(
            private readonly string $loaderKey,
            private readonly Closure $record
        ) {
        }

        public function key(): string
        {
            return $this->loaderKey;
        }

        public function load(array $loadedData): array
        {
            ($this->record)($this->loaderKey);
            return [
                'loaded_' . $this->loaderKey => true,
                '__internal_' . $this->loaderKey => true,
            ];
        }
    };
};

$allKeys = array_merge($sharedKeys, $principalOnlyKeys);
$secretariaCalls = [];
$secretariaLoaders = [];
foreach ($allKeys as $key) {
    $secretariaLoaders[] = $makeLoader($key, $secretariaCalls);
}
$secretariaData = (new DashboardDataLoader($secretariaLoaders))->loadForRole('secretaria');

$assertSame(
    $sharedKeys,
    $secretariaCalls,
    'Carregar a secretaria não pode acionar loaders financeiros ou de governança.'
);
foreach ($principalOnlyKeys as $principalOnlyKey) {
    $assertTrue(
        !isset($secretariaData['loaded_' . $principalOnlyKey]),
        'Loader restrito foi refletido no contexto da secretaria: ' . $principalOnlyKey
    );
}
$assertTrue(
    count(array_filter(
        array_keys($secretariaData),
        static fn(string $key): bool => str_starts_with($key, '__')
    )) === 0,
    'Chaves internas de coordenação não devem chegar às views.'
);

$familiesSource = file_get_contents(
    dirname(__DIR__) . '/src/Admin/Dashboard/Data/FamiliesLoader.php'
) ?: '';
$governanceSource = file_get_contents(
    dirname(__DIR__) . '/src/Admin/Dashboard/Data/PrincipalGovernanceLoader.php'
) ?: '';
$assertTrue(
    str_contains($familiesSource, "'&id=in.('")
        && str_contains($familiesSource, "'select=id,parent_name,parent_document'"),
    'Loader da secretaria deve buscar somente responsáveis citados nas solicitações pendentes.'
);
$assertTrue(
    !str_contains($familiesSource, '&parent_document=not.is.null&order=created_at.desc&limit=10000'),
    'Loader compartilhado não pode carregar toda a base de responsáveis.'
);
$assertTrue(
    str_contains($governanceSource, '&parent_document=not.is.null&order=created_at.desc&limit=10000'),
    'Varredura de duplicidade deve permanecer exclusiva do loader de governança.'
);

$principalCalls = [];
$principalLoaders = [];
foreach ($allKeys as $key) {
    $principalLoaders[] = $makeLoader($key, $principalCalls);
}
(new DashboardDataLoader($principalLoaders))->loadForRole('admin_principal');
$assertSame(
    $allKeys,
    $principalCalls,
    'O administrador principal deve executar todos os loaders na ordem declarada.'
);

if ($failures !== []) {
    fwrite(STDERR, "Falhas nos loaders do dashboard:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK: matriz de loaders separa secretaria dos domínios financeiros e administrativos.\n";
