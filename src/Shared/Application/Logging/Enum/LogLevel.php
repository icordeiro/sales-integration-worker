<?php

declare(strict_types=1);

namespace App\Shared\Application\Logging\Enum;

use InvalidArgumentException;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    public static function fromString(string $value, self $default = self::INFO): self
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return $default;
        }

        return self::tryFrom($normalized)
            ?? throw new InvalidArgumentException(sprintf('Nível de log inválido: %s.', $value));
    }

    public function priority(): int
    {
        return match ($this) {
            self::DEBUG => 10,
            self::INFO => 20,
            self::WARNING => 30,
            self::ERROR => 40,
        };
    }
}
