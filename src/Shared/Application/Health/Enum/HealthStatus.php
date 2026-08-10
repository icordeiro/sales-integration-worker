<?php

declare(strict_types=1);

namespace App\Shared\Application\Health\Enum;

enum HealthStatus: string
{
    case OK = 'ok';
    case WARNING = 'warning';
    case ERROR = 'error';
}
