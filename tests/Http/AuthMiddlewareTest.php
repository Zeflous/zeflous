<?php

declare(strict_types=1);

namespace Zef\Test;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\AllowScopePolicy;
use Zef\Framework\Http\AuthBoundary;
use Zef\Framework\Http\AuthContext;
use Zef\Framework\Http\AuthFailure;
use Zef\Framework\Http\AuthMiddleware;
use Zef\Framework\Http\AuthPolicy;
use Zef\Framework\Http\AuthRefusal;
use Zef\Framework\Http\AuthRequest;
use Zef\Framework\Http\AuthResult;
use Zef\Framework\Http\AuthStatus;
use Zef\Framework\Http\BoundedReplayProtector;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\StaticCredentialProvider;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\MutableClock;
use Zef\Test\Support\Http\RecordingCredentialProvider;
use Zef\Test\Support\Http\RecordingHandler;
use Zef\Test\Support\Http\RecordingReplayProtector;

/**
 * The bearer boundary, on its own terms: the full admission's
 * principal attribute, the early 401 and its bare challenge, every
 * refusal status the boundary's failures earn (the RFC 6750
 * challenges included, the outages' retry window included), the
 * fail-closed answers to the malformed channels the reference
 * crashed on, the replay window's passage under a mutable clock, and
 * the disarmed pass-through.
 *
 * @internal
 */
#[CoversClass(AuthMiddleware::class)]
#[CoversClass(AuthRefusal::class)]
#[CoversClass(AuthRequest::class)]
final class AuthMiddlewareTest extends TestCase
{
    private const string TOKEN = 'bWlkZGxld2FyZS1iZWFyZXItdG9rZW4tZm9yLXRlc3Rz';

    private const string SECOND_TOKEN = 'c2Vjb25kLW1pZGRsZXdhcmUtYmVhcmVyLXRva2Vu';

    private MutableClock $mutableClock;

    private FixedResponseHandler $fixedResponseHandler;

