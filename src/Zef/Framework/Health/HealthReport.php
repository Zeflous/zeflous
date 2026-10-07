<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * Aggregated result of running a set of health checks.
 *
 * Immutable value object: it is built once per poll and then read by the HTTP
 * adapter to render the readiness payload.
 */
final readonly class HealthReport
{
    /**
     * @param array<string, bool> $checks
     */
    private function __construct(private array $checks)
    {
    }

    /**
     * Runs every health check and aggregates the outcome.
     *
     * @param array<array-key, HealthCheckInterface> $checks
     */
    public static function fromChecks(array $checks): self
    {
        $results = [];

        foreach ($checks as $check) {
            $results[$check->name()] = $check->isHealthy();
        }

        return new self($results);
    }

    public function isHealthy(): bool
    {
        return array_all($this->checks, static fn (bool $healthy): bool => $healthy);
    }

    /**
     * @return array<string, bool>
     */
    public function checks(): array
    {
        return $this->checks;
    }
}
