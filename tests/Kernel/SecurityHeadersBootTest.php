<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\BaselineSecurityHeaders;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeadersMiddleware;
use Zef\Framework\Http\SecurityHeadersPolicy;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\ToggledSecurityHeaders;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\SecurityHeadersFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The security-headers family, booted end to end from real configuration
 * files: the fixture's `security.php` knobs and `middleware.php` stack,
 * laid over the framework's own default-layer registration, resolved
 * through the container the boot installed, and composed into the
 * pipeline -- the same door the shipped application profile uses.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(FrameworkDefaults::class)]
#[CoversClass(SecurityHeadersFactory::class)]
#[CoversClass(SecurityHeadersMiddleware::class)]
#[CoversClass(SecurityHeadersPolicy::class)]
#[CoversClass(BaselineSecurityHeaders::class)]
#[CoversClass(ToggledSecurityHeaders::class)]
final class SecurityHeadersBootTest extends TestCase
{
    private const string FIXTURE_CONFIGS = __DIR__ . '/fixtures/security';

    public function testTheFrameworkRegistrationIsInstalledByTheDefaultLayerAlone(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/empty');

        self::assertTrue(
            $kernel->container()->has('security.headers'),
            'the default layer installs the framework registration',
        );
        self::assertTrue($kernel->container()->has(FrameworkDefaults::CONFIG_SERVICE));

        $securityHeaders = $kernel->container()->get('security.headers');
        self::assertInstanceOf(SecurityHeadersMiddleware::class, $securityHeaders);
    }

    public function testTheShippedProfileCarriesTheFrameworkRegistrationWithoutStackMembership(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../../app/configs');

        self::assertTrue($kernel->container()->has('security.headers'));
        self::assertSame(['stack' => []], $kernel->config()->get('middleware'), 'nothing runs until the host opts in');

        $response = new ApplicationBootstrapper()
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertFalse($response->hasHeader('X-Content-Type-Options'), 'an empty stack decorates nothing');
    }

    public function testTheFixtureKnobsDriveTheHardenedResponse(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(self::FIXTURE_CONFIGS);

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame('none', $response->getHeaderLine('X-Permitted-Cross-Domain-Policies'));
        self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'));
        self::assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Resource-Policy'));
        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $response->getHeaderLine('Strict-Transport-Security'),
            'the fixture opted in to HSTS',
        );
        self::assertFalse($response->hasHeader('Permissions-Policy'), 'the fixture switched the toggle off');
        self::assertFalse($response->hasHeader('Content-Security-Policy'), 'the csp knob stayed at its default: off');
    }

    public function testTheEnvironmentOverlayWinsOverTheFileKnobs(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__HEADERS__CSP' => 'on']),
        );

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(
            "default-src 'self'; frame-ancestors 'none'; base-uri 'self'",
            $response->getHeaderLine('Content-Security-Policy'),
            "the env overlay's raw 'on' string folds through the strict reading",
        );
        self::assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertFalse($response->hasHeader('Permissions-Policy'), 'the untouched file knob survives the overlay');
    }
}
