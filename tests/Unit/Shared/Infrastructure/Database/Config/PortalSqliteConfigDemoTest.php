<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Database\Config;

use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use PHPUnit\Framework\TestCase;

final class PortalSqliteConfigDemoTest extends TestCase
{
    public function testResolveCaminhoRelativoConfiguradoParaDemo(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'siw-demo-root';

        $config = PortalSqliteConfig::fromConfiguredPath(
            configuredPath: 'storage/database/siw-demo.sqlite',
            projectRoot: $root
        );

        self::assertSame(
            $root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'siw-demo.sqlite',
            $config->databasePath
        );
    }
}
