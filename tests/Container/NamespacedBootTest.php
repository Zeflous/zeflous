<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotLane;
use Zef\Framework\Container\Aot\AotOutcome;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Container\AotBootLane;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerCompileLane;
use Zef\Framework\Container\ContainerCompilePipeline;
use Zef\Framework\Container\ContainerRegistrations;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\NamespacedServices;
use Zef\Framework\Container\NamespaceScopes;
use Zef\Framework\Container\NamespacesMap;
use Zef\Framework\Container\NamespaceTree;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Namespaces\FallbackFactories;
use Zef\Test\Support\Container\Namespaces\NamespacedService;
use Zef\Test\Support\Container\Namespaces\NamespacedWrapper;

/**
 * The namespace lane's boot behaviour, end to end: the knob parsed with
 * the rest, the locator registered ahead of the definitions, the scope
 * policy refusing the violating boot, the AOT artifact round-trip
 * (the tree rebuilt from the baked identifiers under the digest-proven
 * knob), and the fallback lane answering through the booted container
 * (ROADMAP area 9's namespace half -- the boot half).
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(NamespaceTree::class)]
#[CoversClass(NamespacedServices::class)]
#[CoversClass(ContainerCompileLane::class)]
#[CoversClass(ContainerCompilePipeline::class)]
#[CoversClass(ContainerRegistrations::class)]
#[CoversClass(AotBootLane::class)]
#[CoversClass(NamespaceScopes::class)]
final class NamespacedBootTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-namespaced-boot-' . uniqid('', false) . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    public function testTheMachineryExemptionSkipsOnlyTheMachineryAtTheBuild(): void
    {
        // A machinery identifier FIRST, a declared identifier after it:
        // the build pass skips the one '@'-prefixed entry and keeps
        // walking -- the public set keeps everything the boot declared.
        $namespacesMap = NamespacesMap::parse('namespaces', null);
        $tree = NamespaceTree::sealed(['@decor:app.target:base', 'app.public'], $namespacesMap);

        self::assertSame(['app.public'], $tree->idsUnderPrefix('app'));
    }

    public function testTheBootRegistersTheLocatorAndItAnswersTheBootedSet(): void
    {
        $container = $this->booted($this->config());

        $namespaces = $this->namespaces($container);

        self::assertSame(
            ['app.house', 'app.module.hidden', 'app.short', 'app.window'],
            $namespaces->idsUnder('app'),
        );
        self::assertSame([], $namespaces->idsUnder('framework'));
        self::assertSame([], $namespaces->idsUnder('@decor'));

        $services = $namespaces->servicesUnder('app.module');
        self::assertCount(1, $services);
        $hidden = $services['app.module.hidden'];
        self::assertInstanceOf(NamespacedService::class, $hidden);
        self::assertSame('hidden', $hidden->label);
        self::assertSame(
            $container->get('app.window'),
            $container->get('app.short'),
            'the alias resolves its target',
        );

        self::assertSame('internal', $namespaces->scopeOf('app.module.hidden')?->kind);
        self::assertNull($namespaces->scopeOf('flat'));

        self::assertSame(6, $namespaces->stats()['serviceIds']);
        self::assertTrue($namespaces->stats()['sealed']);
    }

    public function testTheDecoratedIdentifierAnswersInItsOwnSpelling(): void
    {
        $container = $this->booted($this->config());
        $namespaces = $this->namespaces($container);

        self::assertSame(['wrapped.target'], $namespaces->idsUnder('wrapped'));
        $answer = $container->get('wrapped.target');
        self::assertInstanceOf(NamespacedWrapper::class, $answer);
        self::assertSame('wrapped', $answer->label);
        self::assertInstanceOf(NamespacedService::class, $answer->inner);
    }

    public function testAnInternalViolationRefusesTheBoot(): void
    {
        $config = $this->config();
        $config['services']['app.outside'] = [
            'class' => NamespacedService::class,
            'arguments' => [['service' => 'app.module.hidden']],
        ];

        try {
            $this->booted($config);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: service 'app.outside' references internal service "
                . "'app.module.hidden' from outside the guarded namespace 'app.module.'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheModuleBudgetGovernsTheOutboundReach(): void
    {
        $config = $this->config();
        $config['services']['app.reacher'] = [
            'class' => NamespacedWrapper::class,
            'arguments' => ['reacher', ['service' => 'app.module.hidden']],
        ];

        // The module spelling with budget zero: the subtree is closed
        // to the outside.
        $config['namespaces']['scopes']['app.module'] = 'module';

        try {
            $this->booted($config);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Namespace scope violation: service 'app.reacher' references module-scoped service "
                . "'app.module.hidden' while max_cross_scope_refs is 0.",
                $definitionException->getMessage(),
            );
        }

        // Budget one: the single outbound reference is spent, honestly.
        $config['namespaces']['max_cross_scope_refs'] = 1;
        $container = $this->booted($config);

        $reacher = $container->get('app.reacher');
        self::assertInstanceOf(NamespacedWrapper::class, $reacher);
        self::assertSame('reacher', $reacher->label);
        self::assertInstanceOf(NamespacedService::class, $reacher->inner);
        self::assertSame('hidden', $reacher->inner->label);
    }

    public function testTheArtifactLaneRebuildsTheTreeAndAnswersTheSameReadSide(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        // The first boot compiles and dumps; the second loads the
        // artifact and rebuilds the namespace tree from its baked
        // identifiers under the digest-proven knob.
        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $second = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactLoaded, $outcome->lane);

        $namespaces = $this->namespaces($second);

        self::assertSame(
            ['app.house', 'app.module.hidden', 'app.short', 'app.window'],
            $namespaces->idsUnder('app'),
        );
        self::assertSame('internal', $namespaces->scopeOf('app.module.hidden')?->kind);
        self::assertSame(6, $namespaces->stats()['serviceIds']);
    }

    public function testAChangedScopePolicyIsAProvenStaleArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        // The same declaration set under a different scope policy: the
        // digest differs, the artifact is never run, the full lane
        // recompiles under the new governance and re-dumps.
        $changed = $this->aotConfig();
        $changed['namespaces']['scopes']['flat'] = 'internal';

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());

        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertNotSame(AotLane::ArtifactLoaded, $outcome->lane);

        $namespaces = $this->namespaces($second);
        self::assertSame('internal', $namespaces->scopeOf('flat')?->kind);
    }

    public function testTheFallbackLaneAnswersThroughTheBootedContainer(): void
    {
        $container = $this->booted($this->config());
        $container->namespaceFallbacks()->fallback('App\Controllers\\', FallbackFactories::forIdentifier(...));

        self::assertTrue($container->has('App\Controllers\HomeController'));
        $controller = $container->get('App\Controllers\HomeController');
        self::assertInstanceOf(NamespacedService::class, $controller);
        self::assertSame('App\Controllers\HomeController', $controller->label);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function booted(array $config): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config($config), new Container());
    }

    /**
     * The booted container's own namespace locator, typed for the read
     * side the assertions walk.
     */
    private function namespaces(Container $container): NamespacedServices
    {
        $namespaces = $container->get(FrameworkDefaults::NAMESPACES_SERVICE);
        self::assertInstanceOf(NamespacedServices::class, $namespaces);

        return $namespaces;
    }

    /**
     * The governed configuration every boot shares: a window, a house
     * that references it, an alias, a module-scoped service, a flat
     * one, and a decorated target -- the shapes the read side answers
     * over.
     *
     * @return array{
     *     services: array<string, mixed>,
     *     decorators: array<string, mixed>,
     *     namespaces: array{scopes: array<string, string>, max_cross_scope_refs: int},
     * }
     */
    private function config(): array
    {
        return [
            'services' => [
                'app.window' => ['class' => NamespacedService::class, 'arguments' => ['win']],
                'app.house' => ['class' => NamespacedService::class, 'arguments' => [['service' => 'app.window']]],
                'app.short' => ['alias' => 'app.window'],
                'app.module.hidden' => ['class' => NamespacedService::class, 'arguments' => ['hidden']],
                'flat' => ['class' => NamespacedService::class, 'arguments' => ['flat']],
                'wrapped.target' => ['class' => NamespacedService::class, 'arguments' => ['inner']],
            ],
            'decorators' => [
                'wrapped.target' => [
                    ['class' => NamespacedWrapper::class, 'arguments' => ['wrapped', ['service' => '@inner']]],
                ],
            ],
            'namespaces' => [
                'scopes' => [
                    'app.module' => 'internal',
                ],
                'max_cross_scope_refs' => 0,
            ],
        ];
    }

    /**
     * The AOT configuration: the same governed set plus the enabled
     * knob -- the artifact's digest folds the namespaces knob itself.
     *
     * @return array{
     *     aot: array{enabled: bool, warm: bool},
     *     container: array{mode: 'production'},
     *     services: array<string, mixed>,
     *     decorators: array<string, mixed>,
     *     namespaces: array{scopes: array<string, string>, max_cross_scope_refs: int},
     * }
     */
    private function aotConfig(): array
    {
        return [
            'aot' => ['enabled' => true, 'warm' => false],
            'container' => ['mode' => 'production'],
            ...$this->config(),
        ];
    }
}
