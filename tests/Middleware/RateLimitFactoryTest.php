<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityFamilies;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\RateLimitFactory;
use Zef\Test\Support\Http\ApcuDoubleState;
use Zef\Test\Support\Http\ApcuFunctionDoubles;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The rate-limit construction seam, on its own terms: the memory store's
 * default build, the apcu store built and accounted through the shared
 * namespaced function doubles ({@see ApcuFunctionDoubles}, which shadow
 * the global calls in every environment, extension present or not), the
 * redis store's fail-closed boot refusal (the same unified answer
 * whether the extension is missing or the endpoint refuses -- verified
 * in its own process so no class definition ever leaks), and the
 * unknown-family halt through the shared census.
 *
 * @internal
 */
#[CoversClass(RateLimitFactory::class)]
#[CoversClass(SecurityFamilies::class)]
final class RateLimitFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheMemoryStoreFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'enabled' => true,
                    'limit' => 2,
                    'window' => 3600,
                ],
            ],
        ]);

        $middleware = RateLimitFactory::fromConfig($config);

        $response = $middleware
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('2', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('1', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testTheFactoryReadsTheDefaultsWhenTheFamilyIsAbsent(): void
    {
        $middleware = RateLimitFactory::fromConfig(new Config());

        $response = $middleware
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertFalse($response->hasHeader('X-RateLimit-Limit'), 'the default posture is disarmed');
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config(['security' => ['rate_limitt' => ['enabled' => true]]]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('An unknown family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'rate_limitt'"
                . ' (known families: headers, rate_limit, csrf, auth).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheApcuStoreBuildsAndAccountsThroughTheNamespacedDoubles(): void
    {
        ApcuFunctionDoubles::define();

        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'enabled' => true,
                    'limit' => 5,
                    'window' => 60,
                    'store' => 'apcu',
                ],
            ],
        ]);

        $response = RateLimitFactory::fromConfig($config)
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));

        self::assertNotCount(0, ApcuDoubleState::$incCalls, 'the accounting reached the apcu boundary');
    }

    public function testTheRedisStoreHaltsAtBootWhenUnavailable(): void
    {
        $config = new Config([
            'security' => [
                'rate_limit' => [
                    'store' => 'redis',
                    'redis_host' => '127.0.0.1',
                    'redis_port' => 6379,
                ],
            ],
        ]);

        try {
            RateLimitFactory::fromConfig($config);
            self::fail('A redis store the runner cannot connect to must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The redis rate-limit store is unavailable at 127.0.0.1:6379'
                . ' (install ext-redis and start the server, or declare the memory store).',
                $httpException->getMessage(),
                'the same refusal whether the extension is missing or the endpoint refuses',
            );
        }
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example.com/health'));
    }
}
