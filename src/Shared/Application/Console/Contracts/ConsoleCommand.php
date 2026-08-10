<?php

declare(strict_types=1);

namespace App\Shared\Application\Console\Contracts;

use App\Shared\Infrastructure\Console\ConsoleOutput;

interface ConsoleCommand
{
    public function name(): string;

    public function description(): string;

    public function execute(
        array $arguments,
        ConsoleOutput $output
    ): int;
}
