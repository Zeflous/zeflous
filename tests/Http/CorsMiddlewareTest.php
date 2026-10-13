<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\AllowedOrigins;
use Zef\Framework\Http\AllowListEntries;
use Zef\Framework\Http\CorsHeaders;
use Zef\Framework\Http\CorsMiddleware;
use Zef\Framework\Http\CorsOrigin;
use Zef\Framework\Http\CorsPolicy;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\OriginHost;
use Zef\Framework\Http\OriginParser;
use Zef\Framework\Http\OriginPort;
use Zef\Framework\Http\PreflightRequest;
use Zef\Framework\Http\PreflightResponse;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\VaryTokens;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\ForeignMethodServerRequest;

/**
 * @internal
 */
#[CoversClass(CorsMiddleware::class)]
#[CoversClass(CorsPolicy::class)]
#[CoversClass(AllowedOrigins::class)]
#[CoversClass(AllowListEntries::class)]
#[CoversClass(CorsOrigin::class)]
#[CoversClass(OriginParser::class)]
#[CoversClass(OriginHost::class)]
#[CoversClass(OriginPort::class)]
#[CoversClass(PreflightRequest::class)]
#[CoversClass(PreflightResponse::class)]
#[CoversClass(CorsHeaders::class)]
#[CoversClass(VaryTokens::class)]
#[CoversClass(Response::class)]
#[CoversClass(Stream::class)]
#[CoversClass(ServerRequest::class)]
final class CorsMiddlewareTest extends TestCase
{
    private const string ORIGIN = 'https://app.example';

    private const string DEFAULT_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    private const string DEFAULT_HEADERS = 'Content-Type, Authorization, X-Request-ID';

