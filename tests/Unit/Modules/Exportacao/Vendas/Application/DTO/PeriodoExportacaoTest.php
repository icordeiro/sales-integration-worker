<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Exportacao\Vendas\Application\DTO;

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PeriodoExportacaoTest extends TestCase
{
    public function testCriaPeriodoDoDiaComFimExclusivo(): void
    {
        $periodo = PeriodoExportacao::doDia(
            new DateTimeImmutable('2026-08-07 16:30:00-03:00')
        );

        self::assertSame('2026-08-07', $periodo->dataReferencia());
        self::assertSame('2026-08-07 00:00:00', $periodo->inicioParaBanco());
        self::assertSame('2026-08-08 00:00:00', $periodo->fimExclusivoParaBanco());
    }

    public function testCriaPeriodoDoDiaAnterior(): void
    {
        $periodo = PeriodoExportacao::doDiaAnterior(
            new DateTimeImmutable('2026-08-07 14:00:00-03:00')
        );

        self::assertSame('2026-08-06', $periodo->dataReferencia());
    }

    public function testRecusaFimMenorOuIgualAoInicio(): void
    {
        $inicio = new DateTimeImmutable('2026-08-07 00:00:00');

        $this->expectException(InvalidArgumentException::class);

        new PeriodoExportacao(
            inicio: $inicio,
            fimExclusivo: $inicio
        );
    }
}
