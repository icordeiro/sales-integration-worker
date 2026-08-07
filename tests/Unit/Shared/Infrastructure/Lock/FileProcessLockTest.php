<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Lock;

use App\Shared\Infrastructure\Lock\FileProcessLock;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class FileProcessLockTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-lock');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testImpedeSegundaAquisicaoEnquantoPrimeiraEstaAtiva(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'export.lock';
        $first = new FileProcessLock($file);
        $second = new FileProcessLock($file);

        self::assertTrue($first->acquire());
        self::assertTrue($first->isAcquired());
        self::assertFalse($second->acquire());

        $first->release();

        self::assertTrue($second->acquire());
        self::assertTrue($second->isAcquired());

        $second->release();
    }

    public function testAcquireEhIdempotenteNaMesmaInstancia(): void
    {
        $lock = new FileProcessLock(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'export.lock'
        );

        self::assertTrue($lock->acquire());
        self::assertTrue($lock->acquire());

        $lock->release();
        self::assertFalse($lock->isAcquired());
    }
}
