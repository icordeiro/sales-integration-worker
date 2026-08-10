<?php

declare(strict_types=1);

namespace App\Shared\Application\Demo;

final readonly class DemoMode
{
    public function __construct(
        public bool $enabled
    ) {
    }
}
