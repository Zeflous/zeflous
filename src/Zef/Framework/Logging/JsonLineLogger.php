<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use Stringable;

/** Dependency-free PSR-3 logger that writes one structured JSON object per line. */
final readonly class JsonLineLogger implements LoggerInterface
{
    private const array LEVELS = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    private string $path;

    public function __construct(string $path)
    {
        $this->path = JsonLogDestination::resolve($path);
    }

    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!\in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Unknown PSR-3 log level.');
        }

        $line = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => LogContextSanitizer::redact((string) $message),
            'context' => LogContextSanitizer::sanitize($context),
        ], \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

        JsonLineWriter::append($this->path, $line);
    }
}
