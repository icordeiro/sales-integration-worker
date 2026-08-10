<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Database\Config\ClientDatabaseConfig;
use PDO;
use Throwable;

final readonly class PostgreSqlHealthProbe implements HealthProbe
{
    public function __construct(
        private ClientDatabaseConfig $config,
        private int $connectTimeoutSeconds = 3
    ) {
    }

    public function key(): string
    {
        return 'postgresql';
    }

    public function check(): HealthCheckDTO
    {
        $startedAt = hrtime(true);

        try {
            $connection = new PDO(
                dsn: sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d',
                    $this->config->host,
                    $this->config->port,
                    $this->config->database,
                    max(1, $this->connectTimeoutSeconds)
                ),
                username: $this->config->username,
                password: $this->config->password,
                options: [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                ]
            );

            $statement = $connection->query('SELECT 1');

            if ($statement === false) {
                throw new \RuntimeException('Consulta de health check não pôde ser executada.');
            }

            $latencyMilliseconds = (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            );

            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: 'PostgreSQL disponível.',
                details: [
                    'latency_ms' => $latencyMilliseconds,
                ]
            );
        } catch (Throwable) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'PostgreSQL indisponível.'
            );
        }
    }
}
