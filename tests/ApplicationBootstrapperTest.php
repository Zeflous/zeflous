<?php

declare(strict_types=1);

namespace Zef\Test;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\Config\Routing\RoutingRuntime;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Health\HealthChecker;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\TimingMiddleware;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\AuthFactory;
use Zef\Framework\Middleware\CorrelationFactory;
use Zef\Framework\Middleware\CorrelationMiddleware;
use Zef\Framework\Middleware\CsrfFactory;
use Zef\Framework\Middleware\DebugToolbarMiddleware;
use Zef\Framework\Middleware\ErrorMappingMiddleware;
use Zef\Framework\Middleware\MetricsFactory;
use Zef\Framework\Middleware\MetricsMiddleware;
use Zef\Framework\Middleware\RateLimitFactory;
use Zef\Framework\Middleware\SecurityHeadersFactory;
use Zef\Framework\Middleware\TimingFactory;
use Zef\Framework\Middleware\TracingFactory;
use Zef\Framework\Telemetry\CounterMeter;
use Zef\Framework\Telemetry\NoopSpan;
use Zef\Framework\Telemetry\Tracer;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Middleware\MiddlewareTrace;

/**
 * The one-call boot, end to end from real configuration files.
 *
 * Every test in this file boots through the same door the shipped
 * application profile uses: a directory of PHP files read through the
 * configuration layer's own loaders, laid over the framework's code-declared
 * defaults, validated and cached by the configuration bootstrapper, and
 * installed into the container -- never an in-memory array in the place of
 * a file.
 *
 * @internal
 */
#[CoversClass(ApplicationBootstrapper::class)]
#[CoversClass(FrameworkDefaults::class)]
final class ApplicationBootstrapperTest extends TestCase
{
    private const string SHIPPED_CONFIGS = __DIR__ . '/../app/configs';

    private const string FIXTURE_CONFIGS = __DIR__ . '/Kernel/fixtures/configs';

    private const string SCAN_CONFIGS = __DIR__ . '/Kernel/fixtures/scan';

