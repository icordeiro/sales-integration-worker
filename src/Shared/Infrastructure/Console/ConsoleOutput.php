<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use RuntimeException;

final class ConsoleOutput
{
    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    /**
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        mixed $stdout = null,
        mixed $stderr = null
    ) {
        $this->stdout = $stdout ?? $this->openDefaultStream('php://stdout');
        $this->stderr = $stderr ?? $this->openDefaultStream('php://stderr');
    }

    public function line(string $message = ''): void
    {
        fwrite(
            $this->stdout,
            $message . PHP_EOL
        );
    }

    public function error(string $message = ''): void
    {
        fwrite(
            $this->stderr,
            $message . PHP_EOL
        );
    }

    /**
     * @return resource
     */
    private function openDefaultStream(string $uri): mixed
    {
        $stream = fopen($uri, 'wb');

        if ($stream === false) {
            throw new RuntimeException(
                sprintf('Não foi possível abrir o stream %s.', $uri)
            );
        }

        return $stream;
    }
}
