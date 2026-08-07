<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Shared\Application\Contracts\RemoteFileStorage;
use App\Shared\Application\DTO\RemoteFileUploadResult;

final readonly class EnviarArquivoVendasService
{
    public function __construct(
        private RemoteFileStorage $remoteStorage
    ) {
    }

    public function execute(
        ArquivoVendaGerado $arquivo
    ): RemoteFileUploadResult {
        return $this->remoteStorage->uploadAtomically(
            localPath: $arquivo->caminho,
            remoteFileName: $arquivo->nome
        );
    }
}