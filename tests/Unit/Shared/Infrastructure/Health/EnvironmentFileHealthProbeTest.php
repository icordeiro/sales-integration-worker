<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\EnvironmentFileHealthProbe;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class EnvironmentFileHealthProbeTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-env-health');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testRetornaErroQuandoArquivoNaoExiste(): void
    {
        $probe = new EnvironmentFileHealthProbe(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.env',
            ['APP_ENV']
        );

        $result = $probe->check();

        self::assertSame(HealthStatus::ERROR, $result->status);
        self::assertSame('Arquivo .env não encontrado.', $result->message);
    }

    public function testRetornaErroQuandoFaltamChavesObrigatorias(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($file, "APP_ENV=testing\n");

        $probe = new EnvironmentFileHealthProbe(
            $file,
            ['APP_ENV', 'DB_HOST', 'SFTP_HOST']
        );

        $result = $probe->check();

        self::assertSame(HealthStatus::ERROR, $result->status);
        self::assertSame(
            ['DB_HOST', 'SFTP_HOST'],
            $result->details['missing_keys']
        );
    }

    public function testIgnoraComentariosELinhasEmBranco(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.env';
        file_put_contents(
            $file,
            "# comentário\n\nAPP_ENV=testing\nDB_HOST=127.0.0.1\n"
        );

        $probe = new EnvironmentFileHealthProbe(
            $file,
            ['APP_ENV', 'DB_HOST']
        );

        $result = $probe->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertSame(2, $result->details['required_keys']);
    }
}
