<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The trusted-host half of the ingress gate: both the URI host and the
 * effective Host-header authority must be trusted before a request
 * passes the application ingress.
 *
 * PSR-7 allows the Host header to differ from the URI, so a single
 * judgement is not enough -- the URI host is judged first (an empty one
 * is a missing host, never a trusted one), then the *effective*
 * authority: a forwarded Host (`X-Forwarded-Host`) only when the peer
 * that delivered the request is a {@see TrustedProxyMatcher} trusted
 * proxy and the header is present, otherwise the request's own Host
 * header, otherwise the URI host. The chosen authority is parsed by the
 * layer's {@see HostAuthority} grammar and judged by the same
 * {@see TrustedHosts} allow-list -- a spoofed forwarded value from an
 * untrusted peer never reaches the comparison, and the first value of a
 * comma-separated forwarded list is the only value that can claim trust.
 *
 * Attribution: legacy `zef-framework` `Adapters/Http/RequestFactory`
 * (`assertTrustedRequestHost()`, move-verbatim behaviour folded onto the
 * layer's single exception root with byte-identical message texts).
 */
final class TrustedRequestHost
{
    /**
     * Asserts that both the request's URI host and its effective
     * Host-header authority are trusted.
     *
     * @param ServerRequestInterface $serverRequest  the request entering the
     *                                               application ingress
     * @param list<string>           $trustedHosts   the trusted host allow-list
     * @param list<string>           $trustedProxies the trusted proxy entries
     *
     * @throws HttpException when the request carries no host, or either
     *                       host form is not trusted
     */
    public static function assert(
        ServerRequestInterface $serverRequest,
        array $trustedHosts,
        array $trustedProxies,
    ): void {
        $trustedHostPolicy = new TrustedHosts($trustedHosts);

        $uriHost = $serverRequest->getUri()->getHost();

        if ($uriHost === '') {
            throw new HttpException('Missing request host.');
        }

        $trustedHostPolicy->assertTrusted($uriHost);

        $authority = self::effectiveAuthority($serverRequest, $trustedProxies, $uriHost);

        [$host] = HostAuthority::parse($authority);

        if ($host === '') {
            throw new HttpException('Missing request host.');
        }

        $trustedHostPolicy->assertTrusted($host);
    }

    /**
     * Answers the authority literal the effective Host judgement reads:
     * a forwarded Host (only from a trusted proxy), then the request's
     * own Host header, then the URI host.
     *
     * @param ServerRequestInterface $serverRequest  the request entering the ingress
     * @param list<string>           $trustedProxies the trusted proxy entries
     * @param string                 $uriHost        the request's (trusted) URI host
     *
     * @return string the authority literal to parse and judge
     */
    private static function effectiveAuthority(
        ServerRequestInterface $serverRequest,
        array $trustedProxies,
        string $uriHost,
    ): string {
        $remote = ServerValues::text($serverRequest->getServerParams(), 'REMOTE_ADDR', '');

        if (TrustedProxyMatcher::matches($remote, $trustedProxies) && $serverRequest->hasHeader('X-Forwarded-Host')) {
            return self::firstForwardedValue($serverRequest->getHeaderLine('X-Forwarded-Host'));
        }

        if ($serverRequest->hasHeader('Host')) {
            return $serverRequest->getHeaderLine('Host');
        }

        return $uriHost;
    }

    /**
     * Answers the first value of a comma-separated forwarded list: the
     * leftmost value is the one the trusted proxy vouches for, every
     * later entry is client-appended and claims nothing.
     *
     * @param string $value the raw forwarded header value
     *
     * @return string the first, whitespace-trimmed value
     */
    private static function firstForwardedValue(string $value): string
    {
        return mb_trim(explode(',', $value)[0]);
    }
}
