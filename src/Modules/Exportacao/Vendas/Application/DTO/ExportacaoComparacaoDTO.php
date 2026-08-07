<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

final readonly class ExportacaoComparacaoDTO
{
    public function __construct(
        public int $execucaoOrigemId,
        public ?int $quantidadeRegistrosOrigem,
        public ?int $quantidadeRegistrosAtual,
        public ?int $diferencaRegistros,
        public ?int $tamanhoBytesOrigem,
        public ?int $tamanhoBytesAtual,
        public ?int $diferencaBytes,
        public ?string $sha256Origem,
        public ?string $sha256Atual,
        public ?bool $conteudoAlterado
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'execucao_origem_id' => $this->execucaoOrigemId,
            'quantidade_registros_origem' => $this->quantidadeRegistrosOrigem,
            'quantidade_registros_atual' => $this->quantidadeRegistrosAtual,
            'diferenca_registros' => $this->diferencaRegistros,
            'tamanho_bytes_origem' => $this->tamanhoBytesOrigem,
            'tamanho_bytes_atual' => $this->tamanhoBytesAtual,
            'diferenca_bytes' => $this->diferencaBytes,
            'sha256_origem' => $this->sha256Origem,
            'sha256_atual' => $this->sha256Atual,
            'conteudo_alterado' => $this->conteudoAlterado,
        ];
    }
}
