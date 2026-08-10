<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use Throwable;

final readonly class MigrationHealthProbe implements HealthProbe
{
    public function __construct(
        private DatabaseConnection $database,
        private string $migrationsDirectory
    ) {
    }

    public function key(): string
    {
        return 'migrations';
    }

    public function check(): HealthCheckDTO
    {
        try {
            if (!is_dir($this->migrationsDirectory)) {
                return new HealthCheckDTO(
                    status: HealthStatus::ERROR,
                    message: 'Diretório de migrations não encontrado.'
                );
            }

            $migrationFiles = glob(
                $this->migrationsDirectory
                . DIRECTORY_SEPARATOR
                . '*.sql'
            );

            if ($migrationFiles === false) {
                return new HealthCheckDTO(
                    status: HealthStatus::ERROR,
                    message: 'Não foi possível listar as migrations.'
                );
            }

            $available = array_map(
                static fn (string $file): string => basename($file),
                $migrationFiles
            );
            sort($available, SORT_STRING);

            $connection = $this->database->connection();

            $tableStatement = $connection->query(
                <<<'SQL'
                SELECT EXISTS (
                    SELECT 1
                    FROM sqlite_master
                    WHERE type = 'table'
                      AND name = 'schema_migration'
                )
                SQL
            );

            if ($tableStatement === false) {
                throw new \RuntimeException('Não foi possível consultar o controle de migrations.');
            }

            $tableExists = (bool) $tableStatement->fetchColumn();

            if (!$tableExists) {
                return new HealthCheckDTO(
                    status: HealthStatus::ERROR,
                    message: 'Controle de migrations ainda não foi inicializado.',
                    details: [
                        'available' => count($available),
                        'executed' => 0,
                        'pending' => count($available),
                    ]
                );
            }

            $executedStatement = $connection->query(
                'SELECT migration FROM schema_migration ORDER BY migration ASC'
            );

            if ($executedStatement === false) {
                throw new \RuntimeException('Não foi possível consultar as migrations executadas.');
            }

            $executed = $executedStatement->fetchAll(\PDO::FETCH_COLUMN);

            $executed = array_values(
                array_map('strval', $executed)
            );

            $pending = array_values(
                array_diff($available, $executed)
            );

            if ($pending !== []) {
                return new HealthCheckDTO(
                    status: HealthStatus::WARNING,
                    message: 'Existem migrations SQLite pendentes.',
                    details: [
                        'available' => count($available),
                        'executed' => count($executed),
                        'pending' => count($pending),
                        'pending_migrations' => $pending,
                    ]
                );
            }

            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: 'Migrations SQLite atualizadas.',
                details: [
                    'available' => count($available),
                    'executed' => count($executed),
                    'pending' => 0,
                ]
            );
        } catch (Throwable) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Não foi possível verificar as migrations SQLite.'
            );
        }
    }
}
