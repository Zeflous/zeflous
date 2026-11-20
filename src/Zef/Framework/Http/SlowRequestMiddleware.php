<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Slow-request middleware -- the total-duration boundary, the HTTP
 * layer's twentieth PSR-15 middleware and the observability map's
 * seventh family: the roadmap HTTP Phase 4.1 request-lifecycle
 * tracing's closing item, the automatic record a request whose
 * total duration exceeds the configured bar answers.
 *
 * An armed family ({@see SlowRequestPolicy::$enabled}) measures the
 * span from the CANONICAL start point -- the monotonic stamp the
 * ingress factory writes onto the request as its own decoration
 * ({@see RequestFactory::validateIngress()} stamps {@see
 * self::REQUEST_START_ATTRIBUTE} at the same seam the trusted
 * scheme fold runs, the earliest clock a request the application
 * processes can read: the span then covers the ingress guards, the
 * pipeline above this middleware, and the handler alike, one
 * apple-to-apple duration across deployments -- issue #801's
 * finding 4, the first spelling bracketing only the span BELOW this
 * middleware, placement-dependent and excluding the ingress and
 * every outer layer). A request that carries no stamp -- a foreign
 * PSR-7 request the ingress never decorated, or a host-built request
 * -- keeps the local bracket as the honest fallback, the span the
 * request spends inside the remaining pipeline, measured from the
 * clock seam ({@see TimingClock}, one named place the suite can
 * freeze) -- and hands the span to the record fold's verdict ({@see
 * SlowRequestRecord::whenExceeded()}, over the policy's own {@see
 * SlowRequestPolicy::exceededBy()} arithmetic: a span that exactly
 * meets the bar is not slow -- the roadmap's own "melebihi" -- held
 * in integer nanoseconds so the boundary is exact rather than a
 * float's neighbour; a broken clock seam answering a negative span
 * fabricates nothing).
 *
 * The record fires on BOTH outcomes, the one seam a finally block
 * owns: a request that answered slowly AND a request that threw
 * slowly -- the throw path is the pathology the family most exists
 * for (the hung dependency that ends in a 500), and the timing
 * family's "the span belongs to a response" doctrine does not
 * transfer here: this record is a server-side log line, not a
 * header stamped on a response, so there is a place for the span to
 * land even when the pipeline never answered. The throw itself
 * propagates untouched after the record.
 *
 * The record travels through {@see SlowRequestRecord} over the
 * shared {@see ErrorFaultLogWriter} containment: a logging failure
 * can never convert a slow-but-successful request into a failure --
 * the finally writes through a seam that contains its own.
 *
 * The measurement is conditional on the arm, not the other way
 * round: a disarmed family never reads the clock, the timing
 * family's own inert posture -- there is no observable difference
 * between "measured and discarded" and "never measured", and this
 * family does not pay for a distinction nobody can see.
 */
final readonly class SlowRequestMiddleware implements MiddlewareInterface
{
    /** The request attribute the canonical start stamp rides: the
     * monotonic nanoseconds the ingress factory's own decoration
     * writes, the earliest clock a request the application processes
     * can read (the total-duration family's canonical point). */
    public const string REQUEST_START_ATTRIBUTE = 'zef.http.request.start_ns';

    /**
     * The clock seam, one reading at each edge of the measured span.
     *
     * @var Closure():int
     */
    private Closure $nowNs;

    /**
     * @param SlowRequestPolicy    $slowRequestPolicy the family's frozen
     *                                                knob set
     * @param null|LoggerInterface $logger            the PSR-3 sink the
     *                                                slow-request records
     *                                                travel through; null
     *                                                falls back to the
     *                                                SAPI error log the
     *                                                record's own writer
     *                                                carries
     * @param null|(Closure():int) $nowNs             the monotonic clock
     *                                                seam, nanoseconds;
     *                                                defaults to the
     *                                                system monotonic
     *                                                clock ({@see
     *                                                TimingClock})
     */
    public function __construct(
        private SlowRequestPolicy $slowRequestPolicy,
        private ?LoggerInterface $logger = null,
        ?Closure $nowNs = null,
    ) {
        $this->nowNs = $nowNs ?? TimingClock::monotonicNs(...);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->slowRequestPolicy->enabled) {
            return $handler->handle($request);
        }

        return $this->measured($request, $handler);
    }

    /**
     * The measured path: the canonical start the ingress stamped when
     * the request carries one -- the total duration from the earliest
     * point the application processes -- and the local bracket when it
     * does not (the foreign request the ingress never decorated), the
     * verdict answered in the finally the throw path shares -- the
     * span exists either way, and the record is the one place it
     * lands.
     */
    private function measured(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $stampNs = $request->getAttribute(self::REQUEST_START_ATTRIBUTE);
        $startNs = \is_int($stampNs) ? $stampNs : ($this->nowNs)();

        try {
            return $handler->handle($request);
        } finally {
            SlowRequestRecord::whenExceeded(
                $this->logger,
                $request,
                ($this->nowNs)() - $startNs,
                $this->slowRequestPolicy,
            );
        }
    }
}
