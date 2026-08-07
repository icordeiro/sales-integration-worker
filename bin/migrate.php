<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;

$services = require dirname(__DIR__)
    . '/config/bootstrap.php';

/** @var DatabaseConnection $database */
$database = $services['portal_database'];

$connection = $database->connection();

$migrationsDirectory =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'database'
    . DIRECTORY_SEPARATOR
    . 'migrations';

if (!is_dir($migrationsDirectory)) {
    fwrite(
        STDERR,
        'Diretório de migrations não encontrado.'
        . PHP_EOL
    );

    exit(1);
}

$connection->exec(
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS schema_migration
    (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration TEXT NOT NULL UNIQUE,
        executado_em TEXT NOT NULL
            DEFAULT CURRENT_TIMESTAMP
    )
    SQL
);

$files = glob(
    $migrationsDirectory
    . DIRECTORY_SEPARATOR
    . '*.sql'
);

if ($files === false) {
    fwrite(
        STDERR,
        'Não foi possível listar as migrations.'
        . PHP_EOL
    );

    exit(1);
}

sort(
    $files,
    SORT_STRING
);

echo PHP_EOL;
echo 'Migrations SQLite' . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

foreach ($files as $file) {
    $migrationName = basename(
        $file
    );

    $statement = $connection->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM schema_migration
        WHERE migration = :migration
        SQL
    );

    $statement->execute([
        ':migration' => $migrationName,
    ]);

    $alreadyExecuted =
        (int) $statement->fetchColumn() > 0;

    if ($alreadyExecuted) {
        echo '[OK] '
            . $migrationName
            . ' já executada.'
            . PHP_EOL;

        continue;
    }

    $sql = file_get_contents(
        $file
    );

    if ($sql === false) {
        fwrite(
            STDERR,
            '[ERRO] Não foi possível ler '
            . $migrationName
            . PHP_EOL
        );

        exit(1);
    }

    try {
        $connection->beginTransaction();

        $connection->exec(
            $sql
        );

        $insert = $connection->prepare(
            <<<'SQL'
            INSERT INTO schema_migration
            (
                migration
            )
            VALUES
            (
                :migration
            )
            SQL
        );

        $insert->execute([
            ':migration' => $migrationName,
        ]);

        $connection->commit();

        echo '[EXECUTADA] '
            . $migrationName
            . PHP_EOL;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        fwrite(
            STDERR,
            PHP_EOL
            . '[ERRO] '
            . $migrationName
            . PHP_EOL
            . $exception->getMessage()
            . PHP_EOL
            . PHP_EOL
        );

        exit(1);
    }
}

echo str_repeat('-', 60) . PHP_EOL;
echo 'Migrations concluídas.' . PHP_EOL;
echo PHP_EOL;