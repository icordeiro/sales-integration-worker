<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\SftpConnectionHealthProbe;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeRemoteFileStorage;

final class SftpConnectionHealthProbeTest extends TestCase
{
    public function testValidaConexaoSemRealizarUpload(): void
    {
        $storage = new FakeRemoteFileStorage();
        $probe = new SftpConnectionHealthProbe($storage);

        $result = $probe->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertSame(1, $storage->connectionTests);
        self::assertSame([], $storage->uploads);
        self::assertFalse($result->details['upload_performed']);
    }

    public function testRetornaErroQuandoConexaoFalha(): void
    {
        $storage = new FakeRemoteFileStorage();
        $storage->exception = new RuntimeException('offline');

        $result = (new SftpConnectionHealthProbe($storage))->check();

        self::assertSame(HealthStatus::ERROR, $result->status);
        self::assertSame(1, $storage->connectionTests);
    }
}
