<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The tracing family's validated knob set: the `observability.
 * tracing` configuration subtree as one immutable value object --
 * following the timing family's own {@see TimingPolicy} lineage,
 * which followed the auth family's, which followed the CSRF
 * family's, which followed the rate-limit family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly (the toggle through {@see StrictToggles}, the bounds
 * through {@see StrictIntegers}), the unknown-key census is {@see
 * TracingKnobs}: a wrong type or an unknown key halts the boot with
 * the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The arm ships OFF -- the one posture where this family inverts
 * the timing family's example, deliberately. The timing family
 * arms by default because its inert posture costs nothing ({@see
 * TimingPolicy}: an armed family that will not emit never reads
 * the clock); the tracing family's armed posture is never free --
 * every span is an allocation, two clock reads, an identity mint
 * and a queue append -- and the collection it feeds has no
 * consumer until the delivery lane lands (the campaign's
 * hygiene-and-export family). A collection with no consumer is not
 * a default a framework decides on the host's behalf: the
 * reference's own environment posture agrees (`ZEF_OTEL_ENABLED`
 * defaulted false), and this port keeps the fail-closed side of
 * the decision -- the collection is the deployment's, and a
 * deployment that wants it writes the arm explicitly.
 */
final readonly class TracingPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled   the family's arm
     * @param int  $maxQueue  the processor's queue bound -- completed
     *                        spans beyond it are dropped, never queued
     * @param int  $batchSize the largest batch one delivery call
     *                        carries
     */
    public function __construct(
        public bool $enabled = false,
        public int $maxQueue = 2048,
        public int $batchSize = 256,
    ) {
    }

    /**
     * Reads the merged `observability.tracing` map into the policy.
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
                'The observability.tracing configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        TracingKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm and
     * the two bounds -- read strictly, all three.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, maxQueue: int, batchSize: int}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'observability.tracing enabled'),
            'maxQueue' => StrictIntegers::read($map['max_queue'] ?? 2048, 'observability.tracing max_queue'),
            'batchSize' => StrictIntegers::read($map['batch_size'] ?? 256, 'observability.tracing batch_size'),
        ];
    }
}
