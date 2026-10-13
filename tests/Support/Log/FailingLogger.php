<?php

declare(strict_types=1);

namespace Zef\Test\Support\Log;

use Override;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * Deterministic broken logger double: every method lets exactly the
 * exception it was constructed with escape -- the logging side of the
 * error-mapping middleware's last-resort path.
 */
final readonly class FailingLogger implements LoggerInterface
{
    public function __construct(private Throwable $throwable)
    {
    }

    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw $this->throwable;
    }
}
