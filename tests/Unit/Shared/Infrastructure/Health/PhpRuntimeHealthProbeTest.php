<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Application\Health\Enum\HealthStatus;
use App\Shared\Infrastructure\Health\PhpRuntimeHealthProbe;
use PHPUnit\Framework\TestCase;

final class PhpRuntimeHealthProbeTest extends TestCase
{
    public function testRuntimeDeclaradoPeloProjetoEhConsideradoOperacional(): void
    {
        $result = (new PhpRuntimeHealthProbe())->check();

        self::assertSame(HealthStatus::OK, $result->status);
        self::assertSame([], $result->details['missing_extensions']);
        self::assertTrue(
            version_compare(
                (string) $result->details['version'],
                (string) $result->details['minimum_version'],
                '>='
            )
        );
    }
}
