<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception;

use PDOException;
use RuntimeException;

final class VendaExportacaoQueryException extends RuntimeException
{
    public static function couldNotExecute(
        PDOException $exception
    ): self {
        return new self(
            message: 'Não foi possível consultar as vendas para exportação.',
            code: (int) $exception->getCode(),
            previous: $exception
        );
    }
}