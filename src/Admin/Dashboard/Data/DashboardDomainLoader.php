<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

interface DashboardDomainLoader
{
    public function key(): string;

    /**
     * @param array<string, mixed> $loadedData
     * @return array<string, mixed>
     */
    public function load(array $loadedData): array;
}
