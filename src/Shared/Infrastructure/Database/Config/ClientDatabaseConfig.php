<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Config;

use App\Shared\Infrastructure\Environment\Environment;
use InvalidArgumentException;

final readonly class ClientDatabaseConfig
{
    private const MINIMUM_PORT = 1;
    private const MAXIMUM_PORT = 65535;

    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password
    ) {
        $this->validate();
    }

    public static function fromEnvironment(
        Environment $environment
    ): self {
        return new self(
            host: $environment->string('DB_HOST'),
            port: $environment->integer('DB_PORT'),
            database: $environment->string('DB_NAME'),
            username: $environment->string('DB_USER'),
            password: $environment->string('DB_PASS')
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->host,
            $this->port,
            $this->database
        );
    }

    /**
     * Identifica a configuração sem expor a senha.
     */
    public function fingerprint(): string
    {
        return hash(
            'sha256',
            implode('|', [
                $this->host,
                (string) $this->port,
                $this->database,
                $this->username,
            ])
        );
    }

    private function validate(): void
    {
        if (trim($this->host) === '') {
            throw new InvalidArgumentException(
                'O host do banco não pode estar vazio.'
            );
        }

        if (
            $this->port < self::MINIMUM_PORT
            || $this->port > self::MAXIMUM_PORT
        ) {
            throw new InvalidArgumentException(
                'A porta do banco deve estar entre 1 e 65535.'
            );
        }

        if (trim($this->database) === '') {
            throw new InvalidArgumentException(
                'O nome do banco não pode estar vazio.'
            );
        }

        if (trim($this->username) === '') {
            throw new InvalidArgumentException(
                'O usuário do banco não pode estar vazio.'
            );
        }
    }
}