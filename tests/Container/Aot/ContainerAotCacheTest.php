<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Aot\AotArguments;
use Zef\Framework\Container\Aot\AotArtifact;
use Zef\Framework\Container\Aot\AotArtifactCalls;
use Zef\Framework\Container\Aot\AotArtifactEntries;
use Zef\Framework\Container\Aot\AotArtifactEntry;
use Zef\Framework\Container\Aot\AotArtifactEnvelope;
use Zef\Framework\Container\Aot\AotArtifactIds;
use Zef\Framework\Container\Aot\AotArtifactLazies;
use Zef\Framework\Container\Aot\AotBuildEntry;
use Zef\Framework\Container\Aot\AotClosureEntry;
use Zef\Framework\Container\Aot\AotClosures;
use Zef\Framework\Container\Aot\AotDefinitions;
use Zef\Framework\Container\Aot\AotEntries;
use Zef\Framework\Container\Aot\AotException;
use Zef\Framework\Container\Aot\AotLifetimes;
use Zef\Framework\Container\Aot\AotLiterals;
use Zef\Framework\Container\Aot\AotMembers;
use Zef\Framework\Container\Aot\AotRefusals;
use Zef\Framework\Container\Aot\AotUnrenderableException;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompilePlan;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\NamespaceRadixTree;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Aot\CallsHouse;
use Zef\Test\Support\Container\Aot\EnumHouse;
use Zef\Test\Support\Container\Aot\FactoryProduct;
use Zef\Test\Support\Container\Aot\FactorySource;
use Zef\Test\Support\Container\Aot\House;
use Zef\Test\Support\Container\Aot\LooseDefinition;
use Zef\Test\Support\Container\Aot\PlainSink;
use Zef\Test\Support\Container\Aot\Roof;
use Zef\Test\Support\Container\Aot\StampingFactory;
use Zef\Test\Support\Container\Aot\StampingProduct;
use Zef\Test\Support\Container\Aot\Tone;
use Zef\Test\Support\Container\Aot\UnrenderableHouse;
use Zef\Test\Support\Container\Aot\Window;

/**
 * The artifact envelope: dump, load, validate, install -- the boundary's
 * round trip and every corruption shape it refuses.
 *
 * @internal
 */
