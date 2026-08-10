<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\GerarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\SqliteExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\SqliteExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\VrMasterVendaExportacaoGateway;
use App\Modules\Exportacao\Vendas\Infrastructure\File\VendaTxtFormatter;
use App\Modules\Exportacao\Vendas\Infrastructure\File\VendaTxtGenerator;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Logging\MonthlyJsonFileLogger;
use App\Shared\Infrastructure\Logging\SafeLogger;

$services = require __DIR__
    . '/bootstrap.php';

/** @var DatabaseConnection $clientDatabase */
$clientDatabase =
    $services['client_database'];

/** @var DatabaseConnection $portalDatabase */
$portalDatabase =
    $services['portal_database'];

/** @var Environment $environment */
$environment =
    $services['environment'];

/*
|--------------------------------------------------------------------------
| Logging estruturado
|--------------------------------------------------------------------------
|
| O arquivo é rotacionado naturalmente por mês:
| storage/logs/siw-YYYY-MM.log
|
| LOG_LEVEL é opcional. Quando ausente, INFO é utilizado.
|
*/

$logLevel = LogLevel::fromString(
    $environment->optionalString(
        'LOG_LEVEL',
        LogLevel::INFO->value
    ) ?? LogLevel::INFO->value
);

/** @var Logger $logger */
$logger = new SafeLogger(
    new MonthlyJsonFileLogger(
        directory: dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'logs',
        timezone: new DateTimeZone(
            $environment->string('APP_TIMEZONE')
        ),
        minimumLevel: $logLevel,
        channel: 'siw'
    )
);

/*
|--------------------------------------------------------------------------
| Consulta de vendas - PostgreSQL VRMaster
|--------------------------------------------------------------------------
*/

$vendaExportacaoGateway =
    new VrMasterVendaExportacaoGateway(
        $clientDatabase
    );

$consultarVendasService =
    new ConsultarVendasService(
        $vendaExportacaoGateway
    );

/*
|--------------------------------------------------------------------------
| Geração do arquivo TXT
|--------------------------------------------------------------------------
*/

$vendaTxtFormatter =
    new VendaTxtFormatter();

$vendaTxtGenerator =
    new VendaTxtGenerator(
        baseDirectory:
            dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'exports',

        formatter:
            $vendaTxtFormatter,

        companyIdentifier:
            $environment->string(
                'EXPORT_COMPANY'
            )
    );

$gerarArquivoVendasService =
    new GerarArquivoVendasService(
        consultarVendas:
            $consultarVendasService,

        arquivoGenerator:
            $vendaTxtGenerator
    );

/*
|--------------------------------------------------------------------------
| Escrita das execuções - SQLite
|--------------------------------------------------------------------------
*/

$exportacaoExecucaoRepository =
    new SqliteExportacaoExecucaoRepository(
        $portalDatabase
    );

/*
|--------------------------------------------------------------------------
| Leitura do dashboard - SQLite
|--------------------------------------------------------------------------
*/

$exportacaoDashboardQuery =
    new SqliteExportacaoDashboardQuery(
        $portalDatabase
    );

/*
|--------------------------------------------------------------------------
| Serviços
|--------------------------------------------------------------------------
*/

return [
    ...$services,

    'logger' =>
        $logger,

    'venda_exportacao_gateway' =>
        $vendaExportacaoGateway,

    'consultar_vendas_service' =>
        $consultarVendasService,

    'venda_txt_formatter' =>
        $vendaTxtFormatter,

    'venda_txt_generator' =>
        $vendaTxtGenerator,

    'gerar_arquivo_vendas_service' =>
        $gerarArquivoVendasService,

    'exportacao_execucao_repository' =>
        $exportacaoExecucaoRepository,

    'exportacao_dashboard_query' =>
        $exportacaoDashboardQuery,
];