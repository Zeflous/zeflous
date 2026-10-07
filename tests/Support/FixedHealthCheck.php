<?php

declare(strict_types=1);

namespace Zef\Test\Support;

use Override;
use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * Deterministic {@see HealthCheckInterface} test double.
 */
final readonly class FixedHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private string $checkName,
        private bool $healthy,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->checkName;
    }

    #[Override]
    public function isHealthy(): bool
    {
        return $this->healthy;
    }
}
