<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\RouteObservabilityPolicy;
use Zef\Framework\Router\Router;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\TelemetryClock;
use Zef\Framework\Telemetry\TelemetryClockInterface;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The router observability family's config-driven construction
 * seam: the factory a host's own composition calls -- the match
 * telemetry the `RouterRequestHandler` construction carries, and
 * the debug disclosure the `middleware.stack` composes -- the
 * observability campaign's factories' shape, arrived over the two
 * engines the campaign already registered rather than a
 * registration of its own.
 *
 * The family deliberately ships NO service registration: its
 * collaborators compose at seams the container does not yet name.
 * The router facade is not a container service, so the host -- not
 * a declarative entry -- is the one who holds the Router, builds
 * the `RouterRequestHandler` over it, and decides the stack's
 * membership; the factory answers each collaborator from the frozen
 * repository's `observability.router` subtree and the engine
 * services the profile already installs (`observability.tracing`,
 * `observability.meter`), so a host arms the family exactly the way
 * they armed the families before it, and the registration arrives
 * with the router's own future service wiring. This class is the
 * composition seam the slice owns instead, which is why it is
 * marked `@api`.
 *
 * Validation is the family's own ({@see RouteObservabilityPolicy::
 * fromMap()}): every knob read strictly, the unknown family and the
 * unknown knob each a named halt. The two construction faces answer
 * the family's two arms:
 *
 * - {@see self::telemetry()} answers the match telemetry unit, or
 *   null when the family's `enabled` arm is off -- the honest void
 *   (no allocation, no accounting) rather than a noop unit, so a
 *   handler constructed without the family answers exactly as it
 *   always did;
 * - {@see self::debugMiddleware()} answers the debug disclosure
 *   middleware, or null when the `debug_match` arm is off -- the
 *   double fail-closed gate the disclosure's own record spells (the
 *   environment verdict gates it a second time, per request).
 *
 * @api
 */
final readonly class RouteObservabilityFactory
{
    /**
     * Builds the match telemetry the router's request handler
     * carries, over the frozen repository's `observability.router`
     * subtree and the two engine services the profile installs.
     *
     * A disarmed family answers null: the handler's optional
     * construction argument stays absent, and the family's off
     * posture allocates and accounts nothing.
     */
    public static function telemetry(
        Config $config,
        TracerInterface $tracer,
        MeterInterface $meter,
        ?TelemetryClockInterface $telemetryClock = null,
    ): ?RouteMatchTelemetry {
        $routeObservabilityPolicy = self::policy($config);

        if (!$routeObservabilityPolicy->enabled) {
            return null;
        }

        return new RouteMatchTelemetry($tracer, $meter, $telemetryClock ?? new TelemetryClock());
    }

    /**
     * Builds the debug disclosure middleware, over the frozen
     * repository's `observability.router` subtree and the router the
     * host's own composition holds.
     *
     * A family without the debug arm answers null: the stack simply
     * does not carry the disclosure, and the knob's own record
     * spells the second gate (the environment verdict) the
     * middleware applies per request.
     */
    public static function debugMiddleware(Config $config, Router $router): ?RouteDebugMiddleware
    {
        $routeObservabilityPolicy = self::policy($config);

        if (!$routeObservabilityPolicy->debugMatch) {
            return null;
        }

        return new RouteDebugMiddleware($router);
    }

    /**
     * The family's frozen knob set, read from the frozen repository's
     * `observability` map through the observability census.
     */
    private static function policy(Config $config): RouteObservabilityPolicy
    {
        return RouteObservabilityPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'router'),
        );
    }
}
