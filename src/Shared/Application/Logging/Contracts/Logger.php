<?php

declare(strict_types=1);

namespace App\Shared\Application\Logging\Contracts;

use App\Shared\Application\Logging\Enum\LogLevel;

interface Logger
{
    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $event, string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string $event, string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string $event, string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $event, string $message, array $context = []): void;
}
