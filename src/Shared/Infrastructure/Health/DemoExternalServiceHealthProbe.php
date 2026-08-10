<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class DemoExternalServiceHealthProbe implements HealthProbe
{
    public function __construct(
        private string $probeKey,
        private string $serviceLabel
    ) {
    }

    public function key(): string
    {
        return $this->probeKey;
    }

    public function check(): HealthCheckDTO
    {
        return new HealthCheckDTO(
            status: HealthStatus::OK,
            message: sprintf(
                '%s desativado no modo demonstração.',
                $this->serviceLabel
            ),
            details: [
                'mode' => 'demo',
                'connection_tested' => false,
                'external_access_blocked' => true,
            ]
        );
    }
}
