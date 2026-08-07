<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Domain\Exception;

use RuntimeException;

final class ReenvioExportacaoException extends RuntimeException
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
                'A execução #%d não está concluída e não pode ser reenviada.',
                $execucaoId
            )
        );
    }

    public static function arquivoIndisponivel(
        int $execucaoId
    ): self {
        return new self(
            sprintf(
                'O arquivo local da execução #%d não está disponível.',
                $execucaoId
            )
        );
    }

    public static function metadadosIncompletos(
        int $execucaoId
    ): self {
        return new self(
            sprintf(
                'A execução #%d não possui todos os metadados necessários para o reenvio.',
                $execucaoId
            )
        );
    }

    public static function hashDivergente(): self
    {
        return new self(
            'O arquivo local foi alterado desde a exportação original. O SHA-256 não corresponde ao registrado.'
        );
    }

    public static function tamanhoDivergente(): self
    {
        return new self(
            'O tamanho atual do arquivo local diverge do tamanho registrado na exportação original.'
        );
    }
}