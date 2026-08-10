<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use RuntimeException;

final readonly class FakeHealthProbe implements HealthProbe
{
    public function __construct(
        private string $probeKey,
        private ?HealthCheckDTO $result = null,
        private bool $throwException = false
    ) {
    }

    public function key(): string
    {
        return $this->probeKey;
    }

    public function check(): HealthCheckDTO
    {
        if ($this->throwException) {
            throw new RuntimeException('Falha simulada de health probe.');
        }

        if ($this->result === null) {
            throw new RuntimeException('Resultado fake não configurado.');
        }

        return $this->result;
    }
}
