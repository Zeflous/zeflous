<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\InMemorySessionAdapter;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\SessionMiddleware;
use Zef\Framework\Http\SessionPolicy;
use Zef\Framework\Http\SessionResolution;
use Zef\Framework\Http\SessionSettlement;
use Zef\Framework\Http\SessionState;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\MutableClock;
use Zef\Test\Support\Http\RecordingSessionAdapter;
use Zef\Test\Support\Http\SessionObservingHandler;
use Zef\Test\Support\Http\SessionWritingHandler;

/**
 * The session family's PSR-15 boundary, on its own terms: the request
 * decorated with its session bag, the storage verdict and the
 * `Set-Cookie` line the session's outbound walk owes -- the fresh
 * mint a cookieless or expired or malformed-carried request answers,
 * the loaded bag a live cookie answers with no new cookie line, the
 * write-only refresh a clean live request leaves behind, the
 * destruction that retires the storage entry and answers the
 * deletion cookie -- and the fail-closed read the non-string cookie
 * bag refuses with.
 *
 * @internal
 */
#[CoversClass(SessionMiddleware::class)]
#[CoversClass(SessionResolution::class)]
#[CoversClass(SessionSettlement::class)]
final class SessionMiddlewareTest extends TestCase
{
    /** One carried identifier the live-session tests spell once. */
    private const string LIVE_ID = 'AQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

