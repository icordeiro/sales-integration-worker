<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;
use App\Modules\Exportacao\Vendas\Application\DTO\DashboardResumoDTO;
use App\Modules\Exportacao\Vendas\Application\DTO\ExportacaoDetalheDTO;

final readonly class FakeExportacaoDashboardQuery implements ExportacaoDashboardQuery
{
    public function __construct(
        private DashboardResumoDTO $dashboardResumo
    ) {
    }

    public function resumo(): DashboardResumoDTO
    {
        return $this->dashboardResumo;
    }

    public function listarRecentes(int $limit = 50): array
    {
        return [];
    }

    public function buscarDetalhe(int $execucaoId): ?ExportacaoDetalheDTO
    {
        return null;
    }
}
