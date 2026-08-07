<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoReenvioVendas;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReenvioExportacaoException;
use Throwable;

final readonly class ReenviarExportacaoVendasService
{
    public function __construct(
        private ExportacaoExecucaoRepository $execucoes,
        private EnviarArquivoVendasService $enviarArquivo
    ) {
    }

    public function execute(
        int $execucaoOrigemId
    ): ResultadoReenvioVendas {
        $startedAt = microtime(true);

        /*
         * 1. Recupera execução original.
         */
        $origem = $this->execucoes->buscarPorId(
            $execucaoOrigemId
        );

        if ($origem === null) {
            throw ReenvioExportacaoException::execucaoNaoEncontrada(
                $execucaoOrigemId
            );
        }

        if (
            $origem->status !== StatusExportacao::CONCLUIDO
        ) {
            throw ReenvioExportacaoException::execucaoNaoConcluida(
                $execucaoOrigemId
            );
        }

        if (
            $origem->arquivoNome === null
            || $origem->quantidadeRegistros === null
            || $origem->tamanhoBytes === null
            || $origem->sha256 === null
            || $origem->caminhoLocal === null
        ) {
            throw ReenvioExportacaoException::metadadosIncompletos(
                $execucaoOrigemId
            );
        }

        /*
         * 2. Confere arquivo físico.
         */
        if (!is_file($origem->caminhoLocal)) {
            throw ReenvioExportacaoException::arquivoIndisponivel(
                $execucaoOrigemId
            );
        }

        /*
         * 3. Confere tamanho.
         */
        $currentSize = filesize(
            $origem->caminhoLocal
        );

        if (
            $currentSize === false
            || $currentSize !== $origem->tamanhoBytes
        ) {
            throw ReenvioExportacaoException::tamanhoDivergente();
        }

        /*
         * 4. Confere SHA-256.
         */
        $currentHash = hash_file(
            'sha256',
            $origem->caminhoLocal
        );

        if (
            $currentHash === false
            || !hash_equals(
                $origem->sha256,
                $currentHash
            )
        ) {
            throw ReenvioExportacaoException::hashDivergente();
        }

        /*
         * 5. Reconstrói o Value Object do arquivo.
         */
        $arquivo = new ArquivoVendaGerado(
            nome: $origem->arquivoNome,
            caminho: $origem->caminhoLocal,
            dataReferencia: $origem->dataMovimento,
            quantidadeRegistros: $origem->quantidadeRegistros,
            tamanhoBytes: $origem->tamanhoBytes,
            sha256: $origem->sha256
        );

        /*
         * Só criamos a nova execução depois
         * de validar completamente a origem.
         */
        $execucaoId = $this->execucoes->iniciar(
            dataMovimento: $origem->dataMovimento,
            tipoExecucao: TipoExecucao::REENVIO,
            execucaoOrigemId: $origem->id
        );

        try {
            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::VALIDANDO,
                mensagem: sprintf(
                    'Arquivo original da execução #%d validado para reenvio.',
                    $origem->id
                )
            );

            $this->execucoes->registrarArquivo(
                execucaoId: $execucaoId,
                arquivo: $arquivo
            );

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::ENVIANDO,
                mensagem: sprintf(
                    'Reenviando %s para o SFTP.',
                    $arquivo->nome
                )
            );

            $envio = $this->enviarArquivo->execute(
                $arquivo
            );

            $this->execucoes->registrarStatus(
                execucaoId: $execucaoId,
                status: StatusExportacao::CONFIRMANDO_ENVIO,
                mensagem: sprintf(
                    'Reenvio confirmado em %s.',
                    $envio->remotePath
                )
            );

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

            return new ResultadoReenvioVendas(
                execucaoId: $execucaoId,
                execucaoOrigemId: $origem->id,
                arquivo: $arquivo,
                envio: $envio,
                duracaoSegundos: $duration
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
            }

            throw $exception;
        }
    }
}