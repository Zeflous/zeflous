<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * Registry and runner for {@see HealthCheckInterface} implementations.
 *
 * Checks are stored keyed by their name, so the readiness payload keeps a stable
 * insertion-ordered shape and re-registering a name replaces the existing entry
 * in place instead of appending a duplicate.
 */
final class HealthChecker
{
    /** @var array<string, HealthCheckInterface> */
    private array $checks = [];

    public function add(HealthCheckInterface $healthCheck): void
    {
        $this->checks[$healthCheck->name()] = $healthCheck;
    }

    public function count(): int
    {
        return \count($this->checks);
    }

    /**
     * The registered checks, in registration order -- the probe list
     * the metrics family's {@see HealthAggregator} runs under its own
     * scrape semantics (the group filtering, the overall deadline,
     * the crash containment), exposed because a probe runs once per
     * scrape: the aggregator needs the check objects themselves, not
     * the boolean map {@see report()} would answer them with.
     *
     * @return list<HealthCheckInterface>
     */
    public function checks(): array
    {
        return array_values($this->checks);
    }

    public function report(): HealthReport
    {
        return HealthReport::fromChecks($this->checks);
    }
}
