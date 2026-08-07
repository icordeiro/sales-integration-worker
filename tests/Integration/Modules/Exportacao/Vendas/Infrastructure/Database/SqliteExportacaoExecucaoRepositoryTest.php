<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Exportacao\Vendas\Infrastructure\Database;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\SqliteExportacaoExecucaoRepository;
use App\Shared\Application\DTO\RemoteFileUploadResult;
use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Database\SqliteConnection;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class SqliteExportacaoExecucaoRepositoryTest extends TestCase
{
    private string $temporaryDirectory;
    private SqliteConnection $connection;
    private SqliteExportacaoExecucaoRepository $repository;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('A extensão pdo_sqlite é necessária para este teste.');
        }

        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-sqlite');
        $databasePath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'test.sqlite';

        $this->connection = new SqliteConnection(
            new PortalSqliteConfig($databasePath)
        );

        $pdo = $this->connection->connection();
        $this->runMigration($pdo, '001_create_exportacao_execucao.sql');
        $this->runMigration($pdo, '002_add_exportacao_normal_idempotencia.sql');

        $this->repository = new SqliteExportacaoExecucaoRepository(
            $this->connection
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory)) {
            unset($this->repository, $this->connection);
            gc_collect_cycles();
            TestFiles::removeDirectory($this->temporaryDirectory);
        }
    }

    public function testPersisteCicloCompletoDaExecucao(): void
    {
        $id = $this->repository->iniciar(
            '2026-08-06',
            TipoExecucao::NORMAL
        );

        self::assertTrue(
            $this->repository->existeExecucaoNormalBloqueante('2026-08-06')
        );

        $this->repository->registrarStatus(
            $id,
            StatusExportacao::CONSULTANDO,
            'Consultando vendas.'
        );

        $arquivo = new ArquivoVendaGerado(
            nome: 'MV20260806_DEMO.txt',
            caminho: '/tmp/MV20260806_DEMO.txt',
            dataReferencia: '2026-08-06',
            quantidadeRegistros: 120,
            tamanhoBytes: 4096,
            sha256: str_repeat('a', 64)
        );

        $this->repository->registrarArquivo($id, $arquivo);

        $envio = new RemoteFileUploadResult(
            fileName: $arquivo->nome,
            remotePath: '/DELIVERY/' . $arquivo->nome,
            sizeBytes: $arquivo->tamanhoBytes,
            uploadedAt: new DateTimeImmutable('2026-08-07T14:00:00-03:00')
        );

        $this->repository->concluir($id, $arquivo, $envio, 1500);

        $execucao = $this->repository->buscarPorId($id);

        self::assertNotNull($execucao);
        self::assertSame(StatusExportacao::CONCLUIDO, $execucao->status);
        self::assertSame($arquivo->sha256, $execucao->sha256);
        self::assertSame($envio->remotePath, $execucao->caminhoRemoto);

        $eventCount = (int) $this->connection
            ->connection()
            ->query('SELECT COUNT(*) FROM exportacao_execucao_evento')
            ->fetchColumn();

        self::assertSame(3, $eventCount);
    }

    public function testExecucaoNormalFalhaDeixaDeBloquearNovaTentativa(): void
    {
        $id = $this->repository->iniciar('2026-08-06');

        $this->repository->falhar(
            $id,
            'Falha controlada de teste.',
            100
        );

        self::assertFalse(
            $this->repository->existeExecucaoNormalBloqueante('2026-08-06')
        );

        $novoId = $this->repository->iniciar('2026-08-06');

        self::assertGreaterThan($id, $novoId);
    }

    private function runMigration(PDO $pdo, string $fileName): void
    {
        $path = dirname(__DIR__, 7)
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'migrations'
            . DIRECTORY_SEPARATOR
            . $fileName;

        $sql = file_get_contents($path);

        if ($sql === false) {
            self::fail('Não foi possível ler a migration ' . $fileName);
        }

        $pdo->exec($sql);
    }
}
