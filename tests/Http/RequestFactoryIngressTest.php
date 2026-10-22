<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zef\Framework\Http\BodyBudget;
use Zef\Framework\Http\BodyTransfer;
use Zef\Framework\Http\CidrAddress;
use Zef\Framework\Http\HostAuthority;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\IngressBody;
use Zef\Framework\Http\IngressProbe;
use Zef\Framework\Http\LimitedInputStream;
use Zef\Framework\Http\MaskedPrefix;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\ServerValues;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\TrustedHosts;
use Zef\Framework\Http\TrustedProxyMatcher;
use Zef\Framework\Http\TrustedRequestHost;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\ForeignServerRequest;
use Zef\Test\Support\Http\LyingSizeStream;
use Zef\Test\Support\Http\NonSeekableBody;
use Zef\Test\Support\Http\RecordingStream;

/**
 * @internal
 */
#[CoversClass(RequestFactory::class)]
#[CoversClass(TrustedRequestHost::class)]
#[CoversClass(TrustedHosts::class)]
#[CoversClass(TrustedProxyMatcher::class)]
#[CoversClass(CidrAddress::class)]
#[CoversClass(IngressBody::class)]
#[CoversClass(IngressProbe::class)]
#[CoversClass(MaskedPrefix::class)]
#[CoversClass(HostAuthority::class)]
#[CoversClass(ServerValues::class)]
#[CoversClass(LimitedInputStream::class)]
#[CoversClass(BodyBudget::class)]
#[CoversClass(BodyTransfer::class)]
#[CoversClass(ServerRequest::class)]
final class RequestFactoryIngressTest extends TestCase
{
    private const string ALLOWED_HOST = 'api.example.com';

    private const array TRUSTED_HOSTS = [self::ALLOWED_HOST];

    private const array TRUSTED_PROXIES = ['10.0.0.0/8'];

