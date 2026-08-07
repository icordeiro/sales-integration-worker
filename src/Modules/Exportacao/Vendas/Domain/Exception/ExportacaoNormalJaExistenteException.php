<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Domain\Exception;

use RuntimeException;

final class ExportacaoNormalJaExistenteException extends RuntimeException
{
    public static function paraMovimento(
        string $dataMovimento
    ): self {
        return new self(
            sprintf(
                'O movimento %s já possui uma execução normal em andamento ou concluída.',
                $dataMovimento
            )
        );
    }
}