<?php

declare(strict_types=1);

use App\Shared\Application\Console\ConsoleApplication;
use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\DTO\HealthReportDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Application\Health\Services\HealthCheckService;
use App\Shared\Infrastructure\Console\Command\DemoDatabaseCommand;
use App\Shared\Infrastructure\Console\Command\HealthReportCommand;
use App\Shared\Infrastructure\Console\Command\LegacyScriptCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use App\Shared\Infrastructure\Console\HealthConsolePresenter;
use App\Shared\Infrastructure\Health\EnvironmentFileHealthProbe;
use App\Shared\Infrastructure\Health\MigrationHealthProbe;
use App\Shared\Infrastructure\Health\PhpRuntimeHealthProbe;
use App\Shared\Infrastructure\Health\SftpConnectionHealthProbe;
use App\Shared\Infrastructure\Sftp\Config\SftpConfig;
use App\Shared\Infrastructure\Sftp\PhpseclibRemoteFileStorage;
use App\Shared\Infrastructure\Sftp\SftpHostKeyVerifier;

$projectRoot = dirname(__DIR__);

$output = new ConsoleOutput();
$presenter = new HealthConsolePresenter();

$requiredEnvironmentKeys = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_TIMEZONE',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'PORTAL_DB_PATH',
    'EXPORT_COMPANY',
    'SFTP_HOST',
    'SFTP_PORT',
    'SFTP_USER',
    'SFTP_PASS',
    'SFTP_REMOTE_DIR',
    'SFTP_HOST_KEY_FINGERPRINT',
    'SFTP_TIMEOUT',
];

$environmentProbe = new EnvironmentFileHealthProbe(
    environmentFile: $projectRoot
        . DIRECTORY_SEPARATOR
        . '.env',
    requiredKeys: $requiredEnvironmentKeys
);

$statusProvider = static function () use ($projectRoot): HealthReportDTO {
    $services = require $projectRoot
        . DIRECTORY_SEPARATOR
        . 'config'
        . DIRECTORY_SEPARATOR
        . 'health.php';

    /** @var HealthCheckService $health */
    $health = $services['health_check_service'];

    return $health->execute();
};

$doctorProvider = static function () use (
    $projectRoot,
    $environmentProbe
): HealthReportDTO {
    $preflight = new HealthCheckService([
        new PhpRuntimeHealthProbe(),
        $environmentProbe,
    ]);

    $preflightReport = $preflight->execute();

    if ($preflightReport->status === 'unhealthy') {
        return $preflightReport;
    }

    try {
        $services = require $projectRoot
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'health.php';
    } catch (Throwable) {
        return new HealthReportDTO(
            status: 'unhealthy',
            checkedAt: new DateTimeImmutable('now'),
            checks: [
                ...$preflightReport->checks,
                'bootstrap' => new HealthCheckDTO(
                    status: HealthStatus::ERROR,
                    message: 'Não foi possível carregar a configuração da aplicação.'
                ),
            ]
        );
    }

    $probes = [
        $environmentProbe,
        ...$services['health_probes'],

        new MigrationHealthProbe(
            database: $services['portal_database'],
            migrationsDirectory: $projectRoot
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations'
        ),
    ];

    if (!$services['demo_mode']->enabled) {
        try {
            $sftpConfig = SftpConfig::fromEnvironment(
                $services['environment']
            );

            $remoteStorage = new PhpseclibRemoteFileStorage(
                config: $sftpConfig,
                hostKeyVerifier: new SftpHostKeyVerifier()
            );

            $probes[] = new SftpConnectionHealthProbe(
                $remoteStorage
            );
        } catch (Throwable) {
            /*
             * O probe leve de configuração SFTP já reportará o erro.
             * Não tentamos conexão profunda quando a configuração é inválida.
             */
        }
    }

    return (new HealthCheckService($probes))
        ->execute();
};

return new ConsoleApplication(
    commands: [
        new DemoDatabaseCommand(
            commandName: 'demo:reset',
            commandDescription: 'Recria do zero o dataset SQLite de demonstração.',
            projectRoot: $projectRoot,
            reset: true
        ),

        new DemoDatabaseCommand(
            commandName: 'demo:seed',
            commandDescription: 'Cria o dataset SQLite fictício para demonstrar o dashboard.',
            projectRoot: $projectRoot,
            reset: false
        ),

        new HealthReportCommand(
            commandName: 'doctor',
            commandDescription: 'Executa diagnóstico profundo do ambiente, incluindo SFTP real.',
            title: 'SIW - Diagnóstico profundo',
            reportProvider: $doctorProvider,
            presenter: $presenter,
            warningIsFailure: true
        ),

        new HealthReportCommand(
            commandName: 'status',
            commandDescription: 'Exibe rapidamente o estado operacional da integração.',
            title: 'SIW - Status operacional',
            reportProvider: $statusProvider,
            presenter: $presenter,
            warningIsFailure: false
        ),

        new LegacyScriptCommand(
            commandName: 'migrate',
            commandDescription: 'Executa as migrations SQLite pendentes.',
            scriptPath: $projectRoot
                . DIRECTORY_SEPARATOR
                . 'bin'
                . DIRECTORY_SEPARATOR
                . 'migrate.php',
            workingDirectory: $projectRoot
        ),

        new LegacyScriptCommand(
            commandName: 'export:daily',
            commandDescription: 'Executa a exportação diária D-1 usando o fluxo já homologado.',
            scriptPath: $projectRoot
                . DIRECTORY_SEPARATOR
                . 'bin'
                . DIRECTORY_SEPARATOR
                . 'export-sales-daily.php',
            workingDirectory: $projectRoot
        ),
    ],
    output: $output
);
