<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Environment;

use RuntimeException;

final class EnvironmentException extends RuntimeException
{
    public static function variableNotFound(string $key): self
    {
        return new self(
            sprintf('A variável de ambiente "%s" não foi definida.', $key)
        );
    }

    public static function emptyVariable(string $key): self
    {
        return new self(
            sprintf('A variável de ambiente "%s" não pode estar vazia.', $key)
        );
    }

    public static function invalidInteger(string $key, mixed $value): self
    {
        return new self(
            sprintf(
                'A variável de ambiente "%s" deve ser um número inteiro válido. Valor recebido: "%s".',
                $key,
                is_scalar($value) ? (string) $value : get_debug_type($value)
            )
        );
    }

    public static function invalidBoolean(string $key, mixed $value): self
    {
        return new self(
            sprintf(
                'A variável de ambiente "%s" deve ser um booleano válido. Valor recebido: "%s".',
                $key,
                is_scalar($value) ? (string) $value : get_debug_type($value)
            )
        );
    }
}