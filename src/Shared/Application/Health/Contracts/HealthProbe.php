<?php

declare(strict_types=1);

namespace App\Shared\Application\Health\Contracts;

use App\Shared\Application\Health\DTO\HealthCheckDTO;

interface HealthProbe
{
    public function key(): string;

    public function check(): HealthCheckDTO;
}
