<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\StorageHealthProbe;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class StorageHealthProbeTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-health-storage');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testRetornaOkQuandoTodosOsDiretoriosExistemESaoGravaveis(): void
    {
        foreach (['database', 'exports', 'logs', 'runtime'] as $directory) {
            mkdir(
                $this->temporaryDirectory . DIRECTORY_SEPARATOR . $directory,
                0775,
                true
            );
        }

        $result = (new StorageHealthProbe(
            $this->temporaryDirectory
        ))->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertSame('ok', $result->details['directories']['database']);
        self::assertSame('ok', $result->details['directories']['runtime']);
    }

    public function testRetornaErroQuandoDiretorioObrigatorioNaoExiste(): void
    {
        foreach (['database', 'exports', 'logs'] as $directory) {
            mkdir(
                $this->temporaryDirectory . DIRECTORY_SEPARATOR . $directory,
                0775,
                true
            );
        }

        $result = (new StorageHealthProbe(
            $this->temporaryDirectory
        ))->check();

        self::assertSame(HealthStatus::ERROR, $result->status);
        self::assertSame('error', $result->details['directories']['runtime']);
    }
}