    public function testAnAllowedPreflightIsAnsweredWithTheGrant(): void
    {
        $response = $this->preflight(['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST']);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody(), 'a preflight answer carries no content');
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame(self::DEFAULT_METHODS, $response->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame(self::DEFAULT_HEADERS, $response->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame('600', $response->getHeaderLine('Access-Control-Max-Age'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testACustomMethodAndHeaderGrantTravelsToThePreflightAnswer(): void
    {
        $corsMiddleware = new CorsMiddleware(self::ORIGIN, 'GET, POST', 'Content-Type');
        $response = $corsMiddleware->process(
            $this->request('OPTIONS', ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST']),
            $this->handler(new Response(200)),
        );

        self::assertSame('GET, POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testADisallowedPreflightIsAnsweredWithABareVary(): void
    {
        $response = $this->preflight(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST']);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
        self::assertFalse(
            $response->hasHeader('Access-Control-Allow-Origin'),
            'the denial is the absence of the grant',
        );
    }

    public function testAPreflightWithoutAnOriginFallsThroughToTheHandler(): void
    {
        $inner = new Response(200, [], 'handled');
        $response = $this->preflight(['Access-Control-Request-Method' => 'POST'], $inner);

        self::assertSame($inner, $response, 'there is nobody to allow, so the handler answers');
    }

    /**
     * Audit #328 (reference lineage): only a request carrying the
     * `Access-Control-Request-Method` marker is a preflight (fetch spec) --
     * a plain OPTIONS call, even from an allowed origin, must reach the
     * handler instead of being short-circuited with a 204.
     */
    public function testAPlainOptionsIsNotAPreflight(): void
    {
        $response = $this->processed('OPTIONS', ['Origin' => self::ORIGIN], new Response(200, [], 'options-body'));

        self::assertSame(200, $response->getStatusCode(), 'plain OPTIONS falls through to the handler');
        self::assertSame('options-body', (string) $response->getBody());
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAMarkerAloneDoesNotMakeAPreflight(): void
    {
        $response = $this->processed(
            'GET',
            ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST'],
            new Response(200),
        );

        self::assertSame(200, $response->getStatusCode(), 'a GET carrying the marker is an ordinary request');
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testASimpleGetIsDecoratedWithTheAllowSet(): void
    {
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], new Response(200, [], 'ok'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame(self::DEFAULT_METHODS, $response->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertSame(self::DEFAULT_HEADERS, $response->getHeaderLine('Access-Control-Allow-Headers'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testADecoratedResponseKeepsTheHandlersVary(): void
    {
        $inner = new Response(200, ['Vary' => 'Accept-Encoding'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame('Accept-Encoding, Origin', $response->getHeaderLine('Vary'));
    }

    public function testTheOriginTokenIsCanonicalisedWhenAlreadyVaried(): void
    {
        $lowercase = $this->processed('GET', ['Origin' => self::ORIGIN], new Response(200, ['Vary' => 'origin']));
        $uppercase = $this->processed('GET', ['Origin' => self::ORIGIN], new Response(200, ['Vary' => 'ORIGIN']));

        self::assertSame('Origin', $lowercase->getHeaderLine('Vary'));
        self::assertSame('Origin', $uppercase->getHeaderLine('Vary'));
    }

    public function testADuplicateVaryTokenFoldsAwayCaseInsensitively(): void
    {
        $inner = new Response(200, ['Vary' => 'Accept-Encoding, accept-encoding'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame('Accept-Encoding, Origin', $response->getHeaderLine('Vary'));
    }

    public function testTokensAfterADuplicateStillCollect(): void
    {
        $inner = new Response(200, ['Vary' => 'Accept-Encoding, accept-encoding, X-Request-ID'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame(
            'Accept-Encoding, X-Request-ID, Origin',
            $response->getHeaderLine('Vary'),
            'a duplicate folds away without ending the walk',
        );
    }

    /**
     * The Kelvin sign (U+212A) lower-cases into the ASCII `k` under the
     * engine's full Unicode case map, so `X-Kelvin` and `X-` + U+212A +
     * `elvin` (obs-text a field value may legally carry) are the same
     * `Vary` token -- a response varying on both spellings must fold the
     * second away.
     */
    public function testAKelvinSpelledVaryTokenFoldsAway(): void
    {
        $inner = new Response(200, ['Vary' => 'X-Kelvin, X-' . "\u{212A}" . 'elvin'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame('X-Kelvin, Origin', $response->getHeaderLine('Vary'));
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * The collection walk's membership fold is the other direction of
     * the same case map: a Kelvin-spelled token collected first folds
     * away the plain ASCII spelling that follows it.
     */
    public function testAKelvinSpelledFirstVaryTokenFoldsAwayTheAsciiSpelling(): void
    {
        $inner = new Response(200, ['Vary' => 'X-' . "\u{212A}" . 'elvin, X-Kelvin'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame('X-' . "\u{212A}" . 'elvin, Origin', $response->getHeaderLine('Vary'));
    }

    public function testAnEmptyVaryMemberIsNotAToken(): void
    {
        $inner = new Response(200, ['Vary' => 'Accept-Encoding, , X-Request-ID,'], 'ok');
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], $inner);

        self::assertSame('Accept-Encoding, X-Request-ID, Origin', $response->getHeaderLine('Vary'));
    }

    public function testARequestWithoutAnOriginIsNotDecorated(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->processed('GET', [], $inner);

        self::assertSame($inner, $response);
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testADisallowedOriginIsNotDecorated(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->processed('GET', ['Origin' => 'https://evil.example'], $inner);

        self::assertSame($inner, $response);
    }

    public function testAMalformedOriginIsNotDecorated(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->processed('GET', ['Origin' => 'not an origin'], $inner);

        self::assertSame($inner, $response, 'a malformed origin is simply not allowed, never an exception');
    }

    public function testAWildcardPolicyEchoesStar(): void
    {
        $response = $this->processed('GET', ['Origin' => 'https://other.example'], new Response(200, [], 'ok'), '*');

        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * Audit #328 (reference lineage): the echo-all policy must not answer
     * `*` for a request that carries no `Origin` at all -- there is
     * nobody to allow.
     */
    public function testAWildcardPolicyDoesNotEchoStarWithoutAnOrigin(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->processed('GET', [], $inner, '*');

        self::assertSame($inner, $response);
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAWildcardPolicyAnswersPreflightsWithStar(): void
    {
        $response = $this->preflight(
            ['Origin' => 'https://other.example', 'Access-Control-Request-Method' => 'POST'],
            null,
            '*',
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testAnEmptyAllowListDisablesCors(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->preflight(
            ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST'],
            $inner,
            [],
        );

        self::assertSame($inner, $response, 'an empty allow-list is CORS off: even a preflight reaches the handler');
        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testANullAllowListDisablesCors(): void
    {
        $inner = new Response(200, [], 'ok');
        $response = $this->preflight(
            ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST'],
            $inner,
            null,
        );

        self::assertSame($inner, $response, 'the null allow-list is the empty one: CORS off');
    }

    public function testAStringAllowListEntryAdmitsOneOrigin(): void
    {
        $response = $this->processed('GET', ['Origin' => self::ORIGIN], new Response(200, [], 'ok'), self::ORIGIN);

        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testEmptyStringEntriesAreSkipped(): void
    {
        $response = $this->processed(
            'GET',
            ['Origin' => self::ORIGIN],
            new Response(200, [], 'ok'),
            ['  ', '', self::ORIGIN],
        );

        self::assertSame(
            self::ORIGIN,
            $response->getHeaderLine('Access-Control-Allow-Origin'),
            'entries that trim to nothing are skipped without ending the walk',
        );
    }

    public function testASecondAllowListEntryStillAdmits(): void
    {
        $response = $this->processed(
            'GET',
            ['Origin' => 'https://b.example'],
            new Response(200, [], 'ok'),
            ['https://a.example', 'https://b.example'],
        );

        self::assertSame('https://b.example', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testDuplicateEntriesKeepThePolicyWorking(): void
    {
        $response = $this->processed(
            'GET',
            ['Origin' => self::ORIGIN],
            new Response(200, [], 'ok'),
            [self::ORIGIN, self::ORIGIN],
        );

        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testEntriesAfterAWildcardAreNotValidated(): void
    {
        $corsMiddleware = new CorsMiddleware([self::ORIGIN, '*', 'not an origin ///']);

        $response = $corsMiddleware->process(
            $this->request('GET', ['Origin' => 'https://other.example']),
            $this->handler(new Response(200, [], 'ok')),
        );

        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testANonStringEntryFailsClosed(): void
    {
        $this->assertThrows(
            'CORS allow-list entries must be strings, got int.',
            static fn (): CorsMiddleware => new CorsMiddleware([123]),
        );
    }

    public function testAMalformedEntryFailsClosed(): void
    {
        $this->assertThrows(
            'Malformed Origin header.',
            static fn (): CorsMiddleware => new CorsMiddleware([self::ORIGIN, 'not an origin']),
        );
    }

    public function testTheEchoedSpellingIsTheRequestsOwn(): void
    {
        $response = $this->processed(
            'GET',
            ['Origin' => 'HTTPS://APP.EXAMPLE:443'],
            new Response(200, [], 'ok'),
        );

        self::assertSame(
            'HTTPS://APP.EXAMPLE:443',
            $response->getHeaderLine('Access-Control-Allow-Origin'),
            'normalisation is the comparison form; the echo is the spelling as sent',
        );
    }

    public function testThePrivacyOriginIsAdmittedWhenAllowed(): void
    {
        $response = $this->processed('GET', ['Origin' => 'null'], new Response(200, [], 'ok'), 'null');

        self::assertSame('null', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testALowercaseOptionsTokenIsStillAPreflight(): void
    {
        $foreignMethodServerRequest = new ForeignMethodServerRequest(
            $this->request('OPTIONS', ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST']),
            'options',
        );
        $response = new CorsMiddleware(self::ORIGIN)->process(
            $foreignMethodServerRequest,
            $this->handler(new Response(200)),
        );

        self::assertSame(204, $response->getStatusCode(), 'PSR-7 carries no case canonicalisation guarantee');
    }

    /**
     * The long s (U+017F) upper-cases into the ASCII `S` under the
     * engine's full Unicode case map, so a foreign implementation's
     * `option` + U+017F folds into `OPTIONS` -- the method comparison's
     * character-map behaviour, observable only through such a token.
     */
    public function testALongSTokenFoldsIntoOptions(): void
    {
        $foreignMethodServerRequest = new ForeignMethodServerRequest(
            $this->request('GET', ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => 'POST']),
            'option' . "\u{017F}",
        );
        $response = new CorsMiddleware(self::ORIGIN)->process(
            $foreignMethodServerRequest,
            $this->handler(new Response(200)),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testAnOwsOnlyMarkerIsNotAPreflight(): void
    {
        $response = $this->processed(
            'OPTIONS',
            ['Origin' => self::ORIGIN, 'Access-Control-Request-Method' => '   '],
            new Response(200, [], 'ok'),
        );

        self::assertSame(200, $response->getStatusCode(), 'a whitespace-only marker is an absent marker');
        self::assertSame('ok', (string) $response->getBody());
        self::assertSame(self::ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    /**
     * @param array<string, string>   $headers
     * @param null|list<mixed>|string $allowOrigin
     */
    private function preflight(
        array $headers,
        ?Response $response = null,
        array|string|null $allowOrigin = self::ORIGIN,
    ): ResponseInterface {
        return $this->processed('OPTIONS', $headers, $response ?? new Response(200), $allowOrigin);
    }

    /**
     * @param array<string, string>   $headers
     * @param null|list<mixed>|string $allowOrigin
     */
    private function processed(
        string $method,
        array $headers,
        Response $response,
        array|string|null $allowOrigin = self::ORIGIN,
    ): ResponseInterface {
        return new CorsMiddleware($allowOrigin)->process(
            $this->request($method, $headers),
            $this->handler($response),
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method = 'GET', array $headers = []): ServerRequest
    {
        $map = [];
        foreach ($headers as $name => $value) {
            $map[mb_strtolower($name)] = [$value];
        }

        return new ServerRequest(
            $method,
            new Uri('https://api.example.com/cors'),
            [],
            [],
            [],
            [],
            null,
            $map,
            Stream::fromString(''),
        );
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new FixedResponseHandler($response);
    }

    /**
     * @param callable(): mixed $operation
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
