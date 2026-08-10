<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Infrastructure\Health\ExportacaoHealthProbe;
use App\Shared\Application\Demo\DemoMode;
use App\Shared\Application\Health\Contracts\HealthProbe;
use App\Shared\Application\Health\Services\HealthCheckService;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Health\DatabaseHealthProbe;
use App\Shared\Infrastructure\Health\DemoExternalServiceHealthProbe;
use App\Shared\Infrastructure\Health\PhpRuntimeHealthProbe;
use App\Shared\Infrastructure\Health\ProcessLockHealthProbe;
use App\Shared\Infrastructure\Health\PostgreSqlHealthProbe;
use App\Shared\Infrastructure\Health\SftpConfigurationHealthProbe;
use App\Shared\Infrastructure\Health\StorageHealthProbe;
use App\Shared\Infrastructure\Lock\FileProcessLock;

$services = require __DIR__
    . '/services.php';

/** @var Environment $environment */
$environment = $services['environment'];

/** @var DemoMode $demoMode */
$demoMode = $services['demo_mode'];

$healthLock = new FileProcessLock(
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'storage'
    . DIRECTORY_SEPARATOR
    . 'runtime'
    . DIRECTORY_SEPARATOR
    . 'export-sales-daily.lock'
);

/** @var list<HealthProbe> $healthProbes */
$healthProbes = [
    new PhpRuntimeHealthProbe(),

    new DatabaseHealthProbe(
        key: 'sqlite',
        label: $demoMode->enabled ? 'SQLite demo' : 'SQLite',
        database: $services['portal_database']
    ),
];

if ($demoMode->enabled) {
    $healthProbes[] = new DemoExternalServiceHealthProbe(
        probeKey: 'postgresql',
        serviceLabel: 'PostgreSQL'
    );

    $healthProbes[] = new DemoExternalServiceHealthProbe(
        probeKey: 'sftp',
        serviceLabel: 'SFTP'
    );
} else {
    $healthProbes[] = new PostgreSqlHealthProbe(
        config: $services['client_database_config'],
        connectTimeoutSeconds: 3
    );

    $healthProbes[] = new SftpConfigurationHealthProbe(
        $environment
    );
}

$healthProbes[] = new StorageHealthProbe(
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'storage'
);

$healthProbes[] = new ProcessLockHealthProbe(
    $healthLock
);

$healthProbes[] = new ExportacaoHealthProbe(
    $services['exportacao_dashboard_query']
);

$healthCheckService = new HealthCheckService(
    $healthProbes
);

return [
    ...$services,

    'health_lock' =>
        $healthLock,

    'health_probes' =>
        $healthProbes,

    'health_check_service' =>
        $healthCheckService,
];
