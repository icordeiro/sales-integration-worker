<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\EnviarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReprocessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReprocessamentoExportacaoException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeRemoteFileStorage;
use Tests\Support\FakeVendaArquivoGenerator;
use Tests\Support\FakeVendaExportacaoGateway;
use Tests\Support\InMemoryExportacaoExecucaoRepository;
use Tests\Support\TestFiles;

final class ReprocessarExportacaoVendasServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-reprocess');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testConsultaErpNovamenteEGeraExecucaoVinculadaComConteudoAlterado(): void
    {
        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->origin(10, str_repeat('a', 64)));

        $gateway = new FakeVendaExportacaoGateway();
        $gateway->vendas = [$this->sale()];

        $arquivo = $this->generatedFile(str_repeat('b', 64));
        $generator = new FakeVendaArquivoGenerator($arquivo);
        $remote = new FakeRemoteFileStorage();

        $service = $this->service($repository, $gateway, $generator, $remote);
        $result = $service->execute(10);

        self::assertTrue($result->conteudoAlterado);
        self::assertCount(1, $gateway->periodosConsultados);
        self::assertSame('2026-08-06', $gateway->periodosConsultados[0]->dataReferencia());
        self::assertCount(1, $generator->geracoes);
        self::assertSame(11, $generator->geracoes[0]['execucao_id']);
        self::assertSame(1, $generator->geracoes[0]['quantidade']);
        self::assertCount(1, $remote->uploads);

        $nova = $repository->buscarPorId(11);
        self::assertNotNull($nova);
        self::assertSame(TipoExecucao::REPROCESSAMENTO, $nova->tipoExecucao);
        self::assertSame(10, $nova->execucaoOrigemId);
        self::assertSame(StatusExportacao::CONCLUIDO, $nova->status);

        $origem = $repository->buscarPorId(10);
        self::assertNotNull($origem);
        self::assertSame(str_repeat('a', 64), $origem->sha256);
        self::assertSame(StatusExportacao::CONCLUIDO, $origem->status);
    }

    public function testInformaConteudoIdenticoQuandoHashNaoMudou(): void
    {
        $hash = str_repeat('c', 64);
        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->origin(20, $hash));

        $gateway = new FakeVendaExportacaoGateway();
        $gateway->vendas = [$this->sale()];

        $generator = new FakeVendaArquivoGenerator(
            $this->generatedFile($hash)
        );

        $result = $this->service(
            $repository,
            $gateway,
            $generator,
            new FakeRemoteFileStorage()
        )->execute(20);

        self::assertFalse($result->conteudoAlterado);
    }

    public function testRecusaOrigemNaoConcluidaSemConsultarErp(): void
    {
        $repository = new InMemoryExportacaoExecucaoRepository();
        $origin = $this->origin(30, str_repeat('d', 64));
        $repository->seed(new ExportacaoExecucaoDTO(
            id: $origin->id,
            dataMovimento: $origin->dataMovimento,
            tipoExecucao: $origin->tipoExecucao,
            status: StatusExportacao::FALHOU,
            execucaoOrigemId: null,
            arquivoNome: $origin->arquivoNome,
            quantidadeRegistros: $origin->quantidadeRegistros,
            tamanhoBytes: $origin->tamanhoBytes,
            sha256: $origin->sha256,
            caminhoLocal: $origin->caminhoLocal,
            caminhoRemoto: $origin->caminhoRemoto
        ));

        $gateway = new FakeVendaExportacaoGateway();
        $generator = new FakeVendaArquivoGenerator(
            $this->generatedFile(str_repeat('e', 64))
        );

        $service = $this->service(
            $repository,
            $gateway,
            $generator,
            new FakeRemoteFileStorage()
        );

        try {
            $service->execute(30);
            self::fail('Era esperada a recusa da execução de origem.');
        } catch (ReprocessamentoExportacaoException) {
            self::assertCount(0, $gateway->periodosConsultados);
        }
    }

    private function service(
        InMemoryExportacaoExecucaoRepository $repository,
        FakeVendaExportacaoGateway $gateway,
        FakeVendaArquivoGenerator $generator,
        FakeRemoteFileStorage $remote
    ): ReprocessarExportacaoVendasService {
        $processar = new ProcessarExportacaoVendasService(
            consultarVendas: new ConsultarVendasService($gateway),
            arquivoGenerator: $generator,
            enviarArquivo: new EnviarArquivoVendasService($remote),
            execucoes: $repository
        );

        return new ReprocessarExportacaoVendasService(
            execucoes: $repository,
            processarExportacao: $processar
        );
    }

    private function origin(int $id, string $hash): ExportacaoExecucaoDTO
    {
        return new ExportacaoExecucaoDTO(
            id: $id,
            dataMovimento: '2026-08-06',
            tipoExecucao: TipoExecucao::NORMAL,
            status: StatusExportacao::CONCLUIDO,
            execucaoOrigemId: null,
            arquivoNome: 'MV20260806_DEMO.txt',
            quantidadeRegistros: 100,
            tamanhoBytes: 2048,
            sha256: $hash,
            caminhoLocal: '/tmp/original.txt',
            caminhoRemoto: '/DELIVERY/MV20260806_DEMO.txt'
        );
    }

    private function generatedFile(string $hash): ArquivoVendaGerado
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, 'arquivo reprocessado');

        $size = filesize($path);
        self::assertIsInt($size);

        return new ArquivoVendaGerado(
            nome: 'MV20260806_DEMO.txt',
            caminho: $path,
            dataReferencia: '2026-08-06',
            quantidadeRegistros: 101,
            tamanhoBytes: $size,
            sha256: $hash
        );
    }

    private function sale(): VendaExportacaoDTO
    {
        return new VendaExportacaoDTO(
            store: 1,
            barcode: '789123',
            description: 'Produto teste',
            day: '2026-08-06',
            unitSales: '1',
            valueSales: '10.00',
            promo: 'N'
        );
    }
}
