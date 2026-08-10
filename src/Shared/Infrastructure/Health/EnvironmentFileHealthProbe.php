<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class EnvironmentFileHealthProbe implements HealthProbe
{
    /**
     * @param list<string> $requiredKeys
     */
    public function __construct(
        private string $environmentFile,
        private array $requiredKeys
    ) {
    }

    public function key(): string
    {
        return 'environment';
    }

    public function check(): HealthCheckDTO
    {
        if (!is_file($this->environmentFile)) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Arquivo .env não encontrado.'
            );
        }

        if (!is_readable($this->environmentFile)) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Arquivo .env sem permissão de leitura.'
            );
        }

        $lines = file(
            $this->environmentFile,
            FILE_IGNORE_NEW_LINES
        );

        if ($lines === false) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Não foi possível ler o arquivo .env.'
            );
        }

        $presentKeys = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Z0-9_]+)\s*=/', $line, $matches)) {
                continue;
            }

            $presentKeys[$matches[1]] = true;
        }

        $missingKeys = [];

        foreach ($this->requiredKeys as $requiredKey) {
            if (!isset($presentKeys[$requiredKey])) {
                $missingKeys[] = $requiredKey;
            }
        }

        if ($missingKeys !== []) {
            return new HealthCheckDTO(
                status: HealthStatus::ERROR,
                message: 'Arquivo .env incompleto.',
                details: [
                    'missing_keys' => $missingKeys,
                ]
            );
        }

        return new HealthCheckDTO(
            status: HealthStatus::OK,
            message: 'Arquivo .env disponível e estruturalmente completo.',
            details: [
                'required_keys' => count($this->requiredKeys),
            ]
        );
    }
}