#[CoversClass(ContainerAotCache::class)]
#[CoversClass(AotArtifact::class)]
#[CoversClass(AotDefinitions::class)]
#[CoversClass(AotEntries::class)]
#[CoversClass(AotClosures::class)]
#[CoversClass(AotArguments::class)]
#[CoversClass(AotLiterals::class)]
#[CoversClass(AotRefusals::class)]
#[CoversClass(AotArtifactCalls::class)]
#[CoversClass(AotArtifactEntries::class)]
#[CoversClass(AotArtifactEntry::class)]
#[CoversClass(AotArtifactEnvelope::class)]
#[CoversClass(AotArtifactLazies::class)]
#[CoversClass(AotArtifactIds::class)]
#[CoversClass(AotBuildEntry::class)]
#[CoversClass(AotClosureEntry::class)]
#[CoversClass(AotLifetimes::class)]
#[CoversClass(AotMembers::class)]
#[CoversClass(AotException::class)]
final class ContainerAotCacheTest extends TestCase
{
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/zef-aot-' . uniqid('', false) . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        unlink($this->file);
    }

    public function testTheRoundTripCarriesTheWholeEnvelope(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump(
            'digest-of-the-configuration',
            AotEntries::all($this->plannedSet()),
            ['frame.house' => ['app.house']],
            [],
            ['app.answer'],
        );

        $artifact = $containerAotCache->load();

        self::assertTrue($containerAotCache->exists());
        self::assertSame('digest-of-the-configuration', $artifact->digest());
        self::assertSame(['app.answer'], $artifact->unreferenced());
        self::assertSame(['frame.house' => ['app.house']], $artifact->tags());
        self::assertSame([], $artifact->lazy());
        self::assertCount(7, $artifact->entries());
    }

    public function testAMultiTagIndexSurvivesTheRoundTripWhole(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump(
            'digest-of-the-configuration',
            AotEntries::all(self::plannedSet()),
            ['frame.house' => ['app.house'], 'warm.batch' => ['app.window', 'app.calls']],
            [],
            ['app.answer'],
        );

        $artifact = $containerAotCache->load();

        self::assertSame(
            ['frame.house' => ['app.house'], 'warm.batch' => ['app.window', 'app.calls']],
            $artifact->tags(),
        );
    }

    public function testTheStagingNameLivesBesideItsTargetWithTheMarkerAndEntropy(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $first = $containerAotCache->temporaryPath();
        $second = $containerAotCache->temporaryPath();

        self::assertMatchesRegularExpression(
            '#^' . preg_quote($this->file, '#') . '\.tmp\.[0-9a-f]{12}$#',
            $first,
        );
        self::assertNotSame($first, $second);
        self::assertStringStartsWith(\dirname($this->file) . \DIRECTORY_SEPARATOR, $first);
    }

    public function testTheDumpedArtifactFileCarriesTheEnvelopeVerbatim(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump(
            'd',
            AotEntries::all([
                new InstanceDefinition('app.window', Window::class),
                new FactoryDefinition('app.built', FactorySource::class . '::make', [['service' => 'app.window'],
                    'steel']),
            ]),
            ['frame.house' => ['app.window']],
            [],
            [],
        );

        $expectedLines = [
            '<?php',
            '',
            '// Generated by the Zef container AOT cache; never edit it by hand.',
            '',
            'return [',
            "    'digest' => 'd',",
            "    'unreferenced' => array (",
            '),',
            "    'tags' => array (",
            "  'frame.house' => ",
            '  array (',
            "    0 => 'app.window',",
            '  ),',
            '),',
            "    'lazy' => array (",
            '),',
            "    'entries' => [",
            "        'app.window' => [",
            "            'kind' => 'closure',",
            '            \'closure\' => static fn (Zef\Framework\Container\Container $c): '
                . 'object => new Zef\Test\Support\Container\Aot\Window(),',
            "            'lifetime' => 'singleton',",
            '        ],',
            "        'app.built' => array (",
            "  'kind' => 'build',",
            '  \'factory\' => \'Zef\\\Test\\\Support\\\Container\\\Aot\\\FactorySource::make\',',
            "  'arguments' => ",
            '  array (',
            '    0 => ',
            '    array (',
            "      'service' => 'app.window',",
            '    ),',
            "    1 => 'steel',",
            '  ),',
            "  'calls' => ",
            '  array (',
            '  ),',
            "  'lifetime' => 'singleton',",
            '),',
            '    ],',
            '];',
            '',
        ];

        self::assertSame(
            implode("\n", $expectedLines),
            file_get_contents($this->file),
        );
    }

    public function testAFactoryCarryingCallsSurvivesTheRoundTrip(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('d', AotEntries::all([
            new InstanceDefinition('app.window', Window::class),
            new FactoryDefinition(
                'app.stamped',
                StampingFactory::class . '::make',
                [['service' => 'app.window']],
                calls: [new MethodCall('stamp', ['serial-42'])],
            ),
        ]), [], [], []);

        $container = new Container();

        AotDefinitions::install($container, $containerAotCache->load());

        $stamped = $container->get('app.stamped');
        self::assertInstanceOf(StampingProduct::class, $stamped);
        self::assertSame('serial-42', $stamped->serial);
    }

    public function testAFactoryCarryingTwoCallsAppliesThemBothAfterTheRoundTrip(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('d', AotEntries::all([
            new InstanceDefinition('app.window', Window::class),
            new FactoryDefinition(
                'app.stamped',
                StampingFactory::class . '::make',
                [['service' => 'app.window']],
                calls: [
                    new MethodCall('stamp', ['serial-42']),
                    new MethodCall('stamp', ['serial-99']),
                ],
            ),
        ]), [], [], []);

        $container = new Container();

        AotDefinitions::install($container, $containerAotCache->load());

        $stamped = $container->get('app.stamped');
        self::assertInstanceOf(StampingProduct::class, $stamped);
        self::assertSame('serial-99', $stamped->serial);
    }

    public function testAFactoryCarryingAnObjectArgumentRefusesTheDump(): void
    {
        try {
            AotEntries::all([
                new FactoryDefinition('app.built', FactorySource::class . '::make', [new stdClass()]),
            ]);
            self::fail('Expected an AotUnrenderableException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 'app.built' carries a value the AOT artifact cannot export at 'arguments.0' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testAnUnrenderablePlanDegradesInsteadOfCrashingTheDump(): void
    {
        // The compile lane's degrade decision: a plan the boundary
        // cannot render answers false and leaves no artifact behind --
        // never the crashed boot the raw dump refusal was.
        $containerAotCache = new ContainerAotCache($this->file);
        $containerCompilePlan = new ContainerCompilePlan(
            [new InstanceDefinition('app.unrenderable', UnrenderableHouse::class, [new PlainSink()])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );

        self::assertFalse($containerAotCache->dumpPlanOrDegrade($containerCompilePlan, 'digest'));
        self::assertFileDoesNotExist($this->file);
    }

    public function testARenderablePlanStillDumpsThroughTheDegradeSeam(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);
        $containerCompilePlan = new ContainerCompilePlan(
            [new InstanceDefinition('app.tone', EnumHouse::class, [Tone::CALM])],
            ServiceTagIndex::fromDefinitions([]),
            [],
            new NamespaceRadixTree(['ids' => [], 'children' => []], [], 0, 0, 0),
        );

        self::assertTrue($containerAotCache->dumpPlanOrDegrade($containerCompilePlan, 'digest'));
        self::assertFileExists($this->file);
        self::assertStringContainsString('Tone::CALM', (string) file_get_contents($this->file));
    }

    public function testAFactoryCarryingAnObjectInsideACallsArgumentsRefusesTheDump(): void
    {
        try {
            AotEntries::all([
                new FactoryDefinition(
                    'app.built',
                    FactorySource::class . '::make',
                    [],
                    calls: [new MethodCall('stamp', [new stdClass()])],
                ),
            ]);
            self::fail('Expected an AotException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 'app.built' carries a value the AOT artifact cannot export at 'calls.stamp.0' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testAnUnplannedDefinitionRefusesTheDump(): void
    {
        try {
            AotEntries::all([new LooseDefinition('app.loose')]);
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                "The service 'app.loose' reaches the AOT dump unplanned -- "
                . 'run the autowire compiler pass before the dump.',
                $aotException->getMessage(),
            );
        }
    }

    public function testTheLoadedClosureEntriesAreLiveClosures(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('d', AotEntries::all($this->plannedSet()), [], [], []);

        $artifact = $containerAotCache->load();

        $entries = $artifact->entries();

        $house = $entries['app.house'] ?? null;
        self::assertInstanceOf(AotClosureEntry::class, $house);

        $window = $entries['app.window'] ?? null;
        self::assertInstanceOf(AotClosureEntry::class, $window);
    }

    public function testTheLoadedEntriesInstallAndAnswer(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('d', AotEntries::all($this->plannedSet()), ['frame.house' => ['app.house']], [], []);

        $container = new Container();

        $installed = AotDefinitions::install($container, $containerAotCache->load());

        self::assertSame(7, $installed);

        $house = $container->get('app.house');
        self::assertInstanceOf(House::class, $house);
        self::assertSame('double', $house->window->glazing);
        self::assertSame(4, $house->panes);

        $calls = $container->get('app.calls');
        self::assertInstanceOf(CallsHouse::class, $calls);
        self::assertNotNull($calls->roof);
        self::assertSame('slate', $calls->roof->material);

        self::assertSame($house, $container->get('app.alias'));
        self::assertSame(['plain' => 'data'], $container->get('app.answer'));

        $built = $container->get('app.built');
        self::assertInstanceOf(FactoryProduct::class, $built);
        self::assertSame('steel', $built->frame);
        self::assertSame('double', $built->window->glazing);
    }

    public function testTheTransientLifetimeSurvivesTheBoundary(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('d', AotEntries::all($this->plannedSet()), [], [], []);

        $container = new Container();

        AotDefinitions::install($container, $containerAotCache->load());

        self::assertNotSame($container->get('app.roof'), $container->get('app.roof'));
        self::assertSame($container->get('app.house'), $container->get('app.house'));
    }

    public function testADumpOverwritesWhateverTheFileCarried(): void
    {
        $containerAotCache = new ContainerAotCache($this->file);

        $containerAotCache->dump('first', AotEntries::all($this->plannedSet()), [], [], []);
        $containerAotCache->dump('second', AotEntries::all($this->plannedSet()), [], [], []);

        self::assertSame('second', $containerAotCache->load()->digest());
    }

    public function testADumpIntoAMissingDirectoryRefuses(): void
    {
        $containerAotCache = new ContainerAotCache(
            sys_get_temp_dir() . '/zef-aot-missing-' . uniqid('', false) . '/artifact.php',
        );

        try {
            $containerAotCache->dump('d', [], [], [], []);
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                'The container AOT cache directory "'
                . \dirname($containerAotCache->file()) . '" does not exist.',
                $aotException->getMessage(),
            );
        }
    }

    public function testALoadOfAMissingArtifactRefuses(): void
    {
        try {
            new ContainerAotCache($this->file)->load();
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertSame(
                "The container AOT artifact '{$this->file}' does not exist.",
                $aotException->getMessage(),
            );
        }
    }

    public function testATornWriteIsRefusedAsCorrupt(): void
    {
        file_put_contents($this->file, "<?php\nreturn [\n    'digest' => ");

        try {
            new ContainerAotCache($this->file)->load();
            self::fail('Expected an AotException to be thrown.');
        } catch (AotException $aotException) {
            self::assertStringStartsWith(
                "The container AOT artifact '{$this->file}' is not valid PHP (",
                $aotException->getMessage(),
            );
            self::assertStringEndsWith(
                ') -- rebuild the artifact.',
                $aotException->getMessage(),
            );
        }
    }

    #[DataProvider('provideEveryMalformedPayloadRefusesWithTheFileNamedCases')]
    public function testEveryMalformedPayloadRefusesWithTheFileNamed(string $payload, string $message): void
    {
        file_put_contents($this->file, $payload);

        $containerAotCache = new ContainerAotCache($this->file);

        try {
            $containerAotCache->load();
        } catch (AotException $aotException) {
            self::assertSame(str_replace('the-artifact', $this->file, $message), $aotException->getMessage());

            return;
        }

        self::fail('The malformed payload did not refuse.');
    }

    /**
     * @return list<array{string, string}>
     */
    public static function provideEveryMalformedPayloadRefusesWithTheFileNamedCases(): iterable
    {
        $file = 'the-artifact';

        return array_values([
            'non-map payload' => [
                "<?php return 'nope';",
                "The container AOT artifact '{$file}' must return a map, non-map returned.",
            ],
            'missing envelope key' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => []];",
                "The container AOT artifact '{$file}' is malformed: the 'entries' key is missing.",
            ],
            'missing lazy key' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the 'lazy' key is missing.",
            ],
            'unknown envelope key' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [], 'entries' => [],"
                . " 'extra' => 1];",
                "The container AOT artifact '{$file}' is malformed: unknown envelope key extra.",
            ],
            'digest not a string' => [
                "<?php return ['digest' => 1, 'unreferenced' => [], 'tags' => [], 'lazy' => [], 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the digest must be a non-empty string.",
            ],
            'unreferenced not a list' => [
                "<?php return ['digest' => 'd', 'unreferenced' => ['a' => 'b'], 'tags' => [], 'lazy' => [],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the unreferenced report must be a list of "
                . 'identifiers.',
            ],
            'unreferenced not strings' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [1], 'tags' => [], 'lazy' => [], 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the unreferenced report must be a list of "
                . 'identifiers.',
            ],
            'tags not a map' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => 'nope', 'lazy' => [],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the tag index must be a map.",
            ],
            'tag key not a string' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [7 => ['x']], 'lazy' => [],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the tag index keys must be non-empty tag strings.",
            ],
            'tag ids not a list' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => ['t' => 'id'], 'lazy' => [],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the tag index for 't' must be a list of "
                . 'identifiers.',
            ],
            'lazy not a map' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => 'nope',"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the lazy map must be a map.",
            ],
            'lazy key not a string' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [7 => 'a'],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the lazy map keys must be non-empty service"
                . ' identifiers.',
            ],
            'lazy class unknown' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [],"
                . " 'lazy' => ['a' => 'NoSuchClassAnywhere'], 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the lazy map for 'a' must name an existing"
                . ' class.',
            ],
            'lazy class not a string' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => ['a' => 7],"
                . " 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the lazy map for 'a' must name an existing"
                . ' class.',
            ],
            'lazy class the empty string' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [],"
                . " 'lazy' => ['a' => ''], 'entries' => []];",
                "The container AOT artifact '{$file}' is malformed: the lazy map for 'a' must name an existing"
                . ' class.',
            ],
            'entries not a map' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => 'nope'];",
                "The container AOT artifact '{$file}' is malformed: the entries must be a map.",
            ],
            'entry not a map' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => 'nope']];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'entry missing kind' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => []]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'unknown kind' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'weird', 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' carries the unknown entry kind 'weird' (known kinds: closure,"
                . ' build).',
            ],
            'unknown lifetime' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'closure', 'closure' => function () {},"
                . " 'lifetime' => 'eternal']]];",
                "The artifact entry for service 'a' carries the unknown lifetime 'eternal'.",
            ],
            'closure entry not a closure' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'closure', 'closure' => 'nope', 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'closure entry extra keys' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'closure', 'closure' => function () {},"
                . " 'lifetime' => 'singleton', 'factory' => 'x']]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'build entry missing keys' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'build', 'factory' => 'T::m', 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'build factory empty' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'build', 'factory' => '', 'arguments' => [], 'calls' => [],"
                . " 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' member 'factory' must be a non-empty string, string given.",
            ],
            'build calls malformed' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'build', 'factory' => 'T::m', 'arguments' => [],"
                . " 'calls' => ['nope'], 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'entry key not a string' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => [5 => ['kind' => 'closure', 'closure' => function () {},"
                . " 'lifetime' => 'singleton']]];",
                "The artifact entry for service '5' is malformed.",
            ],
            'entry key empty' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['' => ['kind' => 'closure', 'closure' => function () {},"
                . " 'lifetime' => 'singleton']]];",
                "The artifact entry for service '' is malformed.",
            ],
            'entry an offset-accessible object' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => new \\ArrayObject(['kind' => 'build'])]];",
                "The artifact entry for service 'a' is malformed.",
            ],
            'build arguments not a list' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'build', 'factory' => 'T::m',"
                . " 'arguments' => ['style' => 'x'], 'calls' => [], 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' member 'arguments' must be a list, array given.",
            ],
            'call arguments not a list' => [
                "<?php return ['digest' => 'd', 'unreferenced' => [], 'tags' => [], 'lazy' => [],"
                . " 'entries' => ['a' => ['kind' => 'build', 'factory' => 'T::m', 'arguments' => [],"
                . " 'calls' => [['method' => 'stamp', 'arguments' => ['serial' => 'x']]],"
                . " 'lifetime' => 'singleton']]];",
                "The artifact entry for service 'a' member 'arguments' must be a list, array given.",
            ],
        ]);
    }

    /**
     * @return list<ServiceDefinition>
     */
    private function plannedSet(): array
    {
        return [
            new InstanceDefinition('app.window', Window::class),
            new InstanceDefinition(
                'app.house',
                House::class,
                [
                    ['service' => 'app.window'],
                    4,
                    ['style' => 'georgian'],
                ],
                ServiceLifetime::SINGLETON,
                new ServiceTags(['frame.house']),
            ),
            new InstanceDefinition(
                'app.calls',
                CallsHouse::class,
                [['service' => 'app.window']],
                ServiceLifetime::SINGLETON,
                new ServiceTags(),
                [new MethodCall('addRoof', [['service' => 'app.roof']])],
            ),
            new InstanceDefinition('app.roof', Roof::class, [], ServiceLifetime::TRANSIENT),
            new AliasDefinition('app.alias', 'app.house'),
            new ValueDefinition('app.answer', ['plain' => 'data']),
            new FactoryDefinition(
                'app.built',
                FactorySource::class . '::make',
                [['service' => 'app.window'], 'steel'],
            ),
        ];
    }
}
