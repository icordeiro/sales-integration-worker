<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

final readonly class ExportacaoDetalheDTO
{
    /**
     * @param list<ExportacaoEventoDTO> $eventos
     */
    public function __construct(
        public ExportacaoHistoricoDTO $execucao,
        public ?string $sha256,
        public ?string $caminhoLocal,
        public ?string $caminhoRemoto,
        public bool $arquivoLocalDisponivel,
        public bool $podeReenviar,
        public bool $podeReprocessar,
        public ?ExportacaoComparacaoDTO $comparacaoOrigem,
        public array $eventos
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->execucao->toArray(),

            'sha256' => $this->sha256,
            'caminho_local' => $this->caminhoLocal,
            'caminho_remoto' => $this->caminhoRemoto,
            'arquivo_local_disponivel' => $this->arquivoLocalDisponivel,
            'pode_reenviar' => $this->podeReenviar,
            'pode_reprocessar' => $this->podeReprocessar,
            'comparacao_origem' => $this->comparacaoOrigem?->toArray(),

            'eventos' => array_map(
                static fn (
                    ExportacaoEventoDTO $evento
                ): array => $evento->toArray(),
                $this->eventos
            ),
        ];
    }
}
