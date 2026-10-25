<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The slow-request family's validated knob set: the
 * `observability.slow_request` configuration subtree as one
 * immutable value object -- the timing family's own {@see
 * TimingPolicy} lineage, the observability map's seventh family
 * (the roadmap HTTP Phase 4.1 request-lifecycle tracing's closing
 * item: the automatic record a request whose total duration
 * exceeds the configured bar answers).
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly (the toggle through {@see StrictToggles}, the threshold
 * through {@see StrictIntegers}), the unknown-key census is
 * {@see SlowRequestKnobs}, and the threshold's corridor is asserted
 * at the constructor ({@see SlowRequestBounds}) so it holds at both
 * construction paths. A `null` map is the absent family and reads
 * as the constructor defaults, which mirror the framework profile's
 * own declaration ({@see \Zef\Framework\FrameworkDefaults}); the
 * policy test pins the two declarations together so they cannot
 * drift.
 *
 * The shipped default bar (1000ms) is the round number the
 * reference's own operational practice answers: one full second is
 * the weight where a request stops being an answer and starts
 * being a question. The arm is ON by default following the timing
 * family's doctrine -- a deployment that composes the middleware
 * into its stack is asking to be told about pathology -- while the
 * family discloses nothing on the response: the record is a
 * server-side log line, never a header.
 */
final readonly class SlowRequestPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled     the family's arm
     * @param int  $thresholdMs the bar a request must strictly exceed to
     *                          be recorded, in milliseconds
     */
    public function __construct(
        public bool $enabled = true,
        public int $thresholdMs = 1000,
    ) {
        SlowRequestBounds::assertThresholdMs($this->thresholdMs);
    }

    /**
     * Reads the merged `observability.slow_request` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading or its corridor
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The observability.slow_request configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        SlowRequestKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The strict readings of the family's two knobs.
     *
     * @param array<array-key, mixed> $map the family's map, its keys
     *                                     already censused
     *
     * @return array{enabled: bool, thresholdMs: int} the constructor's
     *                                                two arguments, in
     *                                                their declaration
     *                                                order
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? true, 'observability.slow_request enabled'),
            'thresholdMs' => StrictIntegers::read(
                $map['threshold_ms'] ?? 1000,
                'observability.slow_request threshold_ms',
            ),
        ];
    }
}
