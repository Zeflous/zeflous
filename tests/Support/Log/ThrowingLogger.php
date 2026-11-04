<?php

declare(strict_types=1);

namespace Zef\Test\Support\Log;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * Deterministic failing logger double: every level-named call throws,
 * so a test can pin the containment posture an emission's own
 * best-effort seam carries -- an observability sink that fails can
 * neither convert the observed answer into a failure nor mask the
 * exception the observed path already threw.
 */
final class ThrowingLogger implements LoggerInterface
{
    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('The logging sink failed.');
    }
}
