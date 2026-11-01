<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Container\ContainerServices;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Middleware\AuthFactory;
use Zef\Framework\Middleware\BodyParserFactory;
use Zef\Framework\Middleware\CompressionFactory;
use Zef\Framework\Middleware\CorrelationFactory;
use Zef\Framework\Middleware\CsrfFactory;
use Zef\Framework\Middleware\DebugToolbarFactory;
use Zef\Framework\Middleware\ErrorMappingFactory;
use Zef\Framework\Middleware\MetricsFactory;
use Zef\Framework\Middleware\ProcessErrorBoundaryFactory;
use Zef\Framework\Middleware\RateLimitFactory;
use Zef\Framework\Middleware\SecurityHeadersFactory;
use Zef\Framework\Middleware\SessionFactory;
use Zef\Framework\Middleware\SlowRequestFactory;
use Zef\Framework\Middleware\TimingFactory;
use Zef\Framework\Middleware\TracingFactory;

/**
 * The framework's own configuration defaults: the code-declared profile the
 * {@see ApplicationBootstrapper} lays down as the Default layer under the
 * application's `app/configs/` files and the environment (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree" -- Default < App < Env).
 *
 * Defaults live in code, not in a shipped file, so they can never be
 * accidentally deleted or half-edited by a host application: a host that
 * never writes a configuration file boots on exactly this profile, and a
 * host that writes one overrides only the keys it cares about -- the
 * Laravel-style vendor-defaults seam, adapted to the layered builder the
 * configuration system already owns.
 *
 * The profile now ships the security-headers, rate-limit, CSRF and
 * auth families, and the observability campaign's first four
 * families (timing, the tracing core, the correlation boundary,
 * then the export lane's knob block) plus the metrics family's
 * own two engine services and face -- the router lane's
 * observability slice ships its knob block the same way (the
 * `router` family: the match telemetry's arm and the debug
 * disclosure) but deliberately no service registration of its
 * own, because the family's collaborators compose at seams the
 * container does not yet name -- the router facade is not a
 * service, so the host's own composition (the `RouterRequestHandler`
 * construction and the `middleware.stack` membership) wires the
 * family through the `RouteObservabilityFactory` seam, and the
 * registration arrives with the router's own future service
 * wiring -- and it ships them the way
 * every family arrived: the subsystem's *service registration* as
 * a declarative entry under the reserved `framework.services` key
 * the container's existing grammar installs (string-addressed,
 * over the `framework.config` service the bootstrapper registers),
 * and the subsystem's *knobs* under the family's own key. The `framework`
 * namespace is the machinery's own -- a host's `services` key is the
 * host's declaration surface and stays fully theirs, which is also why
 * the registration does not live there: the merge contract counts an
 * empty array as a clearing list, so a host's empty `services.php` would
 * wipe the framework's own registrations out of the merged tree. The
 * registration always exists -- a host cannot delete the framework's own
 * machinery out from under it -- while stack membership stays the host's
 * explicit choice: the `middleware.stack` list decides what runs, exactly
 * as it does for any other middleware. The export family ships no
 * registration of its own -- the lane is the tracing family's sink and
 * retry posture, wired by the `TracingFactory` seam the tracing
 * registration already names, and the drain cadence rides the
 * correlation boundary's own registration -- so its knob block is the
 * family's whole declaration here. The metrics family's engines are
 * the profile's own split declarations: `observability.meter` the
 * shared counter, installed through the composition seam's own
 * constructor the way the tracing core's tracer arrived -- the
 * Telemetry engine the composition lane alone joins -- and
 * `observability.health` the health seam's own {@see HealthChecker}
 * registry, installed through the grammar's `class` form (the
 * instance shape the container's definition shapes carry for a
 * stateful engine a service identity names, the profile's first) --
 * the meter separate
 * because its producers are the lanes that will record against it,
 * the registry separate because a host's probes join it through their
 * own explicit rebind. The slow-request family joined the map the
 * same way the timing family did: the `observability.slow_request`
 * registration over the config service, its records travelling to
 * the error lane's own structured log address rather than a sink of
 * the family's own. The events family joins the catalogue the same
 * way every family did -- the durable delivery store's wired default
 * (`events.deferred.store`, the registration over the
 * `EventStorageWiring` fold, its knobs under the family's own
 * `events` key) -- with the one posture no other family carries: the
 * registration is deliberately TRANSIENT, because the singleton
 * warmer would eagerly resolve a shared lifetime and the wired
 * default's silent knob is the refusal that must fire only at the
 * host's own ask, never at the boot. The remaining growth follows the
 * roadmap's order -- the HTTP lane's ingress knobs (`http.body_budget`,
 * `http.trusted_hosts`, ...) migrate with their wiring, and the
 * container's `aot` switch has arrived with the compile/dump area
 * (roadmap area 8): the `aot` knob below, declared off by default
 * because the framework ships no artifact of its own and a host opts
 * in from their configuration, and the reserved `framework.aot`
 * registration ({@see AOT_SERVICE}) answering the lane's boot outcome.
 * The tags lane is live: the reserved `framework.tags`
 * registration ({@see TAGS_SERVICE}) answers the boot-built locator,
 * and the `tags.interfaces` map below is the autoconfiguration seam
 * (roadmap area 3) -- empty by default, because the framework ships
 * no marker-interface rules of its own; the host's rules arrive with
 * the host's configuration. The namespace lane is live in the same
 * posture: the `namespaces` knob below declares no scope annotations
 * and a zero cross-scope budget, because the framework ships no
 * namespace governance of its own -- a host's `scopes` map arrives
 * with the host's configuration, and the empty knob boots the honest
 * ungoverned tree; the reserved `framework.namespaces` registration
 * ({@see NAMESPACES_SERVICE}) answers the boot-built locator either
 * way.
 */
