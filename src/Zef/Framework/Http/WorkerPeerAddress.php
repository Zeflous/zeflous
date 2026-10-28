<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The peer-address fold: the grammar a worker context's remote address
 * carries, judged by the engine's own literal validator.
 *
 * A worker runtime names its peer with the accept() it performed -- an
 * IPv4 dotted literal or an IPv6 literal, the two spellings
 * `filter_var()` with the IP flags judges (the same trust class as the
 * {@see parse_url()} the URI fold rides). A bracketed IPv6 literal
 * normalises to its bare form (the bracket is the URI authority's
 * spelling of the same literal; `REMOTE_ADDR` carries the bare one the
 * SAPI itself would), and the bare literal lowercases for the IPv6
 * letters the engine's validator accepts in both cases -- the one
 * canonical spelling every downstream reader ({@see ClientAddress},
 * the trusted-proxy boundary) compares against.
 *
 * The fold is ASCII by construction -- the RedactionCandidate
 * measurement argument: a literal that survives the engine's judgment
 * carries ASCII bytes only, so the character map and the byte map agree
 * on every string the fold can ever answer.
 */
final class WorkerPeerAddress
{
    /**
     * Judges a peer address literal, answering its canonical bare form.
     *
     * @param string $address the worker context's remote address; a
     *                        bracketed IPv6 literal is unbracketed
     *
     * @return string the bare address literal, lowercased for the IPv6
     *                letters
     *
     * @throws HttpException when the address is not an IPv4 or IPv6
     *                       literal
     */
    public static function judged(string $address): string
    {
        $bare = str_starts_with($address, '[') && str_ends_with($address, ']')
            ? mb_substr($address, 1, -1)
            : $address;

        if (filter_var($bare, \FILTER_VALIDATE_IP) === false) {
            throw new HttpException("Invalid worker remote address '{$address}'.");
        }

        return mb_strtolower($bare);
    }
}
