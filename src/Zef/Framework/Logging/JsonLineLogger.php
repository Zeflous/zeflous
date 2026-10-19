<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use Override;
use Psr\Log\LoggerInterface;
use Stringable;

/** Dependency-free PSR-3 logger that writes one structured JSON object per line. */
final readonly class JsonLineLogger implements LoggerInterface
{
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
     * @param string               $level   one of the eight PSR-3 level names
     * @param array<string, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        JsonLineWriter::append($this->path, JsonLineFormatter::line($level, $message, $context));
    }
}
