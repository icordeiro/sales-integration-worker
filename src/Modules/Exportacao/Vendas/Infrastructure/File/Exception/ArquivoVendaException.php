<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\File\Exception;

use RuntimeException;
use Throwable;

final class ArquivoVendaException extends RuntimeException
{
    public static function directoryCouldNotBeCreated(
        string $directory
    ): self {
        return new self(
            sprintf(
                'Não foi possível criar o diretório de exportação "%s".',
                $directory
            )
        );
    }

    public static function fileAlreadyExists(
        string $fileName
    ): self {
        return new self(
            sprintf(
                'Já existe um arquivo concluído com o nome "%s".',
                $fileName
            )
        );
    }

    public static function fileCouldNotBeOpened(
        string $fileName
    ): self {
        return new self(
            sprintf(
                'Não foi possível abrir o arquivo "%s" para escrita.',
                $fileName
            )
        );
    }

    public static function writeFailed(): self
    {
        return new self(
            'Ocorreu uma falha durante a escrita do arquivo de vendas.'
        );
    }

    public static function finalizeFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            message: 'Não foi possível finalizar o arquivo de vendas.',
            previous: $previous
        );
    }

    public static function hashFailed(): self
    {
        return new self(
            'Não foi possível calcular o hash do arquivo de vendas.'
        );
    }

    public static function sizeCouldNotBeDetermined(): self
    {
        return new self(
            'Não foi possível determinar o tamanho do arquivo de vendas.'
        );
    }
}