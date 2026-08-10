<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console\Command;

use App\Shared\Application\Console\Contracts\ConsoleCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use RuntimeException;

final readonly class LegacyScriptCommand implements ConsoleCommand
{
    public function __construct(
        private string $commandName,
        private string $commandDescription,
        private string $scriptPath,
        private string $workingDirectory
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

    public function execute(
        array $arguments,
        ConsoleOutput $output
    ): int {
        if ($arguments !== []) {
            $output->error(
                sprintf('O comando %s não aceita argumentos nesta versão.', $this->commandName)
            );

            return 2;
        }

        if (!is_file($this->scriptPath)) {
            $output->error(
                sprintf('Script não encontrado: %s', basename($this->scriptPath))
            );

            return 1;
        }

        $process = proc_open(
            [
                PHP_BINARY,
                $this->scriptPath,
            ],
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            $this->workingDirectory,
            null,
            [
                'bypass_shell' => true,
            ]
        );

        if (!is_resource($process)) {
            throw new RuntimeException(
                sprintf('Não foi possível iniciar o comando %s.', $this->commandName)
            );
        }

        return proc_close($process);
    }
}
