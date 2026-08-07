<?php

declare(strict_types=1);

use App\Modules\Exportacao\Vendas\Application\DTO\PeriodoExportacao;
use App\Modules\Exportacao\Vendas\Application\Services\ProcessarExportacaoVendasService;
use App\Modules\Exportacao\Vendas\Domain\Exception\ExportacaoNormalJaExistenteException;
use App\Shared\Infrastructure\Environment\Environment;
use App\Shared\Infrastructure\Lock\FileProcessLock;

function main(): int
{
    $projectRoot = dirname(__DIR__);

    $services = require $projectRoot
        . '/config/sftp-services.php';

    /** @var Environment $environment */
    $environment =
        $services['environment'];

    /** @var ProcessarExportacaoVendasService $service */
    $service =
        $services['processar_exportacao_vendas_service'];

    /*
    |--------------------------------------------------------------------------
    | Lock exclusivo
    |--------------------------------------------------------------------------
    |
    | Impede duas instâncias simultâneas deste comando.
    |
    | Funciona tanto no Windows quanto no Linux.
    |
    */

    $lock = new FileProcessLock(
        $projectRoot
        . DIRECTORY_SEPARATOR
        . 'storage'
        . DIRECTORY_SEPARATOR
        . 'runtime'
        . DIRECTORY_SEPARATOR
        . 'export-sales-daily.lock'
    );

    try {
        /*
         * A execução automática espera por uma eventual operação manual.
         * Isso evita perder o movimento D-1 se um reprocessamento estiver
         * terminando exatamente no horário do agendamento.
         */
        $lockAcquired = $lock->acquireWithWait(
            timeoutSeconds: 900,
            pollIntervalMilliseconds: 500
        );
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            PHP_EOL
            . 'Não foi possível inicializar o lock da rotina.'
            . PHP_EOL
            . 'Erro: '
            . $exception->getMessage()
            . PHP_EOL
            . PHP_EOL
        );

        return 1;
    }

    if (!$lockAcquired) {
        echo PHP_EOL;
        echo 'Exportação diária NielsenIQ'
            . PHP_EOL;

        echo str_repeat('-', 70)
            . PHP_EOL;

        echo 'Outra operação de exportação permaneceu em andamento por mais de 15 minutos.'
            . PHP_EOL;

        echo 'A rotina diária não será iniciada nesta tentativa.'
            . PHP_EOL;

        echo str_repeat('-', 70)
            . PHP_EOL;

        echo PHP_EOL;

        /*
         * Retornamos falha para deixar o problema visível no agendador.
         * Em condições normais a rotina espera até 15 minutos pelo lock.
         */
        return 1;
    }

    try {
        $timezone = new DateTimeZone(
            $environment->string(
                'APP_TIMEZONE'
            )
        );

        $now = new DateTimeImmutable(
            'now',
            $timezone
        );

        /*
        |--------------------------------------------------------------------------
        | Movimento
        |--------------------------------------------------------------------------
        |
        | A rotina diária processa sempre D-1.
        |
        */

        $movementDate = $now
            ->modify('-1 day')
            ->setTime(0, 0);

        $periodo = PeriodoExportacao::doDia(
            $movementDate
        );

        echo PHP_EOL;
        echo 'Exportação diária NielsenIQ'
            . PHP_EOL;

        echo str_repeat('-', 70)
            . PHP_EOL;

        echo 'Executado em: '
            . $now->format(
                'd/m/Y H:i:s'
            )
            . PHP_EOL;

        echo 'Movimento: '
            . $periodo->dataReferencia()
            . PHP_EOL;

        echo 'PID: '
            . getmypid()
            . PHP_EOL;

        echo str_repeat('-', 70)
            . PHP_EOL;

        echo PHP_EOL;

        try {
            $resultado = $service->execute(
                $periodo
            );

            echo 'Exportação concluída com sucesso.'
                . PHP_EOL;

            echo PHP_EOL;

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
                . $resultado
                    ->arquivo
                    ->sha256
                . PHP_EOL;

            echo 'Destino: '
                . $resultado
                    ->envio
                    ->remotePath
                . PHP_EOL;

            echo 'Tempo total: '
                . number_format(
                    $resultado
                        ->duracaoSegundos,
                    3,
                    ',',
                    '.'
                )
                . ' segundos'
                . PHP_EOL;

            echo PHP_EOL;

            echo str_repeat('-', 70)
                . PHP_EOL;

            echo 'Status final: CONCLUIDO'
                . PHP_EOL;

            echo str_repeat('-', 70)
                . PHP_EOL;

            echo PHP_EOL;

            return 0;
        } catch (
            ExportacaoNormalJaExistenteException $exception
        ) {
            /*
             * Movimento já processado não representa
             * falha da rotina automática.
             */

            echo 'Movimento já processado.'
                . PHP_EOL;

            echo 'Detalhe: '
                . $exception->getMessage()
                . PHP_EOL;

            echo PHP_EOL;

            echo str_repeat('-', 70)
                . PHP_EOL;

            echo 'Nenhuma nova exportação foi realizada.'
                . PHP_EOL;

            echo str_repeat('-', 70)
                . PHP_EOL;

            echo PHP_EOL;

            return 0;
        } catch (Throwable $exception) {
            fwrite(
                STDERR,
                PHP_EOL
                . 'Falha na exportação diária.'
                . PHP_EOL
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

            return 1;
        }
    } finally {
        /*
         * Liberação explícita.
         *
         * O destrutor também protege contra
         * encerramentos normais inesperados.
         */
        $lock->release();
    }
}

exit(
    main()
);