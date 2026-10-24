<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\SessionFactory;
use Zef\Test\Support\Http\SessionObservingHandler;

/**
 * The session middleware's construction seam, on its own terms: the
 * build from the frozen subtree (a decorated request whose session
 * cookie carries the name the subtree declared, not the constructor
 * default), the absent family's armed default posture, and the
 * disarmed pass-through -- through the factory the profile's own
 * `http.session` service registration names.
 *
 * The census halts (the unknown family, the non-map root, the unknown
 * knob) are the family's own and are pinned where they live ({@see
 * \Zef\Test\Http\SessionPolicyTest}); this test pins the wiring the
 * factory alone owns: the subtree read and the policy handed over.
 *
 * @internal
 */
#[CoversClass(SessionFactory::class)]
final class SessionFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'http' => [
                'session' => [
                    'cookie_name' => 'shop_session',
                ],
            ],
        ]);
        $sessionObservingHandler = new SessionObservingHandler();

        $response = SessionFactory::fromConfig($config)
            ->process($this->request(), $sessionObservingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($sessionObservingHandler->state, 'the factory-built boundary decorates the session bag');
        self::assertMatchesRegularExpression(
            '/^shop_session=[A-Za-z0-9_-]{43};/',
            $response->getHeaderLine('Set-Cookie'),
            "the subtree's own cookie name rides the issuance, not the constructor default",
        );
    }

    public function testTheFactoryBuildsTheDefaultArmedPostureFromTheAbsentFamily(): void
    {
        $sessionObservingHandler = new SessionObservingHandler();

        $response = SessionFactory::fromConfig(new Config([]))
            ->process($this->request(), $sessionObservingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($sessionObservingHandler->state);
        self::assertTrue($sessionObservingHandler->state->isNew(), 'the absent family reads the armed default posture');
        self::assertMatchesRegularExpression(
            '/^zef_session=[A-Za-z0-9_-]{43};/',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testTheFactoryBuildsADisarmedPassThrough(): void
    {
        $config = new Config([
            'http' => [
                'session' => [
                    'enabled' => false,
                ],
            ],
        ]);
        $sessionObservingHandler = new SessionObservingHandler();

        $response = SessionFactory::fromConfig($config)
            ->process($this->request(), $sessionObservingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($sessionObservingHandler->state, 'a disabled family is the inert pass-through');
        self::assertSame([], $response->getHeader('Set-Cookie'));
    }

    /**
     * One cookieless request over the http scheme.
     */
    private function request(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('http://app.example.com/'),
        );
    }
}
