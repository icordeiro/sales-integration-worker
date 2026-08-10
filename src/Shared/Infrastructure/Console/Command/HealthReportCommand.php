<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console\Command;

use App\Shared\Application\Console\Contracts\ConsoleCommand;
use App\Shared\Application\Health\DTO\HealthReportDTO;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use App\Shared\Infrastructure\Console\HealthConsolePresenter;
use Closure;
use Throwable;

final readonly class HealthReportCommand implements ConsoleCommand
{
    /**
     * @param Closure():HealthReportDTO $reportProvider
     */
    public function __construct(
        private string $commandName,
        private string $commandDescription,
        private string $title,
        private Closure $reportProvider,
        private HealthConsolePresenter $presenter,
        private bool $warningIsFailure = false
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
        $json = false;

        foreach ($arguments as $argument) {
            if ($argument === '--json') {
                $json = true;
                continue;
            }

            $output->error(
                sprintf('Opção desconhecida para %s: %s', $this->commandName, $argument)
            );

            return 2;
        }

        try {
            $report = ($this->reportProvider)();
        } catch (Throwable) {
            $output->error(
                'Não foi possível inicializar o diagnóstico. Verifique o arquivo .env e as dependências da aplicação.'
            );

            return 1;
        }

        if ($json) {
            $encoded = json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

            if ($encoded === false) {
                $output->error('Não foi possível serializar o relatório em JSON.');

                return 1;
            }

            $output->line($encoded);
        } else {
            $this->presenter->render(
                report: $report,
                output: $output,
                title: $this->title
            );
        }

        if ($report->status === 'unhealthy') {
            return 1;
        }

        if ($this->warningIsFailure && $report->status === 'degraded') {
            return 1;
        }

        return 0;
    }
}
