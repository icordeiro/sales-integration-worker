<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\Database;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Application\DTO\DashboardResumoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoComparacaoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoDetalheDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoEventoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoHistoricoDTO;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Modules\Exportacao\Vendas\Infrastructure\Database\Exception\ExportacaoDashboardQueryException;
use App\Shared\Infrastructure\Database\Contracts\DatabaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class SqliteExportacaoDashboardQuery implements ExportacaoDashboardQuery
{
    public function __construct(
        private DatabaseConnection $database
    ) {
    }

    public function resumo(): DashboardResumoDTO
    {
        try {
            $ultimaExecucao =
                $this->buscarUltimaExecucao();

            $ultimaConcluida =
                $this->buscarUltimaExecucaoConcluida();

            $execucoesEmAndamento =
                $this->contarExecucoesEmAndamento();

            $falhasUltimosSeteDias =
                $this->contarFalhasUltimosSeteDias();

            return new DashboardResumoDTO(
                ultimaExecucao: $ultimaExecucao,
                ultimaExecucaoConcluida: $ultimaConcluida,
                execucoesEmAndamento: $execucoesEmAndamento,
                falhasUltimosSeteDias: $falhasUltimosSeteDias
            );
        } catch (Throwable $exception) {
            throw ExportacaoDashboardQueryException::couldNotRetrieve(
                $exception
            );
        }
    }

    public function listarRecentes(
        int $limit = 50
    ): array {
        if ($limit < 1) {
            $limit = 1;
        }

        if ($limit > 200) {
            $limit = 200;
        }

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
                        erro_mensagem,
                        iniciado_em,
                        concluido_em,
                        duracao_milisegundos
                    FROM exportacao_execucao
                    ORDER BY id DESC
                    LIMIT :limit
                    SQL
                );

            $statement->bindValue(
                ':limit',
                $limit,
                PDO::PARAM_INT
            );

            $statement->execute();

            $result = [];

            while (
                $row = $statement->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {
                $result[] =
                    $this->mapHistorico(
                        $row
                    );
            }

            return $result;
        } catch (Throwable $exception) {
            throw ExportacaoDashboardQueryException::couldNotRetrieve(
                $exception
            );
        }
    }

    public function buscarDetalhe(
        int $execucaoId
    ): ?ExportacaoDetalheDTO {
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
                        caminho_remoto,
                        erro_mensagem,
                        iniciado_em,
                        concluido_em,
                        duracao_milisegundos
                    FROM exportacao_execucao
                    WHERE id = :id
                    LIMIT 1
                    SQL
                );

            $statement->bindValue(
                ':id',
                $execucaoId,
                PDO::PARAM_INT
            );

            $statement->execute();

            $row = $statement->fetch(
                PDO::FETCH_ASSOC
            );

            if ($row === false) {
                return null;
            }

            $execucao =
                $this->mapHistorico(
                    $row
                );

            $eventos =
                $this->buscarEventos(
                    $execucaoId
                );

            $sha256 =
                $row['sha256'] !== null
                    ? (string) $row['sha256']
                    : null;

            $caminhoLocal =
                $row['caminho_local'] !== null
                    ? (string) $row['caminho_local']
                    : null;

            $caminhoRemoto =
                $row['caminho_remoto'] !== null
                    ? (string) $row['caminho_remoto']
                    : null;

            $arquivoLocalDisponivel =
                $caminhoLocal !== null
                && is_file($caminhoLocal);

            $metadadosArquivoCompletos =
                $execucao->arquivoNome !== null
                && $execucao->quantidadeRegistros !== null
                && $execucao->tamanhoBytes !== null
                && $sha256 !== null
                && $caminhoLocal !== null;

            $podeReenviar =
                $execucao->status === StatusExportacao::CONCLUIDO
                && $metadadosArquivoCompletos
                && $arquivoLocalDisponivel;

            $podeReprocessar =
                $execucao->status === StatusExportacao::CONCLUIDO
                && $sha256 !== null;

            $comparacaoOrigem =
                $execucao->execucaoOrigemId !== null
                    ? $this->buscarComparacaoOrigem(
                        execucaoOrigemId: $execucao->execucaoOrigemId,
                        quantidadeRegistrosAtual: $execucao->quantidadeRegistros,
                        tamanhoBytesAtual: $execucao->tamanhoBytes,
                        sha256Atual: $sha256
                    )
                    : null;

            return new ExportacaoDetalheDTO(
                execucao: $execucao,
                sha256: $sha256,
                caminhoLocal: $caminhoLocal,
                caminhoRemoto: $caminhoRemoto,
                arquivoLocalDisponivel: $arquivoLocalDisponivel,
                podeReenviar: $podeReenviar,
                podeReprocessar: $podeReprocessar,
                comparacaoOrigem: $comparacaoOrigem,
                eventos: $eventos
            );
        } catch (Throwable $exception) {
            throw ExportacaoDashboardQueryException::couldNotRetrieve(
                $exception
            );
        }
    }

    private function buscarUltimaExecucao(): ?ExportacaoHistoricoDTO
    {
        $statement = $this->database
            ->connection()
            ->query(
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
                    erro_mensagem,
                    iniciado_em,
                    concluido_em,
                    duracao_milisegundos
                FROM exportacao_execucao
                ORDER BY id DESC
                LIMIT 1
                SQL
            );

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        if ($row === false) {
            return null;
        }

        return $this->mapHistorico(
            $row
        );
    }

    private function buscarUltimaExecucaoConcluida(): ?ExportacaoHistoricoDTO
    {
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
                    erro_mensagem,
                    iniciado_em,
                    concluido_em,
                    duracao_milisegundos
                FROM exportacao_execucao
                WHERE status = :status
                ORDER BY id DESC
                LIMIT 1
                SQL
            );

        $statement->execute([
            ':status' =>
                StatusExportacao::CONCLUIDO->value,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        if ($row === false) {
            return null;
        }

        return $this->mapHistorico(
            $row
        );
    }

    private function contarExecucoesEmAndamento(): int
    {
        $statement = $this->database
            ->connection()
            ->query(
                <<<'SQL'
                SELECT COUNT(*)
                FROM exportacao_execucao
                WHERE status IN (
                    'AGUARDANDO',
                    'CONSULTANDO',
                    'GERANDO_ARQUIVO',
                    'VALIDANDO',
                    'ENVIANDO',
                    'CONFIRMANDO_ENVIO'
                )
                SQL
            );

        return (int) $statement->fetchColumn();
    }

    private function contarFalhasUltimosSeteDias(): int
    {
        $statement = $this->database
            ->connection()
            ->prepare(
                <<<'SQL'
                SELECT COUNT(*)
                FROM exportacao_execucao
                WHERE status = :status
                  AND iniciado_em >= datetime(
                      'now',
                      '-7 days'
                  )
                SQL
            );

        $statement->execute([
            ':status' =>
                StatusExportacao::FALHOU->value,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function buscarComparacaoOrigem(
        int $execucaoOrigemId,
        ?int $quantidadeRegistrosAtual,
        ?int $tamanhoBytesAtual,
        ?string $sha256Atual
    ): ?ExportacaoComparacaoDTO {
        $statement = $this->database
            ->connection()
            ->prepare(
                <<<'SQL'
                SELECT
                    id,
                    quantidade_registros,
                    tamanho_bytes,
                    sha256
                FROM exportacao_execucao
                WHERE id = :id
                LIMIT 1
                SQL
            );

        $statement->bindValue(
            ':id',
            $execucaoOrigemId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        if ($row === false) {
            return null;
        }

        $quantidadeRegistrosOrigem =
            $row['quantidade_registros'] !== null
                ? (int) $row['quantidade_registros']
                : null;

        $tamanhoBytesOrigem =
            $row['tamanho_bytes'] !== null
                ? (int) $row['tamanho_bytes']
                : null;

        $sha256Origem =
            $row['sha256'] !== null
                ? (string) $row['sha256']
                : null;

        $diferencaRegistros =
            $quantidadeRegistrosOrigem !== null
            && $quantidadeRegistrosAtual !== null
                ? $quantidadeRegistrosAtual
                    - $quantidadeRegistrosOrigem
                : null;

        $diferencaBytes =
            $tamanhoBytesOrigem !== null
            && $tamanhoBytesAtual !== null
                ? $tamanhoBytesAtual
                    - $tamanhoBytesOrigem
                : null;

        $conteudoAlterado =
            $sha256Origem !== null
            && $sha256Atual !== null
                ? !hash_equals(
                    $sha256Origem,
                    $sha256Atual
                )
                : null;

        return new ExportacaoComparacaoDTO(
            execucaoOrigemId: (int) $row['id'],
            quantidadeRegistrosOrigem: $quantidadeRegistrosOrigem,
            quantidadeRegistrosAtual: $quantidadeRegistrosAtual,
            diferencaRegistros: $diferencaRegistros,
            tamanhoBytesOrigem: $tamanhoBytesOrigem,
            tamanhoBytesAtual: $tamanhoBytesAtual,
            diferencaBytes: $diferencaBytes,
            sha256Origem: $sha256Origem,
            sha256Atual: $sha256Atual,
            conteudoAlterado: $conteudoAlterado
        );
    }

    /**
     * @return list<ExportacaoEventoDTO>
     */
    private function buscarEventos(
        int $execucaoId
    ): array {
        $statement = $this->database
            ->connection()
            ->prepare(
                <<<'SQL'
                SELECT
                    id,
                    status,
                    mensagem,
                    ocorrido_em
                FROM exportacao_execucao_evento
                WHERE exportacao_execucao_id = :execucao_id
                ORDER BY id ASC
                SQL
            );

        $statement->bindValue(
            ':execucao_id',
            $execucaoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $eventos = [];

        while (
            $row = $statement->fetch(
                PDO::FETCH_ASSOC
            )
        ) {
            $eventos[] =
                new ExportacaoEventoDTO(
                    id: (int) $row['id'],

                    status:
                        StatusExportacao::from(
                            (string) $row['status']
                        ),

                    mensagem:
                        $row['mensagem'] !== null
                            ? (string) $row['mensagem']
                            : null,

                    ocorridoEm:
                        $this->formatTimestampUtc(
                            (string) $row['ocorrido_em']
                        )
                );
        }

        return $eventos;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapHistorico(
        array $row
    ): ExportacaoHistoricoDTO {
        return new ExportacaoHistoricoDTO(
            id: (int) $row['id'],

            dataMovimento:
                (string) $row['data_movimento'],

            tipoExecucao:
                TipoExecucao::from(
                    (string) $row['tipo_execucao']
                ),

            status:
                StatusExportacao::from(
                    (string) $row['status']
                ),

            execucaoOrigemId:
                $row['execucao_origem_id'] !== null
                    ? (int) $row['execucao_origem_id']
                    : null,

            arquivoNome:
                $row['arquivo_nome'] !== null
                    ? (string) $row['arquivo_nome']
                    : null,

            quantidadeRegistros:
                $row['quantidade_registros'] !== null
                    ? (int) $row['quantidade_registros']
                    : null,

            tamanhoBytes:
                $row['tamanho_bytes'] !== null
                    ? (int) $row['tamanho_bytes']
                    : null,

            duracaoMilisegundos:
                $row['duracao_milisegundos'] !== null
                    ? (int) $row['duracao_milisegundos']
                    : null,

            erroMensagem:
                $row['erro_mensagem'] !== null
                    ? (string) $row['erro_mensagem']
                    : null,

            iniciadoEm:
                $this->formatTimestampUtc(
                    (string) $row['iniciado_em']
                ),

            concluidoEm:
                $row['concluido_em'] !== null
                    ? $this->formatTimestampUtc(
                        (string) $row['concluido_em']
                    )
                    : null
        );
    }

    private function formatTimestampUtc(
        string $timestamp
    ): string {
        $date = new DateTimeImmutable(
            $timestamp,
            new DateTimeZone('UTC')
        );

        return $date
            ->setTimezone(
                new DateTimeZone('UTC')
            )
            ->format(DATE_ATOM);
    }
}