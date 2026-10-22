<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\CsrfCookieValue;
use Zef\Framework\Http\CsrfDoubleSubmit;
use Zef\Framework\Http\CsrfMiddleware;
use Zef\Framework\Http\CsrfPolicy;
use Zef\Framework\Http\CsrfSetCookie;
use Zef\Framework\Http\CsrfTokenManager;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The double-submit boundary, on its own terms: the safe-method
 * issuance (a token-less navigation answers a fresh cookie, a carrying
 * one passes untouched), the unsafe-method four-arms refusal, the
 * policy-controlled cookie attributes over https and http alike, the
 * replace-not-append decoration, the reference's own cookie-pair
 * parsing grammar observed through the issuance (a present cookie
 * suppresses it, whatever the pair carries) -- plus the disarmed
 * pass-through and the byte-for-byte key comparison.
 *
 * @internal
 */
#[CoversClass(CsrfCookieValue::class)]
#[CoversClass(CsrfDoubleSubmit::class)]
#[CoversClass(CsrfMiddleware::class)]
#[CoversClass(CsrfSetCookie::class)]
final class CsrfMiddlewareTest extends TestCase
{
    private const string SECRET = 'middleware-test-secret-thirty-two-bytes!';

    private CsrfTokenManager $csrfTokenManager;

    private FixedResponseHandler $fixedResponseHandler;

