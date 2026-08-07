<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Infrastructure\File;

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Infrastructure\File\Exception\ArquivoVendaException;
use App\Modules\Exportacao\Vendas\Infrastructure\File\VendaTxtFormatter;
use App\Modules\Exportacao\Vendas\Infrastructure\File\VendaTxtGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestFiles;

final class VendaTxtGeneratorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('niq-generator');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testGeraArquivoVersionadoComCrLfHashETamanho(): void
    {
        $generator = new VendaTxtGenerator(
            baseDirectory: $this->temporaryDirectory,
            formatter: new VendaTxtFormatter(),
            companyIdentifier: 'DEMO'
        );

        $periodo = PeriodoExportacao::doDia(
            new DateTimeImmutable('2026-08-06')
        );

        $arquivo = $generator->gerar(
            periodo: $periodo,
            vendas: [
                new VendaExportacaoDTO(
                    store: 1,
                    barcode: '789123',
                    description: 'Produto teste',
                    day: '2026-08-06',
                    unitSales: '2',
                    valueSales: '19.90',
                    promo: 'N'
                ),
            ],
            execucaoId: 42
        );

        $esperado = "STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO\r\n"
            . "1|789123|Produto teste|2026-08-06|2|19.90|N\r\n";

        self::assertSame('MV20260806_DEMO.txt', $arquivo->nome);
        self::assertStringContainsString(
            DIRECTORY_SEPARATOR . '2026' . DIRECTORY_SEPARATOR . '08' . DIRECTORY_SEPARATOR . 'execucao-42',
            $arquivo->caminho
        );
        self::assertSame(1, $arquivo->quantidadeRegistros);
        self::assertSame(strlen($esperado), $arquivo->tamanhoBytes);
        self::assertSame(hash('sha256', $esperado), $arquivo->sha256);
        self::assertSame($esperado, file_get_contents($arquivo->caminho));
        self::assertFileDoesNotExist($arquivo->caminho . '.part');
    }

    public function testNaoSobrescreveArquivoFinalExistente(): void
    {
        $generator = new VendaTxtGenerator(
            baseDirectory: $this->temporaryDirectory,
            formatter: new VendaTxtFormatter(),
            companyIdentifier: 'DEMO'
        );

        $periodo = PeriodoExportacao::doDia(
            new DateTimeImmutable('2026-08-06')
        );

        $generator->gerar($periodo, [], 7);

        $this->expectException(ArquivoVendaException::class);

        $generator->gerar($periodo, [], 7);
    }

    public function testRecusaIdentificadorDeEmpresaInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new VendaTxtGenerator(
            baseDirectory: $this->temporaryDirectory,
            formatter: new VendaTxtFormatter(),
            companyIdentifier: 'Empresa Demo'
        );
    }
}
