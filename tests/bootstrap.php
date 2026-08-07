<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$composerAutoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_file($composerAutoload)) {
    require $composerAutoload;
}

/*
 * Os testes usam o namespace Tests\ mesmo que o projeto ainda não possua
 * autoload-dev no composer.json. Assim a suíte funciona sem exigir uma
 * alteração adicional no PSR-4 da aplicação.
 */
spl_autoload_register(
    static function (string $class) use ($projectRoot): void {
        $prefix = 'Tests\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
            . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
);

/*
 * Fallback do namespace App\ para inspeções locais quando vendor/ ainda não
 * existe. No uso normal e no CI, o autoload do Composer é o responsável por
 * carregar o código da aplicação.
 */
if (!is_file($composerAutoload)) {
    spl_autoload_register(
        static function (string $class) use ($projectRoot): void {
            $prefix = 'App\\';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relativeClass = substr($class, strlen($prefix));
            $path = $projectRoot
                . DIRECTORY_SEPARATOR
                . 'src'
                . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
                . '.php';

            if (is_file($path)) {
                require $path;
            }
        }
    );
}
