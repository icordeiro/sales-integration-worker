<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

final readonly class DashboardResumoDTO
{
    public function __construct(
        public ?ExportacaoHistoricoDTO $ultimaExecucao,
        public ?ExportacaoHistoricoDTO $ultimaExecucaoConcluida,
        public int $execucoesEmAndamento,
        public int $falhasUltimosSeteDias
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ultima_execucao' =>
                $this->ultimaExecucao?->toArray(),

            'ultima_execucao_concluida' =>
                $this->ultimaExecucaoConcluida?->toArray(),

            'execucoes_em_andamento' =>
                $this->execucoesEmAndamento,

            'falhas_ultimos_sete_dias' =>
                $this->falhasUltimosSeteDias,
        ];
    }
}