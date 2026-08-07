<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;

$services = require dirname(__DIR__) . '/config/bootstrap.php';

/** @var DatabaseConnection $database */
$database = $services['client_database'];

try {
    $connection = $database->connection();

    $statement = $connection->query(
        <<<'SQL'
            SELECT
                current_database() AS database_name,
                current_user AS database_user,
                current_setting('TimeZone') AS timezone,
                version() AS database_version
        SQL
    );

    /** @var array<string, mixed>|false $result */
    $result = $statement->fetch(\PDO::FETCH_ASSOC);

    if ($result === false) {
        throw new \RuntimeException(
            'O banco não retornou informações da conexão.'
        );
    }

    echo PHP_EOL;
    echo 'Conexão realizada com sucesso.' . PHP_EOL;
    echo '----------------------------------------' . PHP_EOL;
    echo 'Banco: ' . $result['database_name'] . PHP_EOL;
    echo 'Usuário: ' . $result['database_user'] . PHP_EOL;
    echo 'Timezone: ' . $result['timezone'] . PHP_EOL;
    echo 'Status: conectado' . PHP_EOL;
    echo PHP_EOL;

    exit(0);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha ao testar a conexão.'
        . PHP_EOL
        . 'Erro da aplicação: '
        . $exception->getMessage()
        . PHP_EOL
    );

    $previous = $exception->getPrevious();

    if ($previous instanceof \Throwable) {
        fwrite(
            STDERR,
            'Erro técnico: '
            . $previous->getMessage()
            . PHP_EOL
        );
    }

    fwrite(STDERR, PHP_EOL);

    exit(1);
}