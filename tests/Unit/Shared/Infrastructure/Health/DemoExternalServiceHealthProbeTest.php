<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\DemoExternalServiceHealthProbe;
use PHPUnit\Framework\TestCase;

final class DemoExternalServiceHealthProbeTest extends TestCase
{
    public function testMarcaServicoExternoComoBloqueadoSemRealizarConexao(): void
    {
        $probe = new DemoExternalServiceHealthProbe(
            probeKey: 'sftp',
            serviceLabel: 'SFTP'
        );

        $result = $probe->check();

        self::assertSame('sftp', $probe->key());
        self::assertSame(HealthStatus::OK, $result->status);
        self::assertTrue($result->details['external_access_blocked']);
        self::assertFalse($result->details['connection_tested']);
        self::assertSame('demo', $result->details['mode']);
    }
}
