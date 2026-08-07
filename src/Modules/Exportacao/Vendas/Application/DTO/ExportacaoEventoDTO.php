<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;

final readonly class ExportacaoEventoDTO
{
    public function __construct(
        public int $id,
        public StatusExportacao $status,
        public ?string $mensagem,
        public string $ocorridoEm
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'mensagem' => $this->mensagem,
            'ocorrido_em' => $this->ocorridoEm,
        ];
    }
}