<?php

declare(strict_types=1);

namespace Zef\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\CsrfMiddleware;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The CSRF family, booted end to end from real configuration files:
 * the fixture's `security.php` knobs and `middleware.php` stack, laid
 * over the framework's own default-layer registration, resolved
 * through the container the boot installed, and composed into the
 * pipeline -- the same door the shipped application profile uses.
 *
 * The fixture carries no secret on purpose: the signing secret is
 * runtime material and the environment overlay is where it belongs, so
 * every resolving test boots with an `EnvSource` carrying one, exactly
 * as a deployment would -- and the boot without it halts with the
 * remediation spelled out, the family's own fail-loud seam.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(FrameworkDefaults::class)]
#[CoversClass(CsrfMiddleware::class)]
final class CsrfBootTest extends TestCase
{
    private const string FIXTURE_CONFIGS = __DIR__ . '/fixtures/csrf';

    private const string OVERLAY_SECRET = 'boot-overlay-secret-thirty-two-bytes!';

    public function testTheFrameworkRegistrationIsInstalledByTheDefaultLayerAlone(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/empty');

        self::assertTrue(
            $kernel->container()->has('security.csrf'),
            'the default layer installs the framework registration',
        );
        self::assertTrue($kernel->container()->has('security.rate_limit'));
        self::assertTrue($kernel->container()->has('security.headers'));
    }

    public function testTheShippedProfileCarriesTheRegistrationWithoutStackMembership(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/../../app/configs');

        self::assertTrue($kernel->container()->has('security.csrf'));
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
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertFalse($response->hasHeader('Set-Cookie'), 'an empty stack issues nothing');
    }

    public function testAnArmedResolutionWithoutASecretHaltsTheBootWithTheRemediation(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(self::FIXTURE_CONFIGS);

        try {
            $applicationBootstrapper
                ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ;
            self::fail('An armed family resolved without a secret must halt the boot.');
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

    public function testTheFixtureKnobsProtectTheStateMutation(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__CSRF__SECRET' => self::OVERLAY_SECRET]),
        );
        $pipeline = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        $navigated = $pipeline->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')));

        self::assertSame(200, $navigated->getStatusCode());
        self::assertMatchesRegularExpression(
            '/^APP-CSRF-TOKEN=[A-Za-z0-9_-]+\.[a-f0-9]{64}; Path=\/; SameSite=Lax; Secure; HttpOnly$/D',
            $navigated->getHeaderLine('Set-Cookie'),
            "the fixture's own field names and posture, the secret from the overlay",
        );

        $token = $this->tokenFrom($navigated->getHeaderLine('Set-Cookie'));

        $refused = $pipeline->handle(
            $this->postWith($token, 'an-empty-header'),
        );

        self::assertSame(403, $refused->getStatusCode(), 'the header channel missing is a refused submit');

        $admitted = $pipeline->handle($this->postWith($token, $token));

        self::assertSame(200, $admitted->getStatusCode(), "the double submit over the fixture's own header name");
    }

    public function testTheEnvironmentOverlayDisarmsTheBoundary(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', ['APP_SECURITY__CSRF__ENABLED' => 'off']),
        );

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('POST', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(200, $response->getStatusCode(), 'the overlay disarmed the boundary');
        self::assertFalse($response->hasHeader('Set-Cookie'));
    }

    public function testTheEnvironmentOverlayTightensTheEntropy(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(
            self::FIXTURE_CONFIGS,
            new EnvSource('APP_', [
                'APP_SECURITY__CSRF__SECRET' => self::OVERLAY_SECRET,
                'APP_SECURITY__CSRF__TOKEN_BYTES' => '16',
            ]),
        );

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertMatchesRegularExpression(
            '/^APP-CSRF-TOKEN=[A-Za-z0-9_-]{22}\.[a-f0-9]{64};/D',
            $response->getHeaderLine('Set-Cookie'),
            'the overlay tightened the per-token entropy',
        );
    }

    /**
     * The raw Set-Cookie line's own token: the value between the first
     * `=` and the first `;`.
     */
    private function tokenFrom(string $setCookie): string
    {
        $value = mb_substr($setCookie, (int) mb_strpos($setCookie, '=') + 1);

        return mb_substr($value, 0, (int) mb_strpos($value, ';'));
    }

    /**
     * One unsafe request carrying the cookie and the fixture's own
     * header channel.
     */
    private function postWith(string $token, ?string $headerToken): ServerRequest
    {
        $request = new ServerRequest('POST', new Uri('https://app.example.com/health'))
            ->withHeader('Cookie', 'APP-CSRF-TOKEN=' . $token)
        ;

        if ($headerToken !== null) {
            $request = $request->withHeader('X-App-Csrf', $headerToken);
        }

        self::assertInstanceOf(ServerRequest::class, $request);

        return $request;
    }
}
