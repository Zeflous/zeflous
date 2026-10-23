<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\TelemetryClockInterface;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The observed route match: the one unit the router's request
 * handler consults, so a match answers through the observability
 * engines the campaign already owns -- the tracing core's span and
 * the metrics lane's meter -- without the routing lane growing
 * either engine of its own.
 *
 * The unit composes the two recording halves the family owns: the
 * {@see RouteMatchSpan} lifecycle (the match's own timeline, opened
 * under the request span the correlation boundary exposed on the
 * request -- read here, guarded by the boundary's own attribute
 * contract, a fresh trace when no boundary carried one) and the
 * meter's two series, in the seconds domain the container lane's
 * resolve duration established: {@see self::MATCHES_TOTAL} counts
 * every observed match by its outcome and method (the closed
 * outcome vocabulary the explanation spells, the dimensions {@see
 * \Zef\Framework\Telemetry\MeterDimensions} allows the
 * `zef.router.*` series), and {@see
 * self::MATCH_DURATION_SECONDS} observes the match's own duration --
 * the route search time, the roadmap's own metric, measured once on
 * the telemetry clock's monotonic domain: the same window the span's
 * own timeline measures, one clock per consumer, no arithmetic
 * shared between the two engines.
 *
 * The recording is best-effort ({@see ErrorFaultMetricsRecorder}'s
 * doctrine): a meter that fails must never replace the match's own
 * answer, and a match that throws (the engine's contract says it
 * cannot, but the observation is armoured anyway) still ends its
 * span and still propagates the failure exactly as it arrived. A
 * disarmed tracer answers the void span to every call, so an armed
 * family over a disarmed engine records its metrics and harms
 * nothing -- and the factory's null ({@see
 * RouteObservabilityFactory}) is the family's honest off posture:
 * no allocation, no accounting.
 *
 * @internal
 */
final readonly class RouteMatchTelemetry
{
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
     * span the boundary exposed on the request, measures the match
     * on the telemetry clock's own monotonic domain, records the
     * outcome on both engines, and answers the match's own result.
     *
     * @param Closure(): MatchResult $match the match itself
     */
    public function observed(ServerRequestInterface $serverRequest, Closure $match): MatchResult
    {
        $serverRequestMetadata = ServerRequestMetadata::fromRequest($serverRequest);
        $routeMatchSpan = RouteMatchSpan::open(
            $this->tracer,
            $serverRequest->getAttribute(CorrelationMiddleware::SPAN_ATTRIBUTE),
            $serverRequestMetadata,
        );
        $startedNs = $this->telemetryClock->monotonicNs();

        try {
            $matchResult = $match();
        } catch (Throwable $throwable) {
            $routeMatchSpan->recordFailure($throwable);
            $routeMatchSpan->end();

            throw $throwable;
        }

        $durationNs = $this->telemetryClock->monotonicNs() - $startedNs;
        $outcome = RouteMatchExplanation::outcomeOf($matchResult);

        $routeMatchSpan->recordAnswer($matchResult);
        $routeMatchSpan->end();
        $this->record($serverRequestMetadata->method, $outcome, $durationNs);

        return $matchResult;
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
}
