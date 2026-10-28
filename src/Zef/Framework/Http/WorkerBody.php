<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The payload-to-shim converter: the worker's body payload as the
 * request's body stream.
 *
 * A worker runtime hands the body as one resident string -- the
 * protocol already carried it into memory -- so the stream is built
 * over `php://memory`, never a temp file: the ROADMAP's own demand
 * ({@see ROADMAP.md}, "Worker Context Adapter"), because a `php://temp`
 * spill past its threshold would copy the payload to disk for zero
 * memory win (the string is already resident; the spill only adds I/O)
 * while the ingress policy's own byte bound keeps the stream's size
 * bounded by construction.
 *
 * The budget doctrine the SAPI twin states -- "a lying or absent
 * header only decides WHICH half throws" -- collapses here into ONE
 * total judgment: the SAPI's lazy half ({@see LimitedInputStream}
 * policing the read) exists for a body whose true size the boundary
 * cannot state ({@see php://input} hides it behind the claim), but the
 * payload's byte length is known before the stream is even opened. The
 * claim is judged eagerly ({@see RequestBodyPolicy::claimExceeds()},
 * the OWS-trimmed `Content-Length` the header map carries), the true
 * length is judged eagerly (the stated-size judgment
 * {@see IngressBody} performs on a body that states its size), and both
 * lie directions -- a claim inflated over the policy, a claim deflated
 * under an over-budget payload -- throw the same typed refusal at the
 * boundary. There is no lazy half left to police, so none is wrapped:
 * a wrapper that can never trigger is dead logic, and the layer does
 * not carry it.
 */
final class WorkerBody
{
    /**
     * Answers the bounded body stream over the payload string.
     *
     * @param string            $payload           the body payload the worker
     *                                             runtime handed over
     * @param string            $claim             the `Content-Length` value the
     *                                             header map carries, empty when
     *                                             the wire stated none
     * @param RequestBodyPolicy $requestBodyPolicy the ingress policy the body
     *                                             serves
     *
     * @return Stream the readable, seekable, memory-resident body
     *                stream, rewound to its start
     *
     * @throws PayloadTooLargeException when the claim or the payload's
     *                                  own byte length exceeds the
     *                                  policy
     */
    public static function bounded(string $payload, string $claim, RequestBodyPolicy $requestBodyPolicy): Stream
    {
        if ($requestBodyPolicy->claimExceeds($claim)) {
            throw new PayloadTooLargeException();
        }

        if (mb_strlen($payload, '8bit') > $requestBodyPolicy->maxBytes) {
            throw new PayloadTooLargeException();
        }

        $stream = new Stream(StreamOpener::open('php://memory', 'w+b'));
        $stream->write($payload);
        $stream->rewind();

        return $stream;
    }
}