    public function testATrustedRequestPassesTheIngressUnchanged(): void
    {
        $request = $this->trustedRequest();

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded, 'A passing seekable-body request keeps its own instance.');
        self::assertSame(0, $guarded->getBody()->tell(), 'The probe restored the body cursor.');
    }

    public function testTheUriHostIsJudgedOnItsOwn(): void
    {
        $request = $this->trustedRequest([], [], 'https://evil.example.com/users');

        $this->assertThrows(
            'Untrusted host: evil.example.com.',
            static function () use ($request): void {
                RequestFactory::validateIngress(
                    $request,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    public function testTheHostHeaderIsJudgedOnItsOwn(): void
    {
        $request = $this->trustedRequest([], ['host' => ['evil.example.com']]);

        $this->assertThrows(
            'Untrusted host: evil.example.com.',
            static function () use ($request): void {
                RequestFactory::validateIngress(
                    $request,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    public function testARequestWithoutAnyHostIsRejected(): void
    {
        // The request carries a (trusted) Host header so only the missing
        // URI host can reject it -- the header half must not mask the
        // URI half's verdict.
        $serverRequest = new ServerRequest('GET', new Uri('/users'), [], [], [], [], null, [
            'host' => [self::ALLOWED_HOST],
        ]);

        $this->assertThrows(
            'Missing request host.',
            static function () use ($serverRequest): void {
                RequestFactory::validateIngress(
                    $serverRequest,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    /**
     * A forwarded Host is honoured ONLY when the peer that delivered the
     * request is a trusted proxy: the forwarded value is judged, not the
     * (trusted) Host header the request also carries.
     */
    public function testAForwardedHostFromATrustedProxyIsTheJudgedAuthority(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => ['spoof.example.com'],
        ]);

        $this->assertThrows(
            'Untrusted host: spoof.example.com.',
            static function () use ($request): void {
                RequestFactory::validateIngress(
                    $request,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    /**
     * Only the appended end of a comma-separated forwarded list can claim
     * trust -- under the standard appending proxy it is the position the
     * trusted proxy wrote; every earlier entry is client-supplied and
     * claims nothing.
     */
    public function testAClientSuppliedEarlyForwardedValueCannotClaimTrust(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => [self::ALLOWED_HOST . ', evil.example.com'],
        ]);

        $this->assertThrows(
            'Untrusted host: evil.example.com.',
            static function () use ($request): void {
                RequestFactory::validateIngress(
                    $request,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    public function testTheProxyAppendedForwardedValueIsTheJudgedAuthority(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => ['evil.example.com, ' . self::ALLOWED_HOST],
        ]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    /**
     * The spoofing boundary: an untrusted peer's forwarded header never
     * reaches the judgement -- the request's own (trusted) Host header
     * is the authority instead, and the request passes.
     */
    public function testAForwardedHostFromAnUntrustedPeerIsIgnored(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '203.0.113.9'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => ['spoof.example.com'],
        ]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    /**
     * A corrupt REMOTE_ADDR entry is absence, not an address -- the peer
     * stays untrusted and the forwarded header stays ignored.
     */
    public function testACorruptRemoteAddressStaysUntrusted(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => ['10.0.0.1']], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => ['spoof.example.com'],
        ]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    public function testATrustedProxyWithoutAForwardedHostFallsBackToTheHostHeader(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
        ]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    /**
     * An HTTP/1.0 client sends no Host header at all: the URI host is
     * the only host carrier. A foreign implementation is the only shape
     * that reaches this branch -- the layer's own requests bootstrap the
     * Host header from the URI at construction.
     */
    public function testARequestWithoutAHostHeaderAnswersItsUriHost(): void
    {
        $foreignServerRequest = new ForeignServerRequest(
            new Uri('https://' . self::ALLOWED_HOST . '/users'),
            [],
            [],
            Stream::fromString(''),
        );

        $guarded = RequestFactory::validateIngress(
            $foreignServerRequest,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($foreignServerRequest, $guarded);
    }

    public function testAForwardedHostThatCarriesOnlyWhitespaceIsARejectedAuthority(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => [''],
        ]);

        $this->assertThrows(
            'Missing request host.',
            static function () use ($request): void {
                RequestFactory::validateIngress(
                    $request,
                    self::TRUSTED_HOSTS,
                    self::TRUSTED_PROXIES,
                    new RequestBodyPolicy(64),
                );
            },
        );
    }

    public function testAForwardedHostWithAPortIsJudgedByItsHost(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => [self::ALLOWED_HOST],
            'x-forwarded-host' => [self::ALLOWED_HOST . ':8443'],
        ]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    /**
     * A bracketed IPv6 authority literal compares against the allow-list
     * like its URI host form (the comparison form strips the brackets).
     */
    public function testABracketedIpv6AuthorityIsJudgedByItsUriForm(): void
    {
        $request = $this->trustedRequest(['REMOTE_ADDR' => '10.0.0.2'], [
            'host' => ['[::1]:8080'],
        ], 'https://[::1]/users');

        $guarded = RequestFactory::validateIngress(
            $request,
            ['::1'],
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    /**
     * The Host header is folded through the full Unicode case map before
     * the allow-list comparison, exactly like the URI host.
     */
    public function testTheHostHeaderFoldsCaseBeforeComparison(): void
    {
        $request = $this->trustedRequest([], ['host' => ['API.EXAMPLE.COM']]);

        $guarded = RequestFactory::validateIngress(
            $request,
            self::TRUSTED_HOSTS,
            self::TRUSTED_PROXIES,
            new RequestBodyPolicy(64),
        );

        self::assertSame($request, $guarded);
    }

    public function testAnEmptyAllowListSkipsTheHostJudgementEntirely(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('/users'));

        $guarded = RequestFactory::validateIngress(
            $serverRequest,
            [],
            ['10.0.0.0/8'],
            new RequestBodyPolicy(64),
        );

        self::assertSame($serverRequest, $guarded, 'An unconfigured allow-list trusts the host by design.');
    }

    /**
     * The eager half: an over-limit Content-Length claim throws before
     * the body is ever opened -- the journal proves no read reached the
     * stream.
     */
    public function testAnOverLimitClaimThrowsBeforeTheBodyIsTouched(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('12345678'));
        $request = $this->bodyRequest(['content-length' => ['5']], $recordingStream);

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($request): void {
                RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(4));
            },
        );

        self::assertSame([], $recordingStream->readLengths, 'No read reached the body before the claim threw.');
    }

    /**
     * The stated-size half: a size that already exceeds the policy
     * throws before the probe runs -- the journal proves the stream was
     * never read.
     */
    public function testAnOverLimitStatedSizeThrowsBeforeTheProbeRuns(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('12345678'));
        $request = $this->bodyRequest([], $recordingStream);

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($request): void {
                RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(4));
            },
        );

        self::assertSame([], $recordingStream->readLengths, 'No read reached the body before the size threw.');
    }

    /**
     * The exact boundary passes: a size equal to the policy is at the
     * limit, not past it.
     */
    public function testAStatedSizeExactlyAtTheLimitPasses(): void
    {
        $request = $this->bodyRequest([], Stream::fromString('1234'));

        $guarded = RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(4));

        self::assertSame($request, $guarded);
    }

    /**
     * The read-ahead probe: a stream whose stated size understates its
     * deliverable bytes still fails closed -- the probe reads from the
     * start, refuses the bytes past the budget, and restores the cursor
     * on its way out.
     */
    public function testALyingSizeFailsClosedAtTheProbe(): void
    {
        $recordingStream = new RecordingStream(new LyingSizeStream(Stream::fromString('12345678901234'), 2));
        $recordingStream->read(2);

        $request = $this->bodyRequest([], $recordingStream);

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($request): void {
                RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(12));
            },
        );

        self::assertSame(2, $recordingStream->tell(), 'The probe restored the cursor after refusing.');
        self::assertNotSame([], $recordingStream->readLengths, 'The probe did reach the stream.');
    }

    /**
     * The probe reads from the START of the stream, wherever the cursor
     * was parked: without the rewind, the probe would read only the
     * twelve remaining bytes and pass what the whole body forbids.
     */
    public function testTheProbeReadsFromTheStartOfTheBody(): void
    {
        $lyingSizeStream = new LyingSizeStream(Stream::fromString('12345678901234'), 2);
        $lyingSizeStream->read(2);

        $request = $this->bodyRequest([], $lyingSizeStream);

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($request): void {
                RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(12));
            },
        );

        self::assertSame(2, $lyingSizeStream->tell());
    }

    /**
     * A passing probe leaves the body exactly where it found it, and
     * the journal pins the probe's own read length (one byte past the
     * remaining budget -- the probe arithmetic of the lazy wrapper).
     */
    public function testAPassingProbeRestoresTheCursorAndPinsTheProbeRead(): void
    {
        $recordingStream = new RecordingStream(Stream::fromString('abcdef'));
        $recordingStream->read(2);

        $request = $this->bodyRequest([], $recordingStream);

        $guarded = RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(8));

        self::assertSame($request, $guarded);
        self::assertSame(2, $recordingStream->tell(), 'The probe restored the cursor.');
        self::assertSame([2, 9], $recordingStream->readLengths, 'The probe asked for one byte past the budget.');
    }

    /**
     * A non-seekable body cannot be restored, so the probed contents
     * become the request's replacement body -- seekable, and carrying
     * everything readable.
     */
    public function testANonSeekableBodyIsReplacedByItsProbedContents(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://' . self::ALLOWED_HOST . '/users'),
            [],
            [],
            [],
            [],
            null,
            [],
            new NonSeekableBody('payload'),
        );

        $guarded = RequestFactory::validateIngress($serverRequest, [], [], new RequestBodyPolicy(64));

        self::assertNotSame($serverRequest, $guarded, 'A non-seekable body is replaced.');
        self::assertInstanceOf(Stream::class, $guarded->getBody());
        self::assertTrue($guarded->getBody()->isSeekable());
        self::assertSame('payload', $guarded->getBody()->getContents());
    }

    /**
     * The probe guards the non-seekable path too: an unknown size and
     * an absent claim still meet the delivered-bytes judgement.
     */
    public function testAnOverLimitNonSeekableBodyFailsClosedAtTheProbe(): void
    {
        $serverRequest = new ServerRequest(
            'POST',
            new Uri('https://' . self::ALLOWED_HOST . '/users'),
            [],
            [],
            [],
            [],
            null,
            [],
            new NonSeekableBody('12345'),
        );

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($serverRequest): void {
                RequestFactory::validateIngress($serverRequest, [], [], new RequestBodyPolicy(4));
            },
        );
    }

    /**
     * A malformed claim is never a size signal: the digit-less header
     * value is left to the delivered-bytes judgement, and a body inside
     * the policy passes.
     */
    public function testAMalformedClaimIsNotASizeSignal(): void
    {
        $request = $this->bodyRequest(['content-length' => ['twelve']], Stream::fromString('1234'));

        $guarded = RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(4));

        self::assertSame($request, $guarded);
    }

    /**
     * Surrounding whitespace around the claim is trimmed before it is
     * judged, exactly like the factory's eager half.
     */
    public function testASurroundingWhitespaceClaimIsTrimmedBeforeJudgement(): void
    {
        $request = $this->bodyRequest(['content-length' => [' 5 ']], Stream::fromString('1234'));

        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function () use ($request): void {
                RequestFactory::validateIngress($request, [], [], new RequestBodyPolicy(4));
            },
        );
    }

    /**
     * Builds a request whose host halves are trusted (or skipped) so the
     * body half is the only judgement under test.
     *
     * @param array<string, list<string>> $headers
     */
    private function bodyRequest(array $headers, StreamInterface $stream): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('https://' . self::ALLOWED_HOST . '/users'),
            [],
            [],
            [],
            [],
            null,
            $headers,
            $stream,
        );
    }

    /**
     * Builds a request with the given server bag and headers, carrying a
     * Host header by default so both host halves are judged.
     *
     * @param array<string, mixed>        $serverParams
     * @param array<string, list<string>> $headers
     */
    private function trustedRequest(
        array $serverParams = [],
        array $headers = [],
        string $uri = 'https://api.example.com/users',
    ): ServerRequest {
        $headers += ['host' => [self::ALLOWED_HOST]];

        return new ServerRequest(
            'GET',
            new Uri($uri),
            $serverParams,
            [],
            [],
            [],
            null,
            $headers,
            Stream::fromString(''),
        );
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
