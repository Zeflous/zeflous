<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The connection-info injector: the worker context's peer address and
 * port, answered as the precise server entries they own.
 *
 * A worker runtime knows its peer from the socket itself (the accept()
 * the runtime performed) -- not from a SAPI array the engine filled.
 * The ROADMAP's worker-context adapter names the injection "precise":
 * the two entries a peer connection owns are `REMOTE_ADDR` and
 * `REMOTE_PORT` (the spellings the layer's own downstream readers look
 * for -- {@see ClientAddress} reads the address, the trusted-proxy
 * boundary walks it), and NOTHING else is invented: no fake
 * `REQUEST_METHOD` or `REQUEST_URI` entries, because the wire's own
 * shape already lives in the request's own API
 * ({@see ServerRequest::getMethod()}, the URI), and a synthetic SAPI
 * array would hand one request two spellings of the same fact.
 *
 * The port is held to its 1..65535 corridor -- a peer port is never
 * zero. Absence stays absence: a runtime that cannot name its peer (a
 * unix-socket peer, a synthetic request) contributes no entry rather
 * than a placeholder value, and a port stated without its address
 * describes no peer at all (no socket names a peer's port while losing
 * its address).
 */
final class WorkerConnection
{
    private const int PORT_CEILING = 65535;

    /**
     * Judges a peer port against its corridor.
     *
     * @param int $port the worker context's remote port
     *
     * @throws HttpException when the port is zero, negative or above
     *                       the 65535 ceiling
     */
    public static function assertPeerPort(int $port): void
    {
        if ($port < 1 || $port > self::PORT_CEILING) {
            throw new HttpException("Invalid worker remote port {$port}.");
        }
    }

    /**
     * Answers the server entries the peer connection owns.
     *
     * @param null|string $address the validated bare address literal,
     *                             null when the runtime cannot name its
     *                             peer
     * @param null|int    $port    the validated peer port, null when the
     *                             runtime cannot name it
     *
     * @return array<string, int|string> the `REMOTE_ADDR` and
     *                                   `REMOTE_PORT` entries, empty when
     *                                   neither is known
     *
     * @throws HttpException when a port is stated without its address
     */
    public static function entries(?string $address, ?int $port): array
    {
        if ($address === null) {
            if ($port !== null) {
                throw new HttpException('A worker remote port without its address describes no peer.');
            }

            return [];
        }

        return $port === null
            ? ['REMOTE_ADDR' => $address]
            : ['REMOTE_ADDR' => $address, 'REMOTE_PORT' => $port];
    }
}
