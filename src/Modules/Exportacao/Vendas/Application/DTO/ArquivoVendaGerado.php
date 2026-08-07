<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

final readonly class ArquivoVendaGerado
{
    public function __construct(
        public string $nome,
        public string $caminho,
        public string $dataReferencia,
        public int $quantidadeRegistros,
        public int $tamanhoBytes,
        public string $sha256
    ) {
    }
}