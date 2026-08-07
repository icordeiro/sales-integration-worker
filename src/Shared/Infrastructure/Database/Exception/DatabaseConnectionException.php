<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Exception;

use PDOException;
use RuntimeException;

final class DatabaseConnectionException extends RuntimeException
{
    public static function couldNotConnect(
        PDOException $exception
    ): self {
        return new self(
            message: 'Não foi possível estabelecer conexão com o banco de dados do cliente.',
            code: (int) $exception->getCode(),
            previous: $exception
        );
    }

    public static function alreadyInitializedWithAnotherConfiguration(): self
    {
        return new self(
            'A conexão singleton já foi inicializada com outra configuração.'
        );
    }
}