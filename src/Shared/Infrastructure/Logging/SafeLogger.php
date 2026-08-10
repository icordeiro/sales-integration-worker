<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;
use Throwable;

final readonly class SafeLogger implements Logger
{
    public function __construct(private Logger $logger) {}

    public function log(LogLevel $level, string $event, string $message, array $context = []): void
    {
        try {
            $this->logger->log($level, $event, $message, $context);
        } catch (Throwable) {
            /* Observabilidade nunca deve interromper o fluxo operacional. */
        }
    }

    public function info(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $event, $message, $context);
    }

    public function warning(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $event, $message, $context);
    }

    public function error(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $event, $message, $context);
    }
}
