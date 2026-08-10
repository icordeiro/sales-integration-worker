<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Contracts\RemoteFileStorage;
use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use Throwable;

final readonly class SftpConnectionHealthProbe implements HealthProbe
{
    public function __construct(
        private RemoteFileStorage $remoteStorage
    ) {
    }

    public function key(): string
    {
        return 'sftp_connection';
    }

    public function check(): HealthCheckDTO
    {
        $startedAt = hrtime(true);

        try {
            $this->remoteStorage->testConnection();

            $latencyMilliseconds = (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            );

            return new HealthCheckDTO(
                status: HealthStatus::OK,
                message: 'Conexão e autenticação SFTP validadas.',
                details: [
                    'connection_tested' => true,
                    'upload_performed' => false,
                    'latency_ms' => $latencyMilliseconds,
                ]
            );
        } catch (Throwable) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Não foi possível validar a conexão SFTP.',
                details: [
                    'connection_tested' => true,
                    'upload_performed' => false,
                ]
            );
        }
    }
}
