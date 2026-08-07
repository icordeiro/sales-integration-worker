<?php

declare(strict_types=1);

namespace App\Shared\Application\DTO;

use DateTimeImmutable;

final readonly class RemoteFileUploadResult
{
    public function __construct(
        public string $fileName,
        public string $remotePath,
        public int $sizeBytes,
        public DateTimeImmutable $uploadedAt
    ) {
    }
}