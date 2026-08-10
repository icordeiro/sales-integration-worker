<?php

declare(strict_types=1);

namespace App\Shared\Application\Console;

use App\Shared\Application\Console\Contracts\ConsoleCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use Throwable;

final class ConsoleApplication
{
    /** @var array<string, ConsoleCommand> */
    private array $commands = [];

    /**
     * @param list<ConsoleCommand> $commands
     */
    public function __construct(
        array $commands,
        private readonly ConsoleOutput $output,
        private readonly string $applicationName = 'Sales Integration Worker (SIW)'
    ) {
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }

        ksort($this->commands);
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? 'help';

        if (in_array($commandName, ['help', '--help', '-h', 'list'], true)) {
            $this->renderHelp();

            return 0;
        }

        $command = $this->commands[$commandName] ?? null;

        if (!$command instanceof ConsoleCommand) {
            $this->output->error(
                sprintf('Comando desconhecido: %s', $commandName)
            );
            $this->output->error('');
            $this->renderHelp();

            return 2;
        }

        try {
            return $command->execute(
                array_values(array_slice($argv, 2)),
                $this->output
            );
        } catch (Throwable) {
            $this->output->error(
                sprintf('Falha inesperada ao executar o comando %s.', $commandName)
            );

            return 1;
        }
    }

    private function renderHelp(): void
    {
        $this->output->line($this->applicationName);
        $this->output->line(str_repeat('=', 72));
        $this->output->line('Uso:');
        $this->output->line('  php bin/console <comando> [opções]');
        $this->output->line('');
        $this->output->line('Comandos:');

        $width = 0;

        foreach (array_keys($this->commands) as $name) {
            $width = max($width, strlen($name));
        }

        foreach ($this->commands as $command) {
            $this->output->line(
                sprintf(
                    '  %-'. $width .'s  %s',
                    $command->name(),
                    $command->description()
                )
            );
        }

        $this->output->line(
            sprintf(
                '  %-'. $width .'s  %s',
                'help',
                'Exibe esta ajuda.'
            )
        );
        $this->output->line('');
        $this->output->line('Opções de health:');
        $this->output->line('  --json  Retorna o relatório em JSON, sem formatação adicional.');
    }
}
