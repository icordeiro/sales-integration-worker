<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Application\Contracts\RemoteFileStorage;
use App\Shared\Application\DTO\RemoteFileUploadResult;
use DateTimeImmutable;
use Throwable;

final class FakeRemoteFileStorage implements RemoteFileStorage
{
    /** @var list<array{local_path:string,remote_file_name:string}> */
    public array $uploads = [];

    public int $connectionTests = 0;

    public ?Throwable $exception = null;

    public function testConnection(): void
    {
        $this->connectionTests++;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }

    public function uploadAtomically(
        string $localPath,
        string $remoteFileName
    ): RemoteFileUploadResult {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->uploads[] = [
            'local_path' => $localPath,
            'remote_file_name' => $remoteFileName,
        ];

        $size = is_file($localPath)
            ? filesize($localPath)
            : 0;

        return new RemoteFileUploadResult(
            fileName: $remoteFileName,
            remotePath: '/DELIVERY/' . $remoteFileName,
            sizeBytes: $size === false ? 0 : $size,
            uploadedAt: new DateTimeImmutable('2026-08-07T14:00:00-03:00')
        );
    }
}
