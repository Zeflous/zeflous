<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The worker-aware response emitter: the runtime adapter's outbound
 * half for persistent-worker deployments -- the twin the inbound
 * {@see WorkerRequestFactory} answered on the other side of the request
 * cycle.
 *
 * Converts the application's PSR-7 response into the
 * {@see WorkerResponse} wire shape the deployment's glue hands its
 * runtime. The SAPI emitter's ordering and safeguards, replayed on the
 * wire shape: the body stream is rewound first (middleware that
 * inspects the body leaves a seekable stream's cursor at the end), a
 * lying Content-Length is reconciled away before any field reaches the
 * wire ({@see StatusFraming}), and every foreign field is re-judged
 * and skipped -- never trusted, never fatal -- when its name or value
 * fails the RFC 9110 grammars ({@see WorkerResponseHeaders}). The
 * status itself refuses fail-closed: a worker wire shape has no
 * implicit SAPI default to fall back on, so a foreign status outside
 * the 100..599 corridor is the programming defect the typed refusal
 * names, for the glue to catch and answer. The reason phrase rides the
 * skip-on-invalid doctrine: a phrase the status line's byte grammar
 * refuses answers the registered phrase for the code (or an empty
 * phrase) -- never the bytes that would corrupt any status-line
 * serialiser the runtime might run.
 *
 * @api
 */
final readonly class WorkerResponseEmitter
{
    /**
     * Answers the worker wire shape the response describes.
     *
     * @throws HttpException when the response's status is outside the
     *                       100..599 corridor, or an eligible body
     *                       stream is not readable
     */
    public function toWorker(ResponseInterface $response): WorkerResponse
    {
        $status = $response->getStatusCode();

        $reasonPhrase = $response->getReasonPhrase();
        if (!HttpStatusLine::phraseIsEmittable($reasonPhrase)) {
            $reasonPhrase = '';
        }

        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return new WorkerResponse(
            $status,
            HttpStatusLine::resolvePhrase($status, $reasonPhrase),
            WorkerResponseHeaders::acceptable(StatusFraming::honest($response, $status)->getHeaders()),
            WorkerResponseBody::read($response),
            $response->getProtocolVersion(),
        );
    }
}
