<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The export family's validated knob set: the `observability.
 * export` configuration subtree as one immutable value object --
 * following the correlation family's own {@see CorrelationPolicy}
 * lineage, which followed the tracing family's ({@see
 * TracingPolicy}), which followed the timing family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly (the toggles through {@see StrictToggles}, the integers
 * through {@see StrictIntegers}, the two strings with their types
 * named on refusal), the unknown-key census is {@see ExportKnobs}:
 * a wrong type or an unknown key halts the boot with the knob
 * named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins
 * the two declarations together so they cannot drift.
 *
 * The family's own posture is the campaign's third fail-closed
 * decision in a chain: the collection arms explicitly ({@see
 * TracingPolicy}'s off-by-default arm), the delivery lane arms
 * explicitly ({@see self::$enabled}, off -- the in-memory sink is
 * the honest default a family without a collector declaration
 * gets), and the drain cadence arms explicitly ({@see
 * self::$flushPerRequest}, off -- a per-request flush puts the
 * delivery's own latency inside the request path, and a deployment
 * that wants the blocking drain there writes the knob). Three
 * decisions, three knobs, none taken on the host's behalf: a
 * deployment that wants telemetry out of the process writes all
 * three, and every intermediate posture is a state the deployment
 * chose and can name.
 *
 * The retry lane's three knobs ({@see self::$maxRetries}, {@see
 * self::$retryDelayMs}, {@see self::$retryDelayCapMs}) carry the
 * reference's own bounds and one cross-knob invariant -- the cap
 * never sits below the initial delay, because a backoff that
 * starts above its own ceiling is a mis-declared policy, not a
 * policy. The retry count ships at zero: the one-attempt posture
 * the processor's own bound already implied ({@see \Zef\Framework\
 * Telemetry\BatchSpanProcessor}), preserved as the shipped default
 * -- the reference's two-retry default is inverted deliberately,
 * because a blocking backoff in the drain is a latency posture the
 * deployment decides on, not one a framework smuggles in through a
 * default.
 *
 * The endpoint's grammar is the family's fail-loud seam, the CSRF
 * family's own {@see CsrfBounds::assertUsableSecret()} lineage,
 * landed as the bounds' own unit ({@see ExportBounds}) when the
 * archaeology gate demanded the split: an
 * ARMED family with an unusable endpoint halts the resolution the
 * stack membership triggered -- an empty endpoint, a relative one,
 * a scheme the transport does not speak, or a credential embedded
 * in the URL (the transport's own diagnostics would echo it into
 * exception text) -- with the remediation spelled out through the
 * environment overlay or a configuration file or the explicit
 * disarm, while the policy itself stays readable data so an absent
 * family, an empty map and the shipped profile all read as the
 * default posture, none of them an opt-in.
 */
final readonly class ExportPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool   $enabled         the family's arm -- the OTLP sink swap
     *                                and the retry lane's wiring
     * @param string $endpoint        the collector's base URL, validated
     *                                when the family is armed
     * @param int    $timeoutMs       the exchange's own wait, 1..10000
     * @param string $serviceName     the resource block's service identity
     * @param int    $maxRetries      the retry lane's additional attempts,
     *                                0..10 -- zero is the one-attempt
     *                                posture
     * @param int    $retryDelayMs    the first retry's wait, 0..10000
     * @param int    $retryDelayCapMs the wait's cap, 0..60000, never below
     *                                the initial delay
     * @param bool   $flushPerRequest the drain cadence -- the request
     *                                boundary's flush knob
     */
    public function __construct(
        public bool $enabled = false,
        public string $endpoint = '',
        public int $timeoutMs = 500,
        public string $serviceName = 'zef-application',
        public int $maxRetries = 0,
        public int $retryDelayMs = 100,
        public int $retryDelayCapMs = 1000,
        public bool $flushPerRequest = false,
    ) {
        ExportBounds::assertConstructorBounds($timeoutMs, $maxRetries, $retryDelayMs, $retryDelayCapMs);

        if (!$enabled) {
            return;
        }

        ExportBounds::assertUsableEndpoint($endpoint);
        ExportBounds::assertUsableServiceName($serviceName);
    }

    /**
     * Reads the merged `observability.export` map into the policy.
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
                'The observability.export configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        ExportKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the two arms, the
     * endpoint, the service identity, the exchange's wait and the retry
     * lane's three bounds -- read strictly, all eight.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, endpoint: string, timeoutMs: int, serviceName: string,
     *     maxRetries: int, retryDelayMs: int, retryDelayCapMs: int, flushPerRequest: bool}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? false, 'observability.export enabled'),
            'endpoint' => self::readString($map['endpoint'] ?? '', 'observability.export endpoint'),
            'timeoutMs' => StrictIntegers::read($map['timeout_ms'] ?? 500, 'observability.export timeout_ms'),
            'serviceName' => self::readString(
                $map['service_name'] ?? 'zef-application',
                'observability.export service_name',
            ),
            'maxRetries' => StrictIntegers::read($map['max_retries'] ?? 0, 'observability.export max_retries'),
            'retryDelayMs' => StrictIntegers::read(
                $map['retry_delay_ms'] ?? 100,
                'observability.export retry_delay_ms',
            ),
            'retryDelayCapMs' => StrictIntegers::read(
                $map['retry_delay_cap_ms'] ?? 1000,
                'observability.export retry_delay_cap_ms',
            ),
            'flushPerRequest' => StrictToggles::read(
                $map['flush_per_request'] ?? false,
                'observability.export flush_per_request',
            ),
        ];
    }

    /**
     * Reads one string knob's value: a string passes through (the
     * trimming the endpoint's own grammar check reads later), and
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
