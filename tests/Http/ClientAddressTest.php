<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ClientAddress;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * The client-address resolution, on its own terms: the peer-first order,
 * the trusted-proxy boundary, the nearest untrusted forwarded candidate,
 * and the sentinel -- the seam the rate-limit family throttles by.
 *
 * @internal
 */
#[CoversClass(ClientAddress::class)]
final class ClientAddressTest extends TestCase
{
    public function testAnAbsentPeerResolvesToTheSentinel(): void
    {
        self::assertSame('0.0.0.0', ClientAddress::resolve($this->request([], [])));
    }

    public function testACorruptPeerEntryReadsExactlyLikeAbsence(): void
    {
        self::assertSame('0.0.0.0', ClientAddress::resolve($this->request(['REMOTE_ADDR' => ['nested']], [])));
    }

    public function testAnUnparseablePeerResolvesToTheSentinel(): void
    {
        self::assertSame('0.0.0.0', ClientAddress::resolve($this->request(['REMOTE_ADDR' => 'not-an-ip'], [])));
    }

    public function testAnUntrustedPeerKeepsItsOwnAddressEvenWhenForwardedHeadersClaimOtherwise(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '203.0.113.9'],
            ['X-Forwarded-For' => '198.51.100.7'],
        );

        self::assertSame('203.0.113.9', ClientAddress::resolve($request));
    }

    public function testATrustedProxyHandsTheNearestUntrustedForwardedCandidate(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1'],
            ['X-Forwarded-For' => 'not-an-ip, 198.51.100.7, 10.0.0.1'],
        );

        self::assertSame(
            '198.51.100.7',
            ClientAddress::resolve($request, ['10.0.0.1']),
            'the walk starts at the appended end and skips the trusted hop; the invalid leftmost entry is never consulted',
        );
    }

    public function testAClientSuppliedLeftmostCandidateIsIgnoredBehindAnAppendingProxy(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1'],
            ['X-Forwarded-For' => '9.9.9.9, 198.51.100.7'],
        );

        self::assertSame(
            '198.51.100.7',
            ClientAddress::resolve($request, ['10.0.0.1']),
            'a spoofed leftmost entry never becomes the throttled identity',
        );
    }

    public function testAChainOfTrustedProxiesStripsEveryTrustedHop(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.0.0.2'],
            ['X-Forwarded-For' => '198.51.100.7, 10.0.0.1'],
        );

        self::assertSame(
            '198.51.100.7',
            ClientAddress::resolve($request, ['10.0.0.1', '10.0.0.2']),
        );
    }

    public function testAnAllTrustedForwardedChainAnswersThePeersOwnAddress(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.0.0.2'],
            ['X-Forwarded-For' => '10.0.0.1'],
        );

        self::assertSame(
            '10.0.0.2',
            ClientAddress::resolve($request, ['10.0.0.1', '10.0.0.2']),
            'an exhausted trusted walk falls back to the sanitised peer, never to a leftmost claim',
        );
    }

    public function testACidrTrustedProxyIsHonoured(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.1.2.3'],
            ['X-Forwarded-For' => '198.51.100.7'],
        );

        self::assertSame('198.51.100.7', ClientAddress::resolve($request, ['10.0.0.0/8']));
    }

    public function testATrustedProxyWithoutAForwardedHeaderAnswersItsOwnAddress(): void
    {
        $request = $this->request(['REMOTE_ADDR' => '10.0.0.1'], []);

        self::assertSame('10.0.0.1', ClientAddress::resolve($request, ['10.0.0.1']));
    }

    public function testATrustedProxyWithAnEmptyForwardedHeaderAnswersItsOwnAddress(): void
    {
        $request = $this->request(['REMOTE_ADDR' => '10.0.0.1'], ['X-Forwarded-For' => '']);

        self::assertSame('10.0.0.1', ClientAddress::resolve($request, ['10.0.0.1']));
    }

    public function testATrustedProxyWithOnlyInvalidCandidatesAnswersItsOwnAddress(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '10.0.0.1'],
            ['X-Forwarded-For' => 'not-an-ip, also-not-one'],
        );

        self::assertSame('10.0.0.1', ClientAddress::resolve($request, ['10.0.0.1']));
    }

    public function testIpv6PeersResolveLikeIpv4(): void
    {
        $request = $this->request(
            ['REMOTE_ADDR' => '::1'],
            ['X-Forwarded-For' => '2001:db8::1'],
        );

        self::assertSame('2001:db8::1', ClientAddress::resolve($request, ['::1']));
    }

    /**
     * @param array<string, mixed>  $serverParams
     * @param array<string, string> $headers
     */
    private function request(array $serverParams, array $headers): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example.com/health'),
            serverParams: $serverParams,
            headers: $headers,
        );
    }
}
