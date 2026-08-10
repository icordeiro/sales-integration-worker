<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\View\Twig\TwigRenderer;
use App\Shared\Application\Demo\DemoMode;
use Orbiit\Router\Attributes\Route;
use Orbiit\Router\Enums\Method;

final readonly class DashboardController
{
    public function __construct(
        private TwigRenderer $view,
        private DemoMode $demoMode
    ) {
    }

    #[Route(
        path: '/',
        method: Method::GET,
        name: 'dashboard'
    )]
    public function index(): string
    {
        return $this->view->render(
            'pages/dashboard/index.twig',
            [
                'TITLE' => $this->demoMode->enabled
                    ? 'SIW - Modo demonstração'
                    : 'NielsenIQ - Monitor de Exportações',

                'REFRESH_INTERVAL' => 15000,

                'HEALTH_REFRESH_INTERVAL' => 60000,

                'DEMO_MODE' => $this->demoMode->enabled,
            ]
        );
    }
}
