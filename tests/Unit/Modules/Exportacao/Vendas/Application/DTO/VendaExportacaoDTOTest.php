<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\DTO;

use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VendaExportacaoDTOTest extends TestCase
{
    public function testNormalizaLinhaDoBanco(): void
    {
        $dto = VendaExportacaoDTO::fromDatabaseRow([
            'store' => '4',
            'barcode' => ' 7891234567890 ',
            'description' => ' Produto teste ',
            'day' => '2026-08-06',
            'unit_sales' => '12.500',
            'value_sales' => '199.90',
            'promo' => 'y',
        ]);

        self::assertSame(4, $dto->store);
        self::assertSame('7891234567890', $dto->barcode);
        self::assertSame('Produto teste', $dto->description);
        self::assertSame('2026-08-06', $dto->day);
        self::assertSame('12.500', $dto->unitSales);
        self::assertSame('199.90', $dto->valueSales);
        self::assertSame('Y', $dto->promo);
    }

    public function testRecusaColunaObrigatoriaAusente(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VendaExportacaoDTO::fromDatabaseRow([
            'store' => 1,
        ]);
    }

    public function testRecusaIndicadorPromocionalInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VendaExportacaoDTO(
            store: 1,
            barcode: '789',
            description: 'Produto',
            day: '2026-08-06',
            unitSales: '1',
            valueSales: '10.00',
            promo: 'X'
        );
    }
}
