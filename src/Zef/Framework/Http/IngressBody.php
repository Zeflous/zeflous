<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The body half of the ingress gate: the input validation budget
 * enforced against a request that has already been constructed.
 *
 * The ingress path for persistent workers -- requests that did not pass
 * through the superglobal factory, so nobody opened their body under a
 * policy yet. Two judgements answer here before the read-ahead probe
 * takes over ({@see IngressProbe}): the OWS-trimmed `Content-Length`
 * claim ({@see RequestBodyPolicy::claimExceeds()}) and the body's own
 * size when it states one. A lying or absent size only decides WHICH
 * judgement throws; every one of them throws the same message.
 *
 * Attribution: legacy `zef-framework` `Adapters/Http/RequestFactory`
 * (`validateIngress()`'s body half, move-verbatim behaviour folded onto
 * the layer's single exception root with byte-identical message texts).
 */
final class IngressBody
{
    private const string BODY_TOO_LARGE = 'Request body exceeds configured size limit.';

    /**
     * Guards the request's body under the ingress policy.
     *
     * @param ServerRequestInterface $serverRequest     the request entering the
     *                                                  application ingress
     * @param RequestBodyPolicy      $requestBodyPolicy the ingress policy the
     *                                                  body serves
     *
     * @return ServerRequestInterface the request to dispatch -- the same
     *                                request, its body untouched, when the probe could
     *                                restore the cursor; a request whose body is the
     *                                probed contents when it could not
     *
     * @throws HttpException when the claim, the stated size, or the
     *                       delivered bytes exceed the policy
     */
    public static function guard(
        ServerRequestInterface $serverRequest,
        RequestBodyPolicy $requestBodyPolicy,
    ): ServerRequestInterface {
        if ($requestBodyPolicy->claimExceeds($serverRequest->getHeaderLine('Content-Length'))) {
            throw new HttpException(self::BODY_TOO_LARGE);
        }

        $size = $serverRequest->getBody()->getSize();

        if ($size !== null && $size > $requestBodyPolicy->maxBytes) {
            throw new HttpException(self::BODY_TOO_LARGE);
        }

        return IngressProbe::verify($serverRequest, $requestBodyPolicy);
    }
}
