<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Demo;

use App\Shared\Infrastructure\Demo\DemoDatabaseSeeder;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class DemoDatabaseSeederTest extends TestCase
{
    private string $temporaryDirectory;
    private string $databasePath;
    private string $exportsDirectory;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-demo-seed');
        $this->databasePath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'demo.sqlite';
        $this->exportsDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'exports';
        $this->projectRoot = dirname(__DIR__, 5);
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testCriaDatasetDemonstrativoCompleto(): void
    {
        $result = $this->seeder()->seed(reset: true);

        self::assertSame(8, $result['executions']);
        self::assertSame(6, $result['files']);
        self::assertFileExists($this->databasePath);

        $pdo = new PDO('sqlite:' . $this->databasePath);

        self::assertSame(
            8,
            (int) $pdo->query('SELECT COUNT(*) FROM exportacao_execucao')->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM exportacao_execucao WHERE status = 'FALHOU'")->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM exportacao_execucao WHERE tipo_execucao = 'REPROCESSAMENTO'")->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM exportacao_execucao WHERE tipo_execucao = 'REENVIO'")->fetchColumn()
        );
    }

    public function testSeedSemResetNaoDuplicaExecucoes(): void
    {
        $this->seeder()->seed(reset: true);
        $result = $this->seeder()->seed(reset: false);

        self::assertSame(8, $result['executions']);

        $pdo = new PDO('sqlite:' . $this->databasePath);

        self::assertSame(
            8,
            (int) $pdo->query('SELECT COUNT(*) FROM exportacao_execucao')->fetchColumn()
        );
    }

    public function testReenvioMantemMesmoHashEReprocessamentoGeraConteudoDiferente(): void
    {
        $this->seeder()->seed(reset: true);

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $resend = $pdo->query(
            <<<'SQL'
            SELECT atual.sha256 AS atual_sha, origem.sha256 AS origem_sha
            FROM exportacao_execucao atual
            INNER JOIN exportacao_execucao origem
                ON origem.id = atual.execucao_origem_id
            WHERE atual.tipo_execucao = 'REENVIO'
            LIMIT 1
            SQL
        )->fetch();

        self::assertIsArray($resend);
        self::assertSame($resend['origem_sha'], $resend['atual_sha']);

        $reprocess = $pdo->query(
            <<<'SQL'
            SELECT atual.sha256 AS atual_sha, origem.sha256 AS origem_sha
            FROM exportacao_execucao atual
            INNER JOIN exportacao_execucao origem
                ON origem.id = atual.execucao_origem_id
            WHERE atual.tipo_execucao = 'REPROCESSAMENTO'
            LIMIT 1
            SQL
        )->fetch();

        self::assertIsArray($reprocess);
        self::assertNotSame($reprocess['origem_sha'], $reprocess['atual_sha']);
    }

    private function seeder(): DemoDatabaseSeeder
    {
        return new DemoDatabaseSeeder(
            databasePath: $this->databasePath,
            migrationsDirectory: $this->projectRoot
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations',
            exportsDirectory: $this->exportsDirectory,
            timezone: new DateTimeZone('America/Fortaleza')
        );
    }
}
