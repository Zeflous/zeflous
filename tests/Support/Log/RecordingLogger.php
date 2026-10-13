<?php

declare(strict_types=1);

namespace Zef\Test\Support\Log;

use Override;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Deterministic recording logger double: records every level-named call as
 * a (level, message, context) tuple, so a test can assert both that the
 * error sink was reached and what context travelled with it.
 */
final class RecordingLogger implements LoggerInterface
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $calls = [];

    #[Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->record('emergency', $message, $context);
    }

    #[Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->record('alert', $message, $context);
    }

    #[Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->record('critical', $message, $context);
    }

    #[Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->record('error', $message, $context);
    }

    #[Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->record('warning', $message, $context);
    }

    #[Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->record('notice', $message, $context);
    }

    #[Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->record('info', $message, $context);
    }

    #[Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->record('debug', $message, $context);
    }

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->record($level, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function record(string $level, string|Stringable $message, array $context): void
    {
        $this->calls[] = [$level, (string) $message, $context];
    }
}
