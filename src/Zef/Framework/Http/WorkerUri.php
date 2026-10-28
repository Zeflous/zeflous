<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The effective request URI: the worker payload's own spelling, joined
 * to the Host authority when the payload carries only the origin form.
 *
 * A worker runtime hands the URI exactly as it read it off the wire:
 * the absolute form some runtimes reconstruct ({@see https://host/path})
 * or the origin form the request line itself carries
 * ({@see /path?query}). The absolute spelling rides as-is -- the wire's
 * own reconstruction wins, and a Host header that disagrees with it
 * rides the PSR-7 preserve-host rule verbatim, never silently
 * reconciled (the disagreement is the glue's own, and both spellings
 * stay observable). The origin form carries no authority, so the fold
 * joins one: the Host header's authority, parsed by the layer's own
 * {@see HostAuthority} grammar (bracketed IPv6, the non-default port),
 * answering the `localhost` authority when the payload describes
 * neither -- the same CLI-simulation posture the SAPI twin
 * {@see ServerUri} takes for a hostless server array.
 *
 * The joined scheme is the plain one the worker's own socket speaks:
 * TLS termination is upstream of every worker runtime, and honouring
 * `X-Forwarded-Proto` is a trusted-proxy policy decision the security
 * slices own -- never a default an extraction silently makes (the
 * {@see ServerUri} doctrine, replayed on the worker twin).
 */
final class WorkerUri
{
    /**
     * Answers the request URI the payload describes.
     *
     * @param Uri                            $payloadUri the payload's parsed URI
     * @param array<array-key, list<string>> $headers    the canonical header map
     *                                                   {@see WorkerHeaders::normalize()}
     *                                                   answers
     *
     * @return Uri the effective request URI
     *
     * @throws HttpException when the Host authority is malformed
     */
    public static function resolve(Uri $payloadUri, array $headers): Uri
    {
        if ($payloadUri->getAuthority() !== '') {
            return $payloadUri;
        }

        [$host, $authorityPort] = HostAuthority::parse(
            implode(', ', WorkerHeaders::values($headers, 'host')),
        );
        $hostLiteral = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $path = $payloadUri->getPath();
        $query = $payloadUri->getQuery();

        return new Uri('http://'
            . ($hostLiteral !== '' ? $hostLiteral : 'localhost')
            . ($authorityPort !== null ? ':' . $authorityPort : '')
            . $path
            . ($query !== '' ? '?' . $query : ''));
    }
}
