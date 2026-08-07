<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\Contracts\ExportacaoDashboardQuery;

$services = require dirname(__DIR__)
    . '/config/services.php';

/** @var ExportacaoDashboardQuery $query */
$query =
    $services['exportacao_dashboard_query'];

try {
    echo PHP_EOL;
    echo 'Teste da leitura do dashboard'
        . PHP_EOL;

    echo str_repeat('-', 70)
        . PHP_EOL;

    /*
     * Resumo
     */
    $resumo = $query->resumo();

    echo PHP_EOL;
    echo 'RESUMO'
        . PHP_EOL;

    echo str_repeat('-', 70)
        . PHP_EOL;

    if ($resumo->ultimaExecucao !== null) {
        echo 'Última execução: #'
            . $resumo->ultimaExecucao->id
            . PHP_EOL;

        echo 'Movimento: '
            . $resumo->ultimaExecucao->dataMovimento
            . PHP_EOL;

        echo 'Status: '
            . $resumo->ultimaExecucao->status->value
            . PHP_EOL;
    } else {
        echo 'Última execução: nenhuma'
            . PHP_EOL;
    }

    echo 'Em andamento: '
        . $resumo->execucoesEmAndamento
        . PHP_EOL;

    echo 'Falhas últimos 7 dias: '
        . $resumo->falhasUltimosSeteDias
        . PHP_EOL;

    /*
     * Histórico
     */
    echo PHP_EOL;
    echo 'HISTÓRICO'
        . PHP_EOL;

    echo str_repeat('-', 70)
        . PHP_EOL;

    $historico =
        $query->listarRecentes(10);

    foreach ($historico as $execucao) {
        echo sprintf(
            '#%d | %s | %-16s | %s',
            $execucao->id,
            $execucao->dataMovimento,
            $execucao->tipoExecucao->value,
            $execucao->status->value
        );

        echo PHP_EOL;
    }

    /*
     * Detalhe
     */
    if ($historico !== []) {
        $id =
            $historico[0]->id;

        $detalhe =
            $query->buscarDetalhe(
                $id
            );

        echo PHP_EOL;
        echo 'DETALHE DA EXECUÇÃO #'
            . $id
            . PHP_EOL;

        echo str_repeat('-', 70)
            . PHP_EOL;

        if ($detalhe !== null) {
            echo 'Arquivo: '
                . (
                    $detalhe
                        ->execucao
                        ->arquivoNome
                    ?? '-'
                )
                . PHP_EOL;

            echo 'SHA-256: '
                . (
                    $detalhe->sha256
                    ?? '-'
                )
                . PHP_EOL;

            echo 'Arquivo local disponível: '
                . ($detalhe->arquivoLocalDisponivel ? 'SIM' : 'NÃO')
                . PHP_EOL;

            echo 'Pode reenviar: '
                . ($detalhe->podeReenviar ? 'SIM' : 'NÃO')
                . PHP_EOL;

            echo 'Pode reprocessar: '
                . ($detalhe->podeReprocessar ? 'SIM' : 'NÃO')
                . PHP_EOL;

            if ($detalhe->comparacaoOrigem !== null) {
                echo 'Origem: #'
                    . $detalhe->comparacaoOrigem->execucaoOrigemId
                    . PHP_EOL;

                echo 'Diferença de registros: '
                    . (
                        $detalhe->comparacaoOrigem->diferencaRegistros
                        ?? 0
                    )
                    . PHP_EOL;

                echo 'Conteúdo alterado: '
                    . match ($detalhe->comparacaoOrigem->conteudoAlterado) {
                        true => 'SIM',
                        false => 'NÃO',
                        null => 'INDETERMINADO',
                    }
                    . PHP_EOL;
            }

            echo 'Eventos: '
                . count(
                    $detalhe->eventos
                )
                . PHP_EOL;

            foreach (
                $detalhe->eventos
                as $evento
            ) {
                echo '  - '
                    . $evento->status->value
                    . ' | '
                    . $evento->ocorridoEm
                    . PHP_EOL;
            }
        }
    }

    echo PHP_EOL;
    echo str_repeat('-', 70)
        . PHP_EOL;

    echo 'Leitura concluída com sucesso.'
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        PHP_EOL
        . 'Falha na leitura do dashboard.'
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