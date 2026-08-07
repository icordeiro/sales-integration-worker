<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Domain\Exception;

use RuntimeException;

final class ReprocessamentoExportacaoException extends RuntimeException
{
    public static function execucaoNaoEncontrada(
        int $execucaoId
    ): self {
        return new self(
            sprintf(
                'A execução #%d não foi encontrada.',
                $execucaoId
            )
        );
    }

    public static function execucaoNaoConcluida(
        int $execucaoId
    ): self {
        return new self(
            sprintf(
                'A execução #%d não está concluída e não pode ser usada como origem de reprocessamento.',
                $execucaoId
            )
        );
    }

    public static function hashOriginalIndisponivel(
        int $execucaoId
    ): self {
        return new self(
            sprintf(
                'A execução #%d não possui SHA-256 registrado.',
                $execucaoId
            )
        );
    }

    public static function dataMovimentoInvalida(
        string $dataMovimento
    ): self {
        return new self(
            sprintf(
                'A data de movimento "%s" registrada na execução de origem é inválida.',
                $dataMovimento
            )
        );
    }
}