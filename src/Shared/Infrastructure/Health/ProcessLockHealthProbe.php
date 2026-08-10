<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Lock\FileProcessLock;

final readonly class ProcessLockHealthProbe implements HealthProbe
{
    public function __construct(
        private FileProcessLock $processLock
    ) {
    }

    public function key(): string
    {
        return 'lock';
    }

    public function check(): HealthCheckDTO
    {
        $busy = $this->processLock
            ->isLockedByAnotherProcess();

        return new HealthCheckDTO(
            status: HealthStatus::OK,
            message: $busy
                ? 'Worker em execução.'
                : 'Worker disponível.',
            details: [
                'state' => $busy
                    ? 'busy'
                    : 'available',
            ]
        );
    }
}
