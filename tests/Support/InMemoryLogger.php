<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;

final class InMemoryLogger implements Logger
{
    /** @var list<array{level:LogLevel,event:string,message:string,context:array<string,mixed>}> */
    public array $entries = [];

    public function log(LogLevel $level, string $event, string $message, array $context = []): void
    {
        $this->entries[] = compact('level', 'event', 'message', 'context');
    }
    public function info(string $event, string $message, array $context = []): void { $this->log(LogLevel::INFO, $event, $message, $context); }
    public function warning(string $event, string $message, array $context = []): void { $this->log(LogLevel::WARNING, $event, $message, $context); }
    public function error(string $event, string $message, array $context = []): void { $this->log(LogLevel::ERROR, $event, $message, $context); }
}
