<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoExecucaoDTO;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;
use App\Shared\Application\DTO\RemoteFileUploadResult;

final class InMemoryExportacaoExecucaoRepository implements ExportacaoExecucaoRepository
{
    /** @var array<int, ExportacaoExecucaoDTO> */
    public array $execucoes = [];

    /** @var list<array{execucao_id:int,status:StatusExportacao,mensagem:?string}> */
    public array $statusRegistrados = [];

    /** @var list<array{execucao_id:int,erro:string}> */
    public array $falhas = [];

    /** @var array<string, bool> */
    public array $normalBloqueante = [];

    private int $nextId = 1;

    public function seed(ExportacaoExecucaoDTO $execucao): void
    {
        $this->execucoes[$execucao->id] = $execucao;
        $this->nextId = max($this->nextId, $execucao->id + 1);
    }

    public function buscarPorId(int $execucaoId): ?ExportacaoExecucaoDTO
    {
        return $this->execucoes[$execucaoId] ?? null;
    }

    public function existeExecucaoNormalBloqueante(string $dataMovimento): bool
    {
        return $this->normalBloqueante[$dataMovimento] ?? false;
    }

    public function iniciar(
        string $dataMovimento,
        TipoExecucao $tipoExecucao = TipoExecucao::NORMAL,
        ?int $execucaoOrigemId = null
    ): int {
        $id = $this->nextId++;

        $this->execucoes[$id] = new ExportacaoExecucaoDTO(
            id: $id,
            dataMovimento: $dataMovimento,
            tipoExecucao: $tipoExecucao,
            status: StatusExportacao::AGUARDANDO,
            execucaoOrigemId: $execucaoOrigemId,
            arquivoNome: null,
            quantidadeRegistros: null,
            tamanhoBytes: null,
            sha256: null,
            caminhoLocal: null,
            caminhoRemoto: null
        );

        return $id;
    }

    public function registrarStatus(
        int $execucaoId,
        StatusExportacao $status,
        ?string $mensagem = null
    ): void {
        $this->statusRegistrados[] = [
            'execucao_id' => $execucaoId,
            'status' => $status,
            'mensagem' => $mensagem,
        ];

        $current = $this->requireExecution($execucaoId);
        $this->execucoes[$execucaoId] = $this->copy($current, status: $status);
    }

    public function registrarArquivo(
        int $execucaoId,
        ArquivoVendaGerado $arquivo
    ): void {
        $current = $this->requireExecution($execucaoId);

        $this->execucoes[$execucaoId] = new ExportacaoExecucaoDTO(
            id: $current->id,
            dataMovimento: $current->dataMovimento,
            tipoExecucao: $current->tipoExecucao,
            status: $current->status,
            execucaoOrigemId: $current->execucaoOrigemId,
            arquivoNome: $arquivo->nome,
            quantidadeRegistros: $arquivo->quantidadeRegistros,
            tamanhoBytes: $arquivo->tamanhoBytes,
            sha256: $arquivo->sha256,
            caminhoLocal: $arquivo->caminho,
            caminhoRemoto: $current->caminhoRemoto
        );
    }

    public function concluir(
        int $execucaoId,
        ArquivoVendaGerado $arquivo,
        RemoteFileUploadResult $envio,
        int $duracaoMilisegundos
    ): void {
        $current = $this->requireExecution($execucaoId);

        $this->execucoes[$execucaoId] = new ExportacaoExecucaoDTO(
            id: $current->id,
            dataMovimento: $current->dataMovimento,
            tipoExecucao: $current->tipoExecucao,
            status: StatusExportacao::CONCLUIDO,
            execucaoOrigemId: $current->execucaoOrigemId,
            arquivoNome: $arquivo->nome,
            quantidadeRegistros: $arquivo->quantidadeRegistros,
            tamanhoBytes: $arquivo->tamanhoBytes,
            sha256: $arquivo->sha256,
            caminhoLocal: $arquivo->caminho,
            caminhoRemoto: $envio->remotePath
        );
    }

    public function falhar(
        int $execucaoId,
        string $erro,
        int $duracaoMilisegundos
    ): void {
        $this->falhas[] = [
            'execucao_id' => $execucaoId,
            'erro' => $erro,
        ];

        $current = $this->requireExecution($execucaoId);
        $this->execucoes[$execucaoId] = $this->copy(
            $current,
            status: StatusExportacao::FALHOU
        );
    }

    private function requireExecution(int $execucaoId): ExportacaoExecucaoDTO
    {
        return $this->execucoes[$execucaoId]
            ?? throw new \RuntimeException('Execução de teste não encontrada.');
    }

    private function copy(
        ExportacaoExecucaoDTO $current,
        StatusExportacao $status
    ): ExportacaoExecucaoDTO {
        return new ExportacaoExecucaoDTO(
            id: $current->id,
            dataMovimento: $current->dataMovimento,
            tipoExecucao: $current->tipoExecucao,
            status: $status,
            execucaoOrigemId: $current->execucaoOrigemId,
            arquivoNome: $current->arquivoNome,
            quantidadeRegistros: $current->quantidadeRegistros,
            tamanhoBytes: $current->tamanhoBytes,
            sha256: $current->sha256,
            caminhoLocal: $current->caminhoLocal,
            caminhoRemoto: $current->caminhoRemoto
        );
    }
}
