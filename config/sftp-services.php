<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\Contracts\VendaArquivoGenerator;
use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\EnviarArquivoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReenviarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Application\Services\ReprocessarExportacaoVendasService;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Sftp\Config\SftpConfig;
use App\Shared\Infrastructure\Sftp\PhpseclibRemoteFileStorage;
use App\Shared\Infrastructure\Sftp\SftpHostKeyVerifier;

$services = require __DIR__
    . '/services.php';

/** @var Environment $environment */
$environment =
    $services['environment'];

/*
|--------------------------------------------------------------------------
| SFTP
|--------------------------------------------------------------------------
*/

$sftpConfig =
    SftpConfig::fromEnvironment(
        $environment
    );

$sftpHostKeyVerifier =
    new SftpHostKeyVerifier();

$remoteFileStorage =
    new PhpseclibRemoteFileStorage(
        config: $sftpConfig,
        hostKeyVerifier: $sftpHostKeyVerifier
    );

$enviarArquivoVendasService =
    new EnviarArquivoVendasService(
        remoteStorage: $remoteFileStorage
    );

/*
|--------------------------------------------------------------------------
| Orquestrador da exportação
|--------------------------------------------------------------------------
*/

/** @var ConsultarVendasService $consultarVendasService */
$consultarVendasService =
    $services['consultar_vendas_service'];

/** @var VendaArquivoGenerator $arquivoGenerator */
$arquivoGenerator =
    $services['venda_txt_generator'];

/** @var ExportacaoExecucaoRepository $execucoes */
$execucoes =
    $services['exportacao_execucao_repository'];

$processarExportacaoVendasService =
    new ProcessarExportacaoVendasService(
        consultarVendas: $consultarVendasService,
        arquivoGenerator: $arquivoGenerator,
        enviarArquivo: $enviarArquivoVendasService,
        execucoes: $execucoes
    );

$reenviarExportacaoVendasService =
    new ReenviarExportacaoVendasService(
        execucoes: $execucoes,
        enviarArquivo: $enviarArquivoVendasService
    );

$reprocessarExportacaoVendasService =
    new ReprocessarExportacaoVendasService(
        execucoes: $execucoes,
        processarExportacao: $processarExportacaoVendasService
    );

return [
    ...$services,

    'sftp_config' =>
    $sftpConfig,

    'sftp_host_key_verifier' =>
    $sftpHostKeyVerifier,

    'remote_file_storage' =>
    $remoteFileStorage,

    'enviar_arquivo_vendas_service' =>
    $enviarArquivoVendasService,

    'processar_exportacao_vendas_service' =>
    $processarExportacaoVendasService,

    'reenviar_exportacao_vendas_service' =>
    $reenviarExportacaoVendasService,

    'reprocessar_exportacao_vendas_service' =>
    $reprocessarExportacaoVendasService,
];
