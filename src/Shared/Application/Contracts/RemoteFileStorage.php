<?php

declare(strict_types=1);

namespace App\Shared\Application\Contracts;

use App\Shared\Application\DTO\RemoteFileUploadResult;

interface RemoteFileStorage
{
    public function testConnection(): void;

    public function uploadAtomically(
        string $localPath,
        string $remoteFileName
    ): RemoteFileUploadResult;
}