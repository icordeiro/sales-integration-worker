<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\Contracts\VendaArquivoGenerator;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoExportacaoVendas;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ExportacaoNormalJaExistenteException;
use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\NullLogger;
use Throwable;

final readonly class ProcessarExportacaoVendasService
{
    public function __construct(
        private ConsultarVendasService $consultarVendas,
        private VendaArquivoGenerator $arquivoGenerator,
        private EnviarArquivoVendasService $enviarArquivo,
        private ExportacaoExecucaoRepository $execucoes,
        private Logger $logger = new NullLogger()
    ) {
    }

    public function execute(
        PeriodoExportacao $periodo,
        TipoExecucao $tipoExecucao = TipoExecucao::NORMAL,
        ?int $execucaoOrigemId = null,
        ?string $sha256Origem = null
    ): ResultadoExportacaoVendas {
        $startedAt = microtime(true);

        if (
            $tipoExecucao === TipoExecucao::NORMAL
            && $this->execucoes->existeExecucaoNormalBloqueante($periodo->dataReferencia())
        ) {
            $this->logger->info(
                'export.skipped_existing',
                'Movimento já possui execução NORMAL bloqueante.',
                [
                    'movement_date' => $periodo->dataReferencia(),
                    'operation_type' => $tipoExecucao->value,
                ]
            );

            throw ExportacaoNormalJaExistenteException::paraMovimento($periodo->dataReferencia());
        }

        $execucaoId = $this->execucoes->iniciar(
            dataMovimento: $periodo->dataReferencia(),
            tipoExecucao: $tipoExecucao,
            execucaoOrigemId: $execucaoOrigemId
        );

        $baseContext = [
            'execution_id' => $execucaoId,
            'movement_date' => $periodo->dataReferencia(),
            'operation_type' => $tipoExecucao->value,
            'origin_execution_id' => $execucaoOrigemId,
        ];

        $this->logger->info('export.started', 'Execução de exportação iniciada.', $baseContext);

        try {
            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::CONSULTANDO,
                mensagem: sprintf('Consultando vendas do movimento %s.', $periodo->dataReferencia())
            );

            $vendas = $this->consultarVendas->execute($periodo);

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::GERANDO_ARQUIVO,
                mensagem: 'Gerando arquivo TXT.'
            );

            $arquivo = $this->arquivoGenerator->gerar(
                periodo: $periodo,
                vendas: $vendas,
                execucaoId: $execucaoId
            );

            $conteudoAlterado = null;
            $mensagemValidacao = sprintf('Arquivo %s gerado e validado.', $arquivo->nome);

            if ($tipoExecucao === TipoExecucao::REPROCESSAMENTO && $sha256Origem !== null) {
                $conteudoAlterado = !hash_equals($sha256Origem, $arquivo->sha256);

                $mensagemValidacao = $conteudoAlterado
                    ? sprintf('Arquivo %s reprocessado. O conteúdo diverge da execução de origem.', $arquivo->nome)
                    : sprintf('Arquivo %s reprocessado. O conteúdo é idêntico à execução de origem.', $arquivo->nome);
            }

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::VALIDANDO,
                mensagem: $mensagemValidacao
            );

            $this->execucoes->registrarArquivo(execucaoId: $execucaoId, arquivo: $arquivo);

            $this->logger->info(
                'export.file_ready',
                'Arquivo da exportação gerado e validado.',
                [
                    ...$baseContext,
                    'file_name' => $arquivo->nome,
                    'record_count' => $arquivo->quantidadeRegistros,
                    'size_bytes' => $arquivo->tamanhoBytes,
                    'content_changed' => $conteudoAlterado,
                ]
            );

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::ENVIANDO,
                mensagem: sprintf('Enviando %s para o SFTP.', $arquivo->nome)
            );

            $envio = $this->enviarArquivo->execute($arquivo);

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::CONFIRMANDO_ENVIO,
                mensagem: sprintf('Envio confirmado em %s.', $envio->remotePath)
            );

            $duration = microtime(true) - $startedAt;
            $durationMilliseconds = (int) round($duration * 1000);

            $this->execucoes->concluir(
                execucaoId: $execucaoId,
                arquivo: $arquivo,
                envio: $envio,
                duracaoMilisegundos: $durationMilliseconds
            );

            $this->logger->info(
                'export.completed',
                'Exportação concluída com sucesso.',
                [
                    ...$baseContext,
                    'file_name' => $arquivo->nome,
                    'record_count' => $arquivo->quantidadeRegistros,
                    'size_bytes' => $arquivo->tamanhoBytes,
                    'remote_path' => $envio->remotePath,
                    'duration_ms' => $durationMilliseconds,
                    'content_changed' => $conteudoAlterado,
                    'final_status' => StatusExportacao::CONCLUIDO->value,
                ]
            );

            return new ResultadoExportacaoVendas(
                execucaoId: $execucaoId,
                arquivo: $arquivo,
                envio: $envio,
                duracaoSegundos: $duration,
                conteudoAlterado: $conteudoAlterado
            );
        } catch (Throwable $exception) {
            $durationMilliseconds = (int) round((microtime(true) - $startedAt) * 1000);

            try {
                $this->execucoes->falhar(
                    execucaoId: $execucaoId,
                    erro: $exception->getMessage(),
                    duracaoMilisegundos: $durationMilliseconds
                );
            } catch (Throwable) {
            }

            $this->logger->error(
                'export.failed',
                'A execução de exportação falhou.',
                [
                    ...$baseContext,
                    'duration_ms' => $durationMilliseconds,
                    'exception_class' => $exception::class,
                    'error_message' => $exception->getMessage(),
                    'final_status' => StatusExportacao::FALHOU->value,
                ]
            );

            throw $exception;
        }
    }
}
