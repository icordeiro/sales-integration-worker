<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Environment;

final readonly class Environment
{
    /**
     * @param array<string, mixed> $variables
     */
    private function __construct(
        private array $variables
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_ENV);
    }

    public function string(string $key): string
    {
        $value = $this->value($key);

        if (!is_string($value) && !is_numeric($value)) {
            throw EnvironmentException::emptyVariable($key);
        }

        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw EnvironmentException::emptyVariable($key);
        }

        return $normalizedValue;
    }

    public function integer(string $key): int
    {
        $value = $this->value($key);

        $filteredValue = filter_var(
            $value,
            FILTER_VALIDATE_INT
        );

        if ($filteredValue === false) {
            throw EnvironmentException::invalidInteger($key, $value);
        }

        return $filteredValue;
    }

    public function boolean(string $key): bool
    {
        $value = $this->value($key);

        $filteredValue = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($filteredValue === null) {
            throw EnvironmentException::invalidBoolean($key, $value);
        }

        return $filteredValue;
    }

    public function optionalString(
        string $key,
        ?string $default = null
    ): ?string {
        if (!array_key_exists($key, $this->variables)) {
            return $default;
        }

        $value = trim((string) $this->variables[$key]);

        return $value !== '' ? $value : $default;
    }

    private function value(string $key): mixed
    {
        if (!array_key_exists($key, $this->variables)) {
            throw EnvironmentException::variableNotFound($key);
        }

        return $this->variables[$key];
    }
}