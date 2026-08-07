<?php

declare(strict_types=1);

use App\Shared\Application\Contracts\RemoteFileStorage;

$services = require dirname(__DIR__)
    . '/config/sftp-services.php';

/** @var RemoteFileStorage $remoteStorage */
$remoteStorage = $services['remote_file_storage'];

try {
    echo PHP_EOL;
    echo 'Testando conexão SFTP...' . PHP_EOL;

    $remoteStorage->testConnection();

    echo PHP_EOL;
    echo 'Conexão SFTP realizada com sucesso.' . PHP_EOL;
    echo 'Autenticação: OK' . PHP_EOL;
    echo 'Fingerprint: OK' . PHP_EOL;
    echo 'Diretório remoto: OK' . PHP_EOL;
    echo PHP_EOL;

    exit(0);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha no teste SFTP.'
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