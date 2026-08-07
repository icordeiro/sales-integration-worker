<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Domain\Enum;

enum TipoExecucao: string
{
    case NORMAL = 'NORMAL';

    case REPROCESSAMENTO = 'REPROCESSAMENTO';

    case REENVIO = 'REENVIO';
}