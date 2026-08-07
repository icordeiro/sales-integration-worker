<?php

declare(strict_types=1);

namespace App\Infrastructure\View\Twig;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigRenderer
{
    private Environment $twig;

    public function __construct(
        string $viewsPath,
        bool $debug = false
    ) {
        $loader =
            new FilesystemLoader(
                $viewsPath
            );

        $this->twig =
            new Environment(
                $loader,
                [
                    'debug' => $debug,
                    'strict_variables' => $debug,
                    'autoescape' => 'html',
                    'cache' => false,
                ]
            );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(
        string $template,
        array $data = []
    ): string {
        return $this->twig->render(
            $template,
            $data
        );
    }
}