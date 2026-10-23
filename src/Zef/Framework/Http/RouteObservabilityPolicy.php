<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The router observability family's validated knob set: the
 * `observability.router` configuration subtree as one immutable
 * value object -- the router lane's own observability slice, the
 * sixth family the observability map carries, following the
 * correlation family's ({@see CorrelationPolicy}) two-knob shape.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Both knobs are read
 * strictly through {@see StrictToggles}, the unknown-key census is
 * {@see RouteObservabilityKnobs}: a wrong type or an unknown key
 * halts the boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the suite pins the two
 * declarations together so they cannot drift.
 *
 * BOTH knobs ship OFF -- the family's double fail-closed posture,
 * the correlation family's own replayed. The arm ({@see
 * self::$enabled}) is off because an armed family over disarmed
 * engines is work nobody can observe: the tracer answers the void
 * span to every call and the meter's series never reach an
 * exposition, so the honest default is the one that allocates and
 * accounts nothing until the deployment wires the family to the
 * engines explicitly. The debug disclosure ({@see self::$debugMatch})
 * is off for the disclosure's own sake, the timing family's
 * `emit_header` secure default replayed: the `X-Route-Match-Debug`
 * header tells the client the route table's own shape -- the
 * candidate patterns a miss names -- which is a development
 * convenience a production deployment never asked to publish, and
 * the knob is gated a second time by the environment verdict
 * ({@see \Zef\Framework\AppEnvironment}: the header exists only in
 * a development environment, whatever the knob says).
 */
final readonly class RouteObservabilityPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled    the family's arm -- the match telemetry the
     *                         router's request handler answers through
     * @param bool $debugMatch the disclosure: stamp the route match
     *                         explanation on routing-refusal responses, in
     *                         development environments only
     */
    public function __construct(
        public bool $enabled = false,
        public bool $debugMatch = false,
    ) {
    }

    /**
     * Reads the merged `observability.router` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The observability.router configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        RouteObservabilityKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm and the
     * debug disclosure -- read strictly, both.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, debugMatch: bool}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'observability.router enabled'),
            'debugMatch' => StrictToggles::read(
                $map['debug_match'] ?? false,
                'observability.router debug_match',
            ),
        ];
    }
}
