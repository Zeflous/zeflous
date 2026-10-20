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
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerInstalls;
use Zef\Framework\Container\DecoratorLayers;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\ContainerBootstrapper;
use Zef\Framework\FrameworkDefaults;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Decorators\LoggerDecorators;
use Zef\Test\Support\Container\Decorators\PrefixedLogger;

/**
 * The decoration lane's boot behaviour, end to end: the knob parsed
 * with the rest, the chain resolved through the container the way a
 * host observes it, the tag locator answering the decorated instance
 * under the original identifier (the reference container's ZEF-DEEP-08
 * PoC, ported to the declarative boot), the AOT artifact carrying the
 * chain with the digest folding the knob, and the interplay with the
 * contextual lane (ROADMAP area 7's boot half).
 *
 * @internal
 */
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ContainerInstalls::class)]
#[CoversClass(TaggedServices::class)]
final class DecoratedBootTest extends TestCase
{
    private string $artifactFile;

    #[Override]
    protected function setUp(): void
    {
        $this->artifactFile = sys_get_temp_dir() . '/zef-decorated-boot-' . uniqid('', false) . '.php';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->artifactFile)) {
            return;
        }

        unlink($this->artifactFile);
    }

    public function testTheBootResolvesTheChainOutsideIn(): void
    {
        $container = $this->booted($this->config());

        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame(
            'trace(timing(file(app.log):hi))',
            $logger->line('hi'),
            'the first declared decorator is the outermost wrapper',
        );
    }

    public function testTheTagLocatorAnswersTheDecoratedInstance(): void
    {
        $container = $this->booted($this->config());

        $locator = $container->get(FrameworkDefaults::TAGS_SERVICE);
        self::assertInstanceOf(TaggedServices::class, $locator);

        $resolved = $locator->services('app.logger');
        self::assertCount(1, $resolved);
        self::assertInstanceOf(PrefixedLogger::class, $resolved[0]);
        self::assertSame('trace(timing(file(app.log):hi))', $resolved[0]->line('hi'));

        self::assertSame(
            ['logger'],
            $locator->ids('app.logger'),
            'the tag keeps pointing at the ORIGINAL identifier -- never at a synthetic layer',
        );
    }

    public function testAContextuallyRewrittenDeclarationDecoratesThroughTheRewrite(): void
    {
        $config = $this->config();
        $config['services']['app.alt-path'] = ['value' => 'alt.log'];
        $config['contextual'] = ['logger' => ['app.path' => 'app.alt-path']];

        $container = $this->booted($config);

        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame(
            'trace(timing(file(alt.log):hi))',
            $logger->line('hi'),
            'the contextual rewrite landed on the declaration before the decoration re-homed it',
        );
        $base = $container->get(DecoratorLayers::base('logger'));
        self::assertInstanceOf(FileLogger::class, $base);
        self::assertSame('file(alt.log):hi', $base->line('hi'));
    }

    public function testAnInnerSpellingInAnOrdinaryDeclarationIsTheUnknownServiceRefusal(): void
    {
        $config = $this->config();
        $config['services']['app.stray'] = [
            'class' => PrefixedLogger::class,
            'arguments' => [['service' => '@inner'], 'trace'],
        ];

        try {
            $this->booted($config);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.stray' references the unknown service '@inner'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheFirstBootDumpsAndTheSecondLoadsTheDecoratedChain(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $first = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());
        $firstOutcome = $first->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $firstOutcome);
        self::assertSame(AotLane::FullCompile, $firstOutcome->lane);
        $firstLogger = $first->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $firstLogger);
        self::assertSame('trace(timing(file(app.log):hi))', $firstLogger->line('hi'));

        $second = $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());
        $secondOutcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $secondOutcome);
        self::assertSame(AotLane::ArtifactLoaded, $secondOutcome->lane);
        $secondLogger = $second->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $secondLogger);
        self::assertSame(
            'trace(timing(file(app.log):hi))',
            $secondLogger->line('hi'),
            'the artifact carries the wrapper chain -- the layers dumped as ordinary definitions',
        );
    }

    public function testAChangedDecorationIsAProvenStaleArtifact(): void
    {
        $containerAotCache = new ContainerAotCache($this->artifactFile);
        $containerBootstrapper = new ContainerBootstrapper(containerAotCache: $containerAotCache);

        $containerBootstrapper->bootstrap(new Config($this->aotConfig()), new Container());

        $changed = $this->aotConfig();
        $changed['decorators']['logger'] = [
            ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
        ];

        $second = $containerBootstrapper->bootstrap(new Config($changed), new Container());
        $outcome = $second->get(FrameworkDefaults::AOT_SERVICE);
        self::assertInstanceOf(AotOutcome::class, $outcome);
        self::assertSame(AotLane::ArtifactBypassed, $outcome->lane);
        self::assertSame(
            'the artifact digest did not match the configuration -- recompiled and re-dumped',
            $outcome->note,
        );
        $changedLogger = $second->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $changedLogger);
        self::assertSame(
            'timing(file(app.log):hi)',
            $changedLogger->line('hi'),
            "the changed chain answers, never the stale artifact's",
        );
    }

    public function testTheReHomedBaseStaysAddressableUnderItsSyntheticIdentifier(): void
    {
        $container = $this->booted($this->config());

        $base = $container->get(DecoratorLayers::base('logger'));
        self::assertInstanceOf(FileLogger::class, $base);
        self::assertSame('file(app.log):hi', $base->line('hi'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function booted(array $config): Container
    {
        return new ContainerBootstrapper()->bootstrap(new Config($config), new Container());
    }

    /**
     * The decorated boot's configuration: a tagged file logger over a
     * value, wrapped trace-outside-timing.
     */
    /**
     * @return array<string, array<string, mixed>>
     */
    private function config(): array
    {
        return [
            'services' => [
                'app.path' => ['value' => 'app.log'],
                'logger' => [
                    'class' => FileLogger::class,
                    'arguments' => [['service' => 'app.path']],
                    'tags' => ['app.logger'],
                ],
            ],
            'decorators' => [
                'logger' => [
                    ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                    ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
                ],
            ],
        ];
    }

    /**
     * The AOT-lane configuration: the decorated boot with the knob on.
     */
    /**
     * @return array<string, array<string, mixed>>
     */
    private function aotConfig(): array
    {
        $config = $this->config();
        $config['aot'] = ['enabled' => true];

        return $config;
    }
}
