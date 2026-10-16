<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The correlation family's validated knob set: the `observability.
 * correlation` configuration subtree as one immutable value object
 * -- following the tracing family's own {@see TracingPolicy}
 * lineage, which followed the timing family's, which followed the
 * security campaign's families before it.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Both knobs are read
 * strictly through {@see StrictToggles}, the unknown-key census is
 * {@see CorrelationKnobs}: a wrong type or an unknown key halts the
 * boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * BOTH knobs ship OFF -- the family's double fail-closed posture.
 * The arm ({@see self::$enabled}) is off because an armed boundary
 * over a disarmed tracer is work nobody can observe: the request
 * span would be the void singleton, the attributes and status
 * ignored, the echo refused -- two header reads and a handful of
 * void calls per request, all discarded. The timing family arms by
 * default because its own inert posture never reads the clock
 * ({@see TimingPolicy}); this family's inert posture cannot check
 * the tracer's arm (the tracer's contract deliberately hides it --
 * the void span is the same answer either way), so the honest
 * default is the one that does nothing until the deployment wires
 * the boundary to an armed tracer explicitly.
 *
 * The echo ({@see self::$echoTraceParent}) is off for the
 * disclosure's own sake -- the timing family's secure-default
 * posture replayed ({@see TimingPolicy::$emitHeader}'s own
 * record): the response's `traceparent` tells the client the
 * server traces, and hands it the server span's identity -- a
 * disclosure the deployment makes explicitly, not one the
 * framework makes on its behalf.
 */
final readonly class CorrelationPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled         the family's arm -- the propagation boundary
     *                              itself
     * @param bool $echoTraceParent the disclosure: stamp the response's
     *                              `traceparent` header with the request
     *                              span's own identity
     */
    public function __construct(
        public bool $enabled = false,
        public bool $echoTraceParent = false,
    ) {
    }

    /**
     * Reads the merged `observability.correlation` map into the policy.
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
                'The observability.correlation configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        CorrelationKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm and
     * the disclosure -- read strictly, both.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, echoTraceParent: bool}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'observability.correlation enabled'),
            'echoTraceParent' => StrictToggles::read(
                $map['echo_traceparent'] ?? false,
                'observability.correlation echo_traceparent',
            ),
        ];
    }
}
