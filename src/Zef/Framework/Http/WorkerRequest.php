<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The worker payload: the wire shape a persistent-worker runtime hands
 * the application, spelled in the layer's own vocabulary.
 *
 * The ROADMAP's worker-context adapter names RoadRunner and Swoole,
 * but the zero-dependency doctrine pins the shape: no runtime SDK type
 * may cross into `src/`, so the port is this vocabulary -- the payload
 * the deployment's own glue fills from whatever its runtime hands it
 * (the PSR-7 request a RoadRunner PSR7 client answers, the raw request
 * object Swoole's event loop delivers) and the layer converts through
 * its own validated seams ({@see WorkerRequestFactory}). The
 * port-first pattern the durable delivery store seeded, replayed on
 * the inbound side: the vocabulary is ours, the adapter is the
 * deployment's.
 *
 * Construction refuses what cannot become a request -- the
 * {@see DeliveryEnvelope} doctrine replayed: the method token is
 * uppercased and judged ({@see HttpMethod}, the single ingress boundary
 * the SAPI twin normalises at), the URI string is parsed and encoded
 * through the layer's own {@see Uri} (control bytes, the port range,
 * the percent-encoding), the header map is canonicalised by
 * {@see WorkerHeaders} (names judged, values the grammar's own string
 * lists), the protocol version judged by {@see HttpVersion} in its
 * PSR-7 spelling ("1.1", the form the worker's request objects carry),
 * and the peer pair judged by the connection folds ({@see
 * WorkerPeerAddress} the literal grammar, {@see WorkerConnection} the
 * port corridor and the entries) -- the empty
 * address normalised to absence (a runtime that cannot name its peer
 * hands the empty spelling), a port without its address refused.
 *
 * @api host applications and worker-runtime glue construct this payload
 *      from whatever their runtime hands them; the analysed tree never
 *      does (the zero-dependency doctrine keeps the runtime SDKs out)
 */
final readonly class WorkerRequest
{
    public string $method;

    public Uri $uri;

    /** @var array<array-key, list<string>> */
    public array $headers;

    /** @var non-empty-string */
    public string $protocolVersion;

    public ?string $remoteAddress;

    public ?int $remotePort;

    /**
     * @param array<array-key, mixed> $headers         the raw header map the runtime
     *                                                 hands over, names in any case,
     *                                                 values string, list or the
     *                                                 shape the grammar will refuse
     * @param string                  $body            the body payload, binary-safe
     * @param non-empty-string        $protocolVersion the PSR-7 spelling ("1.1")
     * @param null|string             $remoteAddress   the peer address literal,
     *                                                 empty or null when the runtime
     *                                                 cannot name its peer
     * @param null|int                $remotePort      the peer port, 1..65535
     *
     * @throws HttpException when the method, URI, header map, protocol
     *                       version or peer pair is rejected
     */
    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        public string $body = '',
        string $protocolVersion = '1.1',
        ?string $remoteAddress = null,
        ?int $remotePort = null,
    ) {
        $this->method = mb_strtoupper($method);
        HttpMethod::assert($this->method);
        $this->uri = new Uri($uri);
        $this->headers = WorkerHeaders::normalize($headers);
        HttpVersion::assert($protocolVersion);
        $this->protocolVersion = $protocolVersion;

        if ($remoteAddress !== null && $remoteAddress !== '') {
            $address = WorkerPeerAddress::judged($remoteAddress);
        } else {
            $address = null;
        }

        if ($remotePort !== null) {
            WorkerConnection::assertPeerPort($remotePort);

            if ($address === null) {
                throw new HttpException('A worker remote port without its address describes no peer.');
            }
        }

        $this->remoteAddress = $address;
        $this->remotePort = $remotePort;
    }
}
