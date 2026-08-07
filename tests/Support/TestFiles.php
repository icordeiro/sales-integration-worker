<?php

declare(strict_types=1);

namespace Tests\Support;

final class TestFiles
{
    public static function temporaryDirectory(string $prefix): string
    {
        $directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . $prefix
            . '-'
            . bin2hex(random_bytes(8));

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Não foi possível criar diretório temporário de teste.');
        }

        return $directory;
    }

    public static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
