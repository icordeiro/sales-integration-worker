<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;

$services = require dirname(__DIR__)
    . '/config/sftp-services.php';

/** @var ProcessarExportacaoVendasService $service */
$service =
    $services['processar_exportacao_vendas_service'];

$timezone = new DateTimeZone(
    'America/Fortaleza'
);

$dateArgument = $argv[1] ?? null;

if ($dateArgument === null) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Informe a data do movimento.'
        . PHP_EOL
        . PHP_EOL
        . 'Exemplo:'
        . PHP_EOL
        . 'php test-process-sales-export.php 2026-08-07'
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$date = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $dateArgument,
    $timezone
);

if (
    !$date instanceof DateTimeImmutable
    || $date->format('Y-m-d') !== $dateArgument
) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Data inválida. Utilize Y-m-d.'
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$periodo = PeriodoExportacao::doDia(
    $date
);

try {
    echo PHP_EOL;
    echo 'Processamento completo da exportação'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo 'Movimento: '
        . $periodo->dataReferencia()
        . PHP_EOL;

    echo PHP_EOL;

    $resultado = $service->execute(
        $periodo
    );

    echo PHP_EOL;
    echo 'Exportação concluída com sucesso.'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo 'Execução: #'
        . $resultado->execucaoId
        . PHP_EOL;

    echo 'Arquivo: '
        . $resultado->arquivo->nome
        . PHP_EOL;

    echo 'Registros: '
        . number_format(
            $resultado
                ->arquivo
                ->quantidadeRegistros,
            0,
            ',',
            '.'
        )
        . PHP_EOL;

    echo 'Tamanho: '
        . number_format(
            $resultado
                ->arquivo
                ->tamanhoBytes,
            0,
            ',',
            '.'
        )
        . ' bytes'
        . PHP_EOL;

    echo 'SHA-256: '
        . $resultado->arquivo->sha256
        . PHP_EOL;

    echo 'Destino: '
        . $resultado->envio->remotePath
        . PHP_EOL;

    echo 'Tempo total: '
        . number_format(
            $resultado->duracaoSegundos,
            3,
            ',',
            '.'
        )
        . ' segundos'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha na exportação.'
        . PHP_EOL
        . 'Erro: '
        . $exception->getMessage()
        . PHP_EOL
    );

    $previous =
        $exception->getPrevious();

    if ($previous instanceof Throwable) {
        fwrite(
            STDERR,
            'Erro técnico: '
            . $previous->getMessage()
            . PHP_EOL
        );
    }

    fwrite(
        STDERR,
        PHP_EOL
    );

    exit(1);
}