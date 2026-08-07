<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\EnviarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Exception\ExportacaoNormalJaExistenteException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeRemoteFileStorage;
use Tests\Support\FakeVendaArquivoGenerator;
use Tests\Support\FakeVendaExportacaoGateway;
use Tests\Support\InMemoryExportacaoExecucaoRepository;
use Tests\Support\TestFiles;

final class ProcessarExportacaoVendasServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-process');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testNormalEhIdempotenteAntesDeConsultarErp(): void
    {
        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->normalBloqueante['2026-08-06'] = true;

        $gateway = new FakeVendaExportacaoGateway();
        $generator = new FakeVendaArquivoGenerator($this->generatedFile());

        $service = new ProcessarExportacaoVendasService(
            new ConsultarVendasService($gateway),
            $generator,
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $repository
        );

        $this->expectException(ExportacaoNormalJaExistenteException::class);

        try {
            $service->execute(
                PeriodoExportacao::doDia(new DateTimeImmutable('2026-08-06'))
            );
        } finally {
            self::assertCount(0, $gateway->periodosConsultados);
            self::assertCount(0, $repository->execucoes);
        }
    }

    public function testFalhaDuranteConsultaMarcaExecucaoComoFalhou(): void
    {
        $repository = new InMemoryExportacaoExecucaoRepository();
        $gateway = new FakeVendaExportacaoGateway();
        $gateway->exception = new \RuntimeException('ERP indisponível');

        $service = new ProcessarExportacaoVendasService(
            new ConsultarVendasService($gateway),
            new FakeVendaArquivoGenerator($this->generatedFile()),
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $repository
        );

        try {
            $service->execute(
                PeriodoExportacao::doDia(new DateTimeImmutable('2026-08-06'))
            );
            self::fail('Era esperada uma falha na consulta.');
        } catch (\RuntimeException $exception) {
            self::assertSame('ERP indisponível', $exception->getMessage());
        }

        self::assertSame(
            \App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao::FALHOU,
            $repository->buscarPorId(1)?->status
        );
        self::assertCount(1, $repository->falhas);
    }

    private function generatedFile(): ArquivoVendaGerado
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, 'arquivo');
        $size = filesize($path);
        $hash = hash_file('sha256', $path);

        self::assertIsInt($size);
        self::assertIsString($hash);

        return new ArquivoVendaGerado(
            nome: 'MV20260806_DEMO.txt',
            caminho: $path,
            dataReferencia: '2026-08-06',
            quantidadeRegistros: 1,
            tamanhoBytes: $size,
            sha256: $hash
        );
    }
}
