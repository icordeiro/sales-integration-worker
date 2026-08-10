<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use App\Shared\Infrastructure\Health\MigrationHealthProbe;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class MigrationHealthProbeTest extends TestCase
{
    private string $temporaryDirectory;
    private PDO $pdo;
    private DatabaseConnection $database;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-migration-health');
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo = $this->pdo;

        $this->database = new class($pdo) implements DatabaseConnection {
            public function __construct(
                private readonly PDO $pdo
            ) {
            }

            public function connection(): PDO
            {
                return $this->pdo;
            }

            public function isConnected(): bool
            {
                return true;
            }
        };
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testRetornaErroQuandoControleNaoFoiInicializado(): void
    {
        file_put_contents(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '001_init.sql',
            'SELECT 1;'
        );

        $result = $this->probe()->check();

        self::assertSame(HealthStatus::ERROR, $result->status);
        self::assertSame(1, $result->details['pending']);
    }

    public function testRetornaWarningQuandoExistemMigrationsPendentes(): void
    {
        $this->createMigrationTable();

        file_put_contents(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '001_init.sql',
            'SELECT 1;'
        );
        file_put_contents(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '002_more.sql',
            'SELECT 2;'
        );

        $this->pdo->exec(
            "INSERT INTO schema_migration (migration) VALUES ('001_init.sql')"
        );

        $result = $this->probe()->check();

        self::assertSame(HealthStatus::WARNING, $result->status);
        self::assertSame(1, $result->details['pending']);
        self::assertSame(['002_more.sql'], $result->details['pending_migrations']);
    }

    public function testRetornaOkQuandoTodasAsMigrationsForamExecutadas(): void
    {
        $this->createMigrationTable();

        file_put_contents(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '001_init.sql',
            'SELECT 1;'
        );

        $this->pdo->exec(
            "INSERT INTO schema_migration (migration) VALUES ('001_init.sql')"
        );

        $result = $this->probe()->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertSame(0, $result->details['pending']);
    }

    private function probe(): MigrationHealthProbe
    {
        return new MigrationHealthProbe(
            $this->database,
            $this->temporaryDirectory
        );
    }

    private function createMigrationTable(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE schema_migration
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }
}
