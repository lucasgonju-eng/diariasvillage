<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use App\SupabaseClient;

abstract class AbstractSupabaseDashboardLoader implements DashboardDomainLoader
{
    public function __construct(protected readonly SupabaseClient $client)
    {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<int, array<string, mixed>>
     */
    protected function rows(array $result, bool $requireSuccess = false): array
    {
        if ($requireSuccess && !($result['ok'] ?? false)) {
            return [];
        }

        if (!is_array($result['data'] ?? null)) {
            return [];
        }

        return array_values(array_filter($result['data'], 'is_array'));
    }
}
