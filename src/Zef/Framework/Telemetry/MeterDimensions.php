<?php

declare(strict_types=1);

namespace Zef\Framework\Telemetry;

/**
 * The metrics lane's dimension policy: the name-keyed judgement every
 * recording's attribute map answers before it becomes a series -- the
 * unit the {@see CounterMeter} reads, extracted whole so the store's
 * bounds and the name's policy stay one named place each.
 *
 * THE POLICY IS NAME-KEYED DATA, never a knob: the names the
 * FRAMEWORK itself owns carry their own bounded dimension sets --
 * the `zef.http.*` and `zef.container.*` series keep exactly the two
 * low-cardinality slices the request series exist to be read by (the
 * request method and the response status code), plus the exception
 * type the errors total is attributed by and the bounded service
 * identity the resolve duration slices on; the `zef.router.*` series
 * (the router's own match counting) keeps the request method and the
 * closed match-outcome vocabulary the routing lane's series exist to
 * be sliced by; the lifecycle events
 * total keeps the closed event vocabulary. A custom metric family's
 * dimensions pass the hygiene unit's sanitisation
 * ({@see TelemetrySanitizer}) and stay bounded by the store's series
 * ceiling alone -- the allow-list is the framework's own name
 * policy, never the host's.
 *
 * The ordered map the policy answers is the map the composite key
 * renders deterministically: two recordings of the same dimensions
 * under the same name mint the same identity however the caller
 * ordered the map.
 */
final class MeterDimensions
{
    /**
     * The framework-owned series' name prefixes, each over the
     * dimensions its series keep: the two request-counting lanes
     * (HTTP and container) and the router's match counting.
     *
     * @var array<string, list<string>>
     */
    private const array FRAMEWORK_DIMENSIONS = [
        'zef.http.' => self::HTTP_DIMENSIONS,
        'zef.container.' => self::HTTP_DIMENSIONS,
        'zef.router.' => self::ROUTER_DIMENSIONS,
    ];

    /**
     * The HTTP series' allowed dimensions: the request method and the
     * response status code, the two low-cardinality slices the
     * request-counting series exist to be read by.
     */
    private const array HTTP_DIMENSIONS = ['http.request.method', 'http.response.status_code'];

    /**
     * The router series' allowed dimensions: the request method and
     * the closed match-outcome vocabulary (found, not_found,
     * method_not_allowed, not_acceptable) -- the two slices the
     * router's match counting exists to be read by.
     */
    private const array ROUTER_DIMENSIONS = ['http.request.method', 'router.match.outcome'];

    /**
     * The lifecycle events' closed vocabulary: the worker and request
     * boundary events the series knows by name.
     */
    private const array LIFECYCLE_EVENTS = [
        'worker.started',
        'worker.ready',
        'request.started',
        'request.completed',
        'request.failed',
        'worker.recovery.detected',
        'worker.terminated',
        'telemetry.flush',
        'telemetry.shutdown',
    ];

    /**
     * Reads one recording's name policy over its attribute map: the
     * sanitised map the series' identity renders from -- the
     * framework-owned names carrying their bounded allow-lists, the
     * custom families keeping the sanitised dimensions verbatim, the
     * map ordered for the deterministic key.
     *
     * @param string              $name       the metric name
     * @param array<string,mixed> $attributes the recorded attribute map
     *
     * @return array<string,mixed>
     */
    public static function normalized(string $name, array $attributes): array
    {
        $clean = TelemetryValues::attributes($attributes);

        foreach (self::FRAMEWORK_DIMENSIONS as $prefix => $allowed) {
            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            return self::allowedDimensions($name, $clean, $allowed);
        }

        if ($name === 'zef.lifecycle.events.total') {
            return self::lifecycleDimensions($clean);
        }

        return self::sorted($clean);
    }

    /**
     * Keeps the allowed dimensions of a framework-owned series: the
     * low-cardinality slices the series exists to be read by, plus
     * the named extras the two error-shaping series carry.
     *
     * @param string              $name    the metric name
     * @param array<string,mixed> $clean   the sanitised attribute map
     * @param list<string>        $allowed the dimensions the series keeps
     *
     * @return array<string,mixed>
     */
    private static function allowedDimensions(string $name, array $clean, array $allowed): array
    {
        /** @var array<string,mixed> $kept */
        $kept = [];
        foreach ($allowed as $dimension) {
            if (!\array_key_exists($dimension, $clean)) {
                continue;
            }

            $kept[$dimension] = $clean[$dimension];
        }

        if ($name === 'zef.http.errors.total') {
            $kept = self::exceptionDimension($clean, $kept);
        }

        if ($name === 'zef.container.resolve.duration_seconds') {
            $kept = self::serviceDimension($clean, $kept);
        }

        return self::sorted($kept);
    }

    /**
     * The exception type the errors total is attributed by, bounded
     * and sanitised -- the one high-entropy dimension the series
     * keeps, because it is the dimension the series exists to slice
     * by.
     *
     * @param array<string,mixed> $clean
     * @param array<string,mixed> $kept
     *
     * @return array<string,mixed>
     */
    private static function exceptionDimension(array $clean, array $kept): array
    {
        if (\array_key_exists('exception.type', $clean) && \is_string($clean['exception.type'])) {
            $kept['exception.type'] = TelemetrySanitizer::string($clean['exception.type'], 128);
        }

        return $kept;
    }

    /**
     * The service identity the resolve duration is attributed by,
     * held to its own bounded grammar -- the dimension the series
     * exists to slice by, spelled exactly or not at all.
     *
     * @param array<string,mixed> $clean
     * @param array<string,mixed> $kept
     *
     * @return array<string,mixed>
     */
    private static function serviceDimension(array $clean, array $kept): array
    {
        if (\array_key_exists('zef.service.id', $clean) && \is_string($clean['zef.service.id'])) {
            $kept['zef.service.id'] = self::boundedServiceDimension($clean['zef.service.id']);
        }

        return $kept;
    }

    /**
     * The lifecycle events' closed vocabulary: the named events the
     * series knows, everything else folding to `other` -- a
     * high-entropy event name is a series-per-name the closed
     * vocabulary refuses.
     *
     * @param array<string,mixed> $clean
     *
     * @return array<string,mixed>
     */
    private static function lifecycleDimensions(array $clean): array
    {
        if (!\array_key_exists('event.name', $clean)) {
            return [];
        }

        $event = \is_string($clean['event.name']) ? $clean['event.name'] : '';

        return self::sorted([
            'event.name' => \in_array($event, self::LIFECYCLE_EVENTS, true) ? $event : 'other',
        ]);
    }

    /**
     * The service dimension's own grammar: the bounded identifier
     * spelling passes verbatim, everything else folds to the named
     * `[other]` bucket.
     */
    private static function boundedServiceDimension(string $value): string
    {
        return preg_match('/^[a-z0-9._:-]{1,96}$/i', $value) === 1 ? $value : '[other]';
    }

    /**
     * @param array<string,mixed> $dimensions
     *
     * @return array<string,mixed>
     */
    private static function sorted(array $dimensions): array
    {
        ksort($dimensions);

        return $dimensions;
    }
}
