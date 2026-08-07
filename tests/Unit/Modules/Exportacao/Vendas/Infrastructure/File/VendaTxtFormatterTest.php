<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Infrastructure\File;

use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Infrastructure\File\VendaTxtFormatter;
use PHPUnit\Framework\TestCase;

final class VendaTxtFormatterTest extends TestCase
{
    public function testHeaderSegueLayoutEsperado(): void
    {
        $formatter = new VendaTxtFormatter();

        self::assertSame(
            'STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO',
            $formatter->header()
        );
    }

    public function testSanitizaDelimitadorECaracteresDeControle(): void
    {
        $formatter = new VendaTxtFormatter();

        $linha = $formatter->format(
            new VendaExportacaoDTO(
                store: 4,
                barcode: "789|123\t456",
                description: "Café | Premium\r\n500g",
                day: '2026-08-06',
                unitSales: '2.000',
                valueSales: '39.90',
                promo: 'Y'
            )
        );

        self::assertSame(
            '4|789 123 456|Café Premium 500g|2026-08-06|2.000|39.90|Y',
            $linha
        );
    }
}
