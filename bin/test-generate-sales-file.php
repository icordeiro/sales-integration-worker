<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\Services\GerarArquivoVendasService;

$services = require dirname(__DIR__)
    . '/config/services.php';

/** @var GerarArquivoVendasService $service */
$service = $services['gerar_arquivo_vendas_service'];

$timezone = new \DateTimeZone(
    'America/Fortaleza'
);

$dateArgument = $argv[1] ?? null;

if ($dateArgument === null) {
    $dateArgument = new \DateTimeImmutable(
        'yesterday',
        $timezone
    )->format('Y-m-d');
}

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
        . 'Data inválida. Utilize Y-m-d.'
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$periodo = PeriodoExportacao::doDia(
    $date
);

$startedAt = microtime(true);

try {
    echo PHP_EOL;
    echo 'Gerando arquivo de vendas...' . PHP_EOL;
    echo 'Data: '
        . $periodo->dataReferencia()
        . PHP_EOL;
    echo PHP_EOL;

    $arquivo = $service->execute(
        $periodo
    );

    $duration = microtime(true) - $startedAt;

    echo 'Arquivo gerado com sucesso.' . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    echo 'Nome: '
        . $arquivo->nome
        . PHP_EOL;

    echo 'Caminho: '
        . $arquivo->caminho
        . PHP_EOL;

    echo 'Data de referência: '
        . $arquivo->dataReferencia
        . PHP_EOL;

    echo 'Registros: '
        . number_format(
            $arquivo->quantidadeRegistros,
            0,
            ',',
            '.'
        )
        . PHP_EOL;

    echo 'Tamanho: '
        . number_format(
            $arquivo->tamanhoBytes,
            0,
            ',',
            '.'
        )
        . ' bytes'
        . PHP_EOL;

    echo 'SHA-256: '
        . $arquivo->sha256
        . PHP_EOL;

    echo 'Tempo total: '
        . number_format(
            $duration,
            3,
            ',',
            '.'
        )
        . ' segundos'
        . PHP_EOL;

    echo str_repeat('-', 60) . PHP_EOL;
    echo PHP_EOL;

    echo 'Primeiras linhas:' . PHP_EOL;
    echo PHP_EOL;

    $handle = fopen(
        $arquivo->caminho,
        'rb'
    );

    if ($handle !== false) {
        for ($line = 0; $line < 10; $line++) {
            $content = fgets($handle);

            if ($content === false) {
                break;
            }

            echo $content;
        }

        fclose($handle);
    }

    echo PHP_EOL;

    exit(0);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha ao gerar o arquivo.'
        . PHP_EOL
        . 'Erro: '
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