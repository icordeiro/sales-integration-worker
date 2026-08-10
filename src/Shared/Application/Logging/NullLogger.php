<?php

declare(strict_types=1);

namespace App\Shared\Application\Logging;

use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;

final class NullLogger implements Logger
{
    public function log(LogLevel $level, string $event, string $message, array $context = []): void {}
    public function info(string $event, string $message, array $context = []): void {}
    public function warning(string $event, string $message, array $context = []): void {}
    public function error(string $event, string $message, array $context = []): void {}
}
