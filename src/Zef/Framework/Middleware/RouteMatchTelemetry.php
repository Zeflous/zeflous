<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Throwable;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\SpanInterface;
use Zef\Framework\Telemetry\TelemetryClockInterface;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The observed route match: the one unit the router's request
 * handler consults, so a match answers through the observability
 * engines the campaign already owns -- the tracing core's span and
 * the metrics lane's meter -- without the routing lane growing
 * either engine of its own.
 *
 * THE SPAN ({@see self::MATCH_SPAN_NAME}) is the match's own
 * timeline, opened under the request span the correlation boundary
 * exposed on the request ({@see CorrelationMiddleware::SPAN_ATTRIBUTE},
 * passed here as the parent) -- a fresh trace when no boundary
 * carried one, the honest answer to both. The opening attributes
 * are the request's own routing surface (method, path, host), the
 * answered attributes the outcome the matcher settled on and, for a
 * found match, the route's own name. The status folds the outcome
 * the way the request span folds the answer: a found match is the
 * ok one, the two refusals the table itself gave (method not
 * allowed, not acceptable) are the error one -- a trace should
 * surface a routing-layer refusal -- and a not-found is left unset:
 * the void answer, neither success nor failure. A throwing match
 * (the engine's contract says it cannot, but the observation is
 * armoured anyway) records the failure exactly the way the request
 * span does and still propagates it.
 *
 * THE METER records the two series the router lane owns, in the
 * seconds domain the container lane's resolve duration established:
 * {@see self::MATCHES_TOTAL} counts every observed match by its
 * outcome and method (the closed outcome vocabulary the explanation
 * spells, the dimensions {@see \Zef\Framework\Telemetry\MeterDimensions}
 * allows the `zef.router.*` series), and {@see
 * self::MATCH_DURATION_SECONDS} observes the match's own duration --
 * the route search time, the roadmap's own metric. The recording is
 * best-effort ({@see ErrorFaultMetricsRecorder}'s doctrine): a meter
 * that fails must never replace the match's own answer. A disarmed
 * tracer answers the void span to every call, so an armed family
 * over a disarmed engine records its metrics and harms nothing --
 * and the factory's null ({@see RouteObservabilityFactory}) is the
 * family's honest off posture: no allocation, no accounting.
 *
 * @internal
 */
final readonly class RouteMatchTelemetry
{
    /** The match span's name, the request span's own grammar. */
    public const string MATCH_SPAN_NAME = 'zef.router.match';

    /** The match counting series: one per observed match, by outcome and method. */
    public const string MATCHES_TOTAL = 'zef.router.matches.total';

    /** The match duration series, in seconds: the route search time. */
    public const string MATCH_DURATION_SECONDS = 'zef.router.match.duration_seconds';

    /** The nanoseconds one second carries. */
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;

    public function __construct(
        private TracerInterface $tracer,
        private MeterInterface $meter,
        private TelemetryClockInterface $telemetryClock,
    ) {
    }

    /**
     * Observes one match: opens the match span under the request
     * span the boundary exposed (when it did), measures the match on
     * the telemetry clock's own monotonic domain, records the
     * outcome on both engines, and answers the match's own result.
     *
     * @param null|SpanInterface     $parentSpan            the request span the
     *                                                      correlation boundary exposed
     * @param ServerRequestMetadata  $serverRequestMetadata the routing view of the request
     * @param Closure(): MatchResult $match                 the match itself
     */
    public function observed(
        ?SpanInterface $parentSpan,
        ServerRequestMetadata $serverRequestMetadata,
        Closure $match,
    ): MatchResult {
        $span = $this->tracer->startSpan(
            self::MATCH_SPAN_NAME,
            $this->openingAttributes($serverRequestMetadata),
            $parentSpan?->getContext(),
        );
        $startedNs = $this->telemetryClock->monotonicNs();

        try {
            $matchResult = $match();
        } catch (Throwable $throwable) {
            $this->failed($span, $throwable);

            throw $throwable;
        }

        $durationNs = $this->telemetryClock->monotonicNs() - $startedNs;
        $outcome = $this->outcomeOf($matchResult);

        $this->answered($span, $matchResult, $outcome);
        $this->record($serverRequestMetadata->method, $outcome, $durationNs);

        return $matchResult;
    }

    /**
     * The answered span's recording: the outcome and (for a found
     * match) the route's own name as attributes, and the status the
     * outcome folds to -- then the one end.
     */
    private function answered(SpanInterface $span, MatchResult $matchResult, string $outcome): void
    {
        $attributes = ['router.match.outcome' => $outcome];

        if ($matchResult->isFound()) {
            $routeName = $matchResult->route()->name();
            if ($routeName !== null) {
                $attributes['router.route.name'] = $routeName;
            }

            $span->setStatus('OK');
        } elseif ($outcome !== RouteMatchExplanation::OUTCOME_NOT_FOUND) {
            $span->setStatus('ERROR', $outcome);
        }

        $span->setAttributes($attributes);
        $span->end();
    }

    /**
     * The failed match's recording: the error status with the
     * failure's own type, the exception event, and the one end --
     * the failure still travels, exactly as it arrived.
     */
    private function failed(SpanInterface $span, Throwable $throwable): void
    {
        $span->setStatus('ERROR', $throwable::class);
        $span->addEvent('exception', [
            'exception.type' => $throwable::class,
            'exception.message' => $throwable->getMessage(),
        ]);
        $span->end();
    }

    /**
     * The two series' recording, best-effort: the match counting and
     * the match duration, sliced by the dimensions the router
     * series' own policy allows -- a meter that fails is contained,
     * never fatal, because the measurement is never worth the
     * match's own answer.
     */
    private function record(string $method, string $outcome, int $durationNs): void
    {
        try {
            $dimensions = [
                'http.request.method' => $method,
                'router.match.outcome' => $outcome,
            ];

            $this->meter->increment(self::MATCHES_TOTAL, 1, $dimensions);
            $this->meter->observe(
                self::MATCH_DURATION_SECONDS,
                $durationNs / self::NANOSECONDS_PER_SECOND,
                $dimensions,
            );
        } catch (Throwable) {
            // Best-effort: the metrics lane never replaces the match's answer.
        }
    }

    /**
     * The span's opening attributes: the request's own routing
     * surface -- the method, the path, and the host when the request
     * carried one -- kept deliberately minimal, the request span's
     * own three.
     *
     * @return array<string, string>
     */
    private function openingAttributes(ServerRequestMetadata $serverRequestMetadata): array
    {
        $attributes = [
            'http.request.method' => $serverRequestMetadata->method,
            'url.path' => $serverRequestMetadata->path,
        ];

        if ($serverRequestMetadata->host !== null) {
            $attributes['server.address'] = $serverRequestMetadata->host;
        }

        return $attributes;
    }

    /**
     * The outcome a match result settled on, in the explanation's
     * own closed vocabulary.
     */
    private function outcomeOf(MatchResult $matchResult): string
    {
        if ($matchResult->isFound()) {
            return RouteMatchExplanation::OUTCOME_FOUND;
        }

        if ($matchResult->isMethodNotAllowed()) {
            return RouteMatchExplanation::OUTCOME_METHOD_NOT_ALLOWED;
        }

        if ($matchResult->isNotAcceptable()) {
            return RouteMatchExplanation::OUTCOME_NOT_ACCEPTABLE;
        }

        return RouteMatchExplanation::OUTCOME_NOT_FOUND;
    }
}