    private ?string $temporaryRoot = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->temporaryRoot !== null && is_dir($this->temporaryRoot)) {
            $this->remove($this->temporaryRoot);
        }

        $this->temporaryRoot = null;
    }

    public function testBootsTheShippedApplicationProfile(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(self::SHIPPED_CONFIGS);

        self::assertSame(
            [],
            $kernel->config()->get('services'),
            "the host's declaration surface is empty and stays the host's own",
        );
        self::assertSame(
            [
                'http.errors' => [
                    'factory' => \Zef\Framework\Middleware\ErrorMappingFactory::class . '::fromConfig',
                    'arguments' => [
                        ['service' => FrameworkDefaults::CONFIG_SERVICE],
                        ['service' => 'observability.meter'],
                    ],
                ],
                'http.debug_toolbar' => [
                    'factory' => \Zef\Framework\Middleware\DebugToolbarFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'security.headers' => [
                    'factory' => SecurityHeadersFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'security.rate_limit' => [
                    'factory' => RateLimitFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'security.csrf' => [
                    'factory' => CsrfFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'security.auth' => [
                    'factory' => AuthFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'observability.timing' => [
                    'factory' => TimingFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'observability.tracing' => [
                    'factory' => TracingFactory::class . '::fromConfig',
                    'arguments' => [['service' => FrameworkDefaults::CONFIG_SERVICE]],
                ],
                'observability.correlation' => [
                    'factory' => CorrelationFactory::class . '::fromConfig',
                    'arguments' => [
                        ['service' => FrameworkDefaults::CONFIG_SERVICE],
                        ['service' => 'observability.tracing'],
                    ],
                ],
                'observability.meter' => [
                    'factory' => MetricsFactory::class . '::meter',
                    'arguments' => [],
                ],
                'observability.health' => [
                    'class' => HealthChecker::class,
                ],
                'observability.metrics' => [
                    'factory' => MetricsFactory::class . '::fromConfig',
                    'arguments' => [
                        ['service' => FrameworkDefaults::CONFIG_SERVICE],
                        ['service' => 'observability.meter'],
                        ['service' => 'observability.health'],
                    ],
                ],
            ],
            $kernel->config()->get('framework.services'),
            "the default layer carries the framework's own registrations in its reserved namespace",
        );
        self::assertSame([
            'stack' => [
                ['service' => 'http.errors', 'priority' => 1000],
                ['service' => 'http.debug_toolbar', 'priority' => 900],
            ],
        ], $kernel->config()->get('middleware'));
        self::assertInstanceOf(ErrorMappingMiddleware::class, $kernel->container()->get('http.errors'));
        self::assertInstanceOf(DebugToolbarMiddleware::class, $kernel->container()->get('http.debug_toolbar'));
        self::assertSame([], $kernel->config()->get('routes'));
        self::assertTrue($kernel->container()->has('security.headers'));
        self::assertTrue($kernel->container()->has('observability.timing'));
        self::assertInstanceOf(
            TimingMiddleware::class,
            $kernel->container()->get('observability.timing'),
            'the timing family resolves through the container the stack membership triggers',
        );
        self::assertTrue($kernel->container()->has('observability.tracing'));
        self::assertInstanceOf(
            Tracer::class,
            $kernel->container()->get('observability.tracing'),
            'the tracing family resolves through the container, disarmed by the shipped profile',
        );
        self::assertInstanceOf(
            NoopSpan::class,
            $kernel->container()->get('observability.tracing')->startSpan('boot'),
            'the shipped fail-closed arm answers the void span',
        );
        self::assertTrue($kernel->container()->has('observability.correlation'));
        self::assertInstanceOf(
            CorrelationMiddleware::class,
            $kernel->container()->get('observability.correlation'),
            "the correlation family resolves through the container, wired to the tracing family's own tracer",
        );
        self::assertTrue($kernel->container()->has('observability.meter'));
        self::assertInstanceOf(
            CounterMeter::class,
            $kernel->container()->get('observability.meter'),
            "the shared meter resolves through the container -- the profile's first instance-shape registration",
        );
        self::assertTrue($kernel->container()->has('observability.health'));
        self::assertInstanceOf(
            HealthChecker::class,
            $kernel->container()->get('observability.health'),
            "the health registry resolves through the container, the seam a host's own rebind joins their probes into",
        );
        self::assertTrue($kernel->container()->has('observability.metrics'));
        self::assertInstanceOf(
            MetricsMiddleware::class,
            $kernel->container()->get('observability.metrics'),
            'the metrics family resolves through the container over the two engine services,'
                . ' disarmed by the shipped profile',
        );

        $response = new ApplicationBootstrapper()
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/health')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
        self::assertSame(
            '',
            $response->getHeaderLine('traceparent'),
            'the shipped double fail-closed posture keeps the response header-silent',
        );
    }

    public function testBootsTheWholeApplicationFromConfigurationFiles(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(self::FIXTURE_CONFIGS);

        $announcer = $kernel->container()->get('announcer');
        self::assertInstanceOf(Announcer::class, $announcer);
        self::assertSame('[general] Hello Zef!', $announcer->announce('Zef'));
        self::assertSame('made:fixture', $kernel->container()->get('made.subject'));

        $response = new ApplicationBootstrapper()
            ->pipeline($kernel, new FixedResponseHandler(new Response(200, [], 'answered')))
            ->handle(new ServerRequest('GET', new Uri('https://app.example.com/items')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());

        $trace = $kernel->container()->get('mw.trace');
        self::assertInstanceOf(MiddlewareTrace::class, $trace);
        self::assertSame(
            [
                'outer:enter',
                'inner:enter',
                'inner:exit',
                'outer:exit',
            ],
            $trace->marks(),
            'priority 900 runs outside priority 10, whatever the stack declaration order',
        );

        $runtime = RoutingRuntime::fromConfig($kernel->config());
        $found = $runtime->match('GET', '/users/42');
        self::assertTrue($found->isFound());
        self::assertSame(['id' => '42'], $found->parameters());
        self::assertSame('users.show', $found->route()->name());
        self::assertFalse($runtime->match('GET', '/missing')->isFound());
    }

    public function testTheEnvironmentOverlayWinsOverFileValues(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(
            self::SCAN_CONFIGS,
            new EnvSource('APP_', ['APP_FEATURE__FLAG' => 'from-env', 'APP_EXTRA__LEAF' => 'on']),
        );

        self::assertSame('from-env', $kernel->config()->get('feature.flag'));
        self::assertSame('on', $kernel->config()->get('extra.leaf'));
    }

    public function testOnlyDirectPhpChildrenAreLoaded(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(self::SCAN_CONFIGS);

        self::assertSame('from-file', $kernel->config()->get('feature.flag'));
        self::assertNull($kernel->config()->get('hidden'), 'hidden dotfiles are skipped');
        self::assertNull($kernel->config()->get('upper'), 'the extension check is case-sensitive');
        self::assertNull($kernel->config()->get('notes'), 'non-php entries are skipped');
    }

    public function testADirectoryWithoutPhpChildrenBootsDefaultsOnly(): void
    {
        $kernel = new ApplicationBootstrapper()->boot(__DIR__ . '/Kernel/fixtures/empty');

        self::assertSame(FrameworkDefaults::profile(), $kernel->config()->toArray());
        self::assertTrue($kernel->config()->has('framework.services'));
        self::assertFalse($kernel->container()->has('anything'));
        self::assertTrue($kernel->container()->has('security.headers'));
    }

    public function testAPathThatIsNotADirectoryFailsClosed(): void
    {
        $path = __DIR__ . '/Kernel/fixtures/configs/services.php';

        try {
            new ApplicationBootstrapper()->boot($path);
            self::fail('A configuration path that is not a directory must halt the boot.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The application configuration directory "' . $path . '" is not a directory.',
                $configException->getMessage(),
            );
        }
    }

    public function testAMissingConfigurationDirectoryFailsClosed(): void
    {
        $path = __DIR__ . '/Kernel/fixtures/missing-directory';

        try {
            new ApplicationBootstrapper()->boot($path);
            self::fail('A missing configuration directory must halt the boot.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The application configuration directory "' . $path . '" is not a directory.',
                $configException->getMessage(),
            );
        }
    }

    public function testAMalformedRouteTableHaltsTheBoot(): void
    {
        try {
            new ApplicationBootstrapper()->boot(__DIR__ . '/Kernel/fixtures/invalid-routes');
            self::fail('A malformed route table must halt the boot.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString(
                'Configuration failed schema validation',
                $configException->getMessage(),
            );
        }
    }

    public function testAConfigurationFileThatDoesNotReturnAnArrayHaltsTheBoot(): void
    {
        try {
            new ApplicationBootstrapper()->boot(__DIR__ . '/Kernel/fixtures/broken');
            self::fail('A configuration file without an array return must halt the boot.');
        } catch (ConfigException $configException) {
            self::assertStringContainsString('must return an array', $configException->getMessage());
        }
    }

    public function testAPresentCacheIsLoadedAndNeverRebuiltUntilItIsRemoved(): void
    {
        $root = sys_get_temp_dir() . '/zef-appboot-' . uniqid('', false);
        $this->temporaryRoot = $root;
        $configs = $root . '/configs';
        $cacheDirectory = $root . '/cache';

        mkdir($configs, 0o777, true);
        mkdir($cacheDirectory, 0o777, true);
        foreach (['services.php', 'middleware.php', 'routes.php'] as $fixture) {
            copy(self::FIXTURE_CONFIGS . '/' . $fixture, $configs . '/' . $fixture);
        }

        $cacheFile = $cacheDirectory . '/config.php';
        $applicationBootstrapper = new ApplicationBootstrapper(new ConfigCache($cacheFile));

        $booted = $applicationBootstrapper->boot($configs)->config();
        self::assertTrue($booted->has('services'));
        self::assertFileExists($cacheFile);

        file_put_contents($configs . '/feature.php', '<?php return ["flag" => "on"];');

        $cached = $applicationBootstrapper->boot($configs)->config();
        self::assertFalse(
            $cached->has('feature'),
            'a present cache is loaded, never rebuilt -- KDD-6: rebuilding is explicit',
        );
        self::assertTrue($cached->has('services'));

        unlink($cacheFile);

        $rebuilt = $applicationBootstrapper->boot($configs)->config();
        self::assertSame('on', $rebuilt->get('feature.flag'));
        self::assertTrue($rebuilt->has('services'));
    }

    public function testTheDefaultProfileShipsTheEmptyAutoconfigurationKnob(): void
    {
        self::assertSame(['interfaces' => []], FrameworkDefaults::profile()['tags']);
    }

    /**
     * Removes a temporary directory tree; the cache test's known layout
     * keeps this walk shallow.
     */
    private function remove(string $directory): void
    {
        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->remove($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
