<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\DTO\HealthReportDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class HealthConsolePresenter
{
    public function render(
        HealthReportDTO $report,
        ConsoleOutput $output,
        string $title
    ): void {
        $output->line($title);
        $output->line(str_repeat('=', 72));
        $output->line(
            'Status geral: ' . strtoupper($report->status)
        );
        $output->line(
            'Verificado em: ' . $report->checkedAt->format('d/m/Y H:i:s P')
        );
        $output->line('');

        foreach ($report->checks as $key => $check) {
            $output->line(
                sprintf(
                    '%s %-18s %s',
                    $this->statusLabel($check),
                    $key,
                    $check->message
                )
            );

            foreach ($this->details($check) as $detail) {
                $output->line('    ' . $detail);
            }
        }

        $output->line('');
    }

    private function statusLabel(HealthCheckDTO $check): string
    {
        return match ($check->status) {
            HealthStatus::OK => '[OK]     ',
            HealthStatus::WARNING => '[ATENÇÃO]',
            HealthStatus::ERROR => '[ERRO]   ',
        };
    }

    /**
     * @return list<string>
     */
    private function details(HealthCheckDTO $check): array
    {
        $lines = [];

        foreach ($check->details as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'sim' : 'não';
            } elseif ($value === null) {
                $value = '-';
            } elseif (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            } elseif (!is_scalar($value)) {
                continue;
            }

            $lines[] = sprintf(
                '%s: %s',
                str_replace('_', ' ', (string) $key),
                (string) $value
            );
        }

        return $lines;
    }
}
