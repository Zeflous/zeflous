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

    public function report(): HealthReport
    {
        return HealthReport::fromChecks($this->checks);
    }
}
