<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class StorageHealthProbe implements HealthProbe
{
    /** @var list<string> */
    private const REQUIRED_DIRECTORIES = [
        'database',
        'exports',
        'logs',
        'runtime',
    ];

    public function __construct(
        private string $storageDirectory
    ) {
    }

    public function key(): string
    {
        return 'storage';
    }

    public function check(): HealthCheckDTO
    {
        $directories = [];
        $allWritable = true;

        foreach (self::REQUIRED_DIRECTORIES as $directoryName) {
            $directory = rtrim(
                $this->storageDirectory,
                '/\\'
            )
                . DIRECTORY_SEPARATOR
                . $directoryName;

            $available = is_dir($directory)
                && is_writable($directory);

            $directories[$directoryName] = $available
                ? 'ok'
                : 'error';

            if (!$available) {
                $allWritable = false;
            }
        }

        return new HealthCheckDTO(
            status: $allWritable
                ? HealthStatus::OK
                : HealthStatus::ERROR,
            message: $allWritable
                ? 'Diretórios de runtime graváveis.'
                : 'Há diretórios de runtime indisponíveis.',
            details: [
                'directories' => $directories,
            ]
        );
    }
}
