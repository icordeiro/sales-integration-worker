<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaExportacaoGateway;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use Throwable;

final class FakeVendaExportacaoGateway implements VendaExportacaoGateway
{
    /** @var list<VendaExportacaoDTO> */
    public array $vendas = [];

    /** @var list<PeriodoExportacao> */
    public array $periodosConsultados = [];

    public ?Throwable $exception = null;

    public function buscarPorPeriodo(PeriodoExportacao $periodo): iterable
    {
        $this->periodosConsultados[] = $periodo;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        yield from $this->vendas;
    }
}
