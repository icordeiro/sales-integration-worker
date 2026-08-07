<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Lock\FileProcessLock;

require dirname(__DIR__)
    . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);

$lock = new FileProcessLock(
    $projectRoot
    . DIRECTORY_SEPARATOR
    . 'storage'
    . DIRECTORY_SEPARATOR
    . 'runtime'
    . DIRECTORY_SEPARATOR
    . 'test-process.lock'
);

echo PHP_EOL;
echo 'Teste de lock de processo'
    . PHP_EOL;

echo str_repeat('-', 60)
    . PHP_EOL;

if (!$lock->acquire()) {
    echo 'LOCK NÃO OBTIDO'
        . PHP_EOL;

    echo 'Outra instância já está executando.'
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
}

echo 'LOCK OBTIDO'
    . PHP_EOL;

echo 'PID: '
    . getmypid()
    . PHP_EOL;

echo PHP_EOL;

echo 'Mantendo o lock por 20 segundos...'
    . PHP_EOL;

sleep(20);

$lock->release();

echo 'LOCK LIBERADO'
    . PHP_EOL;

echo str_repeat('-', 60)
    . PHP_EOL;

echo PHP_EOL;

exit(0);