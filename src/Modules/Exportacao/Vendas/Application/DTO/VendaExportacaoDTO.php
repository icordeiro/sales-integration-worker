<?php

declare(strict_types=1);

namespace App\Modules\Exportacao\Vendas\Application\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class VendaExportacaoDTO
{
    private const PROMO_SIM = 'Y';
    private const PROMO_NAO = 'N';

    public function __construct(
        public int $store,
        public string $barcode,
        public string $description,
        public string $day,
        public string $unitSales,
        public string $valueSales,
        public string $promo
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        self::assertRequiredColumns($row);

        return new self(
            store: (int) $row['store'],
            barcode: trim((string) $row['barcode']),
            description: trim((string) $row['description']),
            day: self::normalizeDay($row['day']),
            unitSales: self::normalizeDecimal(
                $row['unit_sales'],
                'unit_sales'
            ),
            valueSales: self::normalizeDecimal(
                $row['value_sales'],
                'value_sales'
            ),
            promo: strtoupper(trim((string) $row['promo']))
        );
    }

    /**
     * @return array{
     *     store: int,
     *     barcode: string,
     *     description: string,
     *     day: string,
     *     unit_sales: string,
     *     value_sales: string,
     *     promo: string
     * }
     */
    public function toArray(): array
    {
        return [
            'store' => $this->store,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'day' => $this->day,
            'unit_sales' => $this->unitSales,
            'value_sales' => $this->valueSales,
            'promo' => $this->promo,
        ];
    }

    private function validate(): void
    {
        if ($this->store <= 0) {
            throw new InvalidArgumentException(
                'O código da loja deve ser maior que zero.'
            );
        }

        if ($this->barcode === '') {
            throw new InvalidArgumentException(
                'O código de barras não pode estar vazio.'
            );
        }

        if ($this->description === '') {
            throw new InvalidArgumentException(
                'A descrição do produto não pode estar vazia.'
            );
        }

        if (!in_array(
            $this->promo,
            [self::PROMO_SIM, self::PROMO_NAO],
            true
        )) {
            throw new InvalidArgumentException(
                'O indicador de promoção deve ser Y ou N.'
            );
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function assertRequiredColumns(array $row): void
    {
        $requiredColumns = [
            'store',
            'barcode',
            'description',
            'day',
            'unit_sales',
            'value_sales',
            'promo',
        ];

        foreach ($requiredColumns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'A coluna obrigatória "%s" não foi retornada pela consulta.',
                        $column
                    )
                );
            }
        }
    }

    private static function normalizeDay(mixed $value): string
    {
        $day = trim((string) $value);

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $day
        );

        if (
            !$date instanceof DateTimeImmutable
            || $date->format('Y-m-d') !== $day
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'A data "%s" não está no formato Y-m-d.',
                    $day
                )
            );
        }

        return $day;
    }

    private static function normalizeDecimal(
        mixed $value,
        string $column
    ): string {
        $decimal = trim((string) $value);

        if (
            $decimal === ''
            || preg_match('/^-?\d+(?:\.\d+)?$/', $decimal) !== 1
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'A coluna "%s" não contém um decimal válido.',
                    $column
                )
            );
        }

        return $decimal;
    }
}