    #[Override]
    protected function setUp(): void
    {
        $this->mutableClock = new MutableClock(1730000000000);
        $this->fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'answered'));
    }

    public function testAValidTokenAdmitsAndStampsThePrincipal(): void
    {
        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));

        $response = $this->middleware()->process($this->request('POST', self::TOKEN), $recordingHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'api',
            $recordingHandler->observed()->getAttribute('zef.security.principal'),
            'the allowed request carries its principal',
        );
    }

    /**
     * @dataProvider provideEverySafeMethodResolvesTheAnonymousHandleCases
     */
    #[DataProvider('provideEverySafeMethodResolvesTheAnonymousHandleCases')]
    public function testEverySafeMethodResolvesTheAnonymousHandle(string $method): void
    {
        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));

        $this->middleware(allowAnonymous: true)->process($this->request($method), $recordingHandler);

        self::assertSame(
            'anonymous',
            $recordingHandler->observed()->getAttribute('zef.security.principal'),
            'a credential-less safe method resolves as the anonymous principal',
        );
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideEverySafeMethodResolvesTheAnonymousHandleCases(): iterable
    {
        yield 'GET' => ['GET'];

        yield 'HEAD' => ['HEAD'];

        yield 'OPTIONS' => ['OPTIONS'];

        yield 'TRACE' => ['TRACE'];
    }

    public function testAMultiByteReplayIdIsMeasuredInCharacters(): void
    {
        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));

        $response = $this->middleware()->process(
            $this->request('POST', self::TOKEN, str_repeat('é', 65)),
            $recordingHandler,
        );

        self::assertSame(
            200,
            $response->getStatusCode(),
            '65 characters are 130 bytes, and the replay id bound is 128 characters',
        );
        self::assertSame(
            'api',
            $recordingHandler->observed()->getAttribute('zef.security.principal'),
            'the multi-byte replay id admits like any fresh single-use id',
        );
    }

    public function testAnUnsafeMethodWithoutACredentialIsRefusedWithTheBareChallenge(): void
    {
        $response = $this->middleware()->process($this->request('POST'), $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('Unauthorized', $this->envelope($response)['message']);
    }

    public function testASafeMethodWithoutACredentialIsRefusedByTheDefaultPosture(): void
    {
        $response = $this->middleware()->process($this->request('GET'), $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testAWrongTokenIsRefusedWithTheInvalidTokenChallenge(): void
    {
        $response = $this->middleware()->process($this->request('POST', 'wrong'), $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('Unauthorized', $this->envelope($response)['message']);
    }

    public function testATabSeparatedLineIsNotACredential(): void
    {
        $request = $this->request('POST')->withHeader('Authorization', "Bearer\t{$this->tokenValue()}");

        self::assertInstanceOf(ServerRequest::class, $request);

        $response = $this->middleware()->process($request, $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode(), 'a tab is not a separator: the line is anonymous');
    }

    public function testAnExpiredCredentialIsRefusedWithTheInvalidTokenChallenge(): void
    {
        $middleware = $this->middlewareOver(
            $this->providerAnswering(new AuthResult(AuthStatus::EXPIRED)),
        );

        $response = $middleware->process($this->request('POST', self::TOKEN), $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testAnUnavailableProviderIsRefusedFailClosedWithARetryWindow(): void
    {
        $middleware = $this->middlewareOver(
            $this->providerAnswering(new AuthResult(AuthStatus::UNAVAILABLE)),
        );

        $response = $middleware->process($this->request('POST', self::TOKEN), $this->fixedResponseHandler);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('1', $response->getHeaderLine('Retry-After'));
        self::assertSame('Service Unavailable', $this->envelope($response)['message']);
        self::assertFalse($response->hasHeader('WWW-Authenticate'), "the outage is not the client's credential");
    }

    public function testAnAuthorizationRefusalCarriesTheInsufficientScopeChallenge(): void
    {
        $middleware = $this->middleware(requiredScope: 'admin');

        $response = $middleware->process($this->request('POST', self::TOKEN), $this->fixedResponseHandler);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Bearer error="insufficient_scope"', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('Forbidden', $this->envelope($response)['message']);
    }

    public function testAReplayedIdIsRefusedWithoutAChallenge(): void
    {
        $middleware = $this->middleware();

        $middleware->process($this->request('POST', self::TOKEN, 'replay-1'), $this->fixedResponseHandler);

        $response = $middleware->process($this->request('POST', self::TOKEN, 'replay-1'), $this->fixedResponseHandler);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->hasHeader('WWW-Authenticate'), 'the replay refusal is not an auth challenge');
        self::assertFalse($response->hasHeader('Retry-After'), 'the replay refusal is not an outage');
        self::assertSame('Forbidden', $this->envelope($response)['message']);
    }

    public function testAReplayIdPastItsWindowIsFreshAgain(): void
    {
        $middleware = $this->middleware();

        $middleware->process($this->request('POST', self::TOKEN, 'replay-1'), $this->fixedResponseHandler);

        $this->mutableClock->advance(300_001);
        $response = $middleware->process(
            $this->request('POST', self::TOKEN, 'replay-1'),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnOverLongReplayIdIsRefusedFailClosed(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', self::TOKEN, str_repeat('r', 129)),
            $this->fixedResponseHandler,
        );

        self::assertSame(403, $response->getStatusCode(), 'an id the boundary cannot carry is refused, never crashed');
    }

    public function testAReplayIdOfExactlyTheBoundAdmits(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', self::TOKEN, str_repeat('r', 128)),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAnEmptyReplayHeaderIsNoProtectionAsked(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', self::TOKEN, ''),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testALowerCaseMethodSpellingFolds(): void
    {
        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));

        $this->middleware(allowAnonymous: true)->process($this->request('get'), $recordingHandler);

        self::assertSame('anonymous', $recordingHandler->observed()->getAttribute('zef.security.principal'));
    }

    public function testALowerCaseUnsafeMethodFoldsToo(): void
    {
        $response = $this->middleware()->process($this->request('post'), $this->fixedResponseHandler);

        self::assertSame(401, $response->getStatusCode(), 'the method gate folds case-insensitively');
    }

    public function testALongPathIsTruncatedNotCrashed(): void
    {
        $path = '/' . str_repeat('segment/', 30);

        $response = $this->middleware()->process(
            $this->request('POST', self::TOKEN, null, $path),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode(), 'the label bounds truncate, never throw');
    }

    public function testASecondTableTokenAdmits(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', self::SECOND_TOKEN),
            $this->fixedResponseHandler,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testTheClockIsOneReadingSharedByTheCollaborators(): void
    {
        $credentialProvider = $this->providerAnswering(
            new AuthResult(AuthStatus::AUTHENTICATED, new AuthContext('api', 'static-bearer', 'route', 'api', null)),
        );
        $recordingReplayProtector = new RecordingReplayProtector();
        $authMiddleware = new AuthMiddleware(
            new AuthPolicy(tokens: [self::TOKEN]),
            new AuthBoundary($credentialProvider, new AllowScopePolicy('api'), $recordingReplayProtector),
            $this->mutableClock->now(),
        );

        $authMiddleware->process($this->request('POST', self::TOKEN, 'replay-1'), $this->fixedResponseHandler);

        self::assertSame(
            $credentialProvider->resolutions[0][1],
            $recordingReplayProtector->checks[0][1],
            'one request, one clock reading',
        );
    }

    public function testADisarmedPolicyIsAPassThrough(): void
    {
        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));
        $authMiddleware = new AuthMiddleware(new AuthPolicy(enabled: false));

        $response = $authMiddleware->process($this->request('POST'), $recordingHandler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($recordingHandler->observed()->getAttribute('zef.security.principal'));
    }

    public function testAReplayTableAtCapacityIsRefusedFailClosed(): void
    {
        $authPolicy = new AuthPolicy(tokens: [self::TOKEN], replayCapacity: 1);
        $authMiddleware = new AuthMiddleware(
            $authPolicy,
            new AuthBoundary(
                new StaticCredentialProvider($authPolicy->tokens),
                new AllowScopePolicy('api'),
                new BoundedReplayProtector($authPolicy->replayCapacity, 300_000),
            ),
            $this->mutableClock->now(),
        );

        $authMiddleware->process($this->request('POST', self::TOKEN, 'replay-1'), $this->fixedResponseHandler);

        $response = $authMiddleware->process($this->request('POST', self::TOKEN, 'replay-2'), $this->fixedResponseHandler);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('1', $response->getHeaderLine('Retry-After'));
    }

    /**
     * @dataProvider provideTheRefusalArmsAnswerTheirOwnStatusesCases
     */
    #[DataProvider('provideTheRefusalArmsAnswerTheirOwnStatusesCases')]
    public function testTheRefusalArmsAnswerTheirOwnStatuses(
        AuthFailure $authFailure,
        int $status,
        string $message,
        ?string $challenge,
        bool $retryAfter,
    ): void {
        $refusal = AuthRefusal::for($authFailure);

        self::assertSame($status, $refusal->status);
        self::assertSame($message, $refusal->message);
        self::assertSame($challenge, $refusal->challenge);
        self::assertSame($retryAfter, $refusal->retryAfter);
    }

    /**
     * Every failure's own answer: the taxonomy the boundary refuses
     * over, spelled the way the RFC and the outage posture hold it.
     *
     * @return iterable<non-empty-string, array{AuthFailure, int, string, null|string, bool}>
     */
    public static function provideTheRefusalArmsAnswerTheirOwnStatusesCases(): iterable
    {
        yield 'authentication failed' => [
            AuthFailure::AUTHENTICATION_FAILED,
            401,
            'Unauthorized',
            'Bearer error="invalid_token"',
            false,
        ];

        yield 'authentication unavailable' => [
            AuthFailure::AUTHENTICATION_UNAVAILABLE,
            503,
            'Service Unavailable',
            null,
            true,
        ];

        yield 'credential expired' => [
            AuthFailure::CREDENTIAL_EXPIRED,
            401,
            'Unauthorized',
            'Bearer error="invalid_token"',
            false,
        ];

        yield 'authorization denied' => [
            AuthFailure::AUTHORIZATION_DENIED,
            403,
            'Forbidden',
            'Bearer error="insufficient_scope"',
            false,
        ];

        yield 'replay rejected' => [AuthFailure::REPLAY_REJECTED, 403, 'Forbidden', null, false];

        yield 'replay unavailable' => [AuthFailure::REPLAY_UNAVAILABLE, 503, 'Service Unavailable', null, true];

        yield 'malformed metadata' => [
            AuthFailure::MALFORMED_METADATA,
            500,
            'Internal Server Error',
            null,
            false,
        ];
    }

    public function testTheUnauthenticatedRefusalCarriesTheBareChallenge(): void
    {
        $refusal = AuthRefusal::unauthenticated();

        self::assertSame(401, $refusal->status);
        self::assertSame('Unauthorized', $refusal->message);
        self::assertSame('Bearer', $refusal->challenge);
        self::assertFalse($refusal->retryAfter);
    }

    public function testTheRefusalEnvelopeCarriesTheCorrelationId(): void
    {
        $response = $this->middleware()->process($this->request('POST'), $this->fixedResponseHandler);

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $envelope = $this->envelope($response);
        self::assertTrue($envelope['error']);
        self::assertSame(401, $envelope['status']);
        self::assertIsString($envelope['correlation_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $envelope['correlation_id']);
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

    /**
     * The family's composed middleware, over the static table the
     * knobs shape.
     */
    private function middleware(
        string $requiredScope = 'api',
        bool $allowAnonymous = false,
    ): AuthMiddleware {
        $authPolicy = new AuthPolicy(
            tokens: [self::TOKEN, self::SECOND_TOKEN],
            allowAnonymous: $allowAnonymous,
        );

        return new AuthMiddleware(
            $authPolicy,
            new AuthBoundary(
                new StaticCredentialProvider(
                    $authPolicy->tokens,
                    $authPolicy->principal,
                    $authPolicy->scope,
                    $authPolicy->allowAnonymous,
                ),
                new AllowScopePolicy($requiredScope, $allowAnonymous),
                new BoundedReplayProtector($authPolicy->replayCapacity, $authPolicy->replayWindow * 1000),
            ),
            $this->mutableClock->now(),
        );
    }

    /**
     * The family's composed middleware over a recording provider -- the
     * statuses the static table never answers.
     */
    private function middlewareOver(RecordingCredentialProvider $recordingCredentialProvider): AuthMiddleware
    {
        return new AuthMiddleware(
            new AuthPolicy(tokens: [self::TOKEN]),
            new AuthBoundary($recordingCredentialProvider, new AllowScopePolicy('api'), new RecordingReplayProtector()),
            $this->mutableClock->now(),
        );
    }

    /**
     * One recording provider answering one result.
     */
    private function providerAnswering(AuthResult $authResult): RecordingCredentialProvider
    {
        return new RecordingCredentialProvider($authResult);
    }

    /**
     * The raw credential value one table token carries.
     */
    private function tokenValue(): string
    {
        return self::TOKEN;
    }

    /**
     * One request over the family's two channels: the Authorization
     * header and the replay id, over the given path.
     */
    private function request(
        string $method,
        ?string $token = null,
        ?string $replayId = null,
        string $path = '/health',
    ): ServerRequest {
        $request = new ServerRequest($method, new Uri('https://app.example.com' . $path));

        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer {$token}");
        }

        if ($replayId !== null) {
            $request = $request->withHeader('X-Replay-Id', $replayId);
        }

        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }
}
