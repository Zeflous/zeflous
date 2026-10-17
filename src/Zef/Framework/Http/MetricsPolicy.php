<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The metrics family's validated knob set: the `observability.
 * metrics` configuration subtree as one immutable value object --
 * following the export family's own {@see ExportPolicy} lineage,
 * which followed the correlation family's ({@see CorrelationPolicy}),
 * which followed the tracing family's ({@see TracingPolicy}), which
 * followed the timing family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly (the arm through {@see StrictToggles}, the timeout
 * through {@see StrictIntegers}, the two paths with their types
 * named on refusal), the unknown-key census is {@see MetricsKnobs}:
 * a wrong type or an unknown key halts the boot with the knob
 * named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * THE FAMILY SHIPS OFF -- the campaign's disclosure posture carried
 * to its last family: an exposition endpoint PUBLISHES (an `/metrics`
 * answers the internal series names and their dimensions; a `/health`
 * answers the dependency topology the probes reveal), and a
 * publication knob's safe default is the one that publishes nothing.
 * The endpoints exist only when the deployment arms the family -- the
 * arm is the fourth decision in the campaign's fail-closed chain,
 * and the one the whole chain was building toward.
 *
 * The family's fail-loud seam is the path pair, the export family's
 * own {@see ExportBounds::assertUsableEndpoint()} lineage: an ARMED
 * family with an unusable path halts the resolution the stack
 * membership triggered -- an empty path, a relative one, a path with
 * the control bytes or the query and fragment separators a request
 * target grammar refuses, or a metrics path colliding with the health
 * surface's own three endpoints (a deployment believes `/metrics` is
 * served while the collision never answers it) -- with the
 * remediation spelled out, while the policy itself stays readable
 * data so an absent family, an empty map and the shipped profile all
 * read as the default posture, none of them an opt-in.
 *
 * The scrape's own deadline ({@see self::$timeoutMs}) is the
 * load-balancer contract: the bound the `/health` response carries
 * even when a sync probe hangs -- the overall deadline the
 * aggregator's own skip semantics enforce -- with the export
 * family's timeout bounds and the same 1..10000 corridor.
 */
final readonly class MetricsPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool   $enabled     the family's arm -- the endpoints'
     *                            existence
     * @param string $metricsPath the exposition endpoint's path, the
     *                            scrape target the collector polls
     * @param string $healthPath  the health surface's base path, the
     *                            `/live` and `/ready` views deriving
     *                            from it
     * @param int    $timeoutMs   the health scrape's overall deadline,
     *                            1..10000 -- the bound the response
     *                            carries even when a probe hangs
     */
    public function __construct(
        public bool $enabled = false,
        public string $metricsPath = '/metrics',
        public string $healthPath = '/health',
        public int $timeoutMs = 500,
    ) {
        if ($timeoutMs < 1 || $timeoutMs > 10000) {
            throw new HttpException(
                'The observability.metrics timeout_ms knob must be between 1 and 10000, '
                . $timeoutMs . ' given.',
            );
        }

        if (!$enabled) {
            return;
        }

        MetricsBounds::assertUsablePaths($metricsPath, $healthPath);
    }

    /**
     * Reads the merged `observability.metrics` map into the policy.
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
                'The observability.metrics configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        MetricsKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm, the
     * two endpoint paths and the scrape's deadline -- read strictly,
     * all four.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, metricsPath: string, healthPath: string, timeoutMs: int}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'observability.metrics enabled'),
            'metricsPath' => self::readString($map['metrics_path'] ?? '/metrics', 'observability.metrics metrics_path'),
            'healthPath' => self::readString($map['health_path'] ?? '/health', 'observability.metrics health_path'),
            'timeoutMs' => StrictIntegers::read($map['timeout_ms'] ?? 500, 'observability.metrics timeout_ms'),
        ];
    }

    /**
     * Reads one string knob's value: a string passes through (the
     * trimming the path's own grammar check reads later), and
     * everything else is rejected with the knob named -- a number,
     * a bool or a list is a wrong type, never a coerced spelling.
     */
    private static function readString(mixed $value, string $knob): string
    {
        if (\is_string($value)) {
            return $value;
        }

        throw new HttpException(
            "The {$knob} knob must be a string, " . get_debug_type($value) . self::GIVEN_SUFFIX,
        );
    }
}
