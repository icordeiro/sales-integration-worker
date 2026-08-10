<?php

declare(strict_types=1);

namespace App\Shared\Application\Health\Services;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\DTO\HealthReportDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use DateTimeImmutable;
use Throwable;

final readonly class HealthCheckService
{
    /**
     * @param list<HealthProbe> $probes
     */
    public function __construct(
        private array $probes
    ) {
    }

    public function execute(): HealthReportDTO
    {
        $checks = [];

        foreach ($this->probes as $probe) {
            $key = $probe->key();

            try {
                $checks[$key] = $probe->check();
            } catch (Throwable) {
                $checks[$key] = new HealthCheckDTO(
                    status: HealthStatus::ERROR,
                    message: 'Não foi possível executar esta verificação.'
                );
            }
        }

        return new HealthReportDTO(
            status: $this->resolveOverallStatus($checks),
            checkedAt: new DateTimeImmutable('now'),
            checks: $checks
        );
    }

    /**
     * @param array<string, HealthCheckDTO> $checks
     */
    private function resolveOverallStatus(array $checks): string
    {
        $hasWarning = false;

        foreach ($checks as $check) {
            if ($check->status === HealthStatus::ERROR) {
                return 'unhealthy';
            }

            if ($check->status === HealthStatus::WARNING) {
                $hasWarning = true;
            }
        }

        return $hasWarning
            ? 'degraded'
            : 'healthy';
    }
}
