<?php

declare(strict_types=1);

namespace App\Shared\Application\Health\DTO;

use App\Shared\Application\Health\Enum\HealthStatus;

final readonly class HealthCheckDTO
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public HealthStatus $status,
        public string $message,
        public array $details = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }
}
