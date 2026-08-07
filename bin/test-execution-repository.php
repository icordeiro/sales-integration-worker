<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;

$services = require dirname(__DIR__)
    . '/config/services.php';

/** @var ExportacaoExecucaoRepository $repository */
$repository =
    $services['exportacao_execucao_repository'];

try {
    echo PHP_EOL;
    echo 'Teste do histórico de execução' . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    $id = $repository->iniciar(
        dataMovimento: '2026-08-07'
    );

    echo 'Execução criada: #'
        . $id
        . PHP_EOL;

    $repository->registrarStatus(
        execucaoId: $id,
        status: StatusExportacao::CONSULTANDO,
        mensagem: 'Teste de consulta.'
    );

    echo 'Status CONSULTANDO registrado.'
        . PHP_EOL;

    $repository->registrarStatus(
        execucaoId: $id,
        status: StatusExportacao::GERANDO_ARQUIVO,
        mensagem: 'Teste de geração.'
    );

    echo 'Status GERANDO_ARQUIVO registrado.'
        . PHP_EOL;

    echo str_repeat('-', 60) . PHP_EOL;
    echo 'Teste concluído com sucesso.' . PHP_EOL;
    echo PHP_EOL;

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha no teste.'
        . PHP_EOL
        . 'Erro: '
        . $exception->getMessage()
        . PHP_EOL
    );

    if (
        $exception->getPrevious()
        instanceof Throwable
    ) {
        fwrite(
            STDERR,
            'Erro técnico: '
            . $exception
                ->getPrevious()
                ->getMessage()
            . PHP_EOL
        );
    }

    fwrite(
        STDERR,
        PHP_EOL
    );

    exit(1);
}