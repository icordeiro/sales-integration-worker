<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Response\JsonResponse;
use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception\ExportacaoDashboardQueryException;
use Orbiit\Router\Attributes\Route;
use Orbiit\Router\Enums\Method;
use Throwable;

final readonly class ExportacaoDashboardController
{
    public function __construct(
        private ExportacaoDashboardQuery $query
    ) {
    }

    #[Route(
        path: '/api/dashboard/resumo',
        method: Method::GET
    )]
    public function resumo(): string
    {
        try {
            return JsonResponse::success(
                $this->query
                    ->resumo()
                    ->toArray()
            );
        } catch (
            ExportacaoDashboardQueryException $exception
        ) {
            return JsonResponse::error(
                message:
                    'Não foi possível carregar o resumo do dashboard.',
                status: 500
            );
        }
    }

    #[Route(
        path: '/api/exportacoes',
        method: Method::GET
    )]
    public function listar(): string
    {
        try {
            $limit =
                isset($_GET['limit'])
                    ? (int) $_GET['limit']
                    : 50;

            $execucoes =
                $this->query->listarRecentes(
                    $limit
                );

            return JsonResponse::success(
                array_map(
                    static fn ($execucao): array =>
                        $execucao->toArray(),
                    $execucoes
                )
            );
        } catch (
            ExportacaoDashboardQueryException $exception
        ) {
            return JsonResponse::error(
                message:
                    'Não foi possível carregar o histórico de exportações.',
                status: 500
            );
        }
    }

    #[Route(
        path: '/api/exportacoes/{id}',
        method: Method::GET
    )]
    public function detalhe(
        int $id
    ): string {
        if ($id <= 0) {
            return JsonResponse::error(
                message:
                    'Identificador da execução inválido.',
                status: 400
            );
        }

        try {
            $detalhe =
                $this->query->buscarDetalhe(
                    $id
                );

            if ($detalhe === null) {
                return JsonResponse::error(
                    message:
                        'Execução não encontrada.',
                    status: 404
                );
            }

            return JsonResponse::success(
                $detalhe->toArray()
            );
        } catch (
            ExportacaoDashboardQueryException $exception
        ) {
            return JsonResponse::error(
                message:
                    'Não foi possível carregar os detalhes da execução.',
                status: 500
            );
        }
    }
}