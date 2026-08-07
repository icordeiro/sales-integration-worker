<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoExportacaoVendas;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReprocessamentoExportacaoException;
use DateTimeImmutable;

final readonly class ReprocessarExportacaoVendasService
{
    public function __construct(
        private ExportacaoExecucaoRepository $execucoes,
        private ProcessarExportacaoVendasService $processarExportacao
    ) {
    }

    public function execute(
        int $execucaoOrigemId
    ): ResultadoExportacaoVendas {
        /*
         * 1. Recupera execução original.
         */
        $origem = $this->execucoes->buscarPorId(
            $execucaoOrigemId
        );

        if ($origem === null) {
            throw ReprocessamentoExportacaoException::execucaoNaoEncontrada(
                $execucaoOrigemId
            );
        }

        /*
         * Por enquanto, somente uma execução realmente
         * concluída pode originar um reprocessamento.
         */
        if (
            $origem->status !== StatusExportacao::CONCLUIDO
        ) {
            throw ReprocessamentoExportacaoException::execucaoNaoConcluida(
                $execucaoOrigemId
            );
        }

        if ($origem->sha256 === null) {
            throw ReprocessamentoExportacaoException::hashOriginalIndisponivel(
                $execucaoOrigemId
            );
        }

        /*
         * 2. Reconstrói o período original.
         */
        $dataMovimento = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $origem->dataMovimento
        );

        if (
            !$dataMovimento instanceof DateTimeImmutable
            || $dataMovimento->format('Y-m-d')
                !== $origem->dataMovimento
        ) {
            throw ReprocessamentoExportacaoException::dataMovimentoInvalida(
                $origem->dataMovimento
            );
        }

        $periodo = PeriodoExportacao::doDia(
            $dataMovimento
        );

        /*
         * 3. Usa o mesmo orquestrador.
         *
         * Não duplicamos:
         * - consulta;
         * - geração;
         * - histórico;
         * - SFTP;
         * - tratamento de falha.
         */
        return $this->processarExportacao->execute(
            periodo: $periodo,
            tipoExecucao: TipoExecucao::REPROCESSAMENTO,
            execucaoOrigemId: $origem->id,
            sha256Origem: $origem->sha256
        );
    }
}