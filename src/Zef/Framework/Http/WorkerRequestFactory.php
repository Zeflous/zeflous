<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Builds the inbound server request from a worker runtime's payload.
 *
 * The runtime adapter's worker half -- the ROADMAP's "Worker Context
 * Adapter" -- and the twin of the SAPI extraction
 * {@see RequestFactory::fromServer()} in the inbound direction: where
 * the SAPI twin reads the engine's process state (the superglobals the
 * engine filled, `php://input` the engine owns), this factory reads
 * the wire shape {@see WorkerRequest} spells and answers the same
 * {@see ServerRequest} the application's stack dispatches -- the one
 * composition every runtime mode funnels into, so the middleware lane
 * never learns which runtime carried the request in.
 *
 * The bags the SAPI engine decodes are derived from the wire itself,
 * the derivations the engine would have performed: the query bag from
 * the URI's query component ({@see WorkerQuery}, the engine's own
 * decode), the cookie bag from the `Cookie` header values
 * ({@see WorkerCookies}, the reference's own pair grammar), the
 * effective URI from the payload's spelling joined to the Host
 * authority ({@see WorkerUri}). The parsed body deliberately starts
 * null for EVERY content type -- the worker has no engine-decoded form
 * bag the SAPI's `$_POST` twin would carry, so the decode belongs
 * downstream where {@see FormBodyParser} already owns the
 * url-encoded non-SAPI path and the body-parser middleware's snapshot
 * restores the cursor for every other format. The upload tree starts
 * empty: a worker protocol that ever carries parsed uploads widens the
 * payload with its own upload bag, and until then the multipart DoS
 * posture is the ingress budget plus the glue's own ceilings.
 *
 * The produced request still answers the ingress guard
 * {@see RequestFactory::validateIngress()} the deployment may run:
 * the trusted-host half reads the effective host, the body half a
 * stream that states its size -- the same protections, replayed on a
 * request no superglobal built.
 *
 * @api host applications and worker-runtime glue call this factory from
 *      their own event loops; the analysed tree composes the SAPI twin
 *      instead (the debugging utilities' inspection boot), so the calls
 *      live outside it
 */
final class WorkerRequestFactory
{
    /**
     * Builds the request from a worker payload.
     *
     * @param WorkerRequest          $workerRequest     the wire shape the
     *                                                  worker runtime's glue
     *                                                  filled
     * @param null|RequestBodyPolicy $requestBodyPolicy the ingress policy for
     *                                                  the request body; null
     *                                                  answers the default
     *                                                  two-mebibyte limit
     *
     * @return ServerRequest the inbound request the payload describes
     *
     * @throws PayloadTooLargeException when the payload's body claim or
     *                                  byte length exceeds the ingress
     *                                  policy
     * @throws HttpException            when the Host authority the origin
     *                                  form would join is malformed
     */
    public static function fromWorker(
        WorkerRequest $workerRequest,
        ?RequestBodyPolicy $requestBodyPolicy = null,
    ): ServerRequest {
        $requestBodyPolicy ??= new RequestBodyPolicy();
        $stream = WorkerBody::bounded(
            $workerRequest->body,
            WorkerHeaders::values($workerRequest->headers, 'content-length')[0] ?? '',
            $requestBodyPolicy,
        );
        $uri = WorkerUri::resolve($workerRequest->uri, $workerRequest->headers);

        /** @var array<string, string> $cookieBag the cookie bag (keys fold: the engine's numeric-string claim) */
        $cookieBag = WorkerCookies::bag(WorkerHeaders::values($workerRequest->headers, 'cookie'));

        /** @var array<string, mixed> $queryBag the query bag (keys fold: the engine's numeric-string claim) */
        $queryBag = WorkerQuery::bag($uri->getQuery());

        return new ServerRequest(
            $workerRequest->method,
            $uri,
            WorkerConnection::entries($workerRequest->remoteAddress, $workerRequest->remotePort),
            $cookieBag,
            $queryBag,
            [],
            null,
            $workerRequest->headers,
            $stream,
            $workerRequest->protocolVersion,
        );
    }
}
