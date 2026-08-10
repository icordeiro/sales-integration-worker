<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Infrastructure\Http\Response\JsonResponse;
use App\Shared\Application\Health\Services\HealthCheckService;
use Orbiit\Router\Attributes\Route;
use Orbiit\Router\Enums\Method;
use Throwable;

final readonly class HealthController
{
    public function __construct(
        private HealthCheckService $healthCheck
    ) {
    }

    #[Route(
        path: '/api/health',
        method: Method::GET
    )]
    public function index(): string
    {
        try {
            return JsonResponse::success(
                $this->healthCheck
                    ->execute()
                    ->toArray()
            );
        } catch (Throwable) {
            return JsonResponse::error(
                message: 'Não foi possível verificar a saúde da integração.',
                status: 500
            );
        }
    }
}
