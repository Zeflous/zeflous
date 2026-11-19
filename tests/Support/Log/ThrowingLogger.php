<?php

declare(strict_types=1);

namespace Zef\Test\Support\Log;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * The throwing logger double: the structured-log sink as an
 * always-failing seam, what the best-effort emission containment's
 * own unit reads -- every verb throws the same named failure, so a
 * test asserts that a logger which cannot ship its record never turns
 * the caller's own answer into a post-effect exception (the
 * observation is never worth the query's own answer).
 */
final class ThrowingLogger implements LoggerInterface
{
    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('the log seam refused to record');
    }
}
