<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Infrastructure\File;

use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;

final class VendaTxtFormatter
{
    public const HEADER =
        'STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO';

    public function header(): string
    {
        return self::HEADER;
    }

    public function format(
        VendaExportacaoDTO $venda
    ): string {
        return implode(
            '|',
            [
                (string) $venda->store,
                $this->normalize($venda->barcode),
                $this->normalize($venda->description),
                $venda->day,
                $venda->unitSales,
                $venda->valueSales,
                $venda->promo,
            ]
        );
    }

    private function normalize(string $value): string
    {
        $value = str_replace(
            ['|', "\r", "\n", "\t"],
            [' ', ' ', ' ', ' '],
            $value
        );

        $normalized = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim(
            $normalized ?? $value
        );
    }
}