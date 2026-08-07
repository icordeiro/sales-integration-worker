<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use App\Shared\Application\DTO\RemoteFileUploadResult;

final readonly class ResultadoExportacaoVendas
{
    public function __construct(
        public int $execucaoId,
        public ArquivoVendaGerado $arquivo,
        public RemoteFileUploadResult $envio,
        public float $duracaoSegundos,
        public ?bool $conteudoAlterado = null
    ) {
    }
}