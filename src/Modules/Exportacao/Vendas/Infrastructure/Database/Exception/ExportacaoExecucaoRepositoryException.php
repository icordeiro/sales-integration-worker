<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception;

use RuntimeException;
use Throwable;

final class ExportacaoExecucaoRepositoryException extends RuntimeException
{
    public static function persistenceFailed(
        Throwable $previous
    ): self {
        return new self(
            message: 'Não foi possível registrar a execução da exportação.',
            previous: $previous
        );
    }
}