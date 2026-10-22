<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\AuthClock;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\AuthFactory;
use Zef\Test\Support\Http\ApcuFunctionDoubles;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\RecordingHandler;

/**
 * The auth construction seam, on its own terms: the build from the
 * frozen subtree (a bearer that admits and a wrong one that
 * refuses), the fail-loud halt when the armed family carries no
 * static tokens -- the CSRF family's own seam, argued the same way --
 * the disarmed build that carries no boundary, and the
 * unknown-family halt through the shared census.
 *
 * @internal
 */
#[CoversClass(AuthFactory::class)]
#[CoversClass(AuthClock::class)]
final class AuthFactoryTest extends TestCase
{
    private const string TOKEN = 'ZmFjdG9yeS1iZWFyZXItdG9rZW4tZnJvbS10aGUtdGVzdA';

    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'security' => [
                'auth' => [
                    'tokens' => [self::TOKEN],
                ],
            ],
        ]);

        $middleware = AuthFactory::fromConfig($config);

        $admitted = $middleware->process(
            $this->request('POST', self::TOKEN),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $admitted->getStatusCode());

        $refused = $middleware
            ->process($this->request('POST', 'wrong-but-grammar-valid-token'), new FixedResponseHandler(
                new Response(200, [], 'answered'),
            ))
        ;

        self::assertSame(401, $refused->getStatusCode(), 'a token the table does not carry is refused');
    }

    public function testTheFactoryComposesTheKnobShapedCollaborators(): void
    {
        $config = new Config([
            'security' => [
                'auth' => [
                    'tokens' => [self::TOKEN],
                    'principal' => 'service',
                    'scope' => 'api:read',
                    'required_scope' => 'api:read',
                    'allow_anonymous' => true,
                    'replay_capacity' => 8,
                    'replay_window' => 60,
                ],
            ],
        ]);

        $recordingHandler = new RecordingHandler(new Response(200, [], 'answered'));

        AuthFactory::fromConfig($config)
            ->process($this->request('GET', self::TOKEN), $recordingHandler)
        ;

        self::assertSame(
            'service',
            $recordingHandler->observed()->getAttribute('zef.security.principal'),
            'the principal knob names the principal the table resolves to',
        );
    }

    public function testTheApcuReplayStoreSelectionSharesTheSeenTableAcrossProtectors(): void
    {
        ApcuFunctionDoubles::define();
        // The audit's probabilistic-absence shape: two protectors the
        // same process tree would build -- one per worker -- over the
        // shared table the `replay_store` knob declared.
        $config = new Config([
            'security' => [
                'auth' => [
                    'tokens' => [self::TOKEN],
                    'replay_store' => 'apcu',
                    'replay_window' => 60,
                ],
            ],
        ]);

        $firstWorker = AuthFactory::fromConfig($config);
        $secondWorker = AuthFactory::fromConfig($config);

        $admitted = $firstWorker->process(
            $this->request('POST', self::TOKEN, 'replay-shared-1'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );
        self::assertSame(200, $admitted->getStatusCode(), 'the first sight is admitted');

        $refused = $secondWorker->process(
            $this->request('POST', self::TOKEN, 'replay-shared-1'),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );
        self::assertSame(
            403,
            $refused->getStatusCode(),
            'the second worker answers the duplicate the shared table refused',
        );
    }

    public function testTheFactoryHaltsOnAnUnknownReplayStore(): void
    {
        $config = new Config([
            'security' => [
                'auth' => [
                    'tokens' => [self::TOKEN],
                    'replay_store' => 'reddis',
                ],
            ],
        ]);

        try {
            AuthFactory::fromConfig($config);
            self::fail('An unknown replay store must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth replay_store knob must be one of the known replay stores '
                . "(memory, apcu), 'reddis' given.",
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsWhenTheFamilyIsArmedWithoutTokens(): void
    {
        $config = new Config([
            'security' => [
                'auth' => [],
            ],
        ]);

        try {
            AuthFactory::fromConfig($config);
            self::fail('An armed family with no tokens must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth family is enabled but carries no static bearer tokens:'
                . ' provide at least one through the environment overlay'
                . ' (APP_SECURITY__AUTH__TOKENS, comma-separated, each 32 characters or above)'
                . ' or a configuration file, or disarm the family explicitly'
                . ' (security.auth enabled = false).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryBuildsADisarmedBoundary(): void
    {
        $config = new Config([
            'security' => [
                'auth' => [
                    'enabled' => false,
                ],
            ],
        ]);

        $response = AuthFactory::fromConfig($config)
            ->process($this->request('POST'), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(200, $response->getStatusCode(), 'the disarmed boundary is a pass-through');
    }

    public function testTheSecondsToMillisecondsBridgeCarriesExactly(): void
    {
        self::assertSame(300_000, AuthClock::millisecondsFromSeconds(300));
        self::assertSame(1_000, AuthClock::millisecondsFromSeconds(1));
    }

    public function testTheClockReadsTheCurrentEpochInMilliseconds(): void
    {
        self::assertGreaterThan(10 ** 12, AuthClock::milliseconds());
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config([
            'security' => [
                'autth' => [],
            ],
        ]);

        try {
            AuthFactory::fromConfig($config);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'autth'"
                . ' (known families: headers, rate_limit, csrf, auth).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * One request over the bearer channel.
     */
    private function request(string $method, ?string $token = null, ?string $replayId = null): ServerRequest
    {
        $request = new ServerRequest($method, new Uri('https://app.example.com/health'));

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
