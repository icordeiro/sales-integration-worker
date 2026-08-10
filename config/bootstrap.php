<?php

declare(strict_types=1);

use App\Shared\Application\Demo\DemoMode;
use App\Shared\Infrastructure\Database\Config\ClientDatabaseConfig;
use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Database\PostgreSqlConnection;
use App\Shared\Infrastructure\Database\SqliteConnection;
use App\Shared\Infrastructure\Environment\Environment;
use Dotenv\Dotenv;

require_once dirname(__DIR__)
    . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);

$dotenv = Dotenv::createImmutable(
    $rootPath
);

$dotenv->load();

$dotenv
    ->required([
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
    ])
    ->notEmpty();

$dotenv
    ->required('DB_PORT')
    ->isInteger();

date_default_timezone_set(
    $_ENV['APP_TIMEZONE']
);

$demoEnabled = filter_var(
    $_ENV['APP_DEMO'] ?? 'false',
    FILTER_VALIDATE_BOOLEAN
) === true;

if (!isset($_ENV['DEMO_DB_PATH']) || trim((string) $_ENV['DEMO_DB_PATH']) === '') {
    $_ENV['DEMO_DB_PATH'] = 'storage/database/siw-demo.sqlite';
}

$environment = Environment::fromGlobals();
$demoMode = new DemoMode($demoEnabled);

/*
|--------------------------------------------------------------------------
| PostgreSQL VRMaster
|--------------------------------------------------------------------------
*/

$clientDatabaseConfig =
    ClientDatabaseConfig::fromEnvironment(
        $environment
    );

$clientDatabase =
    PostgreSqlConnection::instance(
        $clientDatabaseConfig
    );

/*
|--------------------------------------------------------------------------
| SQLite do Portal
|--------------------------------------------------------------------------
*/

$portalDatabaseConfig =
    PortalSqliteConfig::fromEnvironment(
        environment: $environment,
        projectRoot: $rootPath,
        pathVariable: $demoMode->enabled
            ? 'DEMO_DB_PATH'
            : 'PORTAL_DB_PATH'
    );

$portalDatabase =
    new SqliteConnection(
        $portalDatabaseConfig
    );

return [
    'environment' =>
        $environment,

    'demo_mode' =>
        $demoMode,

    'client_database_config' =>
        $clientDatabaseConfig,

    'client_database' =>
        $clientDatabase,

    'portal_database_config' =>
        $portalDatabaseConfig,

    'portal_database' =>
        $portalDatabase,
];