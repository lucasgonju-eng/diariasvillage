<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

final class MissingWhatsappLoader extends AbstractSupabaseDashboardLoader
{
    public const KEY = 'missing_whatsapp';

    public function key(): string
    {
        return self::KEY;
    }

    public function load(array $loadedData): array
    {
        $rows = $this->rows($this->client->select(
            'guardians',
            'select=parent_name,email,parent_phone,parent_document,students(name,enrollment)'
                . '&or=(parent_phone.is.null,parent_phone.eq.)&order=created_at.desc&limit=500'
        ));
        DashboardSort::byStudentName(
            $rows,
            static fn(array $row): string => (string) (($row['students']['name'] ?? '') ?: '')
        );

        return ['missingWhatsapp' => $rows];
    }
}
