<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Sftp\Exception;

use RuntimeException;
use Throwable;

final class SftpException extends RuntimeException
{
    public static function connectionFailed(
        ?Throwable $previous = null
    ): self {
        return new self(
            message: 'Não foi possível conectar ao servidor SFTP.',
            previous: $previous
        );
    }

    public static function hostKeyUnavailable(): self
    {
        return new self(
            'Não foi possível obter a chave pública do servidor SFTP.'
        );
    }

    public static function hostKeyMismatch(
        string $sha256,
        string $md5
    ): self {
        return new self(
            sprintf(
                'O fingerprint do servidor SFTP não corresponde ao esperado. SHA256 detectado: %s | MD5 detectado: %s',
                $sha256,
                $md5
            )
        );
    }

    public static function authenticationFailed(): self
    {
        return new self(
            'A autenticação no servidor SFTP falhou.'
        );
    }

    public static function remoteDirectoryUnavailable(
        string $directory
    ): self {
        return new self(
            sprintf(
                'O diretório remoto "%s" não está disponível.',
                $directory
            )
        );
    }

    public static function localFileNotFound(
        string $path
    ): self {
        return new self(
            sprintf(
                'O arquivo local "%s" não foi encontrado.',
                $path
            )
        );
    }

    public static function remoteFileAlreadyExists(
        string $fileName
    ): self {
        return new self(
            sprintf(
                'O arquivo remoto "%s" já existe.',
                $fileName
            )
        );
    }

    public static function partialFileCouldNotBeRemoved(): self
    {
        return new self(
            'Não foi possível remover o arquivo parcial existente no SFTP.'
        );
    }

    public static function uploadFailed(
        string $fileName
    ): self {
        return new self(
            sprintf(
                'Falha no upload do arquivo "%s".',
                $fileName
            )
        );
    }

    public static function sizeMismatch(
        int $localSize,
        int $remoteSize
    ): self {
        return new self(
            sprintf(
                'O tamanho do arquivo remoto diverge do arquivo local. Local: %d bytes. Remoto: %d bytes.',
                $localSize,
                $remoteSize
            )
        );
    }

    public static function renameFailed(
        string $fileName
    ): self {
        return new self(
            sprintf(
                'Não foi possível finalizar o arquivo remoto "%s".',
                $fileName
            )
        );
    }

    public static function invalidRemoteFileName(): self
    {
        return new self(
            'O nome do arquivo remoto é inválido.'
        );
    }
}