<?php

declare(strict_types=1);

namespace App\Shared\Application\Health\DTO;

use DateTimeImmutable;

final readonly class HealthReportDTO
{
    /**
     * @param array<string, HealthCheckDTO> $checks
     */
    public function __construct(
        public string $status,
        public DateTimeImmutable $checkedAt,
        public array $checks
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $checks = [];

        foreach ($this->checks as $key => $check) {
            $checks[$key] = $check->toArray();
        }

        return [
            'status' => $this->status,
            'checked_at' => $this->checkedAt->format(DATE_ATOM),
            'checks' => $checks,
        ];
    }
}
