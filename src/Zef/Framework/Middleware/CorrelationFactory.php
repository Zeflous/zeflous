<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\CorrelationPolicy;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The correlation family's config-driven construction seam: the
 * factory the framework profile's own `observability.correlation`
 * service registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * CorrelationFactory::class . '::fromConfig'` over the
 * `framework.config` service argument and the `observability.
 * tracing` service argument -- resolved through the container's
 * existing declarative grammar, exactly as the families before it.
 * This class is the grammar's string-addressed target, which is
 * why it is marked `@api` rather than being baselined or
 * suppressed.
 *
 * The tracer arrives as a service argument, not a construction of
 * this factory's own: the propagation boundary composes the SAME
 * tracer instance the tracing family registers, so the request
 * spans the boundary opens drain through the one processor the
 * deployment configured -- a second tracer minted here would give
 * the boundary its own private queue and the deployment two
 * collections to reason about, which is one more than the family
 * promised. The dependency the registration spells is the
 * honest one: the boundary is wired to the engine, not a copy of
 * it.
 *
 * Validation is the family's own ({@see CorrelationPolicy::fromMap()}):
 * every knob read strictly, the unknown family and the unknown knob
 * each a named halt. There is no fail-loud seam beyond the reading
 * for the same reason the timing family has none ({@see
 * TimingFactory}'s own record): the family's two safety decisions
 * -- the arm and the disclosure -- are the shipped-off defaults
 * themselves, and a deployment that flips either does so as an
 * explicit, validated knob write.
 *
 * @api
 */
final readonly class CorrelationFactory
{
    /**
     * Builds the propagation boundary from the frozen repository's
     * `observability.correlation` subtree over the tracing family's
     * own tracer service.
     */
    public static function fromConfig(Config $config, TracerInterface $tracer): CorrelationMiddleware
    {
        $correlationPolicy = CorrelationPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'correlation'),
        );

        return new CorrelationMiddleware($correlationPolicy, $tracer);
    }
}
