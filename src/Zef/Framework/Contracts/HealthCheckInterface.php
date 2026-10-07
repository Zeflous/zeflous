<?php

declare(strict_types=1);

namespace Zef\Framework\Contracts;

/**
 * Readiness probe contract used by the `/health/*` endpoints (see `ROADMAP.md`).
 *
 * Implementations must be cheap, allocation-free and safe to call on every
 * health poll of a persistent worker.
 */
interface HealthCheckInterface
{
    /**
     * Human-readable identifier of the check (used as the report key).
     */
    public function name(): string;

    /**
     * True when the subsystem is healthy.
     */
    public function isHealthy(): bool;
}
