<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * Best-effort error metric emission isolated from the response path.
 *
 * The series' dimensions: the request method and the response status the
 * series' low-cardinality slices own, the exception type the errors total
 * is attributed by -- and the ROUTE the request landed on, read off the
 * error boundary's own context (the matched pattern the router's request
 * handler wrote, {@see ErrorRequestContext::route()}). A refusal that
 * carries no route (the unmatched 404, the pre-router middleware failure,
 * a custom stack that never minted a context) answers the CLOSED
 * `unrouted` spelling, never the raw request target: a path that carries
 * captured values is a series-per-path cardinality bomb and a privacy
 * leak the low-cardinality doctrine refuses -- the dimension is sliced
 * by the deployment's own route vocabulary, `unrouted` its honest fourth
 * word.
 */
final class ErrorFaultMetricsRecorder
{
    /** The closed spelling every unrouted refusal answers: no route landed. */
    private const string UNROUTED = 'unrouted';

    public static function record(
        ?MeterInterface $meter,
        Throwable $throwable,
        int $status,
        ServerRequestInterface $serverRequest,
    ): void {
        try {
            $meter?->increment('zef.http.errors.total', 1, [
                'http.request.method' => $serverRequest->getMethod(),
                'http.response.status_code' => $status,
                'exception.type' => self::exceptionType($throwable),
                'http.route' => self::routeDimension($serverRequest),
            ]);
        } catch (Throwable) {
            // Metrics are best-effort and must never replace the original failure.
        }
    }

    private static function exceptionType(Throwable $throwable): string
    {
        $class = $throwable::class;
        $separator = mb_strrpos($class, '\\');

        return $separator === false ? $class : mb_substr($class, $separator + 1);
    }

    /**
     * The route the request landed on, read off the boundary's context:
     * the matched pattern the router's request handler wrote, or the
     * closed unrouted spelling when no route did (and no pattern ever
     * reaches a metric the raw target could leak through).
     */
    private static function routeDimension(ServerRequestInterface $serverRequest): string
    {
        $errorRequestContext = ErrorRequestContext::fromAttribute(
            $serverRequest->getAttribute(ErrorRequestContext::ATTRIBUTE),
        );

        return $errorRequestContext?->route() ?? self::UNROUTED;
    }
}
