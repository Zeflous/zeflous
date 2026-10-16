<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Telemetry\SpanInterface;
use Zef\Framework\Telemetry\TraceContextPropagator;
use Zef\Framework\Telemetry\TracerInterface;

/**
 * The request span the propagation boundary opens: the one measured
 * operation the boundary owns -- the W3C parent extracted from the
 * inbound headers, the span opened under it with the request's own
 * semantic attributes, the answer's and the failure's records, and
 * the end.
 *
 * This is the reference's own telemetry prologue and epilogue as
 * one unit (legacy `zef-main` `Application::handle`: the header
 * reads, the parent extraction, the request-span attributes, the
 * response recording, the failure recording, the finally-end) --
 * the same decomposition the tracing core gave its span: the
 * lifecycle, the recording and the measurement are different
 * types. The boundary ({@see CorrelationMiddleware}) composes this
 * unit; the unit composes the tracer.
 *
 * The inbound pair is read through `getHeaderLine()` so a missing
 * or multi-value header degrades to one line the extractor's
 * grammar judges; the extraction's null (no parent, or a parent
 * the W3C grammar refuses) mints a fresh trace, which is the
 * honest answer to both. The failure's record is verbatim -- the
 * exception's type and message as given -- because the hygiene
 * lane that will sanitise these recording points is a later family
 * in the campaign, and recording what was given is the honest
 * contract until it lands.
 */
final readonly class CorrelationRequestSpan
{
    /** The request span's name -- the reference's own. */
    private const string REQUEST_SPAN_NAME = 'zef.http.request';

    /** The inbound identity header. */
    private const string TRACEPARENT_HEADER = 'traceparent';

    /** The inbound identity's companion header. */
    private const string TRACESTATE_HEADER = 'tracestate';

    /** The response echo's header -- the inbound identity's own name. */
    private const string ECHO_HEADER = 'traceparent';

    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    private function __construct(
        private SpanInterface $span,
    ) {
    }

    /**
     * Opens the request span under the request's inbound W3C parent.
     */
    public static function open(TracerInterface $tracer, ServerRequestInterface $serverRequest): self
    {
        return new self(
            $tracer->startSpan(
                self::REQUEST_SPAN_NAME,
                self::openingAttributes($serverRequest),
                TraceContextPropagator::extract(
                    $serverRequest->getHeaderLine(self::TRACEPARENT_HEADER),
                    self::traceState($serverRequest),
                ),
            ),
        );
    }

    /**
     * The span the boundary exposes on the request for the handler's
     * enrichment -- the same span whose record the answer and the
     * failure write.
     */
    public function span(): SpanInterface
    {
        return $this->span;
    }

    /**
     * The answer's record: the response's status as an attribute,
     * the lifecycle status folded from it -- a 5xx answer folds to
     * the error status, everything else to the ok one.
     */
    public function recordAnswer(ResponseInterface $response): void
    {
        $this->span->setAttribute('http.response.status_code', $response->getStatusCode());

        $status = 'OK';

        if ($response->getStatusCode() >= 500) {
            $status = 'ERROR';
        }

        $this->span->setStatus($status);
    }

    /**
     * The failure's record: the error status with the exception's
     * type as its description, and the exception event -- verbatim,
     * the hygiene lane that will sanitise it being a later family.
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
     * The response's echo: the span's own identity stamped as the
     * `traceparent` header -- only when the disclosure is armed, and
     * only when the identity is one worth propagating (the void
     * context of a disarmed tracer is refused, never echoed).
     *
     * @param bool $disclose the family's disclosure knob
     */
    public function echoed(ResponseInterface $response, bool $disclose): ResponseInterface
    {
        if (!$disclose) {
            return $response;
        }

        $spanContext = $this->span->getContext();

        if (!$spanContext->isValid()) {
            return $response;
        }

        $message = $response->withHeader(self::ECHO_HEADER, $spanContext->traceParent());

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }

    /**
     * The end: the span's own single handoff, the same close whether
     * the boundary answered through it or threw past it.
     */
    public function end(): void
    {
        $this->span->end();
    }

    /**
     * The span's opening attributes: the request's own semantic
     * surface, the reference's three -- the method, the path, the
     * host -- kept deliberately minimal.
     *
     * @return array<string, string>
     */
    private static function openingAttributes(ServerRequestInterface $serverRequest): array
    {
        return [
            'http.request.method' => $serverRequest->getMethod(),
            'url.path' => $serverRequest->getUri()->getPath(),
            'server.address' => $serverRequest->getUri()->getHost(),
        ];
    }

    /**
     * The inbound `tracestate` companion, null when the request
     * carried none -- the empty line the header read answers for an
     * absent header.
     */
    private static function traceState(ServerRequestInterface $serverRequest): ?string
    {
        $headerLine = $serverRequest->getHeaderLine(self::TRACESTATE_HEADER);

        if ($headerLine === '') {
            return null;
        }

        return $headerLine;
    }
}
