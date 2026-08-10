<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class PhpRuntimeHealthProbe implements HealthProbe
{
    private const MINIMUM_VERSION = '8.5.0';

    /** @var list<string> */
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_pgsql',
        'pdo_sqlite',
        'openssl',
        'mbstring',
    ];

    public function key(): string
    {
        return 'php';
    }

    public function check(): HealthCheckDTO
    {
        $missingExtensions = array_values(
            array_filter(
                self::REQUIRED_EXTENSIONS,
                static fn (string $extension): bool => !extension_loaded($extension)
            )
        );

        $versionSupported = version_compare(
            PHP_VERSION,
            self::MINIMUM_VERSION,
            '>='
        );

        $status = $versionSupported && $missingExtensions === []
            ? HealthStatus::OK
            : HealthStatus::ERROR;

        $message = $status === HealthStatus::OK
            ? sprintf('PHP %s operacional.', PHP_VERSION)
            : 'Runtime PHP incompleto ou incompatível.';

        return new HealthCheckDTO(
            status: $status,
            message: $message,
            details: [
                'version' => PHP_VERSION,
                'minimum_version' => self::MINIMUM_VERSION,
                'missing_extensions' => $missingExtensions,
            ]
        );
    }
}
