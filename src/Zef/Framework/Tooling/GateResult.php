<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * Outcome of a single tooling gate: a pass/fail flag plus a human message.
 */
final readonly class GateResult
{
    private function __construct(
        public bool $passed,
        public string $message,
    ) {
    }

    public static function passed(string $message): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }

    public function exitCode(): int
    {
        return $this->passed ? 0 : 1;
    }
}
