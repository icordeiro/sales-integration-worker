<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaExportacaoGateway;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;

final readonly class ConsultarVendasService
{
    public function __construct(
        private VendaExportacaoGateway $gateway
    ) {
    }

    /**
     * @return iterable<VendaExportacaoDTO>
     */
    public function execute(
        PeriodoExportacao $periodo
    ): iterable {
        return $this->gateway->buscarPorPeriodo(
            $periodo
        );
    }
}