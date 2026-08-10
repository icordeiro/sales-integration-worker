<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Console;

use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\DTO\HealthReportDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Console\Command\HealthReportCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use App\Shared\Infrastructure\Console\HealthConsolePresenter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HealthReportCommandTest extends TestCase
{
    public function testStatusHealthyRetornaZero(): void
    {
        [$output, $stdout] = $this->createConsoleOutput();
        $command = $this->command($this->report('healthy'));

        $exitCode = $command->execute([], $output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('HEALTHY', $this->contents($stdout));
        self::assertStringContainsString('[OK]', $this->contents($stdout));
    }

    public function testDegradedPodeSerTratadoComoFalhaNoDoctor(): void
    {
        [$output] = $this->createConsoleOutput();

        $command = $this->command(
            $this->report('degraded', HealthStatus::WARNING),
            warningIsFailure: true
        );

        self::assertSame(1, $command->execute([], $output));
    }


    public function testStatusDegradedContinuaRetornandoZero(): void
    {
        [$output] = $this->createConsoleOutput();

        $command = $this->command(
            $this->report('degraded', HealthStatus::WARNING),
            warningIsFailure: false
        );

        self::assertSame(0, $command->execute([], $output));
    }

    public function testFalhaAoGerarRelatorioRetornaUm(): void
    {
        [$output, $stdout, $stderr] = $this->createConsoleOutput();

        $command = new HealthReportCommand(
            commandName: 'status',
            commandDescription: 'Status.',
            title: 'SIW - Status',
            reportProvider: static function (): HealthReportDTO {
                throw new \RuntimeException('falha');
            },
            presenter: new HealthConsolePresenter(),
            warningIsFailure: false
        );

        $exitCode = $command->execute([], $output);

        self::assertSame(1, $exitCode);
        self::assertSame('', $this->contents($stdout));
        self::assertStringContainsString('Não foi possível inicializar', $this->contents($stderr));
    }

    public function testUnhealthyRetornaUm(): void
    {
        [$output] = $this->createConsoleOutput();
        $command = $this->command(
            $this->report('unhealthy', HealthStatus::ERROR)
        );

        self::assertSame(1, $command->execute([], $output));
    }

    public function testJsonRetornaRelatorioEstruturado(): void
    {
        [$output, $stdout] = $this->createConsoleOutput();
        $command = $this->command($this->report('healthy'));

        $exitCode = $command->execute(['--json'], $output);
        $contents = $this->contents($stdout);

        self::assertSame(0, $exitCode);
        self::assertJson($contents);
        self::assertStringContainsString('"status": "healthy"', $contents);
        self::assertStringContainsString('"php"', $contents);
    }

    public function testOpcaoDesconhecidaRetornaDois(): void
    {
        [$output, $stdout, $stderr] = $this->createConsoleOutput();
        $command = $this->command($this->report('healthy'));

        $exitCode = $command->execute(['--verbose'], $output);

        self::assertSame(2, $exitCode);
        self::assertSame('', $this->contents($stdout));
        self::assertStringContainsString('Opção desconhecida', $this->contents($stderr));
    }

    private function command(
        HealthReportDTO $report,
        bool $warningIsFailure = false
    ): HealthReportCommand {
        return new HealthReportCommand(
            commandName: 'status',
            commandDescription: 'Status.',
            title: 'SIW - Status',
            reportProvider: static fn (): HealthReportDTO => $report,
            presenter: new HealthConsolePresenter(),
            warningIsFailure: $warningIsFailure
        );
    }

    private function report(
        string $status,
        HealthStatus $checkStatus = HealthStatus::OK
    ): HealthReportDTO {
        return new HealthReportDTO(
            status: $status,
            checkedAt: new DateTimeImmutable('2026-08-10T09:00:00-03:00'),
            checks: [
                'php' => new HealthCheckDTO(
                    status: $checkStatus,
                    message: 'PHP operacional.',
                    details: [
                        'version' => '8.5.5',
                    ]
                ),
            ]
        );
    }

    /**
     * @return array{0:ConsoleOutput,1:resource,2:resource}
     */
    private function createConsoleOutput(): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        return [
            new ConsoleOutput($stdout, $stderr),
            $stdout,
            $stderr,
        ];
    }

    /** @param resource $stream */
    private function contents(mixed $stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
