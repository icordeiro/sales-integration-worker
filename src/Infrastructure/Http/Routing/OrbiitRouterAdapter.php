<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Routing;

use App\Core\Container;
use App\Http\Controllers\Api\ExportacaoDashboardController;
use App\Http\Controllers\Api\ExportacaoOperacaoController;
use App\Http\Controllers\DashboardController;
use Orbiit\Router\Router;

final class OrbiitRouterAdapter
{
    private Router $router;

    public function __construct(
        Container $container
    ) {
        $this->router =
            new Router();

        $this->router->setNamespace(
            'App\\Http\\Controllers'
        );

        /*
         * O OrbiitRouter delegará a criação
         * dos controllers ao nosso Container.
         */
        $this->router->setResolver(
            static fn (
                string $controllerClass
            ): object => $container->make(
                $controllerClass
            )
        );

        $this->loadControllers();
    }

    public function dispatch(): mixed
    {
        /*
         * Apache/.htaccess:
         *
         * index.php?route=/api/...
         *
         * Servidor embutido / outros ambientes:
         *
         * REQUEST_URI
         */
        $uri =
            $_GET['route']
            ?? $_SERVER['REQUEST_URI']
            ?? '/';

        $method =
            $_SERVER['REQUEST_METHOD']
            ?? 'GET';

        return $this->router->dispatch(
            (string) $uri,
            (string) $method
        );
    }

    private function loadControllers(): void
    {
        /*
         * Como o namespace-base é
         * App\Http\Controllers, usamos nomes relativos.
         */

        $this->router->loadController(
            'DashboardController'
        );

        $this->router->loadController(
            'Api\\ExportacaoDashboardController'
        );

        $this->router->loadController(
            'Api\\ExportacaoOperacaoController'
        );
    }
}