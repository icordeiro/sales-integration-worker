<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\Contracts;

use App\Modules\Exportacao\Vendas\Application\DTO\DashboardResumoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoDetalheDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoHistoricoDTO;

interface ExportacaoDashboardQuery
{
    public function resumo(): DashboardResumoDTO;

    /**
     * @return list<ExportacaoHistoricoDTO>
     */
    public function listarRecentes(
        int $limit = 50
    ): array;

    public function buscarDetalhe(
        int $execucaoId
    ): ?ExportacaoDetalheDTO;
}