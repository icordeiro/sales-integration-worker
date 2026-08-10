<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Application\Logging\Enum\LogLevel;
use App\Shared\Infrastructure\Logging\MonthlyJsonFileLogger;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class MonthlyJsonFileLoggerTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-log');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testGravaUmaLinhaJsonEstruturada(): void
    {
        $now = new DateTimeImmutable('2026-08-10T09:30:00-03:00');
        $logger = new MonthlyJsonFileLogger(
            directory: $this->temporaryDirectory,
            timezone: new DateTimeZone('America/Fortaleza'),
            clock: static fn (): DateTimeImmutable => $now
        );

        $logger->info('export.completed', 'Exportação concluída.', [
            'execution_id' => 42,
            'movement_date' => '2026-08-09',
            'operation_type' => 'NORMAL',
        ]);

        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'siw-2026-08.log';
        self::assertFileExists($path);

        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode(trim($contents), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('2026-08-10T09:30:00-03:00', $decoded['timestamp']);
        self::assertSame('info', $decoded['level']);
        self::assertSame('siw', $decoded['channel']);
        self::assertSame('export.completed', $decoded['event']);
        self::assertSame(42, $decoded['context']['execution_id']);
    }

    public function testRotacionaAutomaticamenteQuandoOMesMuda(): void
    {
        $now = new DateTimeImmutable('2026-08-31T23:59:59-03:00');
        $logger = new MonthlyJsonFileLogger(
            directory: $this->temporaryDirectory,
            timezone: new DateTimeZone('America/Fortaleza'),
            clock: static function () use (&$now): DateTimeImmutable { return $now; }
        );

        $logger->info('worker.daily_started', 'Agosto.');
        $now = new DateTimeImmutable('2026-09-01T00:00:01-03:00');
        $logger->info('worker.daily_started', 'Setembro.');

        self::assertFileExists($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'siw-2026-08.log');
        self::assertFileExists($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'siw-2026-09.log');
    }

    public function testRespeitaNivelMinimoConfigurado(): void
    {
        $now = new DateTimeImmutable('2026-08-10T09:30:00-03:00');
        $logger = new MonthlyJsonFileLogger(
            directory: $this->temporaryDirectory,
            timezone: new DateTimeZone('America/Fortaleza'),
            minimumLevel: LogLevel::WARNING,
            clock: static fn (): DateTimeImmutable => $now
        );

        $logger->info('ignored', 'Não deve ser gravado.');
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'siw-2026-08.log';
        self::assertFileDoesNotExist($path);

        $logger->warning('worker.lock_timeout', 'Atenção.');
        self::assertFileExists($path);
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
    }

    public function testNormalizaCanalEEventoParaNomesEstaveis(): void
    {
        $now = new DateTimeImmutable('2026-08-10T09:30:00-03:00');
        $logger = new MonthlyJsonFileLogger(
            directory: $this->temporaryDirectory,
            timezone: new DateTimeZone('America/Fortaleza'),
            channel: 'SIW Worker',
            clock: static fn (): DateTimeImmutable => $now
        );

        $logger->error('Export Failed!', 'Falhou.');
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'siw-worker-2026-08.log';
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $decoded = json_decode(trim($contents), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('siw-worker', $decoded['channel']);
        self::assertSame('export_failed', $decoded['event']);
    }
}
