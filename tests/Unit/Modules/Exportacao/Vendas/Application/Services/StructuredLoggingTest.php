<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\EnviarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReenviarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReprocessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Domain\Exception\ReprocessamentoExportacaoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeRemoteFileStorage;
use Tests\Support\FakeVendaArquivoGenerator;
use Tests\Support\FakeVendaExportacaoGateway;
use Tests\Support\InMemoryExportacaoExecucaoRepository;
use Tests\Support\InMemoryLogger;
use Tests\Support\TestFiles;

final class StructuredLoggingTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = TestFiles::temporaryDirectory('siw-service-log');
    }

    protected function tearDown(): void
    {
        TestFiles::removeDirectory($this->temporaryDirectory);
    }

    public function testProcessamentoRegistraInicioArquivoEConclusao(): void
    {
        $logger = new InMemoryLogger();
        $repository = new InMemoryExportacaoExecucaoRepository();
        $service = new ProcessarExportacaoVendasService(
            new ConsultarVendasService(new FakeVendaExportacaoGateway()),
            new FakeVendaArquivoGenerator($this->generatedFile()),
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $repository,
            $logger
        );

        $result = $service->execute(PeriodoExportacao::doDia(new DateTimeImmutable('2026-08-06')));

        self::assertSame(1, $result->execucaoId);
        self::assertSame(['export.started', 'export.file_ready', 'export.completed'], array_column($logger->entries, 'event'));
        self::assertSame('NORMAL', $logger->entries[2]['context']['operation_type']);
        self::assertSame('CONCLUIDO', $logger->entries[2]['context']['final_status']);
    }

    public function testFalhaDoProcessamentoRegistraEventoDeErro(): void
    {
        $logger = new InMemoryLogger();
        $repository = new InMemoryExportacaoExecucaoRepository();
        $gateway = new FakeVendaExportacaoGateway();
        $gateway->exception = new \RuntimeException('ERP indisponível');
        $service = new ProcessarExportacaoVendasService(
            new ConsultarVendasService($gateway),
            new FakeVendaArquivoGenerator($this->generatedFile()),
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $repository,
            $logger
        );

        try {
            $service->execute(PeriodoExportacao::doDia(new DateTimeImmutable('2026-08-06')));
            self::fail('Era esperada uma falha.');
        } catch (\RuntimeException) {
        }

        $last = $logger->entries[array_key_last($logger->entries)];
        self::assertSame('export.failed', $last['event']);
        self::assertSame('FALHOU', $last['context']['final_status']);
    }

    public function testReenvioRegistraCicloOperacional(): void
    {
        $logger = new InMemoryLogger();
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, "HEADER\r\n1|2|3\r\n");
        $repository = new InMemoryExportacaoExecucaoRepository();
        $repository->seed($this->completedOrigin(10, $path));
        $service = new ReenviarExportacaoVendasService(
            $repository,
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $logger
        );

        $service->execute(10);

        self::assertSame(['resend.requested', 'resend.started', 'resend.completed'], array_column($logger->entries, 'event'));
        self::assertSame('REENVIO', $logger->entries[2]['context']['operation_type']);
    }

    public function testReprocessamentoInvalidoRegistraRejeicao(): void
    {
        $logger = new InMemoryLogger();
        $repository = new InMemoryExportacaoExecucaoRepository();
        $processar = new ProcessarExportacaoVendasService(
            new ConsultarVendasService(new FakeVendaExportacaoGateway()),
            new FakeVendaArquivoGenerator($this->generatedFile()),
            new EnviarArquivoVendasService(new FakeRemoteFileStorage()),
            $repository,
            $logger
        );
        $service = new ReprocessarExportacaoVendasService($repository, $processar, $logger);

        try {
            $service->execute(999);
            self::fail('Era esperada uma recusa.');
        } catch (ReprocessamentoExportacaoException) {
        }

        self::assertSame(['reprocess.requested', 'reprocess.rejected'], array_column($logger->entries, 'event'));
        self::assertSame(999, $logger->entries[1]['context']['origin_execution_id']);
    }

    private function generatedFile(): ArquivoVendaGerado
    {
        $path = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'MV20260806_DEMO.txt';
        file_put_contents($path, 'arquivo');
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        self::assertIsInt($size);
        self::assertIsString($hash);
        return new ArquivoVendaGerado('MV20260806_DEMO.txt', $path, '2026-08-06', 1, $size, $hash);
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
