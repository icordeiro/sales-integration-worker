<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Exportacao\Vendas\Application\Contracts\VendaArquivoGenerator;
use App\Modules\Exportacao\Vendas\Application\DTO\ArquivoVendaGerado;
use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use Throwable;

final class FakeVendaArquivoGenerator implements VendaArquivoGenerator
{
    public function __construct(
        public ArquivoVendaGerado $arquivo
    ) {
    }

    /** @var list<array{periodo:PeriodoExportacao,execucao_id:?int,quantidade:int}> */
    public array $geracoes = [];

    public ?Throwable $exception = null;

    public function gerar(
        PeriodoExportacao $periodo,
        iterable $vendas,
        ?int $execucaoId = null
    ): ArquivoVendaGerado {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $quantidade = 0;

        foreach ($vendas as $venda) {
            if (!$venda instanceof VendaExportacaoDTO) {
                throw new \UnexpectedValueException('Venda de teste inválida.');
            }

            $quantidade++;
        }

        $this->geracoes[] = [
            'periodo' => $periodo,
            'execucao_id' => $execucaoId,
            'quantidade' => $quantidade,
        ];

        return $this->arquivo;
    }
}
