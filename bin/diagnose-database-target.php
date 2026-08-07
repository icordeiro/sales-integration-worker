<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;

$services = require dirname(__DIR__) . '/config/bootstrap.php';

/** @var DatabaseConnection $database */
$database = $services['client_database'];

try {
    $pdo = $database->connection();

    $sql = <<<'SQL'
        SELECT
            current_database() AS database_name,
            current_user AS database_user,
            inet_server_addr() AS server_address,
            inet_server_port() AS server_port,
            version() AS database_version,
            current_setting('data_directory') AS data_directory,
            current_setting('TimeZone') AS timezone
    SQL;

    $connectionInfo = $pdo
        ->query($sql)
        ->fetch(\PDO::FETCH_ASSOC);

    echo PHP_EOL;
    echo 'Identidade da conexão utilizada pelo PHP' . PHP_EOL;
    echo str_repeat('-', 65) . PHP_EOL;
    echo 'Servidor: '
        . ($connectionInfo['server_address'] ?? 'local/socket')
        . PHP_EOL;
    echo 'Porta: '
        . ($connectionInfo['server_port'] ?? 'não identificada')
        . PHP_EOL;
    echo 'Banco: '
        . ($connectionInfo['database_name'] ?? 'não identificado')
        . PHP_EOL;
    echo 'Usuário: '
        . ($connectionInfo['database_user'] ?? 'não identificado')
        . PHP_EOL;
    echo 'Timezone: '
        . ($connectionInfo['timezone'] ?? 'não identificado')
        . PHP_EOL;
    echo 'Diretório de dados: '
        . ($connectionInfo['data_directory'] ?? 'não identificado')
        . PHP_EOL;

    echo str_repeat('-', 65) . PHP_EOL;

    $tableInfoStatement = $pdo->query(
        <<<'SQL'
            SELECT
                n.nspname AS schema_name,
                c.relname AS relation_name,
                c.relkind AS relation_type,
                pg_size_pretty(
                    pg_total_relation_size(c.oid)
                ) AS total_size
            FROM pg_class AS c
            INNER JOIN pg_namespace AS n
                ON n.oid = c.relnamespace
            WHERE n.nspname = 'pdv'
              AND c.relname = 'venda'
        SQL
    );

    $tableInfo = $tableInfoStatement->fetch(\PDO::FETCH_ASSOC);

    echo 'Relação consultada: '
        . ($tableInfo['schema_name'] ?? '?')
        . '.'
        . ($tableInfo['relation_name'] ?? '?')
        . PHP_EOL;

    echo 'Tipo da relação: '
        . ($tableInfo['relation_type'] ?? '?')
        . PHP_EOL;

    echo 'Tamanho: '
        . ($tableInfo['total_size'] ?? '?')
        . PHP_EOL;

    echo str_repeat('-', 65) . PHP_EOL;

    $columnStatement = $pdo->query(
        <<<'SQL'
            SELECT
                data_type,
                udt_name
            FROM information_schema.columns
            WHERE table_schema = 'pdv'
              AND table_name = 'venda'
              AND column_name = 'data'
        SQL
    );

    $column = $columnStatement->fetch(\PDO::FETCH_ASSOC);

    echo 'Tipo de pdv.venda.data: '
        . ($column['data_type'] ?? '?')
        . ' / '
        . ($column['udt_name'] ?? '?')
        . PHP_EOL;

    echo str_repeat('-', 65) . PHP_EOL;

    $summaryStatement = $pdo->query(
        <<<'SQL'
            SELECT
                COUNT(*) AS total_vendas,
                MIN(data) AS primeira_data,
                MAX(data) AS ultima_data,
                COUNT(*) FILTER (
                    WHERE data >= DATE '2026-01-01'
                ) AS vendas_2026,
                COUNT(*) FILTER (
                    WHERE data = DATE '2026-08-04'
                ) AS vendas_2026_08_04
            FROM pdv.venda
        SQL
    );

    $summary = $summaryStatement->fetch(\PDO::FETCH_ASSOC);

    echo 'Total de vendas: '
        . number_format(
            (int) ($summary['total_vendas'] ?? 0),
            0,
            ',',
            '.'
        )
        . PHP_EOL;

    echo 'Primeira data: '
        . ($summary['primeira_data'] ?? 'nenhuma')
        . PHP_EOL;

    echo 'Última data: '
        . ($summary['ultima_data'] ?? 'nenhuma')
        . PHP_EOL;

    echo 'Vendas em 2026: '
        . number_format(
            (int) ($summary['vendas_2026'] ?? 0),
            0,
            ',',
            '.'
        )
        . PHP_EOL;

    echo 'Vendas em 04/08/2026: '
        . number_format(
            (int) ($summary['vendas_2026_08_04'] ?? 0),
            0,
            ',',
            '.'
        )
        . PHP_EOL;

    echo str_repeat('-', 65) . PHP_EOL;
    echo 'Últimos registros encontrados:' . PHP_EOL;

    $latestStatement = $pdo->query(
        <<<'SQL'
            SELECT
                id,
                id_loja,
                data
            FROM pdv.venda
            ORDER BY data DESC, id DESC
            LIMIT 10
        SQL
    );

    while (
        ($row = $latestStatement->fetch(\PDO::FETCH_ASSOC)) !== false
    ) {
        echo sprintf(
            'ID: %s | Loja: %s | Data: %s',
            $row['id'],
            $row['id_loja'],
            $row['data']
        );

        echo PHP_EOL;
    }

    echo PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha no diagnóstico: '
        . $exception->getMessage()
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}