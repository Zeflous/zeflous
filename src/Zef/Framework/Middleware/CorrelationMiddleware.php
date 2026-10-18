<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zef\Framework\Http\CorrelationPolicy;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * Correlation middleware -- the propagation boundary, the HTTP
 * stack's tenth PSR-15 middleware and the observability campaign's
 * third family: the seam where an inbound request's W3C
 * trace-context becomes the request span's parent, and the span's
 * own identity becomes the response's echo.
 *
 * THE BOUNDARY IS THE COMPOSITION, and that is why this middleware
 * lives in the middleware lane rather than the HTTP layer every
 * other family's middleware lives in: the boundary's every request
 * answers through the tracing engine ({@see CorrelationRequestSpan}
 * composes the tracer face-first) -- it is the one middleware whose
 * face is not its own -- and the HTTP layer stays engine-free
 * exactly as the tracing core stays config-free. The composition
 * lane is where the two already meet ({@see TracingFactory}); the
 * propagation boundary joins them there.
 *
 * The request span's whole lifecycle is the {@see
 * CorrelationRequestSpan} unit's -- the opening under the inbound
 * parent, the answer's record, the failure's record, the answer's
 * echo, the end -- and the boundary itself owns only the frame:
 * the family's arm, the request the span is exposed on, the
 * failure's untouched propagation. A handler that throws travels
 * past the boundary as it arrived; the span still ends, in the
 * finally, exactly once either way.
 *
 * SECURE DEFAULT ({@see CorrelationPolicy}): both the family's arm
 * and its echo ship OFF. The arm is off because an armed boundary
 * over a disarmed tracer is work nobody can observe -- the void
 * span ignores every recording call, and the boundary cannot check
 * the tracer's arm without breaking the interchangeability the
 * tracing core is built on. The echo is off because a response's
 * `traceparent` is a disclosure -- it tells the client the server
 * traces and hands it the server span's identity -- and a
 * disclosure knob's safe default is the one that discloses
 * nothing. The echo also refuses the void context outright: a
 * disarmed tracer's void span must never stamp the all-zero
 * identity on a response, so the echo reads the identity's
 * validity first ({@see SpanInterface::getContext()}'s own
 * isValid) -- the same refusal every consumer that would
 * propagate the void identity carries.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Application.php`, the handle()'s telemetry prologue
 * and epilogue): the lifecycle moves as the family's own classes
 * (the {@see CorrelationRequestSpan} unit, verbatim in behaviour);
 * the port's deviation is recorded in the campaign log -- the
 * duration attribute is dropped, because the span's own timeline
 * already measures it and a second inline clock pair would
 * duplicate the one measurement the record owns.
 *
 * The export family's cadence lands here, at the boundary that
 * owns the request span's lifecycle ({@see CorrelationRequestSpan}'s
 * own record): when the deployment's flush-per-request knob is
 * armed, the finally drains the collection after the span's
 * record has joined the queue -- the span ends first, the drain
 * delivers second, the ordering the one finally block owns. The
 * knob ships off ({@see \Zef\Framework\Http\ExportPolicy}'s own
 * record): a per-request flush puts the delivery's own latency
 * inside the request path, the third fail-closed decision in the
 * campaign's chain, and the drain the boundary triggers is the
 * tracer's own face ({@see TracerInterface::flush()}), never the
 * processor the boundary does not hold.
 */
final readonly class CorrelationMiddleware implements MiddlewareInterface
{
    /** The request attribute the span is exposed under. */
    public const string SPAN_ATTRIBUTE = 'zef.observability.span';

    /**
     * @param CorrelationPolicy $correlationPolicy the family's frozen knob set
     * @param TracerInterface   $tracer            the tracing engine whose
     *                                             spans the boundary opens
     * @param bool              $flushPerRequest   the export family's drain
     *                                             cadence -- the finally's
     *                                             flush, armed by the
     *                                             deployment
     */
    public function __construct(
        private CorrelationPolicy $correlationPolicy,
        private TracerInterface $tracer,
        private bool $flushPerRequest = false,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->correlationPolicy->enabled) {
            return $handler->handle($request);
        }

        return $this->correlated($request, $handler);
    }

    /**
     * The correlated path: open the request span under the inbound
     * parent, expose it, answer through it.
     */
    private function correlated(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $correlationRequestSpan = CorrelationRequestSpan::open($this->tracer, $request);
        $span = $correlationRequestSpan->span();
        $errorRequestContext = $request->getAttribute(ErrorRequestContext::ATTRIBUTE);
        $spanContext = $span->getContext();

        if ($errorRequestContext instanceof ErrorRequestContext && $spanContext->isValid()) {
            $errorRequestContext->setTraceId($spanContext->traceId);
        }

        try {
            $response = $handler->handle($request->withAttribute(self::SPAN_ATTRIBUTE, $span));
            $correlationRequestSpan->recordAnswer($response);

            return $correlationRequestSpan->echoed($response, $this->correlationPolicy->echoTraceParent);
        } catch (Throwable $throwable) {
            $correlationRequestSpan->recordFailure($throwable);

            throw $throwable;
        } finally {
            $correlationRequestSpan->end();

            if ($this->flushPerRequest) {
                $this->tracer->flush();
            }
        }
    }
}
