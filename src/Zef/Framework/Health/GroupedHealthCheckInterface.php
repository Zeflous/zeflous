<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * The grouped health check: the optional extension a {@see
 * HealthCheckInterface} implementation carries to declare which
 * health view it belongs to -- the metrics family's own grouping
 * over this repository's already-live health seam, exactly where
 * the campaign's lane record reserved the arrival.
 *
 * The groups:
 *
 * - `live` -- process-local truth (the event loop alive, the worker
 *   heartbeat). Never touches a dependency; if this fails the
 *   instance should be restarted, not drained.
 * - `ready` -- dependency-backed truth (the database, the cache, the
 *   queue reachable). Gates traffic routing behind the load
 *   balancer.
 *
 * A check that does NOT implement this interface reads as `ready`
 * ({@see HealthAggregator}'s own default), so a host's existing
 * plain checks keep their behaviour unchanged -- the grouping is an
 * extension the check opts into, never a registration the aggregator
 * demands.
 *
 * A custom group name is matched exactly by the aggregate's group
 * view; the two named groups are the vocabulary the load-balancer
 * contracts (the `/health/live` and `/health/ready` endpoints the
 * reference's own module surfaces) spell.
 */
interface GroupedHealthCheckInterface extends HealthCheckInterface
{
    /**
     * The health view this check belongs to: `'live'`, `'ready'`, or
     * a custom group name the aggregate's own group view matches
     * exactly.
     */
    public function healthGroup(): string;
}
