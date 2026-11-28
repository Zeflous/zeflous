<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The worker answer: the wire shape the application hands its
 * persistent-worker runtime back, spelled in the layer's own vocabulary.
 *
 * The inbound twin ({@see WorkerRequest}) named the doctrine and the
 * zero-dependency pin: no runtime SDK type may cross into `src/`, so the
 * outbound port is this vocabulary -- the payload the deployment's own
 * glue answers to its runtime (the fields RoadRunner's PSR-7 response
 * carries, the `$response->status()/header()/end()` trio Swoole's event
 * loop consumes), built from the application's PSR-7 response through
 * {@see WorkerResponseEmitter} or by the glue's own hand.
 *
 * Construction refuses what cannot become a response -- the
 * {@see WorkerRequest} doctrine replayed on the outbound side: the status
 * is judged by the status-line grammar ({@see HttpStatusLine}, the same
 * 100..599 corridor the layer's own {@see Response} constructor holds),
 * the reason phrase by the status line's byte grammar (CR, LF and NUL
 * refused before they can ride a status line any runtime would
 * serialise), the header map canonicalised by {@see WorkerHeaders} --
 * the one canonical form the worker lane answers both directions
 * through, names lowercased and values the grammar's own string lists --
 * and the protocol version judged by {@see HttpVersion} in its PSR-7
 * spelling ("1.1", the form the worker's request objects carry). The
 * body rides as the binary-safe string the wire shape is: no length
 * claim to reconcile, the payload's own bytes the whole truth.
 *
 * @api host applications and worker-runtime glue construct this payload
 *      for the runtime they own; the analysed tree composes the SAPI twin
 *      instead (the zero-dependency doctrine keeps the runtime SDKs out)
 */
final readonly class WorkerResponse
{
    public int $status;

    public string $reasonPhrase;

    /** @var array<array-key, list<string>> */
    public array $headers;

    /** @var non-empty-string */
    public string $protocolVersion;

    /**
     * @param int                     $status          the HTTP status code, 100..599
     * @param string                  $reasonPhrase    the status-line phrase, empty
     *                                                 when the wire answers none
     * @param array<array-key, mixed> $headers         the raw header map, names in
     *                                                 any case, values string, list
     *                                                 or the shape the grammar will
     *                                                 refuse
     * @param string                  $body            the body payload, binary-safe
     * @param non-empty-string        $protocolVersion the PSR-7 spelling ("1.1")
     *
     * @throws HttpException when the status, reason phrase, header map or
     *                       protocol version is rejected
     */
    public function __construct(
        int $status,
        string $reasonPhrase = '',
        array $headers = [],
        public string $body = '',
        string $protocolVersion = '1.1',
    ) {
        HttpStatusLine::assertCode($status);
        $this->status = $status;
        HttpStatusLine::assertPhrase($reasonPhrase);
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = WorkerHeaders::normalize($headers);
        HttpVersion::assert($protocolVersion);
        $this->protocolVersion = $protocolVersion;
    }
}
