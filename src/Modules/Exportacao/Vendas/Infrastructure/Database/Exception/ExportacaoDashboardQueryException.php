<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception;

use RuntimeException;
use Throwable;

final class ExportacaoDashboardQueryException extends RuntimeException
{
    public static function couldNotRetrieve(
        Throwable $previous
    ): self {
        return new self(
            message: 'Não foi possível consultar os dados do dashboard de exportações.',
            previous: $previous
        );
    }
}