<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Timing middleware -- the response-time boundary, the HTTP layer's
 * ninth PSR-15 middleware and the observability campaign's first
 * family.
 *
 * An armed family ({@see TimingPolicy::$enabled} with {@see
 * TimingPolicy::$emitHeader}) measures the monotonic span its
 * request spends inside the remaining pipeline and answers it as the
 * {@see ResponseTime::HEADER} the response carries -- replace, not
 * append: an inner span the handler's own decoration stamped is the
 * narrower measurement, and the outer one is the truth the client
 * sees. A handler that throws propagates untouched -- the span
 * belongs to a response, and there is none to stamp.
 *
 * The measurement is conditional on the disclosure, not the other
 * way round: a family that will not emit never reads the clock, so
 * the inert posture (armed, header off -- the shipped default)
 * performs no work at all -- there is no observable difference
 * between "measured and discarded" and "never measured", and this
 * family does not pay for a distinction nobody can see.
 *
 * SECURE DEFAULT ({@see TimingPolicy}): the header ships OFF -- a
 * server-side execution timing is an attacker-visible side channel,
 * a full-precision oracle against blind attacks (the reference's own
 * ZEF-DX-08 posture: the header stays ON only in debug mode, where
 * it is a legitimate development signal, and a deployment forces it
 * either way explicitly). The reference's demo constructor defaulted
 * it on; this port's file-first profile defaults it to the
 * production posture, because a disclosure knob's safe default is
 * the one that discloses nothing.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Middleware/TimingMiddleware.php`): the `%.2fms` shape contract
 * and the monotonic measurement are move-verbatim in the family's
 * own classes; restructured -- the clock is one injectable seam the
 * suite can freeze ({@see TimingClock}, two inline `hrtime()` reads
 * in the reference), and the render lives in the {@see ResponseTime}
 * value object rather than inline in the middleware, where the
 * mutation run cannot reach it.
 */
final readonly class TimingMiddleware implements MiddlewareInterface
{
    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    /** The clock seam, one reading at each edge of the measured span.
     *
     * @var Closure():int
     */
    private Closure $nowNs;

    /**
     * @param TimingPolicy         $timingPolicy the family's frozen knob
     *                                           set
     * @param null|(Closure():int) $nowNs        the monotonic clock seam,
     *                                           nanoseconds; defaults to
     *                                           the system monotonic
     *                                           clock ({@see TimingClock})
     */
    public function __construct(
        private TimingPolicy $timingPolicy,
        ?Closure $nowNs = null,
    ) {
        $this->nowNs = $nowNs ?? TimingClock::monotonicNs(...);
    }

    #[Override]
    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        if (!$this->timingPolicy->enabled || !$this->timingPolicy->emitHeader) {
            return $requestHandler->handle($serverRequest);
        }

        return $this->measured($serverRequest, $requestHandler);
    }

    /**
     * The measured path: bracket the remaining pipeline's answer
     * between two monotonic readings and stamp the span it consumed
     * as the response-time header.
     */
    private function measured(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        $startNs = ($this->nowNs)();
        $response = $requestHandler->handle($serverRequest);

        $message = $response->withHeader(
            ResponseTime::HEADER,
            ResponseTime::fromElapsedNs(($this->nowNs)() - $startNs)->value,
        );

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }
}
