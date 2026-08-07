<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Lock;

use RuntimeException;

final class FileProcessLock
{
    /**
     * @var resource|null
     */
    private mixed $handle = null;

    private bool $acquired = false;

    public function __construct(
        private readonly string $lockFile
    ) {
    }

    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $directory = dirname(
            $this->lockFile
        );

        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível criar o diretório de lock "%s".',
                    $directory
                )
            );
        }

        $handle = fopen(
            $this->lockFile,
            'c+'
        );

        if ($handle === false) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível abrir o arquivo de lock "%s".',
                    $this->lockFile
                )
            );
        }

        /*
         * LOCK_NB evita esperar pela outra instância.
         *
         * Se outra execução já possui o lock,
         * retornamos imediatamente.
         */
        if (
            !flock(
                $handle,
                LOCK_EX | LOCK_NB
            )
        ) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;
        $this->acquired = true;

        $this->writeMetadata();

        return true;
    }

    public function acquireWithWait(
        int $timeoutSeconds,
        int $pollIntervalMilliseconds = 500
    ): bool {
        if ($timeoutSeconds < 0) {
            throw new RuntimeException(
                'O tempo máximo de espera do lock não pode ser negativo.'
            );
        }

        if ($pollIntervalMilliseconds < 1) {
            throw new RuntimeException(
                'O intervalo de consulta do lock deve ser maior que zero.'
            );
        }

        $deadline = microtime(true)
            + $timeoutSeconds;

        do {
            if ($this->acquire()) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                return false;
            }

            usleep(
                $pollIntervalMilliseconds
                * 1000
            );
        } while (true);
    }

    public function release(): void
    {
        if (
            !$this->acquired
            || !is_resource($this->handle)
        ) {
            return;
        }

        flock(
            $this->handle,
            LOCK_UN
        );

        fclose(
            $this->handle
        );

        $this->handle = null;
        $this->acquired = false;
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    public function lockFile(): string
    {
        return $this->lockFile;
    }

    public function __destruct()
    {
        $this->release();
    }

    private function writeMetadata(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        $hostname = gethostname();

        if ($hostname === false) {
            $hostname = 'unknown';
        }

        $metadata = [
            'pid' => getmypid(),
            'hostname' => $hostname,
            'started_at' => date(DATE_ATOM),
        ];

        rewind(
            $this->handle
        );

        ftruncate(
            $this->handle,
            0
        );

        fwrite(
            $this->handle,
            json_encode(
                $metadata,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) ?: ''
        );

        fflush(
            $this->handle
        );
    }
}