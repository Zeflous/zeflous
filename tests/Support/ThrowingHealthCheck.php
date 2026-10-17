<?php

declare(strict_types=1);

namespace Zef\Test\Support;

use Override;
use RuntimeException;
use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * Deterministic crashing {@see HealthCheckInterface} test double: the
 * probe that throws on every poll -- the containment a scrape test
 * pins through its degraded record, with the failure's own class
 * nameable (the over-long class the truncation bound reads).
 */
final readonly class ThrowingHealthCheck implements HealthCheckInterface
{
    /**
     * @param class-string<RuntimeException> $exceptionClass the failure the probe throws
     */
    public function __construct(
        private string $checkName = 'exploding',
        private string $exceptionClass = RuntimeException::class,
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
        throw new $this->exceptionClass('the probe itself failed');
    }
}
