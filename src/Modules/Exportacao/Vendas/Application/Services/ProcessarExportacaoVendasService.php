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
use Throwable;

final readonly class ProcessarExportacaoVendasService
{
    public function __construct(
        private ConsultarVendasService $consultarVendas,
        private VendaArquivoGenerator $arquivoGenerator,
        private EnviarArquivoVendasService $enviarArquivo,
        private ExportacaoExecucaoRepository $execucoes
    ) {
    }

    public function execute(
        PeriodoExportacao $periodo,
        TipoExecucao $tipoExecucao = TipoExecucao::NORMAL,
        ?int $execucaoOrigemId = null,
        ?string $sha256Origem = null
    ): ResultadoExportacaoVendas {
        $startedAt = microtime(true);

        /*
         * Idempotência da execução NORMAL.
         */
        if (
            $tipoExecucao === TipoExecucao::NORMAL
            && $this->execucoes->existeExecucaoNormalBloqueante(
                $periodo->dataReferencia()
            )
        ) {
            throw ExportacaoNormalJaExistenteException::paraMovimento(
                $periodo->dataReferencia()
            );
        }

        /*
         * A execução precisa existir antes da geração,
         * pois seu ID também identifica a pasta local.
         */
        $execucaoId = $this->execucoes->iniciar(
            dataMovimento: $periodo->dataReferencia(),
            tipoExecucao: $tipoExecucao,
            execucaoOrigemId: $execucaoOrigemId
        );

        try {
            /*
             * 1. Consulta
             */
            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::CONSULTANDO,
                mensagem: sprintf(
                    'Consultando vendas do movimento %s.',
                    $periodo->dataReferencia()
                )
            );

            $vendas = $this->consultarVendas->execute(
                $periodo
            );

            /*
             * 2. Geração do arquivo
             */
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

            /*
             * 3. Validação
             */
            $conteudoAlterado = null;

            $mensagemValidacao = sprintf(
                'Arquivo %s gerado e validado.',
                $arquivo->nome
            );

            if (
                $tipoExecucao === TipoExecucao::REPROCESSAMENTO
                && $sha256Origem !== null
            ) {
                $conteudoAlterado = !hash_equals(
                    $sha256Origem,
                    $arquivo->sha256
                );

                if ($conteudoAlterado) {
                    $mensagemValidacao = sprintf(
                        'Arquivo %s reprocessado. O conteúdo diverge da execução de origem.',
                        $arquivo->nome
                    );
                } else {
                    $mensagemValidacao = sprintf(
                        'Arquivo %s reprocessado. O conteúdo é idêntico à execução de origem.',
                        $arquivo->nome
                    );
                }
            }

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::VALIDANDO,
                mensagem: $mensagemValidacao
            );

            /*
             * Os metadados são registrados ANTES do SFTP.
             *
             * Se o envio falhar, ainda sabemos exatamente
             * qual arquivo foi produzido.
             */
            $this->execucoes->registrarArquivo(
                execucaoId: $execucaoId,
                arquivo: $arquivo
            );

            /*
             * 4. Envio SFTP
             */
            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::ENVIANDO,
                mensagem: sprintf(
                    'Enviando %s para o SFTP.',
                    $arquivo->nome
                )
            );

            $envio = $this->enviarArquivo->execute(
                $arquivo
            );

            /*
             * O RemoteFileStorage já:
             *
             * - envia .part;
             * - compara tamanho;
             * - renomeia;
             * - confirma o arquivo final.
             */
            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::CONFIRMANDO_ENVIO,
                mensagem: sprintf(
                    'Envio confirmado em %s.',
                    $envio->remotePath
                )
            );

            /*
             * 5. Conclusão
             */
            $duration = microtime(true)
                - $startedAt;

            $durationMilliseconds = (int) round(
                $duration * 1000
            );

            $this->execucoes->concluir(
                execucaoId: $execucaoId,
                arquivo: $arquivo,
                envio: $envio,
                duracaoMilisegundos: $durationMilliseconds
            );

            return new ResultadoExportacaoVendas(
                execucaoId: $execucaoId,
                arquivo: $arquivo,
                envio: $envio,
                duracaoSegundos: $duration,
                conteudoAlterado: $conteudoAlterado
            );
        } catch (Throwable $exception) {
            $durationMilliseconds = (int) round(
                (microtime(true) - $startedAt)
                * 1000
            );

            try {
                $this->execucoes->falhar(
                    execucaoId: $execucaoId,
                    erro: $exception->getMessage(),
                    duracaoMilisegundos: $durationMilliseconds
                );
            } catch (Throwable) {
                /*
                 * A falha ao registrar histórico nunca
                 * deve esconder a exceção original.
                 */
            }

            throw $exception;
        }
    }
}