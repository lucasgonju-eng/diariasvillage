<?php

namespace App\Models;

final class OficinaModular
{
    public const TABLE = 'oficina_modular';

    public const STATUS_QUORUM_LIVRE = 'LIVRE';
    public const STATUS_QUORUM_EM_QUORUM = 'EM_QUORUM';
    public const STATUS_QUORUM_CONFIRMADA = 'CONFIRMADA';
    public const STATUS_QUORUM_CANCELADA = 'CANCELADA';

    public const TIPO_RECORRENTE = 'RECORRENTE';
    public const TIPO_OCASIONAL_30D = 'OCASIONAL_30D';
    public const TIPO_FIXA = 'FIXA';
}
