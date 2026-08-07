<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;
use App\Modules\Exportacao\Vendas\Domain\Enum\TipoExecucao;

final readonly class ExportacaoExecucaoDTO
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
        public ?string $sha256,
        public ?string $caminhoLocal,
        public ?string $caminhoRemoto
    ) {
    }
}