<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use Throwable;

final readonly class DatabaseHealthProbe implements HealthProbe
{
    public function __construct(
        private string $key,
        private string $label,
        private DatabaseConnection $database
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function check(): HealthCheckDTO
    {
        $startedAt = hrtime(true);

        try {
            $statement = $this->database
                ->connection()
                ->query('SELECT 1');

            if ($statement === false) {
                throw new \RuntimeException('Consulta de health check não pôde ser executada.');
            }

            $latencyMilliseconds = (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            );

            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: sprintf('%s disponível.', $this->label),
                details: [
                    'latency_ms' => $latencyMilliseconds,
                ]
            );
        } catch (Throwable) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: sprintf('%s indisponível.', $this->label)
            );
        }
    }
}
