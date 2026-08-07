<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Sftp\Config;

use App\Shared\Infrastructure\Environment\Environment;
use InvalidArgumentException;

final readonly class SftpConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $remoteDirectory,
        public string $hostKeyFingerprint,
        public int $timeout
    ) {
        $this->validate();
    }

    public static function fromEnvironment(
        Environment $environment
    ): self {
        return new self(
            host: $environment->string('SFTP_HOST'),
            port: $environment->integer('SFTP_PORT'),
            username: $environment->string('SFTP_USER'),
            password: $environment->string('SFTP_PASS'),
            remoteDirectory: $environment->string('SFTP_REMOTE_DIR'),
            hostKeyFingerprint: $environment->string(
                'SFTP_HOST_KEY_FINGERPRINT'
            ),
            timeout: $environment->integer('SFTP_TIMEOUT')
        );
    }

    private function validate(): void
    {
        if ($this->host === '') {
            throw new InvalidArgumentException(
                'O host SFTP não pode estar vazio.'
            );
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException(
                'A porta SFTP deve estar entre 1 e 65535.'
            );
        }

        if ($this->username === '') {
            throw new InvalidArgumentException(
                'O usuário SFTP não pode estar vazio.'
            );
        }

        if ($this->password === '') {
            throw new InvalidArgumentException(
                'A senha SFTP não pode estar vazia.'
            );
        }

        if ($this->remoteDirectory === '') {
            throw new InvalidArgumentException(
                'O diretório remoto não pode estar vazio.'
            );
        }

        if ($this->hostKeyFingerprint === '') {
            throw new InvalidArgumentException(
                'O fingerprint do servidor SFTP não pode estar vazio.'
            );
        }

        if ($this->timeout < 1) {
            throw new InvalidArgumentException(
                'O timeout SFTP deve ser maior que zero.'
            );
        }
    }
}