<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\CsrfTokenManager;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\CsrfFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The CSRF construction seam, on its own terms: the build from the
 * frozen subtree (a double submit that passes and a missing channel
 * that refuses), the fail-loud halt when the armed family carries no
 * secret -- the one posture the reference answered with a silent
 * disable -- and the unknown-family halt through the shared census.
 *
 * @internal
 */
#[CoversClass(CsrfFactory::class)]
final class CsrfFactoryTest extends TestCase
{
    private const string SECRET = 'factory-test-secret-thirty-two-bytes!';

    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'security' => [
                'csrf' => [
                    'secret' => self::SECRET,
                ],
            ],
        ]);

        $middleware = CsrfFactory::fromConfig($config);
        $token = new CsrfTokenManager(self::SECRET)->issue();

        $admitted = $middleware->process(
            $this->request('POST', $token, $token),
            new FixedResponseHandler(new Response(200, [], 'answered')),
        );

        self::assertSame(200, $admitted->getStatusCode());

        $refused = $middleware
            ->process($this->request('POST', $token), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(403, $refused->getStatusCode(), 'the header channel missing is a refused submit');
    }

    public function testTheFactoryHaltsWhenTheFamilyIsArmedWithoutASecret(): void
    {
        $config = new Config([
            'security' => [
                'csrf' => [],
            ],
        ]);

        try {
            CsrfFactory::fromConfig($config);
            self::fail('An armed family with no secret must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.csrf family is enabled but carries no usable signing secret:'
                . ' provide one through the environment overlay (APP_SECURITY__CSRF__SECRET,'
                . ' at least 32 bytes) or a configuration file, or disarm the family explicitly'
                . ' (security.csrf enabled = false).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryBuildsADisarmedBoundary(): void
    {
        $config = new Config([
            'security' => [
                'csrf' => [
                    'enabled' => false,
                ],
            ],
        ]);

        $response = CsrfFactory::fromConfig($config)
            ->process($this->request('POST'), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(200, $response->getStatusCode(), 'the disarmed boundary is a pass-through');
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config(['security' => ['csrff' => ['enabled' => true]]]);

        try {
            CsrfFactory::fromConfig($config);
            self::fail('An unknown family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'csrff'"
                . ' (known families: headers, rate_limit, csrf).',
                $httpException->getMessage(),
            );
        }
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
}
