<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Contracts;

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;

interface VendaExportacaoGateway
{
    /**
     * @return iterable<VendaExportacaoDTO>
     */
    public function buscarPorPeriodo(
        PeriodoExportacao $periodo
    ): iterable;
}