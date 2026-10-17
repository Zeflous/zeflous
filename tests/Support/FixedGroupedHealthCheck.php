<?php

declare(strict_types=1);

namespace Zef\Test\Support;

use Override;
use Zef\Framework\Health\GroupedHealthCheckInterface;

/**
 * Deterministic {@see GroupedHealthCheckInterface} test double: a
 * fixed name, outcome and declared group, counting the probes it
 * answered -- the count a group-filtering test pins when it asserts
 * a probe never ran.
 */
final class FixedGroupedHealthCheck implements GroupedHealthCheckInterface
{
    public int $probes = 0;

    public function __construct(
        private readonly string $checkName,
        private readonly bool $healthy,
        private readonly string $group,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->checkName;
    }

    #[Override]
    public function healthGroup(): string
    {
        return $this->group;
    }

    #[Override]
    public function isHealthy(): bool
    {
        ++$this->probes;

        return $this->healthy;
    }
}
