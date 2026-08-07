<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class SqliteConnection implements DatabaseConnection
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly PortalSqliteConfig $config
    ) {
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $this->ensureDirectoryExists();

        try {
            $this->pdo = new PDO(
                $this->config->dsn(),
                null,
                null,
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false,

                    PDO::ATTR_STRINGIFY_FETCHES =>
                        false,
                ]
            );

            $this->configure(
                $this->pdo
            );

            return $this->pdo;
        } catch (PDOException $exception) {
            $this->pdo = null;

            throw new RuntimeException(
                'Não foi possível conectar ao banco SQLite do portal.',
                previous: $exception
            );
        }
    }

    public function isConnected(): bool
    {
        try {
            $connection = $this->connection();

            $connection->query(
                'SELECT 1'
            );

            return true;
        } catch (Throwable) {
            $this->pdo = null;

            return false;
        }
    }

    private function ensureDirectoryExists(): void
    {
        $directory = $this->config->directory();

        if (is_dir($directory)) {
            return;
        }

        if (
            !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível criar o diretório do banco SQLite: %s',
                    $directory
                )
            );
        }
    }

    private function configure(
        PDO $connection
    ): void {
        /*
         * Foreign keys não vêm habilitadas
         * por padrão em todas as conexões SQLite.
         */
        $connection->exec(
            'PRAGMA foreign_keys = ON'
        );

        /*
         * Aguarda até 5 segundos se outro processo
         * estiver gravando no banco.
         */
        $connection->exec(
            'PRAGMA busy_timeout = 5000'
        );

        /*
         * WAL permite leitura enquanto o worker
         * estiver realizando gravações.
         */
        $statement = $connection->query(
            'PRAGMA journal_mode = WAL'
        );

        $journalMode = $statement !== false
            ? $statement->fetchColumn()
            : false;

        if (
            !is_string($journalMode)
            || strtolower($journalMode) !== 'wal'
        ) {
            throw new RuntimeException(
                'Não foi possível habilitar WAL no banco SQLite.'
            );
        }

        /*
         * Bom equilíbrio entre segurança e desempenho
         * quando utilizando WAL.
         */
        $connection->exec(
            'PRAGMA synchronous = NORMAL'
        );
    }
}