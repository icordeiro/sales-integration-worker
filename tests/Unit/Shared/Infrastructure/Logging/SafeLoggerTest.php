<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;
use App\Shared\Infrastructure\Logging\SafeLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SafeLoggerTest extends TestCase
{
    public function testFalhaDoLoggerInternoNaoInterrompeAplicacao(): void
    {
        $inner = new class implements Logger {
            public function log(LogLevel $level, string $event, string $message, array $context = []): void { throw new RuntimeException('Disco indisponível'); }
            public function info(string $event, string $message, array $context = []): void { $this->log(LogLevel::INFO, $event, $message, $context); }
            public function warning(string $event, string $message, array $context = []): void { $this->log(LogLevel::WARNING, $event, $message, $context); }
            public function error(string $event, string $message, array $context = []): void { $this->log(LogLevel::ERROR, $event, $message, $context); }
        };

        $logger = new SafeLogger($inner);
        $logger->info('test.event', 'Mensagem.');
        $logger->warning('test.event', 'Mensagem.');
        $logger->error('test.event', 'Mensagem.');
        self::assertTrue(true);
    }
}
