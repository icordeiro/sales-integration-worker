<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Health;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class ExportacaoHealthProbe implements HealthProbe
{
    public function __construct(
        private ExportacaoDashboardQuery $dashboardQuery
    ) {
    }

    public function key(): string
    {
        return 'exportacao';
    }

    public function check(): HealthCheckDTO
    {
        $resumo = $this->dashboardQuery
            ->resumo();

        $ultima = $resumo->ultimaExecucao;
        $ultimaConcluida = $resumo->ultimaExecucaoConcluida;

        if ($ultima === null) {
            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: 'Nenhuma execução registrada.',
                details: [
                    'last_execution_id' => null,
                    'last_status' => null,
                    'last_movement' => null,
                    'last_success_movement' => null,
                    'in_progress' => $resumo->execucoesEmAndamento,
                    'failures_7d' => $resumo->falhasUltimosSeteDias,
                ]
            );
        }

        $status = in_array(
            $ultima->status,
            [
                StatusExportacao::FALHOU,
                StatusExportacao::CANCELADO,
            ],
            true
        )
            ? HealthStatus::WARNING
            : HealthStatus::OK;

        return new HealthCheckDTO(
            status: $status,
            message: $status === HealthStatus::WARNING
                ? 'A última execução terminou sem sucesso.'
                : 'Fluxo de exportação operacional.',
            details: [
                'last_execution_id' => $ultima->id,
                'last_status' => $ultima->status->value,
                'last_movement' => $ultima->dataMovimento,
                'last_success_movement' => $ultimaConcluida?->dataMovimento,
                'in_progress' => $resumo->execucoesEmAndamento,
                'failures_7d' => $resumo->falhasUltimosSeteDias,
            ]
        );
    }
}
