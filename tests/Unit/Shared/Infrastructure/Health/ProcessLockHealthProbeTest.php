<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\ProcessLockHealthProbe;
use App\Shared\Infrastructure\Lock\FileProcessLock;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class ProcessLockHealthProbeTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-health-lock');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testInformaDisponivelEOcupadoSemTomarPosseDoLock(): void
    {
        $lockFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . 'worker.lock';

        $owner = new FileProcessLock($lockFile);
        $probeLock = new FileProcessLock($lockFile);
        $probe = new ProcessLockHealthProbe($probeLock);

        $available = $probe->check();

        self::assertSame(HealthStatus::OK, $available->status);
        self::assertSame('available', $available->details['state']);

        self::assertTrue($owner->acquire());

        $busy = $probe->check();

        self::assertSame(HealthStatus::OK, $busy->status);
        self::assertSame('busy', $busy->details['state']);
        self::assertFalse($probeLock->isAcquired());

        $owner->release();
    }
}
