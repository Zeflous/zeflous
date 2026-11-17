<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The trusted-proxy half of the request's effective scheme: the
 * `X-Forwarded-Proto` claim, honoured only when the peer that delivered
 * the request is a {@see TrustedProxyMatcher} trusted proxy.
 *
 * The TLS-terminating proxy topology (#797): the proxy answers the
 * client's TLS while forwarding plain http to the application, so the
 * request's own HTTPS state says `http` and every scheme-derived
 * security posture -- the `Secure` cookie attribute, HSTS -- silently
 * disarms behind the deployment's own TLS. The forwarded protocol claim
 * is the proxy's own statement about the hop the client actually rode,
 * and honouring it is the same trusted-proxy policy decision the
 * forwarded Host ({@see TrustedRequestHost}) and the forwarded For
 * ({@see ClientAddress}) already carry: an untrusted peer's claim is
 * never read, and of the comma-separated appended list only the last
 * value can claim trust -- the walk and the vocabulary judgement the
 * claim's own text carries live in {@see ForwardedProtocolClaim}.
 *
 * The claim is operator data, not client input: a trusted proxy claiming
 * a value that is neither `http` nor `https` refuses loudly with the
 * value named -- a proxy the operator vouched for spelling nonsense is a
 * deployment defect the operator must see, never a silent `http` that
 * disarms the security posture the claim was supposed to arm.
 */
final class TrustedRequestScheme
{
    /**
     * Answers the request's effective scheme: the trusted proxy's
     * forwarded protocol claim when the peer is trusted and claims one,
     * the request's own URI scheme otherwise.
     *
     * @param ServerRequestInterface $serverRequest  the request entering the
     *                                               application ingress
     * @param list<string>           $trustedProxies the trusted proxy entries
     *                                               (exact addresses or CIDR networks)
     *
     * @return 'http'|'https' the effective scheme
     *
     * @throws HttpException when a trusted peer's claim is neither
     *                       `http` nor `https`
     */
    public static function effective(
        ServerRequestInterface $serverRequest,
        array $trustedProxies,
    ): string {
        if ($trustedProxies === [] || !$serverRequest->hasHeader('X-Forwarded-Proto')) {
            return self::own($serverRequest);
        }

        $remote = ServerValues::text($serverRequest->getServerParams(), 'REMOTE_ADDR', '');

        if (!TrustedProxyMatcher::matches($remote, $trustedProxies)) {
            return self::own($serverRequest);
        }

        return ForwardedProtocolClaim::effective($serverRequest->getHeaderLine('X-Forwarded-Proto'));
    }

    /**
     * Applies the effective scheme to the request's URI: the forwarded
     * claim replaces the locally derived scheme only when a trusted peer
     * claims one, so the request the ingress answers carries the scheme
     * every downstream consumer -- the session and CSRF cookie builders'
     * `Secure` attribute, the security headers' HSTS arm -- derives its
     * posture from.
     *
     * @param ServerRequestInterface $serverRequest  the request entering the ingress
     * @param list<string>           $trustedProxies the trusted proxy entries
     *
     * @return ServerRequestInterface the request carrying the effective scheme
     *
     * @throws HttpException when a trusted peer's claim is neither
     *                       `http` nor `https`
     */
    public static function apply(
        ServerRequestInterface $serverRequest,
        array $trustedProxies,
    ): ServerRequestInterface {
        $effective = self::effective($serverRequest, $trustedProxies);

        if ($effective === self::own($serverRequest)) {
            return $serverRequest;
        }

        $request = $serverRequest->withUri($serverRequest->getUri()->withScheme($effective));
        \assert($request instanceof ServerRequestInterface, 'withUri() must preserve the request type.');

        return $request;
    }

    /**
     * The request's own URI scheme, narrowed to the two spellings the
     * layer's requests carry.
     *
     * @return 'http'|'https'
     */
    private static function own(ServerRequestInterface $serverRequest): string
    {
        $scheme = $serverRequest->getUri()->getScheme();

        return $scheme === 'https' ? 'https' : 'http';
    }
}
