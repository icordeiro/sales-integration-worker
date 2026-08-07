<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;

final readonly class ExportacaoHistoricoDTO
{
    public function __construct(
        public int $id,
        public string $dataMovimento,
        public TipoExecucao $tipoExecucao,
        public StatusExportacao $status,
        public ?int $execucaoOrigemId,
        public ?string $arquivoNome,
        public ?int $quantidadeRegistros,
        public ?int $tamanhoBytes,
        public ?int $duracaoMilisegundos,
        public ?string $erroMensagem,
        public string $iniciadoEm,
        public ?string $concluidoEm
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'data_movimento' => $this->dataMovimento,
            'tipo_execucao' => $this->tipoExecucao->value,
            'status' => $this->status->value,
            'execucao_origem_id' => $this->execucaoOrigemId,
            'arquivo_nome' => $this->arquivoNome,
            'quantidade_registros' => $this->quantidadeRegistros,
            'tamanho_bytes' => $this->tamanhoBytes,
            'duracao_milisegundos' => $this->duracaoMilisegundos,
            'erro_mensagem' => $this->erroMensagem,
            'iniciado_em' => $this->iniciadoEm,
            'concluido_em' => $this->concluidoEm,
        ];
    }
}