    public function testADisarmedFamilyIsTheInertPassThrough(): void
    {
        $request = $this->request([]);
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $seen = null;

            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                $this->seen = $serverRequest;

                return new Response(200);
            }
        };

        $response = new SessionMiddleware(new SessionPolicy(enabled: false))->process($request, $handler);

        self::assertSame($request, $handler->seen, "the request is the handler's own, untouched");
        self::assertSame([], $response->getHeader('Set-Cookie'), 'a disabled family answers no cookie line');
    }

    public function testACookielessRequestTheHandlerNeverWroteAnswersNeither(): void
    {
        // The round-3 audit's R3-4: a mint the handler never wrote into
        // is not state worth persisting -- no storage entry, no issuance
        // cookie. The cookie-less traffic (crawler, attacker, private
        // browsing) that mints and never returns must not grow the
        // storage one abandoned empty entry per request.
        $recordingSessionAdapter = new RecordingSessionAdapter();
        $sessionObservingHandler = new SessionObservingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), $recordingSessionAdapter)->process(
            $this->request([]),
            $sessionObservingHandler,
        );

        $state = $sessionObservingHandler->state;
        self::assertNotNull($state, 'the handler answered a request decorated with the session bag');
        self::assertTrue($state->isNew());
        self::assertSame([], $state->data());
        self::assertSame([], $recordingSessionAdapter->savedIds, 'an untouched mint is never saved');
        self::assertSame([], $response->getHeader('Set-Cookie'), 'an untouched mint owes no cookie line');
    }

    public function testACookielessRequestTheHandlerWritesAnswersTheCookieAndTheSave(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);
        $sessionWritingHandler = new SessionWritingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request([]),
            $sessionWritingHandler,
        );

        $state = $sessionWritingHandler->state;
        self::assertNotNull($state);
        self::assertTrue($state->isNew());

        $cookieLines = $response->getHeader('Set-Cookie');
        self::assertCount(1, $cookieLines);
        self::assertMatchesRegularExpression(
            '/^zef_session=[A-Za-z0-9_-]{43}; Path=\/; SameSite=Lax; HttpOnly$/',
            $cookieLines[0],
            'the fresh identifier rides the secure-default cookie, Secure staying scheme-conditional over http',
        );
        self::assertSame(
            ['user' => 7],
            $inMemorySessionAdapter->load($state->id()),
            'the written bag is saved under the fresh identifier',
        );
    }

    public function testASecureSchemeCarriesTheSecureAttribute(): void
    {
        $sessionWritingHandler = new SessionWritingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), new InMemorySessionAdapter(1800))->process(
            $this->request([], 'https'),
            $sessionWritingHandler,
        );

        self::assertMatchesRegularExpression(
            '/^zef_session=[A-Za-z0-9_-]{43}; Path=\/; SameSite=Lax; Secure; HttpOnly$/',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testACarriedLiveSessionIsLoadedWithoutANewCookie(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);
        $inMemorySessionAdapter->save(self::LIVE_ID, ['user' => 7]);

        $sessionObservingHandler = new SessionObservingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request(['zef_session' => self::LIVE_ID]),
            $sessionObservingHandler,
        );

        $state = $sessionObservingHandler->state;
        self::assertNotNull($state);
        self::assertFalse($state->isNew());
        self::assertSame(self::LIVE_ID, $state->id());
        self::assertSame(7, $state->value('user'));
        self::assertSame([], $response->getHeader('Set-Cookie'), 'a live session owes no cookie line');
    }

    public function testACarriedExpiredSessionAnswersAFreshSession(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save(self::LIVE_ID, ['user' => 7]);

        $mutableClock->advance(1800);
        $sessionObservingHandler = new SessionObservingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request(['zef_session' => self::LIVE_ID]),
            $sessionObservingHandler,
        );

        $state = $sessionObservingHandler->state;
        self::assertNotNull($state);
        self::assertTrue($state->isNew(), 'the expired session answers a fresh mint');
        self::assertNotSame(self::LIVE_ID, $state->id());
        self::assertNull($inMemorySessionAdapter->load(self::LIVE_ID), 'the stale identifier stays retired');
        self::assertSame(
            [],
            $response->getHeader('Set-Cookie'),
            'an untouched fresh mint owes no cookie line -- the handler never wrote into it',
        );
    }

    public function testAMalformedCarriedValueIsTreatedAsAbsent(): void
    {
        $recordingSessionAdapter = new RecordingSessionAdapter();
        $sessionObservingHandler = new SessionObservingHandler();

        $response = new SessionMiddleware(new SessionPolicy(), $recordingSessionAdapter)->process(
            $this->request(['zef_session' => 'not!a!session!id!at!all!!!!']),
            $sessionObservingHandler,
        );

        $state = $sessionObservingHandler->state;
        self::assertNotNull($state);
        self::assertTrue($state->isNew(), 'a malformed cookie is answered with a fresh session, not a refusal');
        self::assertSame(
            [],
            $recordingSessionAdapter->loadedIds,
            'the storage is never asked about a value the grammar already refused',
        );
        self::assertSame(
            [],
            $response->getHeader('Set-Cookie'),
            'an untouched fresh mint owes no cookie line',
        );
    }

    public function testANonStringCookieBagValueRefusesFailClosed(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The request cookie bag carries a non-string value under the name'
            . " 'zef_session' (int).",
        );

        new SessionMiddleware(new SessionPolicy(), new InMemorySessionAdapter(1800))->process(
            $this->request(['zef_session' => 7]),
            new SessionObservingHandler(),
        );
    }

    public function testAHandlerWritePersistsWithoutANewCookie(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);
        $inMemorySessionAdapter->save(self::LIVE_ID, []);

        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                $state = $serverRequest->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                Assert::assertInstanceOf(SessionState::class, $state);
                $state->set('user', 7);

                return new Response(200);
            }
        };

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request(['zef_session' => self::LIVE_ID]),
            $handler,
        );

        self::assertSame(['user' => 7], $inMemorySessionAdapter->load(self::LIVE_ID), 'the write is persisted');
        self::assertSame([], $response->getHeader('Set-Cookie'), 'a live session owes no cookie line');
    }

    public function testACleanLiveRequestDoesNotRefreshTheIdleClock(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save(self::LIVE_ID, ['user' => 7]);

        $mutableClock->advance(100);

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request(['zef_session' => self::LIVE_ID]),
            new SessionObservingHandler(),
        );

        self::assertSame([], $response->getHeader('Set-Cookie'));

        $mutableClock->advance(1700);

        self::assertNull(
            $inMemorySessionAdapter->load(self::LIVE_ID),
            'the clean request did not write, so it did not refresh the idle clock -- the write-only refresh',
        );
    }

    public function testAHandlerDestructionRetiresTheStorageAndAnswersTheDeletionCookie(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);
        $inMemorySessionAdapter->save(self::LIVE_ID, ['user' => 7]);

        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                $state = $serverRequest->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                Assert::assertInstanceOf(SessionState::class, $state);
                $state->destroy();

                return new Response(200);
            }
        };

        $response = new SessionMiddleware(new SessionPolicy(), $inMemorySessionAdapter)->process(
            $this->request(['zef_session' => self::LIVE_ID]),
            $handler,
        );

        self::assertNull($inMemorySessionAdapter->load(self::LIVE_ID), 'the storage entry is destroyed');
        self::assertSame(
            ['zef_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; SameSite=Lax; HttpOnly'],
            $response->getHeader('Set-Cookie'),
            'the deletion cookie retires the identifier the client still carries',
        );
    }

    public function testADestroyedFreshSessionStillAnswersTheDeletionCookie(): void
    {
        $recordingSessionAdapter = new RecordingSessionAdapter();
        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                $state = $serverRequest->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                Assert::assertInstanceOf(SessionState::class, $state);
                $state->destroy();

                return new Response(200);
            }
        };

        $response = new SessionMiddleware(new SessionPolicy(), $recordingSessionAdapter)->process(
            $this->request([]),
            $handler,
        );

        self::assertCount(
            1,
            $recordingSessionAdapter->destroyedIds,
            'the unsaved identifier is still destroyed, the no-op it spells',
        );
        self::assertSame([], $recordingSessionAdapter->savedIds, 'a destroyed fresh session is never saved');
        self::assertMatchesRegularExpression(
            '/^zef_session=; Path=\/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0;/',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testTheIssuanceAppendsBesideTheHandlersOwnCookie(): void
    {
        $handler = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                $state = $serverRequest->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                Assert::assertInstanceOf(SessionState::class, $state);
                $state->set('user', 7);

                $message = new Response(200)->withAddedHeader('Set-Cookie', 'theme=dark; Path=/');
                Assert::assertInstanceOf(ResponseInterface::class, $message);

                return $message;
            }
        };

        $response = new SessionMiddleware(new SessionPolicy(), new InMemorySessionAdapter(1800))->process(
            $this->request([]),
            $handler,
        );

        $cookieLines = $response->getHeader('Set-Cookie');
        self::assertCount(2, $cookieLines, 'a session issuance never costs the handler its own cookie');
        self::assertSame('theme=dark; Path=/', $cookieLines[0]);
        self::assertMatchesRegularExpression('/^zef_session=/', $cookieLines[1]);
    }

    /**
     * One request over the given cookie bag and scheme.
     *
     * @param array<string, mixed> $cookieParams
     */
    private function request(array $cookieParams, string $scheme = 'http'): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri($scheme . '://app.example.com/'),
            cookieParams: $cookieParams,
        );
    }
}
