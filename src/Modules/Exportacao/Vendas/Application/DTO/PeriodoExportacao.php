<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PeriodoExportacao
{
    public function __construct(
        public DateTimeImmutable $inicio,
        public DateTimeImmutable $fimExclusivo
    ) {
        if ($this->fimExclusivo <= $this->inicio) {
            throw new InvalidArgumentException(
                'O fim do período deve ser posterior ao início.'
            );
        }
    }

    public static function doDia(DateTimeImmutable $data): self
    {
        $inicio = $data->setTime(0, 0, 0);

        return new self(
            inicio: $inicio,
            fimExclusivo: $inicio->modify('+1 day')
        );
    }

    public static function doDiaAnterior(
        DateTimeImmutable $dataAtual
    ): self {
        return self::doDia(
            $dataAtual->modify('-1 day')
        );
    }

    public function dataReferencia(): string
    {
        return $this->inicio->format('Y-m-d');
    }

    public function inicioParaBanco(): string
    {
        return $this->inicio->format('Y-m-d H:i:s');
    }

    public function fimExclusivoParaBanco(): string
    {
        return $this->fimExclusivo->format('Y-m-d H:i:s');
    }
}