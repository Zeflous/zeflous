<?php

declare(strict_types=1);

namespace Zef\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Http\ThrowingHandler;

/**
 * The pipeline's own minimum posture (issue #802's finding 2), booted end
 * to end from the DEFAULT layer alone: the profile's `middleware.stack`
 * ships the error boundary, so the defaults-only boot -- an application
 * directory with no configuration file -- composes a pipeline that
 * answers a failing handler with the RFC 9457 problem document instead
 * of an empty pass-through, while a host's own stack declaration (the
 * security fixture's single entry, or the deliberate empty list)
 * REPLACES the default wholesale -- the host's explicit choice stays
 * the winner it always was.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(FrameworkDefaults::class)]
final class DefaultStackBootTest extends TestCase
{
    public function testTheDefaultLayerShipsTheErrorBoundaryStack(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/empty');

        self::assertSame(
            ['stack' => [['service' => 'http.errors', 'priority' => 1000]]],
            $kernel->config()->get('middleware'),
            'the default profile carries the error boundary alone',
        );
    }

    public function testADefaultsOnlyBootAnswersAFailureWithTheProblemDocument(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(__DIR__ . '/fixtures/empty');

        $response = $applicationBootstrapper
            ->pipeline($kernel, new ThrowingHandler(new RuntimeException('the fixture failed')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(500, $response->getStatusCode());
        self::assertSame(
            'application/problem+json; charset=utf-8',
            $response->getHeaderLine('Content-Type'),
        );
        self::assertStringNotContainsString(
            'the fixture failed',
            (string) $response->getBody(),
            'the production envelope never leaks the failure detail',
        );
    }

    public function testAHostStackReplacesTheDefaultWholesale(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/fixtures/security');

        self::assertSame(
            ['stack' => ['security.headers']],
            $kernel->config()->get('middleware'),
            'the host list replaces the default -- no error-boundary residue appends to it',
        );
    }

    public function testADeliberateEmptyStackStillWins(): void
    {
        $applicationBootstrapper = new ApplicationBootstrapper();
        $kernel = $applicationBootstrapper->boot(__DIR__ . '/fixtures/default-stack');

        self::assertSame(['stack' => []], $kernel->config()->get('middleware'));

        $response = $applicationBootstrapper
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'answered',
            (string) $response->getBody(),
            'the deliberate empty stack runs no middleware at all -- the host choice wins',
        );
    }
}
