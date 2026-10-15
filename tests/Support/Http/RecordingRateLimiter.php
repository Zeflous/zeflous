<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use RuntimeException;
use Zef\Framework\Http\RateLimitDecision;
use Zef\Framework\Http\RateLimiterInterface;

/**
 * Deterministic rate-limiter double: records every check's key, limit and
 * window, and answers the configured decision (or throws, when the test
 * needs the unavailability path).
 */
final class RecordingRateLimiter implements RateLimiterInterface
{
    /** @var list<array{string, int, int}> */
    public array $checks = [];

    public RateLimitDecision $decision;

    public bool $throws = false;

    public function __construct(?RateLimitDecision $decision = null)
    {
        $this->decision = $decision ?? new RateLimitDecision(true, 100, 99, 60);
    }

    #[Override]
    public function check(string $key, int $limit, int $windowSeconds): RateLimitDecision
    {
        $this->checks[] = [$key, $limit, $windowSeconds];

        if ($this->throws) {
            throw new RuntimeException('The store is unavailable.');
        }

        return $this->decision;
    }
}
