<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Config;

use App\Shared\Infrastructure\Environment\Environment;
use InvalidArgumentException;

final readonly class PortalSqliteConfig
{
    public function __construct(
        public string $databasePath
    ) {
        if (trim($this->databasePath) === '') {
            throw new InvalidArgumentException(
                'O caminho do banco SQLite do portal não pode estar vazio.'
            );
        }
    }

    public static function fromEnvironment(
        Environment $environment,
        string $projectRoot,
        string $pathVariable = 'PORTAL_DB_PATH'
    ): self {
        return self::fromConfiguredPath(
            configuredPath: $environment->string($pathVariable),
            projectRoot: $projectRoot
        );
    }

    public static function fromConfiguredPath(
        string $configuredPath,
        string $projectRoot
    ): self {
        $databasePath = self::resolvePath(
            configuredPath: trim($configuredPath),
            projectRoot: $projectRoot
        );

        return new self(
            databasePath: $databasePath
        );
    }

    public function dsn(): string
    {
        return 'sqlite:' . $this->databasePath;
    }

    public function directory(): string
    {
        return dirname(
            $this->databasePath
        );
    }

    private static function resolvePath(
        string $configuredPath,
        string $projectRoot
    ): string {
        if (self::isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        $relativePath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $configuredPath
        );

        return rtrim(
            $projectRoot,
            '/\\'
        )
            . DIRECTORY_SEPARATOR
            . ltrim(
                $relativePath,
                '/\\'
            );
    }

    private static function isAbsolutePath(
        string $path
    ): bool {
        /*
         * Linux / Unix:
         *
         * /var/data/database.sqlite
         */
        if (str_starts_with($path, '/')) {
            return true;
        }

        /*
         * Windows:
         *
         * C:\dados\database.sqlite
         */
        if (
            preg_match(
                '/^[A-Za-z]:[\\\\\/]/',
                $path
            ) === 1
        ) {
            return true;
        }

        /*
         * UNC:
         *
         * \\servidor\diretorio
         *
         * Suportamos tecnicamente, embora não seja
         * recomendado colocar SQLite em compartilhamento.
         */
        return str_starts_with(
            $path,
            '\\\\'
        );
    }
}