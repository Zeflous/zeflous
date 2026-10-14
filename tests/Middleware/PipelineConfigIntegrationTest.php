<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\PipelineFactory;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Middleware\MiddlewareTrace;
use Zef\Test\Support\Middleware\RecordingMiddleware;

/**
 * The two lanes' config-driven composition, end to end.
 *
 * One frozen configuration carries both sides: the `services` map defines
 * the middleware instances themselves (value definitions holding the
 * objects), and the `middleware.stack` list orders them by priority. The
 * {@see ContainerBootstrapper} installs the services, the
 * {@see PipelineFactory} resolves and orders the stack, and one `handle()`
 * call proves the onion ran in the priority order the configuration
 * declared -- the whole pipeline assembled from configuration, the way the
 * kernel assembles it in production.
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(PipelineFactory::class)]
final class PipelineConfigIntegrationTest extends TestCase
{
    public function testTheWholePipelineIsAssembledFromOneConfiguration(): void
    {
        $middlewareTrace = new MiddlewareTrace();

        $config = new Config([
            'services' => [
                'mw.security' => ['value' => new RecordingMiddleware('security', $middlewareTrace)],
                'mw.logging' => ['value' => new RecordingMiddleware('logging', $middlewareTrace)],
                'mw.throttle' => ['value' => new RecordingMiddleware('throttle', $middlewareTrace)],
            ],
            'middleware' => [
                'stack' => [
                    ['service' => 'mw.logging', 'priority' => 200],
                    ['service' => 'mw.security', 'priority' => 800],
                    'mw.throttle',
                ],
            ],
        ]);

        $container = new ContainerBootstrapper()->bootstrap($config, new Container());

        $pipeline = new PipelineFactory(
            $container,
            $config,
            new FixedResponseHandler(new Response(200, [], 'answered')),
        )->build();

        $response = $pipeline->handle(new ServerRequest('GET', new Uri('https://api.example.com/items')));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
        self::assertSame(
            [
                'security:enter',
                'logging:enter',
                'throttle:enter',
                'throttle:exit',
                'logging:exit',
                'security:exit',
            ],
            $middlewareTrace->marks(),
            'priority 800 runs outermost, priority 200 second, the priority-0 string entry innermost',
        );
    }

    public function testADeclarativelyBuiltMiddlewareConsumesContainerServices(): void
    {
        $middlewareTrace = new MiddlewareTrace();

        $config = new Config([
            'services' => [
                'app.mark' => ['value' => 'cfg'],
                'mw.stamping' => ['value' => new RecordingMiddleware('stamping', $middlewareTrace)],
            ],
            'middleware' => [
                'stack' => [
                    ['service' => 'mw.stamping', 'priority' => 10],
                ],
            ],
        ]);

        $container = new ContainerBootstrapper()->bootstrap($config, new Container());
        $container->set(
            'app.reporter',
            static function (Container $container): string {
                $mark = $container->get('app.mark');
                \assert(\is_string($mark));

                return 'reporter-over-' . $mark;
            },
        );

        $pipeline = new PipelineFactory(
            $container,
            $config,
            new FixedResponseHandler(new Response(200)),
        )->build();

        $pipeline->handle(new ServerRequest('GET', new Uri('https://api.example.com/items')));

        self::assertSame(['stamping:enter', 'stamping:exit'], $middlewareTrace->marks());

        $reporter = $container->get('app.reporter');

        self::assertIsString($reporter);
        self::assertSame('reporter-over-cfg', $reporter);
    }
}
