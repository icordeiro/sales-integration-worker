<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Contracts;

use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;

interface VendaArquivoGenerator
{
    /**
     * @param iterable<VendaExportacaoDTO> $vendas
     */
    public function gerar(
        PeriodoExportacao $periodo,
        iterable $vendas,
        ?int $execucaoId = null
    ): ArquivoVendaGerado;
}