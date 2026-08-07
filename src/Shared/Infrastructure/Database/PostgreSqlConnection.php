<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\Config\ClientDatabaseConfig;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use App\Shared\Infrastructure\Database\Exception\DatabaseConnectionException;
use PDO;
use PDOException;

final class PostgreSqlConnection implements DatabaseConnection
{
    private static ?self $instance = null;

    private ?PDO $pdo = null;

    private readonly string $configurationFingerprint;

    private function __construct(
        private readonly ClientDatabaseConfig $config
    ) {
        $this->configurationFingerprint = $config->fingerprint();
    }

    private function __clone(): void
    {
    }

    public function __wakeup(): void
    {
        throw new DatabaseConnectionException(
            'A conexão com o banco não pode ser desserializada.'
        );
    }

    public static function instance(
        ClientDatabaseConfig $config
    ): self {
        if (self::$instance === null) {
            self::$instance = new self($config);

            return self::$instance;
        }

        if (
            self::$instance->configurationFingerprint
            !== $config->fingerprint()
        ) {
            throw DatabaseConnectionException::
                alreadyInitializedWithAnotherConfiguration();
        }

        return self::$instance;
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->pdo = $this->createConnection();

        return $this->pdo;
    }

    public function isConnected(): bool
    {
        if (!$this->pdo instanceof PDO) {
            return false;
        }

        try {
            $statement = $this->pdo->query('SELECT 1');

            return $statement !== false;
        } catch (PDOException) {
            $this->pdo = null;

            return false;
        }
    }

    private function createConnection(): PDO
    {
        try {
            $pdo = new PDO(
                dsn: $this->config->dsn(),
                username: $this->config->username,
                password: $this->config->password,
                options: [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_PERSISTENT => false,
                ]
            );

            $pdo->exec("SET TIME ZONE 'America/Fortaleza'");
            $pdo->exec("SET CLIENT_ENCODING TO 'UTF8'");

            return $pdo;
        } catch (PDOException $exception) {
            throw DatabaseConnectionException::couldNotConnect(
                $exception
            );
        }
    }
}