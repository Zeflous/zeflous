<?php

declare(strict_types=1);

namespace Zef\Test\Config;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Config\ConfigBootstrapper;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\Config\Merge\LayeredConfigBuilder;

/**
 * @internal
 */
#[CoversClass(ConfigBootstrapper::class)]
final class ConfigBootstrapperTest extends TestCase
{
    private string $directory;

    /** @var non-empty-string */
    private string $cacheFile;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-bootstrap-' . uniqid('', false);
        mkdir($this->directory);
        $this->cacheFile = $this->directory . '/config.cache.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }

        rmdir($this->directory);
    }

    public function testBootsTheMergedRepositoryFromTheBuilder(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()
            ->withDefaultFile(__DIR__ . '/Loader/fixtures/app.php', ['app.php'])
            ->withApp(['debug' => false])
            ->withEnvironment(new EnvSource('APP_', ['APP_NAME' => 'staging']))
        ;

        $config = new ConfigBootstrapper()->boot($layeredConfigBuilder);

        self::assertSame('staging', $config->get('name'));
        self::assertFalse($config->get('debug'));
    }

    public function testBootsARoutelessRepository(): void
    {
        $config = new ConfigBootstrapper()->boot(
            LayeredConfigBuilder::create()->withDefaults(['debug' => true]),
        );

        self::assertTrue($config->get('debug'));
    }

    public function testBootsARepositoryWithValidRouteEntries(): void
    {
        $config = new ConfigBootstrapper()->boot(
            LayeredConfigBuilder::create()->withDefaults([
                'routes' => [
                    ['path' => '/health', 'method' => ['GET'], 'handler' => 'HealthController@check'],
                    [
                        'path' => '/users/{id}',
                        'method' => ['GET'],
                        'handler' => 'UserController@show',
                        'constraints' => ['id' => '[0-9]+'],
                    ],
                ],
            ]),
        );

        // The declared type is deliberately wider than the inferred literal:
        // the shape assertion is the runtime proof, which the analyser
        // cannot narrow away for us.
        $routes = $config->get('routes');
        self::assertIsArray($routes);
        self::assertCount(2, $routes);
    }

    public function testRejectsARepositoryWhoseRouteEntryViolatesTheSchema(): void
    {
        try {
            new ConfigBootstrapper()->boot(
                LayeredConfigBuilder::create()->withDefaults([
                    'routes' => [
                        ['path' => '/health', 'method' => ['GET']],
                    ],
                ]),
            );
            self::fail('A route entry without a handler must halt the bootstrap.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: handler: expected a non-empty string, got missing.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsARoutesKeyThatIsNotAnArrayOfEntries(): void
    {
        try {
            new ConfigBootstrapper()->boot(
                LayeredConfigBuilder::create()->withDefaults(['routes' => '/health']),
            );
            self::fail('A scalar "routes" configuration must halt the bootstrap.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }
    }

    public function testRejectsAnExplicitlyNullRoutesKey(): void
    {
        try {
            new ConfigBootstrapper()->boot(
                LayeredConfigBuilder::create()->withDefaults(['routes' => null]),
            );
            self::fail('An explicitly null "routes" configuration must halt the bootstrap.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }
    }

    public function testABootedRepositoryIsFrozenAndReusable(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()->withDefaults(['answer' => 42]);
        $configBootstrapper = new ConfigBootstrapper();

        self::assertNotSame(
            $configBootstrapper->boot($layeredConfigBuilder),
            $configBootstrapper->boot($layeredConfigBuilder),
        );
        self::assertSame(42, $configBootstrapper->boot($layeredConfigBuilder)->get('answer'));
    }

    public function testACompiledCacheShortCircuitsTheBuilderEntirely(): void
    {
        // The builder's layers disagree with the cache on every key, so a
        // boot that returns the cached values proves the builder -- its
        // loaders and its merge -- was never consulted: had build() run,
        // the defaults below would have won.
        $configCache = new ConfigCache($this->cacheFile);
        $configCache->dump(['name' => 'from-cache', 'debug' => false]);

        $layeredConfigBuilder = LayeredConfigBuilder::create()
            ->withDefaults(['name' => 'from-builder', 'debug' => true])
        ;

        $config = new ConfigBootstrapper($configCache)->boot($layeredConfigBuilder);

        self::assertSame('from-cache', $config->get('name'));
        self::assertFalse($config->get('debug'));
    }

    public function testAColdBootCompilesTheCacheForTheNextBoot(): void
    {
        $configCache = new ConfigCache($this->cacheFile);
        $configBootstrapper = new ConfigBootstrapper($configCache);

        $config = $configBootstrapper->boot(LayeredConfigBuilder::create()->withDefaults(['name' => 'compiled']));

        self::assertSame('compiled', $config->get('name'));
        self::assertTrue($configCache->exists());

        // The warm boot reads the artifact: the cached value wins over the
        // rebuilt layers, proving the sources were never revisited.
        $warm = $configBootstrapper->boot(LayeredConfigBuilder::create()->withDefaults(['name' => 'rebuilt']));

        self::assertSame('compiled', $warm->get('name'));
    }

    public function testAnInvalidConfigurationIsNeverCompiledIntoTheCache(): void
    {
        $configCache = new ConfigCache($this->cacheFile);

        try {
            new ConfigBootstrapper($configCache)->boot(
                LayeredConfigBuilder::create()->withDefaults(['routes' => 'not-a-list']),
            );
            self::fail('An invalid configuration must halt the bootstrap.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "routes" configuration must be a list of route entries.',
                $configException->getMessage(),
            );
        }

        self::assertFalse($configCache->exists());
    }

    public function testACorruptCacheHaltsTheBootstrap(): void
    {
        $configCache = new ConfigCache(__DIR__ . '/Cache/fixtures/returns-int.php');

        try {
            new ConfigBootstrapper($configCache)->boot(LayeredConfigBuilder::create());
            self::fail('A corrupt configuration cache must halt the bootstrap.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The configuration cache "' . __DIR__ . '/Cache/fixtures/returns-int.php" '
                    . 'is corrupted: it does not return an array.',
                $configException->getMessage(),
            );
        }
    }
}
