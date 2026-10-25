<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * The slow-request family's record fold: the verdict and the record --
 * whether a span earned a record at all, and the bounded,
 * privacy-by-default context the record it earned carries, written
 * through the shared {@see ErrorFaultLogWriter} containment -- the
 * diagnostic lane's own twin over the family's own facts.
 *
 * The bounds and the privacy posture are the error lane's own
 * ({@see DiagnosticContextFactory}, `docs/error-handling.md`,
 * privacy-by-default): the request facts are exactly method and
 * path -- user identifiers, IP addresses, user-agent strings, query
 * strings and request bodies are deliberately excluded -- with the
 * path capped at the same 2048 characters. The correlation
 * identifier rides the request's own claim grammar ({@see
 * CorrelationId::for()}): a request that claimed an `X-Request-ID`
 * records under the claim the response echoes, so the slow-request
 * record joins the error lane's records on the same identifier;
 * a request that claimed none records under a fresh one, exactly
 * as the error lane's own records do. A trace id is deliberately
 * absent for the same reason the diagnostic lane's records omit
 * it: the record correlates through the request id alone.
 *
 * The two duration facts are the family's own: the elapsed
 * milliseconds (rendered at the two-decimal precision the timing
 * family's header established, the number a log consumer graphs)
 * and the threshold the request was judged against (the number an
 * operator reads to know which knob was in force when the record
 * was written).
 */
final class SlowRequestRecord
{
    /** The record's severity: a slow request is an operational warning,
     * not a failure -- the error lane owns failures. */
    private const string LEVEL = 'warning';

    /** The record's message: the family's own spelling, so a stored
     * record's origin is readable without its context ({@see
     * ErrorFaultLogWriter}'s own message-lane doctrine). */
    private const string MESSAGE = 'Slow request';

    /** The request-fact cap the error lane's own records carry. */
    private const int FACT_BOUND = 2048;

    /**
     * The conditional write: the span that strictly exceeds the bar
     * answers its record, the span that meets or misses the bar
     * answers nothing -- the one question (does this span earn a
     * record?) and its answer (the record itself) in one fold, so the
     * boundary that brackets the span never re-derives the bar's own
     * arithmetic.
     *
     * @param null|LoggerInterface   $logger            the PSR-3 sink the record
     *                                                  travels through; null falls
     *                                                  back to the SAPI error log
     *                                                  the writer owns
     * @param ServerRequestInterface $serverRequest     the request that was
     *                                                  measured
     * @param int                    $elapsedNs         the request's total
     *                                                  monotonic span, in
     *                                                  nanoseconds
     * @param SlowRequestPolicy      $slowRequestPolicy the bar the span was
     *                                                  judged against
     */
    public static function whenExceeded(
        ?LoggerInterface $logger,
        ServerRequestInterface $serverRequest,
        int $elapsedNs,
        SlowRequestPolicy $slowRequestPolicy,
    ): void {
        if (!$slowRequestPolicy->exceededBy($elapsedNs)) {
            return;
        }

        self::write($logger, $serverRequest, $elapsedNs, $slowRequestPolicy->thresholdMs);
    }

    /**
     * Writes one slow-request record through the containment seam.
     *
     * @param null|LoggerInterface   $logger        the PSR-3 sink the record
     *                                              travels through; null falls
     *                                              back to the SAPI error log
     *                                              the writer owns
     * @param ServerRequestInterface $serverRequest the request that was slow
     * @param int                    $elapsedNs     the request's total
     *                                              monotonic span, in
     *                                              nanoseconds (a positive
     *                                              value: the verdict only
     *                                              records spans that strictly
     *                                              exceed the bar)
     * @param int                    $thresholdMs   the bar the span was judged
     *                                              against, in milliseconds
     */
    private static function write(
        ?LoggerInterface $logger,
        ServerRequestInterface $serverRequest,
        int $elapsedNs,
        int $thresholdMs,
    ): void {
        ErrorFaultLogWriter::write(
            $logger,
            self::LEVEL,
            [
                'request_id' => CorrelationId::for($serverRequest),
                'method' => $serverRequest->getMethod(),
                'path' => mb_substr($serverRequest->getUri()->getPath(), 0, self::FACT_BOUND),
                'elapsed_ms' => round($elapsedNs / 1000000, 2),
                'threshold_ms' => $thresholdMs,
            ],
            self::MESSAGE,
        );
    }
}