    #[Override]
    protected function setUp(): void
    {
        $this->csrfTokenManager = new CsrfTokenManager(self::SECRET);
        $this->fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'answered'));
    }

    public function testASafeRequestWithoutTheCookieIsAnsweredWithAFreshOne(): void
    {
        $response = $this->middleware()->process($this->request('GET'), $this->fixedResponseHandler);

        self::assertMatchesRegularExpression(
            '/^ZEF-XSRF-TOKEN=[A-Za-z0-9_-]+\.[a-f0-9]{64}; Path=\/; SameSite=Strict; Secure; HttpOnly$/D',
            $response->getHeaderLine('Set-Cookie'),
            "the fresh token as the family's cookie, attributes policy-controlled",
        );
        self::assertSame(200, $response->getStatusCode());
    }

    public function testASafeRequestCarryingTheCookiePassesUntouched(): void
    {
        $token = $this->csrfTokenManager->issue();

        $response = $this->middleware()
            ->process($this->request('GET', $token), $this->fixedResponseHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Set-Cookie'), "no re-issue: churning races the page's own POSTs");
    }

    public function testASafeRequestCarryingAnAttackerCookieIsNotReAnswered(): void
    {
        $response = $this->middleware()
            ->process($this->request('GET', 'attacker-pinned-value'), $this->fixedResponseHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse(
            $response->hasHeader('Set-Cookie'),
            "the reference's own posture: a present cookie suppresses the re-issue, whatever it carries",
        );
    }

    /**
     * @dataProvider provideEverySafeMethodNeedsNoTokenCases
     */
    #[DataProvider('provideEverySafeMethodNeedsNoTokenCases')]
    public function testEverySafeMethodNeedsNoToken(string $method): void
    {
        $response = $this->middleware()->process($this->request($method), $this->fixedResponseHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Set-Cookie'), 'a token-less navigation is answered with a fresh one');
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideEverySafeMethodNeedsNoTokenCases(): iterable
    {
        yield 'GET' => ['GET'];

        yield 'HEAD' => ['HEAD'];

        yield 'OPTIONS' => ['OPTIONS'];

        yield 'TRACE' => ['TRACE'];
    }

    public function testAnUnsafeRequestHonouringTheDoubleSubmitPasses(): void
    {
        $token = $this->csrfTokenManager->issue();

        $response = $this->middleware()
            ->process($this->request('POST', $token, $token), $this->fixedResponseHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testALowerCaseMethodSpellingIsStillUnsafe(): void
    {
        $token = $this->csrfTokenManager->issue();

        $response = $this->middleware()
            ->process($this->request('post', $token, $token), $this->fixedResponseHandler)
        ;

        self::assertSame(200, $response->getStatusCode(), 'the method gate folds case-insensitively');
    }

    /**
     * @dataProvider provideTheRefusedSubmitsAnswerTheForbiddenEnvelopeCases
     */
    #[DataProvider('provideTheRefusedSubmitsAnswerTheForbiddenEnvelopeCases')]
    public function testTheRefusedSubmitsAnswerTheForbiddenEnvelope(?string $cookieToken, ?string $headerToken): void
    {
        $response = $this->middleware()
            ->process($this->request('POST', $cookieToken, $headerToken), $this->fixedResponseHandler)
        ;

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $envelope = $this->envelope($response);
        self::assertTrue($envelope['error']);
        self::assertSame(403, $envelope['status']);
        self::assertSame('Forbidden', $envelope['message']);
        self::assertIsString($envelope['correlation_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $envelope['correlation_id']);
    }

    /**
     * @return iterable<non-empty-string, array{null|string, null|string}>
     */
    public static function provideTheRefusedSubmitsAnswerTheForbiddenEnvelopeCases(): iterable
    {
        yield 'no cookie, no header' => [null, null];

        yield 'cookie only' => ['anything', null];

        yield 'header only' => [null, 'anything'];

        yield 'empty header value' => ['anything', ''];

        yield 'mismatched pair' => ['anything', 'something-else'];
    }

    public function testAnUnsignedCookieEchoedInBothChannelsIsStillRefused(): void
    {
        $attackerValue = 'QWJjZGVmZ2hpamtsbW5vcA';

        $response = $this->middleware()
            ->process($this->request('POST', $attackerValue, $attackerValue), $this->fixedResponseHandler)
        ;

        self::assertSame(403, $response->getStatusCode(), 'matching channels do not substitute for the signature');
    }

    public function testASignedCookieEchoedWrongIsRefused(): void
    {
        $token = $this->csrfTokenManager->issue();
        [$body] = explode('.', $token, 2);

        $response = $this->middleware()
            ->process($this->request('POST', $token, $body), $this->fixedResponseHandler)
        ;

        self::assertSame(403, $response->getStatusCode(), 'the header must carry the whole token, not its body');
    }

    public function testAnHttpRequestReceivesItsTokenWithoutTheSecureAttribute(): void
    {
        $response = $this->middleware()->process($this->httpRequest('GET'), $this->fixedResponseHandler);

        $setCookie = $response->getHeaderLine('Set-Cookie');

        self::assertStringContainsString('SameSite=Strict', $setCookie);
        self::assertStringNotContainsString('Secure', $setCookie, 'Secure only over https requests');
        self::assertStringContainsString('HttpOnly', $setCookie);
    }

    public function testTheCookiePostureKnobsShapeTheAttributes(): void
    {
        $csrfMiddleware = new CsrfMiddleware(
            new CsrfPolicy(
                secret: self::SECRET,
                cookieHttpOnly: false,
                sameSite: 'None',
            ),
            $this->csrfTokenManager,
        );

        $response = $csrfMiddleware->process($this->httpRequest('GET'), $this->fixedResponseHandler);

        self::assertMatchesRegularExpression(
            '/^ZEF-XSRF-TOKEN=[A-Za-z0-9_-]+\.[a-f0-9]{64}; Path=\/; SameSite=None; Secure$/D',
            $response->getHeaderLine('Set-Cookie'),
            'HttpOnly off, SameSite None -- and Secure travels with None whatever the scheme, '
            . 'because no browser keeps a None cookie without it',
        );
    }

    public function testTheIssuanceAppendsBesideTheHandlersOwnCookie(): void
    {
        $fixedResponseHandler = new FixedResponseHandler(
            $this->withASessionCookie(new Response(200, [], 'answered')),
        );

        $response = $this->middleware()->process($this->request('GET'), $fixedResponseHandler);

        self::assertMatchesRegularExpression(
            '/^session=abc123; Path=\/, ZEF-XSRF-TOKEN=/D',
            $response->getHeaderLine('Set-Cookie'),
            'append-not-replace: the token cookie rides beside handler-set values',
        );
        self::assertCount(2, $response->getHeader('Set-Cookie'), 'both cookies survive the issuance decoration');
    }

    public function testADisarmedPolicyIsAPassThrough(): void
    {
        $csrfMiddleware = new CsrfMiddleware(new CsrfPolicy(enabled: false), null);

        $response = $csrfMiddleware->process($this->request('POST'), $this->fixedResponseHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    /**
     * @dataProvider provideTheCookieParsingFollowsTheReferenceGrammarCases
     */
    #[DataProvider('provideTheCookieParsingFollowsTheReferenceGrammarCases')]
    public function testTheCookieParsingFollowsTheReferenceGrammar(string $cookieHeader, bool $present): void
    {
        $request = new ServerRequest('GET', new Uri('https://app.example.com/health'))
            ->withHeader('Cookie', $cookieHeader)
        ;

        self::assertInstanceOf(ServerRequest::class, $request);

        $response = $this->middleware()->process($request, $this->fixedResponseHandler);

        self::assertSame(
            $present,
            !$response->hasHeader('Set-Cookie'),
            'the pair reads as ' . ($present ? 'present' : 'absent') . ' under the reference grammar',
        );
    }

    /**
     * The reference's cookie-pair grammar, observed through the
     * issuance: a PRESENT cookie suppresses the fresh one, whatever the
     * pair carries, so every grammar arm answers "present" or "absent"
     * visibly.
     *
     * @return iterable<non-empty-string, array{string, bool}> the raw
     *                                                         Cookie
     *                                                         header and
     *                                                         whether the
     *                                                         family's
     *                                                         cookie must
     *                                                         read as
     *                                                         present
     */
    public static function provideTheCookieParsingFollowsTheReferenceGrammarCases(): iterable
    {
        $token = 'QWJjZGVmZ2hpamtsbW5vcA';

        yield 'one pair of many' => ['session=abc; ZEF-XSRF-TOKEN=' . $token . '; theme=dark', true];

        yield 'value with an equals byte' => ['ZEF-XSRF-TOKEN=YWJjZA==.deadbeef', true];

        yield 'value with several equals bytes' => ['ZEF-XSRF-TOKEN=a=b=c', true];

        yield 'pair without a separator, the name itself' => ['ZEF-XSRF-TOKEN', true];

        yield 'pair without a separator, another name' => ['flag', false];

        yield 'absent cookie' => ['session=abc; theme=dark', false];

        yield 'prefix lookalike is not the cookie' => ['ZEF-XSRF-TOKEX=nope', false];

        yield 'space before the separator splits the key' => ['ZEF-XSRF-TOKEN = padded', false];
    }

    public function testTheKeyComparisonIsByteForByteNeverLoose(): void
    {
        $csrfMiddleware = new CsrfMiddleware(
            new CsrfPolicy(secret: self::SECRET, cookieName: '31'),
            $this->csrfTokenManager,
        );

        $request = new ServerRequest('GET', new Uri('https://app.example.com/health'))
            ->withHeader('Cookie', '0x1F=lookalike')
        ;

        self::assertInstanceOf(ServerRequest::class, $request);

        $response = $csrfMiddleware->process($request, $this->fixedResponseHandler);

        self::assertTrue(
            $response->hasHeader('Set-Cookie'),
            "'0x1F' is not the cookie '31' however PHP's == would equate them: the key comparison is byte-for-byte",
        );
    }

    /**
     * @return array<array-key, mixed> the decoded error envelope
     */
    private function envelope(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            self::fail('The error envelope did not decode to an object.');
        }

        return $decoded;
    }

    private function middleware(): CsrfMiddleware
    {
        return new CsrfMiddleware(new CsrfPolicy(secret: self::SECRET), $this->csrfTokenManager);
    }

    /**
     * One handler answer carrying its own session cookie, through the
     * withHeader() chain the PSR-7 interface types as MessageInterface.
     */
    private function withASessionCookie(Response $response): Response
    {
        $message = $response->withHeader('Set-Cookie', 'session=abc123; Path=/');

        self::assertInstanceOf(Response::class, $message, 'withHeader() must preserve the response type.');

        return $message;
    }

    private function request(string $method, ?string $cookieToken = null, ?string $headerToken = null): ServerRequest
    {
        $request = new ServerRequest($method, new Uri('https://app.example.com/health'));

        if ($cookieToken !== null) {
            $request = $request->withHeader('Cookie', 'ZEF-XSRF-TOKEN=' . $cookieToken);
        }

        if ($headerToken !== null) {
            $request = $request->withHeader('X-CSRF-Token', $headerToken);
        }

        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }

    private function httpRequest(string $method): ServerRequest
    {
        return new ServerRequest($method, new Uri('http://app.example.com/health'));
    }
}
