<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request-side half of the HEAD method's identity rule (RFC 9110
 * section 9.3.2): a HEAD request executes the handler's GET logic.
 *
 * The judgment is an exact-token comparison: RFC 9110 section 9.1
 * declares method names case-sensitive, so only the registered `HEAD`
 * spelling reissues as `GET` -- a lowercase `head` is a different,
 * unregistered token that passes through untouched. The reference
 * implementation (legacy `zef-framework`
 * `Adapters.Http.HttpRequestRunner`) accepted any case-fold of `HEAD`
 * through `strtoupper()`; this shim narrows the gate to the exact
 * token. That narrowing also keeps the comparison outside the Unicode
 * case-map's immortal zone: the `HEAD` alphabet (H, E, A, D) is
 * reachable from no non-ASCII character's case-fold, so a
 * `mb_strtoupper()` gate here could never be distinguished from
 * `strtoupper()` by any test (KDD-H18 in
 * `docs/ci-notes/http-layer.md`), while the exact-token gate carries
 * its own kill channel -- the lowercase pass-through pin.
 */
final class HeadRequest
{
    /**
     * Whether the request carries the registered HEAD token.
     */
    public static function isHead(ServerRequestInterface $serverRequest): bool
    {
        return $serverRequest->getMethod() === 'HEAD';
    }

    /**
     * Reissues the request under the GET method: every other component
     * -- the URI, the headers, the bags, the attributes -- travels with
     * the request untouched, so a handler written for GET answers HEAD
     * without knowing the method was folded (the roadmap's "execute the
     * logic" half; the body's fate is decided on the way out, see
     * {@see HeadResponse}).
     */
    public static function asGet(ServerRequestInterface $serverRequest): ServerRequestInterface
    {
        $request = $serverRequest->withMethod('GET');
        \assert($request instanceof ServerRequestInterface, 'withMethod() must preserve the request type.');

        return $request;
    }
}
