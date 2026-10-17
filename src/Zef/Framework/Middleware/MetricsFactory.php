<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Health\HealthAggregator;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Http\MetricsPolicy;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\PrometheusRenderer;

/**
 * The metrics family's config-driven construction seam: the factory
 * the framework profile's own `observability.metrics` service
 * registration names -- the campaign's closing family, arrived over
 * the two engine services the same registration block installs.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * MetricsFactory::class . '::fromConfig'` over the `framework.config`
 * service argument and the two engine arguments (`observability.meter`,
 * `observability.health`) -- resolved through the container's
 * existing declarative grammar, exactly as every family's is. This
 * class is the grammar's string-addressed target, which is why it is
 * marked `@api` rather than being baselined or suppressed.
 *
 * Validation is the family's own ({@see MetricsPolicy::fromMap()}):
 * every knob read strictly, the unknown family and the unknown knob
 * each a named halt. The construction is the two engines' wiring:
 * the shared meter the registration installs (the {@see CounterMeter}
 * the profile's own registration declares, or whatever meter
 * implementation a host's rebind of `observability.meter` installs --
 * the same instance every producer records against and the
 * exposition renders), the {@see HealthChecker} the registration
 * installs as the health registry (the seam a host's own rebind
 * joins their probes into -- the explicit rebind the container
 * grammar already spells), the {@see PrometheusRenderer} the
 * exposition reads, and the {@see HealthAggregator} over the
 * registry on the system monotonic clock -- the arm a disabled
 * family carries answers nothing: the middleware passes every
 * request through, the endpoints exist only when the deployment
 * armed them.
 *
 * THE ENGINE SERVICES ARE THE FAMILY'S OWN TWO DECLARATIONS: the
 * health registry through the grammar's `class` form (the Health
 * layer the kernel's own composition already allows -- the instance
 * shape the container's definition shapes carry for a stateful
 * engine a service identity names, the profile's first), and the
 * shared meter through this seam's own constructor -- the Telemetry
 * engine the composition lane alone joins, exactly the one-way
 * direction the tracing core's own record spells. The meter
 * registers as its own service because its producers are the lanes
 * that will record against it (the framework's own request and
 * lifecycle lanes, and the host's), exactly the way the tracing
 * core's tracer registered ahead of its consumer families; the
 * health registry registers as its own service because a host's
 * probes join it through their own explicit rebind -- `services.php`
 * replacing the framework's registration with a factory composing
 * their checks, the rebind that wins the grammar's own rules
 * already spell.
 *
 * @api
 */
final readonly class MetricsFactory
{
    /**
     * The shared meter the profile's `observability.meter`
     * registration installs: the engine constructor the composition
     * seam owns, exactly the way the tracing family's tracer
     * arrived -- the Telemetry engine the composition lane alone
     * joins (the engine's own layer stays config-blind, and the
     * profile references the seam, never the engine).
     */
    public static function meter(): CounterMeter
    {
        return new CounterMeter();
    }

    /**
     * Builds the exposition boundary from the frozen repository's
     * `observability.metrics` subtree, over the two engine services
     * the registration block installs.
     */
    public static function fromConfig(
        Config $config,
        MeterInterface $meter,
        HealthChecker $healthChecker,
    ): MetricsMiddleware {
        $metricsPolicy = MetricsPolicy::fromMap(
            ObservabilityFamilies::subtree($config->get('observability'), 'metrics'),
        );

        return new MetricsMiddleware(
            $metricsPolicy,
            $meter,
            new PrometheusRenderer(),
            new HealthAggregator($healthChecker),
        );
    }
}
