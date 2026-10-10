<?php

declare(strict_types=1);

namespace Zef\Test\Config\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Merge\EnvSource;
use Zef\Framework\Config\Merge\LayeredConfigBuilder;

/**
 * @internal
 */
#[CoversClass(LayeredConfigBuilder::class)]
final class LayeredConfigBuilderTest extends TestCase
{
    private const string PROJECT = __DIR__ . '/../Loader/fixtures/project';

    private const array PROJECT_DATA = [
        'app' => ['name' => 'project'],
        'database' => ['mysql' => ['host' => 'localhost', 'port' => 3306]],
    ];

    public function testBuildsAnEmptyRepositoryWithoutLayers(): void
    {
        self::assertSame([], LayeredConfigBuilder::create()->build()->toArray());
    }

    public function testBuildsTheLayersInTheDefaultAppEnvOrder(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['db' => ['host' => 'default', 'port' => 3306], 'debug' => true])
            ->withApp(['db' => ['host' => 'app'], 'debug' => false])
            ->withEnvironment(new EnvSource('APP_', ['APP_DB__HOST' => 'env']))
            ->build()
        ;

        self::assertSame(
            ['db' => ['host' => 'env', 'port' => 3306], 'debug' => false],
            $config->toArray(),
        );
    }

    public function testOverridesScalarsThroughEveryLayer(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['retries' => 1])
            ->withApp(['retries' => 2])
            ->withEnvironment(new EnvSource('APP_', ['APP_RETRIES' => '3']))
            ->build()
        ;

        self::assertSame('3', $config->get('retries'));
    }

    public function testRecursesMapsAcrossLayers(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['cache' => ['store' => 'file', 'ttl' => 60]])
            ->withApp(['cache' => ['store' => 'redis']])
            ->build()
        ;

        self::assertSame(['store' => 'redis', 'ttl' => 60], $config->get('cache'));
    }

    public function testReplacesListsAcrossLayers(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['middleware' => ['first', 'second']])
            ->withApp(['middleware' => ['third']])
            ->build()
        ;

        self::assertSame(['third'], $config->get('middleware'));
    }

    public function testWithDefaultsReturnsANewBuilderAndLeavesTheOriginalUntouched(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()->withApp(['app' => true]);
        $derived = $layeredConfigBuilder->withDefaults(['default' => true]);

        self::assertNotSame($layeredConfigBuilder, $derived);
        self::assertSame(['app' => true], $layeredConfigBuilder->build()->toArray());
        self::assertSame(['default' => true, 'app' => true], $derived->build()->toArray());
    }

    public function testWithAppReturnsANewBuilderAndLeavesTheOriginalUntouched(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()->withDefaults(['default' => true]);
        $derived = $layeredConfigBuilder->withApp(['app' => true]);

        self::assertNotSame($layeredConfigBuilder, $derived);
        self::assertSame(['default' => true], $layeredConfigBuilder->build()->toArray());
        self::assertSame(['default' => true, 'app' => true], $derived->build()->toArray());
    }

    public function testWithEnvironmentReturnsANewBuilderAndLeavesTheOriginalUntouched(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()->withDefaults(['default' => true]);
        $derived = $layeredConfigBuilder->withEnvironment(new EnvSource('APP_', ['APP_DEFAULT' => 'env']));

        self::assertNotSame($layeredConfigBuilder, $derived);
        self::assertSame(['default' => true], $layeredConfigBuilder->build()->toArray());
        self::assertSame(['default' => 'env'], $derived->build()->toArray());
    }

    public function testWithDefaultFileLoadsThroughTheConfigLoader(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaultFile(__DIR__ . '/../Loader/fixtures/app.php', ['app.php'])
            ->build()
        ;

        self::assertSame(['name' => 'zef', 'debug' => true], $config->toArray());
    }

    public function testWithDefaultDirectoryLoadsThroughTheConfigLoader(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaultDirectory(self::PROJECT, ['app.php', 'database.php'])
            ->build()
        ;

        self::assertSame(self::PROJECT_DATA, $config->toArray());
    }

    public function testWithAppFileOverridesTheDefaults(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['name' => 'base', 'debug' => false, 'extra' => 'kept'])
            ->withAppFile(__DIR__ . '/../Loader/fixtures/app.php', ['app.php'])
            ->build()
        ;

        self::assertSame(['name' => 'zef', 'debug' => true, 'extra' => 'kept'], $config->toArray());
    }

    public function testWithAppFileNestsTheLoadedLayerUnderAPrefix(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaults(['name' => 'base'])
            ->withAppFile(__DIR__ . '/../Loader/fixtures/app.php', ['app.php'], 'runtime')
            ->build()
        ;

        self::assertSame(
            ['name' => 'base', 'runtime' => ['name' => 'zef', 'debug' => true]],
            $config->toArray(),
        );
    }

    public function testWithAppDirectoryNestsTheLoadedLayerUnderAPrefix(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaultDirectory(self::PROJECT, ['app.php', 'database.php'])
            ->withAppDirectory(self::PROJECT, ['app.php', 'database.php'], 'override')
            ->build()
        ;

        self::assertSame(
            self::PROJECT_DATA + ['override' => self::PROJECT_DATA],
            $config->toArray(),
        );
    }

    public function testWithADirectoryPropagatesLoaderFailures(): void
    {
        try {
            LayeredConfigBuilder::create()
                ->withAppDirectory(self::PROJECT, ['app.php'])
                ->build()
            ;
            self::fail('A directory child outside the allowlist must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                \sprintf(
                    'The configuration file "%s" is not allowed.',
                    self::PROJECT . '/database.php',
                ),
                $configException->getMessage(),
            );
        }
    }

    public function testBuildReturnsADistinctRepositoryOnEveryCall(): void
    {
        $layeredConfigBuilder = LayeredConfigBuilder::create()->withDefaults(['name' => 'zef']);
        $config = $layeredConfigBuilder->build();

        self::assertNotSame($config, $layeredConfigBuilder->build());
        self::assertSame($config->toArray(), $layeredConfigBuilder->build()->toArray());
    }

    public function testTheEnvironmentLayerOverridesFileLoadedDefaults(): void
    {
        $config = LayeredConfigBuilder::create()
            ->withDefaultDirectory(self::PROJECT, ['app.php', 'database.php'])
            ->withEnvironment(
                new EnvSource('APP_', ['APP_DATABASE__MYSQL__HOST' => 'db.prod.example.com']),
            )
            ->build()
        ;

        self::assertSame(
            [
                'app' => ['name' => 'project'],
                'database' => ['mysql' => ['host' => 'db.prod.example.com', 'port' => 3306]],
            ],
            $config->toArray(),
        );
    }
}
