<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Sftp\Config\SftpConfig;
use Throwable;

final readonly class SftpConfigurationHealthProbe implements HealthProbe
{
    public function __construct(
        private Environment $environment
    ) {
    }

    public function key(): string
    {
        return 'sftp';
    }

    public function check(): HealthCheckDTO
    {
        try {
            SftpConfig::fromEnvironment(
                $this->environment
            );

            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: 'Configuração SFTP válida.',
                details: [
                    'mode' => 'configuration_only',
                    'connection_tested' => false,
                ]
            );
        } catch (Throwable) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Configuração SFTP inválida.',
                details: [
                    'mode' => 'configuration_only',
                    'connection_tested' => false,
                ]
            );
        }
    }
}
