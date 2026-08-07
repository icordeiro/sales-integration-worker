<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\DTO\VendaExportacaoDTO;
use App\Modules\Exportacao\Vendas\Application\Services\ConsultarVendasService;

$services = require dirname(__DIR__) . '/config/services.php';

/** @var ConsultarVendasService $consultarVendasService */
$consultarVendasService = $services['consultar_vendas_service'];

$timezone = new \DateTimeZone('America/Fortaleza');

$dateArgument = $argv[1]
    ?? new \DateTimeImmutable('yesterday', $timezone)
        ->format('Y-m-d');

$limitArgument = $argv[2] ?? '20';

$limit = filter_var(
    $limitArgument,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
            'default' => 20,
        ],
    ]
);

$date = \DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $dateArgument,
    $timezone
);

if (
    !$date instanceof \DateTimeImmutable
    || $date->format('Y-m-d') !== $dateArgument
) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Data inválida. Utilize o formato Y-m-d.'
        . PHP_EOL
        . 'Exemplo: php bin/test-sales-query.php 2026-08-04'
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$periodo = PeriodoExportacao::doDia(
    $date
);

$startedAt = microtime(true);
$totalRecords = 0;
$displayedRecords = 0;

try {
    echo PHP_EOL;
    echo 'Consultando vendas de: '
        . $periodo->dataReferencia()
        . PHP_EOL;
    echo 'Período inicial: '
        . $periodo->inicioParaBanco()
        . PHP_EOL;
    echo 'Período final exclusivo: '
        . $periodo->fimExclusivoParaBanco()
        . PHP_EOL;
    echo PHP_EOL;

    echo 'STORE|BARCODE|DESCRIPTION|DAY|UNIT_SALES|VALUE_SALES|PROMO';
    echo PHP_EOL;

    foreach (
        $consultarVendasService->execute($periodo)
        as $sale
    ) {
        if (!$sale instanceof VendaExportacaoDTO) {
            throw new \RuntimeException(
                'O serviço retornou um registro inválido.'
            );
        }

        $totalRecords++;

        if ($displayedRecords >= $limit) {
            continue;
        }

        echo implode(
            '|',
            [
                (string) $sale->store,
                $sale->barcode,
                $sale->description,
                $sale->day,
                $sale->unitSales,
                $sale->valueSales,
                $sale->promo,
            ]
        );

        echo PHP_EOL;

        $displayedRecords++;
    }

    $duration = microtime(true) - $startedAt;

    echo PHP_EOL;
    echo '----------------------------------------' . PHP_EOL;
    echo 'Data de referência: '
        . $periodo->dataReferencia()
        . PHP_EOL;
    echo 'Registros encontrados: '
        . number_format($totalRecords, 0, ',', '.')
        . PHP_EOL;
    echo 'Registros exibidos: '
        . number_format($displayedRecords, 0, ',', '.')
        . PHP_EOL;
    echo 'Tempo da consulta: '
        . number_format($duration, 3, ',', '.')
        . ' segundos'
        . PHP_EOL;
    echo PHP_EOL;

    exit(0);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha ao consultar as vendas.'
        . PHP_EOL
        . 'Erro da aplicação: '
        . $exception->getMessage()
        . PHP_EOL
    );

    $previous = $exception->getPrevious();

    if ($previous instanceof \Throwable) {
        fwrite(
            STDERR,
            'Erro técnico: '
            . $previous->getMessage()
            . PHP_EOL
        );
    }

    fwrite(STDERR, PHP_EOL);

    exit(1);
}