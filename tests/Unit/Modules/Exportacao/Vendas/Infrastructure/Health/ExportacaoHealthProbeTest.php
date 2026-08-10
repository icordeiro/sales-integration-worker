<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Infrastructure\Health;

use App\Modules\Exportacao\Vendas\Application\DTO\DashboardResumoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoHistoricoDTO;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Infrastructure\Health\ExportacaoHealthProbe;
use App\Shared\Application\Health\Enum\HealthStatus;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeExportacaoDashboardQuery;

final class ExportacaoHealthProbeTest extends TestCase
{
    public function testSemExecucoesEhUmEstadoOperacionalValido(): void
    {
        $query = new FakeExportacaoDashboardQuery(
            new DashboardResumoDTO(
                ultimaExecucao: null,
                ultimaExecucaoConcluida: null,
                execucoesEmAndamento: 0,
                falhasUltimosSeteDias: 0
            )
        );

        $result = (new ExportacaoHealthProbe($query))->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertNull($result->details['last_execution_id']);
        self::assertSame(0, $result->details['in_progress']);
    }

    public function testUltimaExecucaoFalhaDeixaHealthDegradadoSemMarcarInfraComoErro(): void
    {
        $failed = new ExportacaoHistoricoDTO(
            id: 12,
            dataMovimento: '2026-08-09',
            tipoExecucao: TipoExecucao::NORMAL,
            status: StatusExportacao::FALHOU,
            execucaoOrigemId: null,
            arquivoNome: null,
            quantidadeRegistros: null,
            tamanhoBytes: null,
            duracaoMilisegundos: 800,
            erroMensagem: 'Falha simulada.',
            iniciadoEm: '2026-08-10T14:00:00+00:00',
            concluidoEm: '2026-08-10T14:00:01+00:00'
        );

        $lastSuccess = new ExportacaoHistoricoDTO(
            id: 11,
            dataMovimento: '2026-08-08',
            tipoExecucao: TipoExecucao::NORMAL,
            status: StatusExportacao::CONCLUIDO,
            execucaoOrigemId: null,
            arquivoNome: 'MV20260808_DEMO.txt',
            quantidadeRegistros: 100,
            tamanhoBytes: 2048,
            duracaoMilisegundos: 700,
            erroMensagem: null,
            iniciadoEm: '2026-08-09T14:00:00+00:00',
            concluidoEm: '2026-08-09T14:00:01+00:00'
        );

        $query = new FakeExportacaoDashboardQuery(
            new DashboardResumoDTO(
                ultimaExecucao: $failed,
                ultimaExecucaoConcluida: $lastSuccess,
                execucoesEmAndamento: 0,
                falhasUltimosSeteDias: 1
            )
        );

        $result = (new ExportacaoHealthProbe($query))->check();

        self::assertSame(HealthStatus::WARNING, $result->status);
        self::assertSame(12, $result->details['last_execution_id']);
        self::assertSame('FALHOU', $result->details['last_status']);
        self::assertSame('2026-08-08', $result->details['last_success_movement']);
        self::assertSame(1, $result->details['failures_7d']);
    }
}
