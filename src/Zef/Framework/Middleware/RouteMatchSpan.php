<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Throwable;
use Zef\Framework\Config\Routing\MatchResult;
use Zef\Framework\Config\Routing\RouteMatchExplanation;
use Zef\Framework\Telemetry\SpanInterface;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The match span the observability family opens: the one measured
 * operation the family owns -- the span opened under the request
 * span the correlation boundary exposed (a fresh trace when no
 * boundary carried one, the honest answer to both), the request's
 * own routing surface as the opening attributes, the answer's and
 * the failure's records, and the end.
 *
 * This is the {@see CorrelationRequestSpan} decomposition replayed
 * for the router's own operation: the lifecycle, the recording and
 * the measurement are different types, so the match span is its own
 * unit rather than a branch of the request span's -- the boundary
 * composes that unit, the telemetry ({@see RouteMatchTelemetry})
 * composes this one, and neither span knows the other exists.
 *
 * The status folds the outcome the way the request span folds the
 * answer: a found match is the ok one; the two refusals the table
 * itself gave (method not allowed, not acceptable) are the error
 * one, because a trace should surface a routing-layer refusal; a
 * not-found is left unset -- the void answer, neither success nor
 * failure. The failure's record is bounded by the hygiene lane that
 * has landed on the recording points the span owns: the exception's
 * type and message as given.
 *
 * @internal
 */
final readonly class RouteMatchSpan
{
    /** The match span's name, the request span's own grammar. */
    public const string MATCH_SPAN_NAME = 'zef.router.match';

    private function __construct(
        private SpanInterface $span,
    ) {
    }

    /**
     * Opens the match span under the request span the boundary
     * exposed on the request, carrying the request's own routing
     * surface -- kept deliberately minimal, the request span's own
     * three.
     *
     * The parent arrives as the boundary's attribute value reads it:
     * a foreign value under the attribute name is narrowed by the
     * interface's own contract, never trusted as a parent -- the
     * same guarded read {@see CorrelationMiddleware}'s boundary
     * performs for the request span it exposes.
     */
    public static function open(
        TracerInterface $tracer,
        mixed $parentSpan,
        ServerRequestMetadata $serverRequestMetadata,
    ): self {
        return new self(
            $tracer->startSpan(
                self::MATCH_SPAN_NAME,
                self::openingAttributes($serverRequestMetadata),
                $parentSpan instanceof SpanInterface ? $parentSpan->getContext() : null,
            ),
        );
    }

    /**
     * The answer's record: the outcome and (for a found match) the
     * route's own name as attributes, and the lifecycle status the
     * outcome folds to -- OK for a found match, ERROR for the two
     * table-given refusals, nothing for a not-found.
     */
    public function recordAnswer(MatchResult $matchResult): void
    {
        $outcome = RouteMatchExplanation::outcomeOf($matchResult);
        $attributes = ['router.match.outcome' => $outcome];

        if ($matchResult->isFound()) {
            $routeName = $matchResult->route()->name();
            if ($routeName !== null) {
                $attributes['router.route.name'] = $routeName;
            }

            $this->span->setStatus('OK');
        } elseif ($outcome !== RouteMatchExplanation::OUTCOME_NOT_FOUND) {
            $this->span->setStatus('ERROR', $outcome);
        }

        foreach ($attributes as $key => $value) {
            $this->span->setAttribute($key, $value);
        }
    }

    /**
     * The failure's record: the error status with the exception's
     * type as its description, and the exception event -- the
     * message as given, the same recording the request span owns.
     */
    public function recordFailure(Throwable $throwable): void
    {
        $this->span->setStatus('ERROR', $throwable::class);
        $this->span->addEvent('exception', [
            'exception.type' => $throwable::class,
            'exception.message' => $throwable->getMessage(),
        ]);
    }

    /**
     * The end: the span's own single handoff, the same close whether
     * the telemetry answered through it or threw past it.
     */
    public function end(): void
    {
        $this->span->end();
    }

    /**
     * The span's opening attributes: the request's own routing
     * surface -- the method, the path, and the host when the request
     * carried one.
     *
     * @return array<string, string>
     */
    private static function openingAttributes(ServerRequestMetadata $serverRequestMetadata): array
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
}
