<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\TrustedRequestScheme;
use Zef\Framework\Http\Uri;

/**
 * The trusted-proxy scheme fold's own battery (#797): the forwarded
 * protocol claim honoured only from a trusted peer (the TLS-terminating
 * topology's Secure cookie and HSTS posture), the appended list's last
 * value the only claimant, the operator-data refusal for a trusted
 * proxy's nonsense spelling, and the request rewrite carrying the
 * effective scheme the downstream security posture derives from.
 *
 * @internal
 */
#[CoversClass(TrustedRequestScheme::class)]
#[CoversClass(ServerRequest::class)]
final class TrustedRequestSchemeTest extends TestCase
{
    private const array TRUSTED_PROXIES = ['10.0.0.0/8'];

    public function testAnUntrustedPeerSClaimIsNeverRead(): void
    {
        $request = $this->request('http', '192.0.2.9', ['X-Forwarded-Proto' => 'https']);

        self::assertSame(
            'http',
            TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES),
            'a claim from a peer outside the trusted list changes nothing',
        );
    }

    public function testATrustedPeerSHttpsClaimUpgradesTheScheme(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'https']);

        self::assertSame(
            'https',
            TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES),
            'the TLS-terminating topology: plain http behind the proxy, https at the client hop',
        );
    }

    public function testATrustedPeerSHttpClaimAnswersSymmetrically(): void
    {
        $request = $this->request('https', '10.1.2.3', ['X-Forwarded-Proto' => 'http']);

        self::assertSame(
            'http',
            TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES),
            'the claim is authoritative in both directions, not an upgrade-only hint',
        );
    }

    public function testTheLastValueOfAnAppendedListIsTheOnlyClaimant(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'http, https']);

        self::assertSame(
            'https',
            TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES),
            'the appended end is the position the trusted proxy wrote, every earlier entry client-supplied',
        );
    }

    public function testTheClaimFoldsCaseInsensitively(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'HTTPS']);

        self::assertSame('https', TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES));
    }

    public function testAnAbsentClaimKeepsTheOwnScheme(): void
    {
        $request = $this->request('http', '10.1.2.3', []);

        self::assertSame('http', TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES));
    }

    public function testAnEmptyProxyListNeverReadsTheHeader(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'https']);

        self::assertSame(
            'http',
            TrustedRequestScheme::effective($request, []),
            'no trusted proxies configured means the claim claims nothing',
        );
    }

    public function testATrustedPeerSNonsenseClaimRefusesLoudly(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'banana']);

        try {
            TrustedRequestScheme::effective($request, self::TRUSTED_PROXIES);
            self::fail('A trusted proxy spelling nonsense must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The X-Forwarded-Proto claim a trusted proxy delivered must be http or https, '
                . '\'banana\' given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testApplyRewritesTheUriOnlyWhenTheSchemeMoves(): void
    {
        $request = $this->request('http', '10.1.2.3', ['X-Forwarded-Proto' => 'https']);
        $rewritten = TrustedRequestScheme::apply($request, self::TRUSTED_PROXIES);

        self::assertSame('https', $rewritten->getUri()->getScheme());
        self::assertSame('api.example.com', $rewritten->getUri()->getHost());
        self::assertSame('/users', $rewritten->getUri()->getPath());

        $unchanged = $this->request('http', '192.0.2.9', ['X-Forwarded-Proto' => 'https']);

        self::assertSame(
            $unchanged,
            TrustedRequestScheme::apply($unchanged, self::TRUSTED_PROXIES),
            'an untrusted peer answers the same instance, no rewrite',
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $scheme, string $remoteAddress, array $headers): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri($scheme . '://api.example.com/users'),
            ['REMOTE_ADDR' => $remoteAddress],
            [],
            [],
            [],
            null,
            $headers,
            Stream::fromString(''),
        );
    }
}
