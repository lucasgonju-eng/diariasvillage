<?php
declare(strict_types=1);

namespace App\Admin\Dashboard\Data;

use InvalidArgumentException;

final class DashboardDefinition
{
    /**
     * @return array<string, array{label: string, partial: string}>
     */
    public static function tabs(): array
    {
        return [
            'charges' => ['label' => 'Cobrança manual', 'partial' => 'charges.php'],
            'chamada' => ['label' => 'Chamada', 'partial' => 'chamada.php'],
            'familias' => ['label' => 'Famílias', 'partial' => 'familias.php'],
            'inadimplentes' => ['label' => 'Cobranças em aberto', 'partial' => 'inadimplentes.php'],
            'recebidas' => ['label' => 'Cobranças recebidas', 'partial' => 'recebidas.php'],
            'sem-whatsapp' => ['label' => 'Sem WhatsApp', 'partial' => 'sem-whatsapp.php'],
            'pendencias' => ['label' => 'Pendência de cadastro', 'partial' => 'pendencias.php'],
            'mensalistas' => ['label' => 'Mensalistas', 'partial' => 'mensalistas.php'],
            'oficinas-modulares' => ['label' => 'Oficinas Modulares', 'partial' => 'oficinas-modulares.php'],
            'exclusoes' => ['label' => 'Exclusões', 'partial' => 'exclusoes.php'],
            'duplicados' => ['label' => 'Duplicados', 'partial' => 'duplicados.php'],
            'reset-senha' => ['label' => 'Resetar senha', 'partial' => 'reset-senha.php'],
            'acesso-secretaria' => ['label' => 'Acesso da Secretaria', 'partial' => 'acesso-secretaria.php'],
            'fluxo-caixa' => ['label' => 'Fluxo de Caixa', 'partial' => 'fluxo-caixa.php'],
            'dados-asaas' => ['label' => 'Dados do Asaas', 'partial' => 'dados-asaas.php'],
            'email-massa' => ['label' => 'Enviar E-mails em Massa', 'partial' => 'email-massa.php'],
            'entries' => ['label' => 'Entradas confirmadas', 'partial' => 'entries.php'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function tabsForRole(string $role): array
    {
        if ($role === 'admin_principal') {
            return array_keys(self::tabs());
        }
        if ($role === 'secretaria') {
            return ['chamada', 'familias', 'sem-whatsapp', 'mensalistas', 'entries'];
        }
        throw new InvalidArgumentException('Papel administrativo sem acesso ao dashboard.');
    }

    public static function defaultTab(string $role): string
    {
        return $role === 'admin_principal' ? 'charges' : 'entries';
    }
}
