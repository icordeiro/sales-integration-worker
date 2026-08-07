<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use App\Modules\Exportacao\Vendas\Application\Services\EnviarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReenviarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReenvioExportacaoException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeRemoteFileStorage;
use Tests\Support\InMemoryExportacaoExecucaoRepository;
use Tests\Support\TestFiles;

final class ReenviarExportacaoVendasServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-resend');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testReenviaExatamenteArquivoOriginalECriaExecucaoVinculada(): void
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, "HEADER\r\n1|2|3\r\n");

        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->completedOrigin(10, $path));

        $remote = new FakeRemoteFileStorage();
        $service = new ReenviarExportacaoVendasService(
            $repository,
            new EnviarArquivoVendasService($remote)
        );

        $result = $service->execute(10);

        self::assertSame(11, $result->execucaoId);
        self::assertSame(10, $result->execucaoOrigemId);
        self::assertCount(1, $remote->uploads);
        self::assertSame($path, $remote->uploads[0]['local_path']);
        self::assertSame('MV20260806_DEMO.txt', $remote->uploads[0]['remote_file_name']);

        $nova = $repository->buscarPorId(11);
        self::assertNotNull($nova);
        self::assertSame(TipoExecucao::REENVIO, $nova->tipoExecucao);
        self::assertSame(StatusExportacao::CONCLUIDO, $nova->status);
        self::assertSame(10, $nova->execucaoOrigemId);

        $origem = $repository->buscarPorId(10);
        self::assertNotNull($origem);
        self::assertSame(TipoExecucao::NORMAL, $origem->tipoExecucao);
        self::assertSame(StatusExportacao::CONCLUIDO, $origem->status);
    }

    public function testRecusaArquivoAlteradoComMesmoTamanho(): void
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, 'AAAA');

        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->completedOrigin(20, $path));

        file_put_contents($path, 'BBBB');

        $remote = new FakeRemoteFileStorage();
        $service = new ReenviarExportacaoVendasService(
            $repository,
            new EnviarArquivoVendasService($remote)
        );

        $this->expectException(ReenvioExportacaoException::class);
        $this->expectExceptionMessage('SHA-256');

        $service->execute(20);
    }

    public function testFalhaDeSftpMarcaNovaExecucaoComoFalhou(): void
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, 'arquivo valido');

        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->completedOrigin(30, $path));

        $remote = new FakeRemoteFileStorage();
        $remote->exception = new \RuntimeException('SFTP indisponível');

        $service = new ReenviarExportacaoVendasService(
            $repository,
            new EnviarArquivoVendasService($remote)
        );

        try {
            $service->execute(30);
            self::fail('Era esperada uma exceção de envio.');
        } catch (\RuntimeException $exception) {
            self::assertSame('SFTP indisponível', $exception->getMessage());
        }

        $nova = $repository->buscarPorId(31);
        self::assertNotNull($nova);
        self::assertSame(StatusExportacao::FALHOU, $nova->status);
        self::assertCount(1, $repository->falhas);
    }

    private function completedOrigin(int $id, string $path): ExportacaoExecucaoDTO
    {
        $size = filesize($path);
        $hash = hash_file('sha256', $path);

        self::assertIsInt($size);
        self::assertIsString($hash);

        return new ExportacaoExecucaoDTO(
            id: $id,
            dataMovimento: '2026-08-06',
            tipoExecucao: TipoExecucao::NORMAL,
            status: StatusExportacao::CONCLUIDO,
            execucaoOrigemId: null,
            arquivoNome: 'MV20260806_DEMO.txt',
            quantidadeRegistros: 2,
            tamanhoBytes: $size,
            sha256: $hash,
            caminhoLocal: $path,
            caminhoRemoto: '/DELIVERY/MV20260806_DEMO.txt'
        );
    }
}
