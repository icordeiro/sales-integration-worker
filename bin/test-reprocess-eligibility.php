<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoExecucaoRepository;
use App\Modules\Exportacao\Vendas\Domain\Enum\StatusExportacao;

$services = require dirname(__DIR__)
    . '/config/services.php';

/** @var ExportacaoExecucaoRepository $repository */
$repository =
    $services['exportacao_execucao_repository'];

$execucaoId = isset($argv[1])
    ? (int) $argv[1]
    : 0;

if ($execucaoId <= 0) {
    fwrite(
        STDERR,
        'Informe o ID da execução.'
        . PHP_EOL
    );

    exit(1);
}

$execucao = $repository->buscarPorId(
    $execucaoId
);

if ($execucao === null) {
    fwrite(
        STDERR,
        'Execução não encontrada.'
        . PHP_EOL
    );

    exit(1);
}

$elegivel =
    $execucao->status
        === StatusExportacao::CONCLUIDO
    && $execucao->sha256 !== null;

echo PHP_EOL;
echo 'Elegibilidade para reprocessamento'
    . PHP_EOL;

echo str_repeat('-', 60)
    . PHP_EOL;

echo 'Execução: #'
    . $execucao->id
    . PHP_EOL;

echo 'Movimento: '
    . $execucao->dataMovimento
    . PHP_EOL;

echo 'Tipo original: '
    . $execucao->tipoExecucao->value
    . PHP_EOL;

echo 'Status: '
    . $execucao->status->value
    . PHP_EOL;

echo 'Arquivo: '
    . ($execucao->arquivoNome ?? '-')
    . PHP_EOL;

echo 'SHA-256: '
    . ($execucao->sha256 ?? '-')
    . PHP_EOL;

echo 'Elegível: '
    . ($elegivel ? 'SIM' : 'NÃO')
    . PHP_EOL;

echo str_repeat('-', 60)
    . PHP_EOL;

echo PHP_EOL;

exit(
    $elegivel
        ? 0
        : 1
);