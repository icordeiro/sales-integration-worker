<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Kernel;
use App\Infrastructure\Http\Routing\OrbiitRouterAdapter;
use App\Infrastructure\View\Twig\TwigRenderer;
use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Application\Services\ReenviarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReprocessarExportacaoVendasService;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Lock\FileProcessLock;

$services =
    require __DIR__
    . '/services.php';

/*
|--------------------------------------------------------------------------
| Container HTTP
|--------------------------------------------------------------------------
*/

$container =
    new Container();

/*
|--------------------------------------------------------------------------
| Serviços já construídos
|--------------------------------------------------------------------------
*/

/** @var Environment $environment */
$environment =
    $services['environment'];

/** @var ExportacaoDashboardQuery $dashboardQuery */
$dashboardQuery =
    $services['exportacao_dashboard_query'];

$container->instance(
    Environment::class,
    $environment
);

$container->instance(
    ExportacaoDashboardQuery::class,
    $dashboardQuery
);

/*
|--------------------------------------------------------------------------
| Operações manuais
|--------------------------------------------------------------------------
|
| O dashboard de leitura continua leve. A pilha SFTP só é montada quando
| uma rota de reenvio/reprocessamento realmente é chamada.
|
*/

$operationServices = null;

$resolveOperationServices =
    static function () use (&$operationServices): array {
        if ($operationServices === null) {
            $operationServices =
                require __DIR__
                . '/sftp-services.php';
        }

        return $operationServices;
    };

$container->bind(
    ReenviarExportacaoVendasService::class,
    static function (
        Container $container
    ) use ($resolveOperationServices): ReenviarExportacaoVendasService {
        unset($container);

        $resolved =
            $resolveOperationServices();

        return $resolved['reenviar_exportacao_vendas_service'];
    }
);

$container->bind(
    ReprocessarExportacaoVendasService::class,
    static function (
        Container $container
    ) use ($resolveOperationServices): ReprocessarExportacaoVendasService {
        unset($container);

        $resolved =
            $resolveOperationServices();

        return $resolved['reprocessar_exportacao_vendas_service'];
    }
);

$container->bind(
    FileProcessLock::class,
    static function (
        Container $container
    ): FileProcessLock {
        unset($container);

        return new FileProcessLock(
            dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'runtime'
            . DIRECTORY_SEPARATOR
            . 'export-sales-daily.lock'
        );
    }
);

/*
|--------------------------------------------------------------------------
| Twig
|--------------------------------------------------------------------------
*/

$twig =
    new TwigRenderer(
        viewsPath:
            dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'views',

        debug:
            $environment->boolean(
                'APP_DEBUG'
            )
    );

$container->instance(
    TwigRenderer::class,
    $twig
);

/*
|--------------------------------------------------------------------------
| Router
|--------------------------------------------------------------------------
*/

$router =
    new OrbiitRouterAdapter(
        $container
    );

$container->instance(
    OrbiitRouterAdapter::class,
    $router
);

/*
|--------------------------------------------------------------------------
| Kernel
|--------------------------------------------------------------------------
*/

$kernel =
    new Kernel(
        $router
    );

$container->instance(
    Kernel::class,
    $kernel
);

return [
    ...$services,

    'container' =>
        $container,

    'twig' =>
        $twig,

    'router' =>
        $router,

    'kernel' =>
        $kernel,
];