final readonly class FrameworkDefaults
{
    /**
     * The container service identifier under which the frozen repository is
     * registered at boot: the profile's own registrations address it (their
     * factories resolve the repository through it), and the bootstrapper
     * installs it before a single definition. The identifier lives here --
     * with the declarations that reference it -- so the kernel's own
     * dependency graph stays one-directional.
     */
    public const string CONFIG_SERVICE = 'framework.config';

    /**
     * The container service identifier under which the tags lane's
     * read side ({@see Container\TaggedServices}) is
     * registered at boot: the boot-built locator over the planned
     * definition set's declared tags. The identifier lives beside the
     * profile's other reserved names so the framework's own namespace
     * stays one catalogue.
     */
    public const string TAGS_SERVICE = ContainerServices::TAGS_SERVICE;

    /**
     * The container service identifier under which the compile/dump
     * lane's boot answer ({@see Container\Aot\AotOutcome}) is registered
     * at boot: which lane answered, why, and what the compile saw. The
     * `framework.config` precedent -- introspection through the
     * container's own public surface, registered ahead of the
     * definitions so a host's same-identifier rebind wins.
     */
    public const string AOT_SERVICE = ContainerServices::AOT_SERVICE;

    /**
     * The container service identifier under which the namespace
     * layer's read side ({@see Container\NamespacedServices}) is
     * registered at boot: the locator over the boot's sealed radix
     * tree. The `framework.tags` precedent -- introspection through
     * the container's own public surface, registered ahead of the
     * definitions so a host's same-identifier rebind wins.
     */
    public const string NAMESPACES_SERVICE = ContainerServices::NAMESPACES_SERVICE;

    /**
     * The container service identifier under which the debug
     * inventory's read side ({@see Container\ContainerInventory}) is
     * registered at boot: the declarations the boot spelled and the
     * lazy marks the runtime holds, the `framework.tags` precedent's
     * own family -- introspection through the container's own public
     * surface, registered ahead of the definitions so a host's
     * same-identifier rebind wins.
     */
    public const string INVENTORY_SERVICE = ContainerServices::INVENTORY_SERVICE;

    /**
     * The container service identifier under which the process error
     * boundary ({@see Middleware\ProcessErrorBoundary}) is registered at
     * boot: the composition lane's process-level error face, installed by
     * the one-call boot orchestrator when the `http.error_handling.
     * process_boundary` knob asks for it -- the `http.errors` precedent's
     * own family, so the error lane's services stay one catalogue. The
     * spelling is the boundary family's own
     * (`ProcessErrorBoundaryFactory::SERVICE_ID`); this alias is the
     * registration seam's single hand-off.
     */
    public const string PROCESS_BOUNDARY_SERVICE = ProcessErrorBoundaryFactory::SERVICE_ID;

    /** The config-driven factories' shared construction entry, as the
     * service registrations spell it. */
    private const string CONFIG_FACTORY_ENTRY = '::fromConfig';

    /**
     * The framework's default configuration profile.
     *
     * @return array<non-empty-string, mixed>
     */
    public static function profile(): array
    {
        return [
            'framework' => [
                'services' => self::serviceRegistrations(),
            ],
            'security' => [
                'headers' => [
                    'hsts' => false,
                    'csp' => false,
                    'content_security_policy' => "default-src 'self'; frame-ancestors 'none'; base-uri 'self'",
                    'permissions_policy' => true,
                ],
                'rate_limit' => [
                    'enabled' => false,
                    'limit' => 100,
                    'window' => 60,
                    'max_keys' => 10000,
                    'store' => 'memory',
                    'trusted_proxies' => [],
                    'redis_host' => '127.0.0.1',
                    'redis_port' => 6379,
                ],
                'csrf' => [
                    'enabled' => true,
                    'secret' => '',
                    'cookie_name' => 'ZEF-XSRF-TOKEN',
                    'header_name' => 'X-CSRF-Token',
                    'token_bytes' => 32,
                    'cookie_secure' => true,
                    'cookie_http_only' => true,
                    'same_site' => 'Strict',
                ],
                'auth' => [
                    'enabled' => true,
                    'tokens' => [],
                    'principal' => 'api',
                    'scope' => 'api',
                    'required_scope' => 'api',
                    'allow_anonymous' => false,
                    'replay_capacity' => 1024,
                    'replay_window' => 300,
                ],
            ],
            'observability' => [
                'timing' => [
                    'enabled' => true,
                    'emit_header' => false,
                ],
                'tracing' => [
                    'enabled' => false,
                    'max_queue' => 2048,
                    'batch_size' => 256,
                ],
                'correlation' => [
                    'enabled' => false,
                    'echo_traceparent' => false,
                ],
                'export' => [
                    'enabled' => false,
                    'endpoint' => '',
                    'timeout_ms' => 500,
                    'service_name' => 'zef-application',
                    'max_retries' => 0,
                    'retry_delay_ms' => 100,
                    'retry_delay_cap_ms' => 1000,
                    'flush_per_request' => false,
                ],
                'metrics' => [
                    'enabled' => false,
                    'metrics_path' => '/metrics',
                    'health_path' => '/health',
                    'timeout_ms' => 500,
                ],
                'router' => [
                    'enabled' => false,
                    'debug_match' => false,
                ],
                'slow_request' => [
                    'enabled' => true,
                    'threshold_ms' => 1000,
                ],
            ],
            'tags' => [
                'interfaces' => [],
            ],
            'contextual' => [],
            'decorators' => [],
            'interceptors' => [],
            'listeners' => [],
            'modules' => [],
            'namespaces' => ['scopes' => [], 'max_cross_scope_refs' => 0],
            'parameters' => [],
            'events' => [
                'deferred' => [
                    'store' => '',
                    'path' => '',
                    'capacity' => EventDeliveryDeclaration::DEFAULT_CAPACITY,
                ],
            ],
            'aot' => [
                'enabled' => false,
                'warm' => false,
            ],
            'container' => [
                'mode' => 'auto',
            ],
        ];
    }

    /**
     * The boot-ordered service registrations the default profile wires:
     * the HTTP error boundary and debug toolbar lanes, the security
     * middleware family, and the observability collaborators -- each
     * config-driven factory addressed through the shared construction
     * entry ({@see CONFIG_FACTORY_ENTRY}) so a host's same-identifier
     * rebind wins over the framework's own defaults.
     *
     * @return array<non-empty-string, array<string, mixed>>
     */
    private static function serviceRegistrations(): array
    {
        return [
            'http.errors' => [
                'factory' => ErrorMappingFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                    ['service' => 'observability.meter'],
                ],
            ],
            'http.errors.process_boundary' => [
                'factory' => ProcessErrorBoundaryFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                    ['service' => 'observability.meter'],
                ],
            ],
            'http.debug_toolbar' => [
                'factory' => DebugToolbarFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'http.compression' => [
                'factory' => CompressionFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'http.body_parser' => [
                'factory' => BodyParserFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'http.session' => [
                'factory' => SessionFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'security.headers' => [
                'factory' => SecurityHeadersFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'security.rate_limit' => [
                'factory' => RateLimitFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'security.csrf' => [
                'factory' => CsrfFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'security.auth' => [
                'factory' => AuthFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'observability.timing' => [
                'factory' => TimingFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'observability.tracing' => [
                'factory' => TracingFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'observability.correlation' => [
                'factory' => CorrelationFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                    ['service' => 'observability.tracing'],
                ],
            ],
            'observability.meter' => [
                'factory' => MetricsFactory::class . '::meter',
                'arguments' => [],
            ],
            'observability.health' => [
                'class' => HealthChecker::class,
            ],
            'observability.metrics' => [
                'factory' => MetricsFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                    ['service' => 'observability.meter'],
                    ['service' => 'observability.health'],
                ],
            ],
            'observability.slow_request' => [
                'factory' => SlowRequestFactory::class . self::CONFIG_FACTORY_ENTRY,
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
            ],
            'events.deferred.store' => [
                'factory' => EventStorageWiring::class . '::store',
                'arguments' => [
                    ['service' => self::CONFIG_SERVICE],
                ],
                'lifetime' => 'transient',
            ],
        ];
    }
}
