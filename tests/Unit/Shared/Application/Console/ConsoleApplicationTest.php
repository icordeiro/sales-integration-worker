<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Console;

use App\Shared\Application\Console\ConsoleApplication;
use App\Shared\Application\Console\Contracts\ConsoleCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use PHPUnit\Framework\TestCase;

final class ConsoleApplicationTest extends TestCase
{
    public function testExibeAjudaQuandoNenhumComandoEhInformado(): void
    {
        [$output, $stdout, $stderr] = $this->createConsoleOutput();

        $application = new ConsoleApplication(
            commands: [
                $this->command('status', 'Exibe o status.'),
            ],
            output: $output
        );

        $exitCode = $application->run(['bin/console']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Sales Integration Worker', $this->contents($stdout));
        self::assertStringContainsString('status', $this->contents($stdout));
        self::assertSame('', $this->contents($stderr));
    }

    public function testExecutaComandoRegistradoComArgumentos(): void
    {
        [$output, $stdout] = $this->createConsoleOutput();
        $receivedArguments = [];

        $command = new class($receivedArguments) implements ConsoleCommand {
            /** @var list<string> */
            public array $receivedArguments = [];

            public function __construct(array $ignored)
            {
                unset($ignored);
            }

            public function name(): string
            {
                return 'status';
            }

            public function description(): string
            {
                return 'Status.';
            }

            public function execute(array $arguments, ConsoleOutput $output): int
            {
                $this->receivedArguments = $arguments;
                $output->line('executado');

                return 7;
            }
        };

        $application = new ConsoleApplication([$command], $output);

        $exitCode = $application->run([
            'bin/console',
            'status',
            '--json',
        ]);

        self::assertSame(7, $exitCode);
        self::assertSame(['--json'], $command->receivedArguments);
        self::assertStringContainsString('executado', $this->contents($stdout));
    }

    public function testComandoDesconhecidoRetornaCodigoDois(): void
    {
        [$output, $stdout, $stderr] = $this->createConsoleOutput();

        $application = new ConsoleApplication([], $output);

        $exitCode = $application->run([
            'bin/console',
            'inexistente',
        ]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Comando desconhecido', $this->contents($stderr));
        self::assertStringContainsString('Uso:', $this->contents($stdout));
    }

    private function command(string $name, string $description): ConsoleCommand
    {
        return new class($name, $description) implements ConsoleCommand {
            public function __construct(
                private readonly string $commandName,
                private readonly string $commandDescription
            ) {
            }

            public function name(): string
            {
                return $this->commandName;
            }

            public function description(): string
            {
                return $this->commandDescription;
            }

            public function execute(array $arguments, ConsoleOutput $output): int
            {
                unset($arguments, $output);

                return 0;
            }
        };
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
