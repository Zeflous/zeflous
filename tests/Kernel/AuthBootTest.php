<?php

declare(strict_types=1);

namespace Zef\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\AuthMiddleware;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The auth family, booted end to end from real configuration files:
 * the fixture's `security.php` knobs and `middleware.php` stack, laid
 * over the framework's own default-layer registration, resolved
 * through the container the boot installed, and composed into the
 * pipeline -- the same door the shipped application profile uses.
 *
 * The fixture carries no static tokens on purpose: the token table is
 * runtime material and the environment overlay is where it belongs,
 * so every resolving test boots with an `EnvSource` carrying one,
 * exactly as a deployment would -- and the boot without it halts with
 * the remediation spelled out, the family's own fail-loud seam.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(AuthMiddleware::class)]
#[CoversClass(FrameworkDefaults::class)]
final class AuthBootTest extends TestCase
{
    private const string FIXTURE_CONFIGS = __DIR__ . '/fixtures/auth';

    private const string OVERLAY_TOKENS = 'Ym9vdC1vdmVybGF5LXRva2VuLWZyb20tZW52aXJvbg';

    public function testTheFrameworkRegistrationIsInstalledByTheDefaultLayerAlone(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/empty');

        self::assertTrue(
            $kernel->container()->has('security.auth'),
            'the default layer installs the framework registration',
        );
        self::assertTrue($kernel->container()->has('security.csrf'));
        self::assertTrue($kernel->container()->has('security.rate_limit'));
        self::assertTrue($kernel->container()->has('security.headers'));
    }

    public function testTheShippedProfileCarriesTheRegistrationWithoutStackMembership(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../../app/configs');

        self::assertTrue($kernel->container()->has('security.auth'));
        self::assertSame(
            [
                'stack' => [
                    ['service' => 'http.errors', 'priority' => 1000],
                    ['service' => 'http.debug_toolbar', 'priority' => 900],
                ],
            ],
            $kernel->config()->get('middleware'),
            'only the global error boundary and development toolbar are in the default stack',
        );

        $response = new ApplicationBootstrapper()
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('POST', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(200, $response->getStatusCode(), 'an empty stack authenticates nobody');
    }

    public function testAnArmedResolutionWithoutTokensHaltsTheBootWithTheRemediation(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(self::FIXTURE_CONFIGS);

        try {
            $applicationBootstrapper
                ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ;
            self::fail('An armed family resolved without tokens must halt the boot.');
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

    public function testTheOverlayTokenAdmitsTheStateMutation(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__AUTH__TOKENS' => self::OVERLAY_TOKENS]),
        );
        $pipeline = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        $request = new ServerRequest('POST', new Uri('https://app.example.com/health'))
            ->withHeader('Authorization', 'Bearer ' . self::OVERLAY_TOKENS)
        ;

        self::assertInstanceOf(ServerRequest::class, $request);

        $admitted = $pipeline->handle($request);

        self::assertSame(200, $admitted->getStatusCode(), 'the overlay token is table material');
    }

    public function testTheFixtureAnonymousPostureAdmitsSafeMethodsOnly(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__AUTH__TOKENS' => self::OVERLAY_TOKENS]),
        );
        $pipeline = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        $safe = $pipeline->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')));
        $unsafe = $pipeline->handle(new ServerRequest('POST', new Uri('https://app.example.com/health')));

        self::assertSame(200, $safe->getStatusCode(), 'the fixture allows read-only anonymous access');
        self::assertSame(401, $unsafe->getStatusCode(), 'anonymous never reaches an unsafe method');
    }
}
