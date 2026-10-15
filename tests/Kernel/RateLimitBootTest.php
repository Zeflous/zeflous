<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RateLimitMiddleware;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The rate-limit family, booted end to end from real configuration
 * files: the fixture's `security.php` knobs and `middleware.php` stack,
 * laid over the framework's own default-layer registration, resolved
 * through the container the boot installed, and composed into the
 * pipeline -- the same door the shipped application profile uses.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(FrameworkDefaults::class)]
#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitBootTest extends TestCase
{
    private const string FIXTURE_CONFIGS = __DIR__ . '/fixtures/rate-limit';

    public function testTheFrameworkRegistrationIsInstalledByTheDefaultLayerAlone(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/empty');

        self::assertTrue(
            $kernel->container()->has('security.rate_limit'),
            'the default layer installs the framework registration',
        );
        self::assertTrue($kernel->container()->has('security.headers'));

        $rateLimiter = $kernel->container()->get('security.rate_limit');
        self::assertInstanceOf(RateLimitMiddleware::class, $rateLimiter);
    }

    public function testTheShippedProfileCarriesTheFrameworkRegistrationWithoutStackMembership(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../../app/configs');

        self::assertTrue($kernel->container()->has('security.rate_limit'));
        self::assertSame(['stack' => []], $kernel->config()->get('middleware'), 'nothing runs until the host opts in');

        $response = new ApplicationBootstrapper()
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertFalse($response->hasHeader('X-RateLimit-Limit'), 'an empty stack accounts nothing');
    }

    public function testTheFixtureKnobsAdmitOneRequestAndDenyTheSecond(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(self::FIXTURE_CONFIGS);
        $pipeline = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example.com/health'));

        $admitted = $pipeline->handle($serverRequest);

        self::assertSame(200, $admitted->getStatusCode());
        self::assertSame('1', $admitted->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('0', $admitted->getHeaderLine('X-RateLimit-Remaining'));

        $denied = $pipeline->handle($serverRequest);

        self::assertSame(429, $denied->getStatusCode());
        self::assertSame('3600', $denied->getHeaderLine('Retry-After'), "the hour-long window's remaining time");
        self::assertSame('1', $denied->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('0', $denied->getHeaderLine('X-RateLimit-Remaining'));

        $envelope = $this->envelope($denied);
        self::assertTrue($envelope['error']);
        self::assertSame(429, $envelope['status']);
        self::assertSame('Too Many Requests', $envelope['message']);
        self::assertIsString($envelope['correlation_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $envelope['correlation_id']);
    }

    public function testTheEnvironmentOverlayDisarmsTheThrottle(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__RATE_LIMIT__ENABLED' => 'off']),
        );

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader('X-RateLimit-Limit'), 'the overlay disarmed the throttle');
    }

    public function testTheEnvironmentOverlayTightensTheBudget(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__RATE_LIMIT__LIMIT' => '1']),
        );

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame('1', $response->getHeaderLine('X-RateLimit-Limit'), 'the overlay tightened the budget');
    }

    public function testAnUnknownFamilyHaltsAtResolution(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(__DIR__ . '/fixtures/rate-limit-typo');

        try {
            $applicationBootstrapper
                ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ;
            self::fail("An unknown family in the stack's subtree must halt the resolution.");
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'rate_limitt'"
                . ' (known families: headers, rate_limit, csrf).',
                $httpException->getMessage(),
            );
        }
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
}
