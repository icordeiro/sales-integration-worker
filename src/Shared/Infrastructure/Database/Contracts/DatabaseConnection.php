<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Contracts;

use PDO;

interface DatabaseConnection
{
    public function connection(): PDO;

    public function isConnected(): bool;
}