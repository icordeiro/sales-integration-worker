<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Response\JsonResponse;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoExportacaoVendas;
use App\Modules\Exportacao\Vendas\Application\DTO\ResultadoReenvioVendas;
use App\Modules\Exportacao\Vendas\Application\Services\ReenviarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReprocessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReenvioExportacaoException;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReprocessamentoExportacaoException;
use App\Shared\Infrastructure\Lock\FileProcessLock;
use App\Shared\Infrastructure\Sftp\Exception\SftpException;
use Orbiit\Router\Attributes\Route;
use Orbiit\Router\Enums\Method;
use Throwable;

final readonly class ExportacaoOperacaoController
{
    public function __construct(
        private ReenviarExportacaoVendasService $reenviar,
        private ReprocessarExportacaoVendasService $reprocessar,
        private FileProcessLock $processLock
    ) {
    }

    #[Route(
        path: '/api/exportacoes/{id}/reenviar',
        method: Method::POST
    )]
    public function reenviar(
        int $id
    ): string {
        if ($id <= 0) {
            return JsonResponse::error(
                message: 'Identificador da execução inválido.',
                status: 400
            );
        }

        if (!$this->isValidWriteRequest()) {
            return JsonResponse::error(
                message: 'Requisição de operação inválida.',
                status: 403
            );
        }

        if (!$this->acquireLock()) {
            return JsonResponse::error(
                message: 'Outra exportação está em andamento. Aguarde a conclusão antes de reenviar.',
                status: 409
            );
        }

        try {
            $resultado = $this->reenviar->execute(
                $id
            );

            return JsonResponse::success(
                $this->mapResultadoReenvio(
                    $resultado
                ),
                201
            );
        } catch (ReenvioExportacaoException $exception) {
            return JsonResponse::error(
                message: $exception->getMessage(),
                status: 422
            );
        } catch (SftpException $exception) {
            return JsonResponse::error(
                message: $exception->getMessage(),
                status: $this->sftpStatus($exception)
            );
        } catch (Throwable) {
            return JsonResponse::error(
                message: 'Não foi possível concluir o reenvio da exportação.',
                status: 500
            );
        } finally {
            $this->processLock->release();
        }
    }

    #[Route(
        path: '/api/exportacoes/{id}/reprocessar',
        method: Method::POST
    )]
    public function reprocessar(
        int $id
    ): string {
        if ($id <= 0) {
            return JsonResponse::error(
                message: 'Identificador da execução inválido.',
                status: 400
            );
        }

        if (!$this->isValidWriteRequest()) {
            return JsonResponse::error(
                message: 'Requisição de operação inválida.',
                status: 403
            );
        }

        if (!$this->acquireLock()) {
            return JsonResponse::error(
                message: 'Outra exportação está em andamento. Aguarde a conclusão antes de reprocessar.',
                status: 409
            );
        }

        try {
            $resultado = $this->reprocessar->execute(
                $id
            );

            return JsonResponse::success(
                $this->mapResultadoReprocessamento(
                    execucaoOrigemId: $id,
                    resultado: $resultado
                ),
                201
            );
        } catch (ReprocessamentoExportacaoException $exception) {
            return JsonResponse::error(
                message: $exception->getMessage(),
                status: 422
            );
        } catch (SftpException $exception) {
            return JsonResponse::error(
                message: $exception->getMessage(),
                status: $this->sftpStatus($exception)
            );
        } catch (Throwable) {
            return JsonResponse::error(
                message: 'Não foi possível concluir o reprocessamento da exportação.',
                status: 500
            );
        } finally {
            $this->processLock->release();
        }
    }

    private function acquireLock(): bool
    {
        try {
            return $this->processLock->acquire();
        } catch (Throwable) {
            return false;
        }
    }

    private function isValidWriteRequest(): bool
    {
        $requestedWith = strtolower(
            trim(
                (string) (
                    $_SERVER['HTTP_X_REQUESTED_WITH']
                    ?? ''
                )
            )
        );

        if ($requestedWith !== 'xmlhttprequest') {
            return false;
        }

        $contentType = strtolower(
            trim(
                (string) (
                    $_SERVER['CONTENT_TYPE']
                    ?? ''
                )
            )
        );

        return str_starts_with(
            $contentType,
            'application/json'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapResultadoReenvio(
        ResultadoReenvioVendas $resultado
    ): array {
        return [
            'operacao' => 'REENVIO',
            'execucao_id' => $resultado->execucaoId,
            'execucao_origem_id' => $resultado->execucaoOrigemId,
            'arquivo_nome' => $resultado->arquivo->nome,
            'quantidade_registros' => $resultado->arquivo->quantidadeRegistros,
            'tamanho_bytes' => $resultado->arquivo->tamanhoBytes,
            'sha256' => $resultado->arquivo->sha256,
            'caminho_remoto' => $resultado->envio->remotePath,
            'duracao_segundos' => $resultado->duracaoSegundos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapResultadoReprocessamento(
        int $execucaoOrigemId,
        ResultadoExportacaoVendas $resultado
    ): array {
        return [
            'operacao' => 'REPROCESSAMENTO',
            'execucao_id' => $resultado->execucaoId,
            'execucao_origem_id' => $execucaoOrigemId,
            'arquivo_nome' => $resultado->arquivo->nome,
            'quantidade_registros' => $resultado->arquivo->quantidadeRegistros,
            'tamanho_bytes' => $resultado->arquivo->tamanhoBytes,
            'sha256' => $resultado->arquivo->sha256,
            'caminho_remoto' => $resultado->envio->remotePath,
            'duracao_segundos' => $resultado->duracaoSegundos,
            'conteudo_alterado' => $resultado->conteudoAlterado,
        ];
    }

    private function sftpStatus(
        SftpException $exception
    ): int {
        return str_contains(
            $exception->getMessage(),
            'já existe'
        )
            ? 409
            : 502;
    }
}
