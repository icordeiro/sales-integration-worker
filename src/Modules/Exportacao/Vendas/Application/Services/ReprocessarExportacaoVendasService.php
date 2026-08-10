<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoExportacaoVendas;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReprocessamentoExportacaoException;
use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\NullLogger;
use DateTimeImmutable;
use Throwable;

final readonly class ReprocessarExportacaoVendasService
{
    public function __construct(
        private ExportacaoExecucaoRepository $execucoes,
        private ProcessarExportacaoVendasService $processarExportacao,
        private Logger $logger = new NullLogger()
    ) {
    }

    public function execute(int $execucaoOrigemId): ResultadoExportacaoVendas
    {
        $this->logger->info('reprocess.requested', 'Reprocessamento solicitado.', [
            'origin_execution_id' => $execucaoOrigemId,
            'operation_type' => TipoExecucao::REPROCESSAMENTO->value,
        ]);

        try {
            $origem = $this->execucoes->buscarPorId($execucaoOrigemId);
            if ($origem === null) {
                throw ReprocessamentoExportacaoException::execucaoNaoEncontrada($execucaoOrigemId);
            }
            if ($origem->status !== StatusExportacao::CONCLUIDO) {
                throw ReprocessamentoExportacaoException::execucaoNaoConcluida($execucaoOrigemId);
            }
            if ($origem->sha256 === null) {
                throw ReprocessamentoExportacaoException::hashOriginalIndisponivel($execucaoOrigemId);
            }

            $dataMovimento = DateTimeImmutable::createFromFormat('!Y-m-d', $origem->dataMovimento);
            if (
                !$dataMovimento instanceof DateTimeImmutable
                || $dataMovimento->format('Y-m-d') !== $origem->dataMovimento
            ) {
                throw ReprocessamentoExportacaoException::dataMovimentoInvalida($origem->dataMovimento);
            }
        } catch (Throwable $exception) {
            $this->logger->warning('reprocess.rejected', 'Reprocessamento recusado antes da criação de uma nova execução.', [
                'origin_execution_id' => $execucaoOrigemId,
                'operation_type' => TipoExecucao::REPROCESSAMENTO->value,
                'exception_class' => $exception::class,
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $periodo = PeriodoExportacao::doDia($dataMovimento);

        $this->logger->info('reprocess.accepted', 'Execução de origem validada; o movimento será consultado novamente.', [
            'origin_execution_id' => $origem->id,
            'movement_date' => $origem->dataMovimento,
            'operation_type' => TipoExecucao::REPROCESSAMENTO->value,
        ]);

        return $this->processarExportacao->execute(
            periodo: $periodo,
            tipoExecucao: TipoExecucao::REPROCESSAMENTO,
            execucaoOrigemId: $origem->id,
            sha256Origem: $origem->sha256
        );
    }
}
