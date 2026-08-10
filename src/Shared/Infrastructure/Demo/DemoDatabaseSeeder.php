<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Demo;

use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Database\SqliteConnection;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final readonly class DemoDatabaseSeeder
{
    public function __construct(
        private string $databasePath,
        private string $migrationsDirectory,
        private string $exportsDirectory,
        private DateTimeZone $timezone
    ) {
    }

    /**
     * @return array{database_path:string,executions:int,files:int}
     */
    public function seed(bool $reset = false): array
    {
        if ($reset) {
            $this->resetArtifacts();
        }

        $database = new SqliteConnection(
            new PortalSqliteConfig($this->databasePath)
        );

        $connection = $database->connection();

        $this->applyMigrations($connection);

        $existing = (int) $connection
            ->query('SELECT COUNT(*) FROM exportacao_execucao')
            ->fetchColumn();

        if ($existing > 0 && !$reset) {
            return [
                'database_path' => $this->databasePath,
                'executions' => $existing,
                'files' => $this->countDemoFiles(),
            ];
        }

        if ($existing > 0) {
            $connection->exec('DELETE FROM exportacao_execucao_evento');
            $connection->exec('DELETE FROM exportacao_execucao');
        }

        $today = new DateTimeImmutable('today', $this->timezone);

        $definitions = [
            [
                'offset' => 6,
                'type' => 'NORMAL',
                'status' => 'CONCLUIDO',
                'records' => 11842,
                'duration' => 17640,
                'origin' => null,
                'changed' => null,
            ],
            [
                'offset' => 5,
                'type' => 'NORMAL',
                'status' => 'FALHOU',
                'records' => null,
                'duration' => 8320,
                'origin' => null,
                'changed' => null,
            ],
            [
                'offset' => 4,
                'type' => 'NORMAL',
                'status' => 'CONCLUIDO',
                'records' => 12104,
                'duration' => 18120,
                'origin' => null,
                'changed' => null,
            ],
            [
                'offset' => 3,
                'type' => 'NORMAL',
                'status' => 'CONCLUIDO',
                'records' => 12360,
                'duration' => 18450,
                'origin' => null,
                'changed' => null,
                'key' => 'reprocess_origin',
            ],
            [
                'offset' => 3,
                'type' => 'REPROCESSAMENTO',
                'status' => 'CONCLUIDO',
                'records' => 12418,
                'duration' => 19040,
                'origin_key' => 'reprocess_origin',
                'changed' => true,
            ],
            [
                'offset' => 2,
                'type' => 'NORMAL',
                'status' => 'CONCLUIDO',
                'records' => 11995,
                'duration' => 16970,
                'origin' => null,
                'changed' => null,
                'key' => 'resend_origin',
            ],
            [
                'offset' => 2,
                'type' => 'REENVIO',
                'status' => 'CONCLUIDO',
                'records' => 11995,
                'duration' => 4120,
                'origin_key' => 'resend_origin',
                'origin_file_key' => 'resend_origin',
                'changed' => false,
            ],
            [
                'offset' => 1,
                'type' => 'NORMAL',
                'status' => 'CONCLUIDO',
                'records' => 12687,
                'duration' => 18780,
                'origin' => null,
                'changed' => null,
            ],
        ];

        $ids = [];
        $fileMetadataByKey = [];
        $files = 0;

        $connection->beginTransaction();

        try {
            foreach ($definitions as $index => $definition) {
                $movement = $today
                    ->sub(new DateInterval('P' . $definition['offset'] . 'D'))
                    ->format('Y-m-d');

                $originId = null;

                if (isset($definition['origin_key'])) {
                    $originId = $ids[$definition['origin_key']] ?? null;
                } elseif (isset($definition['origin'])) {
                    $originId = $definition['origin'];
                }

                $fileMetadata = null;

                if (isset($definition['origin_file_key'])) {
                    $fileMetadata = $fileMetadataByKey[$definition['origin_file_key']] ?? null;
                } elseif ($definition['status'] === 'CONCLUIDO') {
                    $fileMetadata = $this->createDemoFile(
                        movement: $movement,
                        executionIndex: $index + 1,
                        records: (int) $definition['records'],
                        variant: (string) $definition['type']
                    );
                    $files++;
                }

                $executionId = $this->insertExecution(
                    connection: $connection,
                    movement: $movement,
                    type: (string) $definition['type'],
                    status: (string) $definition['status'],
                    originId: $originId,
                    records: $definition['records'] !== null
                        ? (int) $definition['records']
                        : null,
                    durationMs: (int) $definition['duration'],
                    fileMetadata: $fileMetadata,
                    ordinal: $index
                );

                if (isset($definition['key'])) {
                    $ids[$definition['key']] = $executionId;

                    if ($fileMetadata !== null) {
                        $fileMetadataByKey[$definition['key']] = $fileMetadata;
                    }
                }

                $this->insertTimeline(
                    connection: $connection,
                    executionId: $executionId,
                    status: (string) $definition['status'],
                    failed: $definition['status'] === 'FALHOU',
                    movement: $movement
                );
            }

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new RuntimeException(
                'Não foi possível gerar o dataset de demonstração.',
                previous: $exception
            );
        }

        return [
            'database_path' => $this->databasePath,
            'executions' => count($definitions),
            'files' => $files,
        ];
    }

    private function applyMigrations(PDO $connection): void
    {
        if (!is_dir($this->migrationsDirectory)) {
            throw new RuntimeException('Diretório de migrations não encontrado para o demo mode.');
        }

        $connection->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_migration
            (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );

        $files = glob(
            rtrim($this->migrationsDirectory, '/\\')
            . DIRECTORY_SEPARATOR
            . '*.sql'
        );

        if ($files === false) {
            throw new RuntimeException('Não foi possível listar as migrations do demo mode.');
        }

        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $name = basename($file);

            $statement = $connection->prepare(
                'SELECT COUNT(*) FROM schema_migration WHERE migration = :migration'
            );
            $statement->execute([':migration' => $name]);

            if ((int) $statement->fetchColumn() > 0) {
                continue;
            }

            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException('Não foi possível ler a migration ' . $name . '.');
            }

            $connection->exec($sql);

            $insert = $connection->prepare(
                'INSERT INTO schema_migration (migration) VALUES (:migration)'
            );
            $insert->execute([':migration' => $name]);
        }
    }

    /**
     * @param array{name:string,path:string,size:int,sha256:string}|null $fileMetadata
     */
    private function insertExecution(
        PDO $connection,
        string $movement,
        string $type,
        string $status,
        ?int $originId,
        ?int $records,
        int $durationMs,
        ?array $fileMetadata,
        int $ordinal
    ): int {
        unset($ordinal);

        $started = (new DateTimeImmutable(
            $movement . ' 17:00:00',
            new DateTimeZone('UTC')
        ))->format('Y-m-d H:i:s');

        $completed = $status === 'FALHOU'
            ? (new DateTimeImmutable($started, new DateTimeZone('UTC')))
                ->modify('+8 seconds')
                ->format('Y-m-d H:i:s')
            : (new DateTimeImmutable($started, new DateTimeZone('UTC')))
                ->modify('+' . max(1, (int) ceil($durationMs / 1000)) . ' seconds')
                ->format('Y-m-d H:i:s');

        $error = $status === 'FALHOU'
            ? 'Falha simulada: indisponibilidade temporária do destino de demonstração.'
            : null;

        $statement = $connection->prepare(
            <<<'SQL'
            INSERT INTO exportacao_execucao
            (
                data_movimento,
                tipo_execucao,
                execucao_origem_id,
                status,
                arquivo_nome,
                quantidade_registros,
                tamanho_bytes,
                sha256,
                caminho_local,
                caminho_remoto,
                erro_mensagem,
                iniciado_em,
                concluido_em,
                duracao_milisegundos,
                criado_em,
                atualizado_em
            )
            VALUES
            (
                :data_movimento,
                :tipo_execucao,
                :execucao_origem_id,
                :status,
                :arquivo_nome,
                :quantidade_registros,
                :tamanho_bytes,
                :sha256,
                :caminho_local,
                :caminho_remoto,
                :erro_mensagem,
                :iniciado_em,
                :concluido_em,
                :duracao_milisegundos,
                :criado_em,
                :atualizado_em
            )
            SQL
        );

        $statement->execute([
            ':data_movimento' => $movement,
            ':tipo_execucao' => $type,
            ':execucao_origem_id' => $originId,
            ':status' => $status,
            ':arquivo_nome' => $fileMetadata['name'] ?? null,
            ':quantidade_registros' => $records,
            ':tamanho_bytes' => $fileMetadata['size'] ?? null,
            ':sha256' => $fileMetadata['sha256'] ?? null,
            ':caminho_local' => $fileMetadata['path'] ?? null,
            ':caminho_remoto' => $fileMetadata !== null
                ? '/DEMO/' . $fileMetadata['name']
                : null,
            ':erro_mensagem' => $error,
            ':iniciado_em' => $started,
            ':concluido_em' => $completed,
            ':duracao_milisegundos' => $durationMs,
            ':criado_em' => $started,
            ':atualizado_em' => $completed,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function insertTimeline(
        PDO $connection,
        int $executionId,
        string $status,
        bool $failed,
        string $movement
    ): void {
        $statuses = $failed
            ? ['AGUARDANDO', 'CONSULTANDO', 'FALHOU']
            : [
                'AGUARDANDO',
                'CONSULTANDO',
                'GERANDO_ARQUIVO',
                'VALIDANDO',
                'ENVIANDO',
                'CONFIRMANDO_ENVIO',
                'CONCLUIDO',
            ];

        $base = new DateTimeImmutable(
            $movement . ' 17:00:00',
            new DateTimeZone('UTC')
        );

        $messages = [
            'AGUARDANDO' => 'Execução demonstrativa criada.',
            'CONSULTANDO' => 'Consulta simulada concluída.',
            'GERANDO_ARQUIVO' => 'Arquivo demonstrativo gerado.',
            'VALIDANDO' => 'Validação demonstrativa concluída.',
            'ENVIANDO' => 'Envio simulado para /DEMO.',
            'CONFIRMANDO_ENVIO' => 'Confirmação simulada do destino.',
            'CONCLUIDO' => 'Execução demonstrativa concluída com sucesso.',
            'FALHOU' => 'Falha simulada para demonstrar o tratamento de erros.',
        ];

        $statement = $connection->prepare(
            <<<'SQL'
            INSERT INTO exportacao_execucao_evento
            (
                exportacao_execucao_id,
                status,
                mensagem,
                ocorrido_em
            )
            VALUES
            (
                :execucao_id,
                :status,
                :mensagem,
                :ocorrido_em
            )
            SQL
        );

        foreach ($statuses as $index => $timelineStatus) {
            $statement->execute([
                ':execucao_id' => $executionId,
                ':status' => $timelineStatus,
                ':mensagem' => $messages[$timelineStatus] ?? null,
                ':ocorrido_em' => $base
                    ->modify('+' . ($index * 2) . ' seconds')
                    ->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return array{name:string,path:string,size:int,sha256:string}
     */
    private function createDemoFile(
        string $movement,
        int $executionIndex,
        int $records,
        string $variant
    ): array {
        $directory = rtrim($this->exportsDirectory, '/\\')
            . DIRECTORY_SEPARATOR
            . 'execucao-' . $executionIndex;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório de exports demonstrativos.');
        }

        $name = 'MV'
            . str_replace('-', '', $movement)
            . '_DEMO.txt';

        $path = $directory . DIRECTORY_SEPARATOR . $name;

        $content = implode("\r\n", [
            'STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO',
            '001|7890000000011|DEMO PRODUCT A|' . $movement . '|3.000|29.90|N',
            '001|7890000000028|DEMO PRODUCT B|' . $movement . '|1.000|15.50|Y',
            '# SIW DEMO DATASET - ' . $variant . ' - logical records: ' . $records,
            '',
        ]);

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Não foi possível gravar um arquivo demonstrativo.');
        }

        return [
            'name' => $name,
            'path' => $path,
            'size' => filesize($path) ?: strlen($content),
            'sha256' => hash_file('sha256', $path) ?: hash('sha256', $content),
        ];
    }

    private function resetArtifacts(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-wal', $this->databasePath . '-shm'] as $file) {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('Não foi possível remover o banco demo anterior.');
            }
        }

        if (is_dir($this->exportsDirectory)) {
            $this->removeDirectoryContents($this->exportsDirectory);
        }
    }

    private function removeDirectoryContents(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectoryContents($path);
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }
    }

    private function countDemoFiles(): int
    {
        if (!is_dir($this->exportsDirectory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->exportsDirectory,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }
}
