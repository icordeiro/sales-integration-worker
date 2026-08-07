<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;

$services = require dirname(__DIR__)
    . '/config/bootstrap.php';

/** @var DatabaseConnection $database */
$database = $services['portal_database'];

/** @var PortalSqliteConfig $config */
$config = $services['portal_database_config'];

try {
    $connection = $database->connection();

    $sqliteVersion = $connection
        ->query(
            'SELECT sqlite_version()'
        )
        ->fetchColumn();

    $journalMode = $connection
        ->query(
            'PRAGMA journal_mode'
        )
        ->fetchColumn();

    $foreignKeys = $connection
        ->query(
            'PRAGMA foreign_keys'
        )
        ->fetchColumn();

    $busyTimeout = $connection
        ->query(
            'PRAGMA busy_timeout'
        )
        ->fetchColumn();

    echo PHP_EOL;
    echo 'Banco SQLite do Portal' . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    echo 'Status: conectado' . PHP_EOL;

    echo 'Arquivo: '
        . $config->databasePath
        . PHP_EOL;

    echo 'SQLite: '
        . $sqliteVersion
        . PHP_EOL;

    echo 'Journal mode: '
        . strtoupper(
            (string) $journalMode
        )
        . PHP_EOL;

    echo 'Foreign keys: '
        . ((int) $foreignKeys === 1
            ? 'ON'
            : 'OFF')
        . PHP_EOL;

    echo 'Busy timeout: '
        . $busyTimeout
        . ' ms'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha ao testar o SQLite.'
        . PHP_EOL
        . 'Erro: '
        . $exception->getMessage()
        . PHP_EOL
    );

    if (
        $exception->getPrevious()
            instanceof Throwable
    ) {
        fwrite(
            STDERR,
            'Erro técnico: '
            . $exception
                ->getPrevious()
                ->getMessage()
            . PHP_EOL
        );
    }

    fwrite(
        STDERR,
        PHP_EOL
    );

    exit(1);
}