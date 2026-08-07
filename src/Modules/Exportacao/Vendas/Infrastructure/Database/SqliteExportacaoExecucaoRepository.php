<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception\ExportacaoExecucaoRepositoryException;
use App\Shared\Application\DTO\RemoteFileUploadResult;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use PDO;
use Throwable;

final readonly class SqliteExportacaoExecucaoRepository implements ExportacaoExecucaoRepository
{
    public function __construct(
        private DatabaseConnection $database
    ) {}

    public function iniciar(
        string $dataMovimento,
        TipoExecucao $tipoExecucao = TipoExecucao::NORMAL,
        ?int $execucaoOrigemId = null
    ): int {
        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare(
                <<<'SQL'
                INSERT INTO exportacao_execucao
                (
                    data_movimento,
                    tipo_execucao,
                    execucao_origem_id,
                    status
                )
                VALUES
                (
                    :data_movimento,
                    :tipo_execucao,
                    :execucao_origem_id,
                    :status
                )
                SQL
            );

            $statement->bindValue(
                ':data_movimento',
                $dataMovimento,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':tipo_execucao',
                $tipoExecucao->value,
                PDO::PARAM_STR
            );

            if ($execucaoOrigemId === null) {
                $statement->bindValue(
                    ':execucao_origem_id',
                    null,
                    PDO::PARAM_NULL
                );
            } else {
                $statement->bindValue(
                    ':execucao_origem_id',
                    $execucaoOrigemId,
                    PDO::PARAM_INT
                );
            }

            $statement->bindValue(
                ':status',
                StatusExportacao::AGUARDANDO->value,
                PDO::PARAM_STR
            );

            $statement->execute();

            $execucaoId = (int) $connection->lastInsertId();

            if ($execucaoId <= 0) {
                throw new \RuntimeException(
                    'Não foi possível recuperar o ID da execução.'
                );
            }

            $this->insertEvento(
                connection: $connection,
                execucaoId: $execucaoId,
                status: StatusExportacao::AGUARDANDO,
                mensagem: 'Execução criada.'
            );

            $connection->commit();

            return $execucaoId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    public function registrarStatus(
        int $execucaoId,
        StatusExportacao $status,
        ?string $mensagem = null
    ): void {
        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare(
                <<<'SQL'
                UPDATE exportacao_execucao
                SET
                    status = :status,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = :id
                SQL
            );

            $statement->execute([
                ':status' => $status->value,
                ':id' => $execucaoId,
            ]);

            $this->insertEvento(
                connection: $connection,
                execucaoId: $execucaoId,
                status: $status,
                mensagem: $mensagem
            );

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    public function registrarArquivo(
        int $execucaoId,
        ArquivoVendaGerado $arquivo
    ): void {
        try {
            $statement = $this->database
                ->connection()
                ->prepare(
                    <<<'SQL'
                    UPDATE exportacao_execucao
                    SET
                        arquivo_nome = :arquivo_nome,
                        quantidade_registros = :quantidade_registros,
                        tamanho_bytes = :tamanho_bytes,
                        sha256 = :sha256,
                        caminho_local = :caminho_local,
                        atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = :id
                    SQL
                );

            $statement->execute([
                ':arquivo_nome' => $arquivo->nome,
                ':quantidade_registros' => $arquivo->quantidadeRegistros,
                ':tamanho_bytes' => $arquivo->tamanhoBytes,
                ':sha256' => $arquivo->sha256,
                ':caminho_local' => $arquivo->caminho,
                ':id' => $execucaoId,
            ]);
        } catch (Throwable $exception) {
            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    public function concluir(
        int $execucaoId,
        ArquivoVendaGerado $arquivo,
        RemoteFileUploadResult $envio,
        int $duracaoMilisegundos
    ): void {
        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare(
                <<<'SQL'
                UPDATE exportacao_execucao
                SET
                    status = :status,
                    arquivo_nome = :arquivo_nome,
                    quantidade_registros = :quantidade_registros,
                    tamanho_bytes = :tamanho_bytes,
                    sha256 = :sha256,
                    caminho_local = :caminho_local,
                    caminho_remoto = :caminho_remoto,
                    erro_mensagem = NULL,
                    concluido_em = CURRENT_TIMESTAMP,
                    duracao_milisegundos = :duracao_milisegundos,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = :id
                SQL
            );

            $statement->execute([
                ':status' => StatusExportacao::CONCLUIDO->value,
                ':arquivo_nome' => $arquivo->nome,
                ':quantidade_registros' => $arquivo->quantidadeRegistros,
                ':tamanho_bytes' => $arquivo->tamanhoBytes,
                ':sha256' => $arquivo->sha256,
                ':caminho_local' => $arquivo->caminho,
                ':caminho_remoto' => $envio->remotePath,
                ':duracao_milisegundos' => $duracaoMilisegundos,
                ':id' => $execucaoId,
            ]);

            $this->insertEvento(
                connection: $connection,
                execucaoId: $execucaoId,
                status: StatusExportacao::CONCLUIDO,
                mensagem: 'Arquivo enviado e confirmado com sucesso.'
            );

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    public function falhar(
        int $execucaoId,
        string $erro,
        int $duracaoMilisegundos
    ): void {
        $connection = $this->database->connection();

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare(
                <<<'SQL'
                UPDATE exportacao_execucao
                SET
                    status = :status,
                    erro_mensagem = :erro,
                    concluido_em = CURRENT_TIMESTAMP,
                    duracao_milisegundos = :duracao_milisegundos,
                    atualizado_em = CURRENT_TIMESTAMP
                WHERE id = :id
                SQL
            );

            $statement->execute([
                ':status' => StatusExportacao::FALHOU->value,
                ':erro' => mb_substr($erro, 0, 5000),
                ':duracao_milisegundos' => $duracaoMilisegundos,
                ':id' => $execucaoId,
            ]);

            $this->insertEvento(
                connection: $connection,
                execucaoId: $execucaoId,
                status: StatusExportacao::FALHOU,
                mensagem: mb_substr($erro, 0, 5000)
            );

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    private function insertEvento(
        PDO $connection,
        int $execucaoId,
        StatusExportacao $status,
        ?string $mensagem
    ): void {
        $statement = $connection->prepare(
            <<<'SQL'
            INSERT INTO exportacao_execucao_evento
            (
                exportacao_execucao_id,
                status,
                mensagem
            )
            VALUES
            (
                :execucao_id,
                :status,
                :mensagem
            )
            SQL
        );

        $statement->execute([
            ':execucao_id' => $execucaoId,
            ':status' => $status->value,
            ':mensagem' => $mensagem,
        ]);
    }

    public function existeExecucaoNormalBloqueante(
        string $dataMovimento
    ): bool {
        try {
            $statement = $this->database
                ->connection()
                ->prepare(
                    <<<'SQL'
                SELECT EXISTS (
                    SELECT 1
                    FROM exportacao_execucao
                    WHERE data_movimento = :data_movimento
                      AND tipo_execucao = 'NORMAL'
                      AND status IN (
                          'AGUARDANDO',
                          'CONSULTANDO',
                          'GERANDO_ARQUIVO',
                          'VALIDANDO',
                          'ENVIANDO',
                          'CONFIRMANDO_ENVIO',
                          'CONCLUIDO'
                      )
                )
                SQL
                );

            $statement->execute([
                ':data_movimento' => $dataMovimento,
            ]);

            return (int) $statement->fetchColumn() === 1;
        } catch (Throwable $exception) {
            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }

    public function buscarPorId(
        int $execucaoId
    ): ?ExportacaoExecucaoDTO {
        try {
            $statement = $this->database
                ->connection()
                ->prepare(
                    <<<'SQL'
                SELECT
                    id,
                    data_movimento,
                    tipo_execucao,
                    execucao_origem_id,
                    status,
                    arquivo_nome,
                    quantidade_registros,
                    tamanho_bytes,
                    sha256,
                    caminho_local,
                    caminho_remoto
                FROM exportacao_execucao
                WHERE id = :id
                LIMIT 1
                SQL
                );

            $statement->execute([
                ':id' => $execucaoId,
            ]);

            $row = $statement->fetch(
                PDO::FETCH_ASSOC
            );

            if ($row === false) {
                return null;
            }

            return new ExportacaoExecucaoDTO(
                id: (int) $row['id'],
                dataMovimento: (string) $row['data_movimento'],
                tipoExecucao: TipoExecucao::from(
                    (string) $row['tipo_execucao']
                ),
                status: StatusExportacao::from(
                    (string) $row['status']
                ),
                execucaoOrigemId: $row['execucao_origem_id'] !== null
                    ? (int) $row['execucao_origem_id']
                    : null,
                arquivoNome: $row['arquivo_nome'] !== null
                    ? (string) $row['arquivo_nome']
                    : null,
                quantidadeRegistros: $row['quantidade_registros'] !== null
                    ? (int) $row['quantidade_registros']
                    : null,
                tamanhoBytes: $row['tamanho_bytes'] !== null
                    ? (int) $row['tamanho_bytes']
                    : null,
                sha256: $row['sha256'] !== null
                    ? (string) $row['sha256']
                    : null,
                caminhoLocal: $row['caminho_local'] !== null
                    ? (string) $row['caminho_local']
                    : null,
                caminhoRemoto: $row['caminho_remoto'] !== null
                    ? (string) $row['caminho_remoto']
                    : null
            );
        } catch (Throwable $exception) {
            throw ExportacaoExecucaoRepositoryException::persistenceFailed(
                $exception
            );
        }
    }
}
