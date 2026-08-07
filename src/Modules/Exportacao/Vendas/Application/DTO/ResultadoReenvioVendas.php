<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use App\Shared\Application\DTO\RemoteFileUploadResult;

final readonly class ResultadoReenvioVendas
{
    public function __construct(
        public int $execucaoId,
        public int $execucaoOrigemId,
        public ArquivoVendaGerado $arquivo,
        public RemoteFileUploadResult $envio,
        public float $duracaoSegundos
    ) {
    }
}