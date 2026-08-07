<?php

declare(strict_types=1);

use App\Shared\Application\Contracts\RemoteFileStorage;

$services = require dirname(__DIR__)
    . '/config/sftp-services.php';

/** @var RemoteFileStorage $remoteStorage */
$remoteStorage = $services['remote_file_storage'];

$localPath = $argv[1] ?? null;

if (
    $localPath === null
    || trim($localPath) === ''
) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Informe o caminho do arquivo TXT.'
        . PHP_EOL
        . PHP_EOL
        . 'Exemplo:'
        . PHP_EOL
        . 'php test-sftp-upload.php '
        . '"..\storage\exports\2026\08\MV20260807_COMPANY.txt"'
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$resolvedPath = realpath(
    $localPath
);

if (
    $resolvedPath === false
    || !is_file($resolvedPath)
) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Arquivo não encontrado: '
        . $localPath
        . PHP_EOL
        . PHP_EOL
    );

    exit(1);
}

$fileName = basename(
    $resolvedPath
);

$startedAt = microtime(true);

try {
    echo PHP_EOL;
    echo 'Teste de envio SFTP' . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    echo 'Arquivo local: '
        . $resolvedPath
        . PHP_EOL;

    echo 'Nome remoto: '
        . $fileName
        . PHP_EOL;

    echo 'Destino: /DELIVERY'
        . PHP_EOL;

    echo PHP_EOL;
    echo 'Enviando...' . PHP_EOL;
    echo PHP_EOL;

    $result = $remoteStorage->uploadAtomically(
        localPath: $resolvedPath,
        remoteFileName: $fileName
    );

    $duration = microtime(true)
        - $startedAt;

    echo 'Arquivo enviado com sucesso.'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo 'Arquivo: '
        . $result->fileName
        . PHP_EOL;

    echo 'Caminho remoto: '
        . $result->remotePath
        . PHP_EOL;

    echo 'Tamanho: '
        . number_format(
            $result->sizeBytes,
            0,
            ',',
            '.'
        )
        . ' bytes'
        . PHP_EOL;

    echo 'Enviado em: '
        . $result->uploadedAt->format(
            'd/m/Y H:i:s'
        )
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

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha no envio SFTP.'
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

    fwrite(
        STDERR,
        PHP_EOL
    );

    exit(1);
}