<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Services;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaArquivoGenerator;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;

final readonly class GerarArquivoVendasService
{
    public function __construct(
        private ConsultarVendasService $consultarVendas,
        private VendaArquivoGenerator $arquivoGenerator
    ) {
    }

    public function execute(
        PeriodoExportacao $periodo
    ): ArquivoVendaGerado {
        $vendas = $this->consultarVendas->execute(
            $periodo
        );

        return $this->arquivoGenerator->gerar(
            periodo: $periodo,
            vendas: $vendas
        );
    }
}