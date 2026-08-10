<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Health;

use App\Shared\Application\Health\DTO\HealthCheckDTO;
use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Application\Health\Services\HealthCheckService;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeHealthProbe;

final class HealthCheckServiceTest extends TestCase
{
    public function testRetornaHealthyQuandoTodasAsVerificacoesEstaoOk(): void
    {
        $service = new HealthCheckService([
            new FakeHealthProbe(
                'php',
                new HealthCheckDTO(
                    HealthStatus::OK,
                    'PHP operacional.'
                )
            ),
            new FakeHealthProbe(
                'sqlite',
                new HealthCheckDTO(
                    HealthStatus::OK,
                    'SQLite disponível.'
                )
            ),
        ]);

        $report = $service->execute();

        self::assertSame('healthy', $report->status);
        self::assertSame(HealthStatus::OK, $report->checks['php']->status);
        self::assertSame(HealthStatus::OK, $report->checks['sqlite']->status);
    }

    public function testRetornaDegradedQuandoExisteWarning(): void
    {
        $service = new HealthCheckService([
            new FakeHealthProbe(
                'exportacao',
                new HealthCheckDTO(
                    HealthStatus::WARNING,
                    'A última execução falhou.'
                )
            ),
        ]);

        self::assertSame(
            'degraded',
            $service->execute()->status
        );
    }

    public function testRetornaUnhealthyQuandoExisteErro(): void
    {
        $service = new HealthCheckService([
            new FakeHealthProbe(
                'postgresql',
                new HealthCheckDTO(
                    HealthStatus::ERROR,
                    'PostgreSQL indisponível.'
                )
            ),
        ]);

        self::assertSame(
            'unhealthy',
            $service->execute()->status
        );
    }

    public function testIsolaFalhaInesperadaDeUmaProbe(): void
    {
        $service = new HealthCheckService([
            new FakeHealthProbe(
                probeKey: 'storage',
                throwException: true
            ),
        ]);

        $report = $service->execute();

        self::assertSame('unhealthy', $report->status);
        self::assertSame(
            HealthStatus::ERROR,
            $report->checks['storage']->status
        );
        self::assertSame(
            'Não foi possível executar esta verificação.',
            $report->checks['storage']->message
        );
    }
}
