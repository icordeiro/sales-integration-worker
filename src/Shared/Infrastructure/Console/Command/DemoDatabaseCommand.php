<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console\Command;

use App\Shared\Application\Console\Contracts\ConsoleCommand;
use App\Shared\Infrastructure\Console\ConsoleOutput;
use App\Shared\Infrastructure\Database\Config\PortalSqliteConfig;
use App\Shared\Infrastructure\Demo\DemoDatabaseSeeder;
use DateTimeZone;
use Dotenv\Dotenv;
use Throwable;

final readonly class DemoDatabaseCommand implements ConsoleCommand
{
    public function __construct(
        private string $commandName,
        private string $commandDescription,
        private string $projectRoot,
        private bool $reset
    ) {
    }

    public function name(): string
    {
        return $this->commandName;
    }

    public function description(): string
    {
        return $this->commandDescription;
    }

    public function execute(
        array $arguments,
        ConsoleOutput $output
    ): int {
        unset($arguments);

        try {
            Dotenv::createImmutable($this->projectRoot)
                ->safeLoad();

            $configuredPath = trim(
                (string) (
                    $_ENV['DEMO_DB_PATH']
                    ?? 'storage/database/siw-demo.sqlite'
                )
            );

            $config = PortalSqliteConfig::fromConfiguredPath(
                configuredPath: $configuredPath,
                projectRoot: $this->projectRoot
            );

            $timezone = new DateTimeZone(
                trim((string) ($_ENV['APP_TIMEZONE'] ?? 'UTC'))
                ?: 'UTC'
            );

            $seeder = new DemoDatabaseSeeder(
                databasePath: $config->databasePath,
                migrationsDirectory: $this->projectRoot
                    . DIRECTORY_SEPARATOR
                    . 'database'
                    . DIRECTORY_SEPARATOR
                    . 'migrations',
                exportsDirectory: $this->projectRoot
                    . DIRECTORY_SEPARATOR
                    . 'storage'
                    . DIRECTORY_SEPARATOR
                    . 'exports'
                    . DIRECTORY_SEPARATOR
                    . 'demo',
                timezone: $timezone
            );

            $result = $seeder->seed(
                reset: $this->reset
            );

            $output->line(
                $this->reset
                    ? 'SIW - Demo reset concluído'
                    : 'SIW - Demo dataset disponível'
            );
            $output->line(str_repeat('=', 72));
            $output->line('Execuções: ' . $result['executions']);
            $output->line('Arquivos demo: ' . $result['files']);
            $output->line('Banco: ' . $this->relativePath($result['database_path']));
            $output->line('');
            $output->line('Nenhum acesso ao ERP ou SFTP foi realizado.');
            $output->line('Para visualizar o dataset, defina APP_DEMO=true no .env e suba o dashboard.');

            return 0;
        } catch (Throwable $exception) {
            $output->error('Não foi possível preparar o modo demonstração.');
            $output->error($exception->getMessage());

            return 1;
        }
    }

    private function relativePath(string $path): string
    {
        $root = rtrim($this->projectRoot, '/\\')
            . DIRECTORY_SEPARATOR;

        if (str_starts_with($path, $root)) {
            return str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($path, strlen($root))
            );
        }

        return $path;
    }
}
