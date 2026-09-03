<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\SupabaseClient;
use InvalidArgumentException;
use LogicException;

final class DashboardDataLoader
{
    public const ROLE_LOADERS = [
        'admin_principal' => [
            MonthlyPlansLoader::KEY,
            FamiliesLoader::KEY,
            MissingWhatsappLoader::KEY,
            ConfirmedEntriesLoader::KEY,
            PrincipalFinancialLoader::KEY,
            PrincipalGovernanceLoader::KEY,
        ],
        'secretaria' => [
            MonthlyPlansLoader::KEY,
            FamiliesLoader::KEY,
            MissingWhatsappLoader::KEY,
            ConfirmedEntriesLoader::KEY,
        ],
    ];

    /** @var array<string, DashboardDomainLoader> */
    private array $loaders = [];

    /**
     * @param iterable<DashboardDomainLoader> $loaders
     */
    public function __construct(iterable $loaders)
    {
        foreach ($loaders as $loader) {
            $key = $loader->key();
            if (isset($this->loaders[$key])) {
                throw new InvalidArgumentException('Loader duplicado no dashboard: ' . $key);
            }
            $this->loaders[$key] = $loader;
        }
    }

    public static function create(SupabaseClient $client): self
    {
        return new self([
            new MonthlyPlansLoader(),
            new FamiliesLoader($client),
            new MissingWhatsappLoader($client),
            new ConfirmedEntriesLoader($client),
            new PrincipalFinancialLoader($client),
            new PrincipalGovernanceLoader($client),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function loaderKeysForRole(string $role): array
    {
        if (!isset(self::ROLE_LOADERS[$role])) {
            throw new InvalidArgumentException('Papel administrativo inválido para o dashboard.');
        }
        return self::ROLE_LOADERS[$role];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadForRole(string $role): array
    {
        $data = self::defaults();
        foreach (self::loaderKeysForRole($role) as $loaderKey) {
            if (!isset($this->loaders[$loaderKey])) {
                throw new LogicException('Loader obrigatório não registrado: ' . $loaderKey);
            }
            $data = array_replace($data, $this->loaders[$loaderKey]->load($data));
        }

        foreach (array_keys($data) as $key) {
            if (str_starts_with((string) $key, '__')) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'payments' => [],
            'monthlyEntries' => [],
            'queuedPending' => [],
            'manualPending' => [],
            'monthlyRowsForJs' => [],
            'monthlySubmissions' => [],
            'inadimplentesMonthlyMetaById' => [],
            'manualPaid' => [],
            'missingWhatsapp' => [],
            'pendencias' => [],
            'pendenciasPagas' => [],
            'studentsById' => [],
            'studentsByEnrollment' => [],
            'studentsForJs' => [],
            'duplicateGroups' => [],
            'duplicateEnrollmentGroups' => [],
            'cpfDuplicateGroups' => [],
            'guardiansById' => [],
            'familyLinkRequests' => [],
            'exclusionsLog' => [],
            'secretariaAccount' => null,
            'valorPendencia' => 77.00,
        ];
    }
}
