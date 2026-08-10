<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Application\Logging\Contracts\Logger;
use App\Shared\Application\Logging\Enum\LogLevel;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Stringable;
use Throwable;

final class MonthlyJsonFileLogger implements Logger
{
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    public function __construct(
        private readonly string $directory,
        private readonly DateTimeZone $timezone,
        private readonly LogLevel $minimumLevel = LogLevel::INFO,
        private readonly string $channel = 'siw',
        ?Closure $clock = null
    ) {
        $this->clock = $clock ?? fn (): DateTimeImmutable => new DateTimeImmutable('now', $this->timezone);
    }

    public function log(LogLevel $level, string $event, string $message, array $context = []): void
    {
        if ($level->priority() < $this->minimumLevel->priority()) {
            return;
        }

        $now = ($this->clock)()->setTimezone($this->timezone);
        $payload = [
            'timestamp' => $now->format(DateTimeInterface::ATOM),
            'level' => $level->value,
            'channel' => $this->normalizedChannel(),
            'event' => $this->normalizeEvent($event),
            'message' => trim($message),
            'context' => $this->normalizeContext($context),
        ];

        try {
            $line = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível serializar a entrada de log.', 0, $exception);
        }

        $this->ensureDirectory();
        $written = file_put_contents($this->filePath($now), $line . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Não foi possível gravar o log estruturado.');
        }
    }

    public function info(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $event, $message, $context);
    }

    public function warning(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $event, $message, $context);
    }

    public function error(string $event, string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $event, $message, $context);
    }

    private function normalizedChannel(): string
    {
        $normalized = strtolower(trim($this->channel));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized);
        $normalized = trim((string) $normalized, '-');
        return $normalized !== '' ? $normalized : 'siw';
    }

    private function normalizeEvent(string $event): string
    {
        $normalized = strtolower(trim($event));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '_', $normalized);
        return trim((string) $normalized, '_');
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Não foi possível criar o diretório de logs: %s', $this->directory));
        }
    }

    private function filePath(DateTimeImmutable $date): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $this->normalizedChannel()
            . '-'
            . $date->format('Y-m')
            . '.log';
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[(string) $key] = $this->normalizeValue($value);
        }
        ksort($normalized);
        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }
        if ($value instanceof Throwable) {
            return ['class' => $value::class, 'message' => $value->getMessage()];
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }
            return $normalized;
        }
        return get_debug_type($value);
    }
}
