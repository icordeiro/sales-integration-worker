<?php

declare(strict_types=1);

namespace App\Core;

use App\Infrastructure\Http\Response\JsonResponse;
use App\Infrastructure\Http\Routing\OrbiitRouterAdapter;
use Orbiit\Router\Exceptions\MethodNotAllowedException;
use Orbiit\Router\Exceptions\RouteNotFoundException;
use Throwable;

final readonly class Kernel
{
    public function __construct(
        private OrbiitRouterAdapter $router
    ) {
    }

    public function handle(): void
    {
        try {
            $response =
                $this->router->dispatch();

            $this->emit(
                $response
            );
        } catch (
            RouteNotFoundException $exception
        ) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Rota não encontrada.',
                    status: 404
                );

                return;
            }

            http_response_code(404);

            echo 'Página não encontrada.';
        } catch (
            MethodNotAllowedException $exception
        ) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Método HTTP não permitido.',
                    status: 405
                );

                return;
            }

            http_response_code(405);

            echo 'Método não permitido.';
        } catch (Throwable $exception) {
            if ($this->isApiRequest()) {
                echo JsonResponse::error(
                    message: 'Erro interno da aplicação.',
                    status: 500
                );

                return;
            }

            http_response_code(500);

            echo 'Erro interno da aplicação.';
        }
    }

    private function emit(
        mixed $response
    ): void {
        if ($response === null) {
            return;
        }

        if (is_string($response)) {
            echo $response;

            return;
        }

        if (
            is_int($response)
            || is_float($response)
            || is_bool($response)
        ) {
            echo (string) $response;

            return;
        }

        echo JsonResponse::success(
            $response
        );
    }

    private function isApiRequest(): bool
    {
        $route =
            $_GET['route']
            ?? $_SERVER['REQUEST_URI']
            ?? '/';

        $path =
            parse_url(
                (string) $route,
                PHP_URL_PATH
            );

        if (!is_string($path)) {
            return false;
        }

        return str_starts_with(
            $path,
            '/api/'
        );
    }
}