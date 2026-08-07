<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Sftp;

use App\Shared\Application\Contracts\RemoteFileStorage;
use App\Shared\Application\DTO\RemoteFileUploadResult;
use App\Shared\Infrastructure\Sftp\Config\SftpConfig;
use App\Shared\Infrastructure\Sftp\Exception\SftpException;
use DateTimeImmutable;
use DateTimeZone;
use phpseclib3\Net\SFTP;
use Throwable;

final class PhpseclibRemoteFileStorage implements RemoteFileStorage
{
    private ?SFTP $connection = null;

    public function __construct(
        private readonly SftpConfig $config,
        private readonly SftpHostKeyVerifier $hostKeyVerifier
    ) {
    }

    public function testConnection(): void
    {
        $this->connection();
    }

    public function uploadAtomically(
        string $localPath,
        string $remoteFileName
    ): RemoteFileUploadResult {
        if (!is_file($localPath)) {
            throw SftpException::localFileNotFound(
                $localPath
            );
        }

        $this->assertValidRemoteFileName(
            $remoteFileName
        );

        $sftp = $this->connection();

        $finalRemotePath = $this->buildRemotePath(
            $remoteFileName
        );

        $partialRemotePath = $finalRemotePath
            . '.part';

        /*
         * Não sobrescrevemos silenciosamente um arquivo
         * já concluído.
         */
        if ($sftp->file_exists($finalRemotePath)) {
            throw SftpException::remoteFileAlreadyExists(
                $remoteFileName
            );
        }

        /*
         * Equivalente conceitual ao:
         *
         * -resumesupport=off
         *
         * Sempre começamos um upload limpo.
         */
        if ($sftp->file_exists($partialRemotePath)) {
            if (!$sftp->delete($partialRemotePath)) {
                throw SftpException::
                    partialFileCouldNotBeRemoved();
            }
        }

        $localSize = filesize(
            $localPath
        );

        if ($localSize === false) {
            throw SftpException::uploadFailed(
                $remoteFileName
            );
        }

        $uploaded = $sftp->put(
            $partialRemotePath,
            $localPath,
            SFTP::SOURCE_LOCAL_FILE
        );

        if (!$uploaded) {
            throw SftpException::uploadFailed(
                $remoteFileName
            );
        }

        $remoteStat = $sftp->stat(
            $partialRemotePath
        );

        $remoteSize = is_array($remoteStat)
            ? (int) ($remoteStat['size'] ?? -1)
            : -1;

        if ($remoteSize !== $localSize) {
            if ($sftp->file_exists($partialRemotePath)) {
                $sftp->delete(
                    $partialRemotePath
                );
            }

            throw SftpException::sizeMismatch(
                $localSize,
                $remoteSize
            );
        }

        /*
         * Só depois do upload completo e tamanho validado
         * o arquivo recebe o nome definitivo.
         */
        if (
            !$sftp->rename(
                $partialRemotePath,
                $finalRemotePath
            )
        ) {
            throw SftpException::renameFailed(
                $remoteFileName
            );
        }

        if (!$sftp->file_exists($finalRemotePath)) {
            throw SftpException::renameFailed(
                $remoteFileName
            );
        }

        return new RemoteFileUploadResult(
            fileName: $remoteFileName,
            remotePath: $finalRemotePath,
            sizeBytes: $localSize,
            uploadedAt: new DateTimeImmutable(
                'now',
                new DateTimeZone(
                    'America/Fortaleza'
                )
            )
        );
    }

    private function connection(): SFTP
    {
        if ($this->connection instanceof SFTP) {
            return $this->connection;
        }

        try {
            $sftp = new SFTP(
                $this->config->host,
                $this->config->port,
                $this->config->timeout
            );

            /*
             * O fingerprint antigo é RSA.
             *
             * Priorizamos RSA com SHA-2 e deixamos
             * ssh-rsa por último apenas para compatibilidade
             * com servidores legados.
             */
            $sftp->setPreferredAlgorithms([
                'hostkey' => implode(
                    ',',
                    [
                        'rsa-sha2-512',
                        'rsa-sha2-256',
                        'ssh-rsa',
                    ]
                ),
            ]);

            $serverPublicKey = $sftp
                ->getServerPublicHostKey();

            if (
                !is_string($serverPublicKey)
                || $serverPublicKey === ''
            ) {
                throw SftpException::
                    hostKeyUnavailable();
            }

            /*
             * Valida ANTES de enviar usuário/senha.
             */
            $this->hostKeyVerifier->verify(
                $serverPublicKey,
                $this->config->hostKeyFingerprint
            );

            if (
                !$sftp->login(
                    $this->config->username,
                    $this->config->password
                )
            ) {
                throw SftpException::
                    authenticationFailed();
            }

            if (
                !$sftp->is_dir(
                    $this->config->remoteDirectory
                )
            ) {
                throw SftpException::
                    remoteDirectoryUnavailable(
                        $this->config->remoteDirectory
                    );
            }

            $this->connection = $sftp;

            return $this->connection;
        } catch (SftpException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw SftpException::connectionFailed(
                $exception
            );
        }
    }

    private function buildRemotePath(
        string $fileName
    ): string {
        return rtrim(
            $this->config->remoteDirectory,
            '/'
        )
            . '/'
            . $fileName;
    }

    private function assertValidRemoteFileName(
        string $fileName
    ): void {
        if (
            $fileName === ''
            || str_contains($fileName, '/')
            || str_contains($fileName, '\\')
            || $fileName !== basename($fileName)
        ) {
            throw SftpException::
                invalidRemoteFileName();
        }
    }
}