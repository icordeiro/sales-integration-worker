<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Domain\Enum;

enum StatusExportacao: string
{
    case AGUARDANDO = 'AGUARDANDO';

    case CONSULTANDO = 'CONSULTANDO';

    case GERANDO_ARQUIVO = 'GERANDO_ARQUIVO';

    case VALIDANDO = 'VALIDANDO';

    case ENVIANDO = 'ENVIANDO';

    case CONFIRMANDO_ENVIO = 'CONFIRMANDO_ENVIO';

    case CONCLUIDO = 'CONCLUIDO';

    case FALHOU = 'FALHOU';

    case CANCELADO = 'CANCELADO';
}