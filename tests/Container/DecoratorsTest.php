<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DecoratorApplication;
use Zef\Framework\Container\DecoratorEntry;
use Zef\Framework\Container\DecoratorInner;
use Zef\Framework\Container\DecoratorLayers;
use Zef\Framework\Container\DecoratorMap;
use Zef\Framework\Container\DecoratorRehoming;
use Zef\Framework\Container\DecoratorRewrites;
use Zef\Framework\Container\Decorators;
use Zef\Framework\Container\DecoratorSet;
use Zef\Framework\Container\DecoratorSpellings;
use Zef\Framework\Container\DecoratorTarget;
use Zef\Framework\Container\DecoratorWrappers;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\TaggedServices;
use Zef\Framework\Container\UnplannedInstanceDefinition;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Decorators\DualLogger;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Decorators\LoggerDecorators;
use Zef\Test\Support\Container\Decorators\NotingLogger;
use Zef\Test\Support\Container\Decorators\PrefixedLogger;
use Zef\Test\Support\Container\Decorators\ShapelessDefinition;
use Zef\Test\Support\Container\Decorators\ValueWrappers;

/**
 * The decoration lane's boot apply: the definition surgery over a
 * planned set -- the re-homed base, the wrapper chain, the tags that
 * travel with the original identifier (the reference container's
 * ZEF-DEEP-08, ported), the lifetimes every layer shares, and the
 * refusals the guards own (ROADMAP area 7's apply half).
 *
 * @internal
 */
#[CoversClass(Decorators::class)]
#[CoversClass(DecoratorApplication::class)]
#[CoversClass(DecoratorSet::class)]
#[CoversClass(DecoratorSpellings::class)]
#[CoversClass(DecoratorRewrites::class)]
#[CoversClass(DecoratorRehoming::class)]
#[CoversClass(DecoratorWrappers::class)]
#[CoversClass(DecoratorInner::class)]
#[CoversClass(DecoratorTarget::class)]
#[CoversClass(DecoratorLayers::class)]
#[CoversClass(InstanceDefinition::class)]
#[CoversClass(FactoryDefinition::class)]
#[CoversClass(AliasDefinition::class)]
#[CoversClass(ValueDefinition::class)]
#[CoversClass(ServiceTagIndex::class)]
final class DecoratorsTest extends TestCase
{
    public function testAnEmptyMapAnswersTheDefinitionsUnchanged(): void
    {
        $definitions = $this->defaultSet();

        self::assertSame(
            $definitions,
            Decorators::apply($definitions, DecoratorMap::parse('decorators', null)),
        );
    }

    public function testASingleClassDecoratorReHomesTheTargetAndWrapsIt(): void
    {
        $applied = Decorators::apply($this->defaultSet(), $this->prefixedDecoration());

        self::assertCount(3, $applied);

        $wrapper = $applied[0];
        self::assertInstanceOf(InstanceDefinition::class, $wrapper);
        self::assertSame('logger', $wrapper->id);
        self::assertSame(PrefixedLogger::class, $wrapper->class);
        self::assertSame(
            [['service' => DecoratorLayers::base('logger')], 'trace'],
            $wrapper->arguments,
            "the inner reference is rewritten to the re-homed base's identifier",
        );
        self::assertSame(ServiceLifetime::SINGLETON, $wrapper->lifetime);
        self::assertSame(['app.logger'], $wrapper->tags->all(), 'the tags travel with the original identifier');

        $base = $applied[2];
        self::assertInstanceOf(InstanceDefinition::class, $base);
        self::assertSame(DecoratorLayers::base('logger'), $base->id);
        self::assertSame(FileLogger::class, $base->class);
        self::assertSame([['service' => 'app.path']], $base->arguments);
        self::assertSame([], $base->tags->all(), 'the re-homed base carries no tags -- ZEF-DEEP-08');

        self::assertSame('app.path', $applied[1]->id, 'the sibling declaration is untouched');
    }

    public function testAChainWrapsOutsideInWithSyntheticLayers(): void
    {
        $applied = Decorators::apply(
            $this->defaultSet(),
            DecoratorMap::parse('decorators', [
                'logger' => [
                    ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                    ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
                ],
            ]),
        );

        self::assertCount(4, $applied);

        $outermost = $applied[0];
        self::assertInstanceOf(InstanceDefinition::class, $outermost);
        self::assertSame('logger', $outermost->id);
        self::assertSame(
            [['service' => DecoratorLayers::layer('logger', 1)], 'trace'],
            $outermost->arguments,
            'the outermost wrapper references the first synthetic layer inward',
        );

        $inner = $applied[3];
        self::assertInstanceOf(FactoryDefinition::class, $inner);
        self::assertSame(DecoratorLayers::layer('logger', 1), $inner->id);
        self::assertSame(
            [['service' => DecoratorLayers::base('logger')]],
            $inner->arguments,
            'the innermost wrapper references the re-homed base',
        );
        self::assertSame([], $inner->tags->all());

        $base = $applied[2];
        self::assertSame(DecoratorLayers::base('logger'), $base->id);
    }

    public function testTheRewrittenSetResolvesTheChainOutsideIn(): void
    {
        $applied = Decorators::apply(
            $this->defaultSet(),
            DecoratorMap::parse('decorators', [
                'logger' => [
                    ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                    ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
                ],
            ]),
        );

        ServiceDefinitionGraph::validate($applied, new Container());

        $container = $this->installed($applied);

        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame(
            'trace(timing(file(app.log):hi))',
            $logger->line('hi'),
            'first entry outermost: trace wraps timing wraps the file logger',
        );
    }

    public function testTheTagLocatorAnswersTheDecoratedInstance(): void
    {
        $applied = Decorators::apply($this->defaultSet(), $this->prefixedDecoration());

        $taggedServices = new TaggedServices(
            ServiceTagIndex::fromDefinitions($applied),
            $this->installed($applied),
        );

        $resolved = $taggedServices->services('app.logger');
        self::assertCount(1, $resolved);
        self::assertInstanceOf(PrefixedLogger::class, $resolved[0], 'the tag answers the DECORATED instance');
        self::assertSame(
            'trace(file(app.log):hi)',
            $resolved[0]->line('hi'),
            'never the re-homed base the tag index would have answered pre-fix -- ZEF-DEEP-08',
        );
    }

    public function testEveryLayerSharesTheDecoratedDeclarationLifetime(): void
    {
        $transient = [
            new InstanceDefinition(
                'logger',
                FileLogger::class,
                [['service' => 'app.path']],
                ServiceLifetime::TRANSIENT,
                new ServiceTags(['app.logger']),
            ),
            new ValueDefinition('app.path', 'app.log'),
        ];

        $applied = Decorators::apply($transient, $this->prefixedDecoration());

        self::assertSame(ServiceLifetime::TRANSIENT, $applied[0]->lifetime);
        self::assertSame(ServiceLifetime::TRANSIENT, $applied[2]->lifetime);

        $container = $this->installed($applied);
        self::assertNotSame($container->get('logger'), $container->get('logger'));
    }

    public function testAnAliasTargetIsDecoratedThroughItsResolution(): void
    {
        $definitions = [
            new AliasDefinition('logger', 'app.file'),
            new InstanceDefinition('app.file', FileLogger::class),
        ];

        $applied = Decorators::apply(
            $definitions,
            DecoratorMap::parse('decorators', [
                'logger' => [
                    ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
                ],
            ]),
        );

        $base = $applied[2];
        self::assertInstanceOf(AliasDefinition::class, $base);
        self::assertSame(DecoratorLayers::base('logger'), $base->id);
        self::assertSame('app.file', $base->target, 'the re-homed base is the alias itself, target intact');

        $container = $this->installed($applied);
        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame('timing(file(app.log):hi)', $logger->line('hi'));
    }

    public function testAValueTargetIsDecoratedThroughItsLiteral(): void
    {
        $definitions = [new ValueDefinition('app.banner', 'welcome')];

        $applied = Decorators::apply(
            $definitions,
            DecoratorMap::parse('decorators', [
                'app.banner' => [
                    ['factory' => ValueWrappers::class . '::exclaim', 'arguments' => [['service' => '@inner']]],
                ],
            ]),
        );

        $base = $applied[1];
        self::assertInstanceOf(ValueDefinition::class, $base);
        self::assertSame(DecoratorLayers::base('app.banner'), $base->id);
        self::assertSame('welcome', $base->value);

        $container = $this->installed($applied);
        self::assertSame('welcome!', $container->get('app.banner'));
    }

    public function testTheOriginalsMethodCallsSurviveOnTheReHomedBase(): void
    {
        $definitions = [
            new InstanceDefinition(
                'logger',
                NotingLogger::class,
                [['service' => 'app.path']],
                ServiceLifetime::SINGLETON,
                new ServiceTags(['app.logger']),
                [new MethodCall('note', ['booted'])],
            ),
            new ValueDefinition('app.path', 'app.log'),
        ];

        $applied = Decorators::apply($definitions, $this->prefixedDecoration());

        $container = $this->installed($applied);

        $base = $container->get(DecoratorLayers::base('logger'));
        self::assertInstanceOf(NotingLogger::class, $base);
        self::assertSame(
            ['booted'],
            $base->notes(),
            "the re-homed base keeps the declaration's method calls -- the calls lane rides along",
        );
        $wrapper = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $wrapper);
        self::assertSame('trace(file(app.log):hi)', $wrapper->line('hi'));
    }

    public function testASquattedBaseCollidesThroughTheDirectSurgery(): void
    {
        $definitions = [
            new InstanceDefinition('logger', FileLogger::class),
            new ValueDefinition(DecoratorLayers::base('logger'), 'squatted'),
        ];

        $chain = DecoratorMap::parse('decorators', [
            'logger' => [
                ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
            ],
        ])->decorations()['logger'];

        try {
            DecoratorSet::decorated($definitions, 0, $definitions[0], $chain);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service identifier '@decor:logger:base' collides with the decoration lane's "
                . 'synthetic spelling for this target -- spell a host identifier instead.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testASquattedSpellingOutsideTheOwnedListDoesNotCollide(): void
    {
        $definitions = [
            new InstanceDefinition('logger', FileLogger::class),
            new ValueDefinition(DecoratorLayers::layer('logger', 0), 'outside the grammar'),
            new ValueDefinition(DecoratorLayers::layer('logger', 2), 'beyond the chain'),
        ];

        $chain = DecoratorMap::parse('decorators', [
            'logger' => [
                ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
            ],
        ])->decorations()['logger'];

        $applied = DecoratorSet::decorated($definitions, 0, $definitions[0], $chain);

        self::assertCount(
            5,
            $applied,
            "the surgery proceeds: layer 0 is below and layer 2 beyond a two-entry chain's own spellings",
        );
    }

    public function testTheInnerReferenceRecognitionAndPresence(): void
    {
        self::assertTrue(DecoratorInner::names(['service' => DecoratorLayers::INNER]));
        self::assertFalse(DecoratorInner::names(['service' => 'other.id']));
        self::assertFalse(DecoratorInner::names('literal'));

        self::assertTrue(DecoratorInner::carried([['service' => DecoratorLayers::INNER], 'beside']));
        self::assertFalse(DecoratorInner::carried(['literal', ['service' => 'other.id']]));
        self::assertFalse(DecoratorInner::carried([]));

        self::assertSame(
            [['service' => 'the.layer'], 'beside'],
            DecoratorInner::rewritten([['service' => DecoratorLayers::INNER], 'beside'], 'the.layer'),
        );
    }

    public function testAFactoryTargetIsReHomedWithItsSpellingAndArguments(): void
    {
        $definitions = [
            new FactoryDefinition('logger', LoggerDecorators::class . '::timing', [['service' => 'app.file']]),
            new InstanceDefinition('app.file', FileLogger::class),
        ];

        $applied = Decorators::apply($definitions, $this->prefixedDecoration());

        $base = $applied[2];
        self::assertInstanceOf(FactoryDefinition::class, $base);
        self::assertSame(DecoratorLayers::base('logger'), $base->id);
        self::assertSame(LoggerDecorators::class . '::timing', $base->factory);
        self::assertSame([['service' => 'app.file']], $base->arguments);

        $container = $this->installed($applied);
        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame('trace(timing(file(app.log):hi))', $logger->line('hi'));
    }

    public function testADuplicatedTargetDecoratesItsLastDeclaration(): void
    {
        $definitions = [
            new InstanceDefinition('logger', FileLogger::class, [['service' => 'app.path']]),
            new ValueDefinition('app.path', 'app.log'),
            new InstanceDefinition('logger', FileLogger::class, [['service' => 'app.alt-path']]),
            new ValueDefinition('app.alt-path', 'alt.log'),
        ];

        $applied = Decorators::apply($definitions, $this->prefixedDecoration());

        $container = $this->installed($applied);

        $base = $container->get(DecoratorLayers::base('logger'));
        self::assertInstanceOf(FileLogger::class, $base);
        self::assertSame(
            'file(alt.log):hi',
            $base->line('hi'),
            'the LAST declaration is the effective one the re-homing carries',
        );

        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame('trace(file(alt.log):hi)', $logger->line('hi'));
    }

    public function testAThreeDecoratorChainWrapsThreeDeep(): void
    {
        $applied = Decorators::apply(
            $this->defaultSet(),
            DecoratorMap::parse('decorators', [
                'logger' => [
                    ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                    ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
                    ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'audit']],
                ],
            ]),
        );

        $container = $this->installed($applied);

        $logger = $container->get('logger');
        self::assertInstanceOf(PrefixedLogger::class, $logger);
        self::assertSame('trace(timing(audit(file(app.log):hi)))', $logger->line('hi'));
    }

    public function testAnEmptyDecorationMapLeavesASquattedNamespaceUnchallenged(): void
    {
        $definitions = [new ValueDefinition(DecoratorLayers::base('logger'), 'squatted')];

        self::assertSame(
            $definitions,
            Decorators::apply($definitions, DecoratorMap::parse('decorators', null)),
            'the namespace reservation binds only when the lane runs',
        );
    }

    public function testAHandBuiltEntryCarryingNoShapeIsRefused(): void
    {
        try {
            DecoratorRewrites::wrapper(
                new DecoratorEntry(null, null, [['service' => DecoratorLayers::INNER]]),
                'logger',
                DecoratorLayers::base('logger'),
                new InstanceDefinition('logger', FileLogger::class),
                new ServiceTags(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A decorator entry carries exactly one shape key -- '
                . 'hand-built entries must spell either class or factory.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAHandBuiltDefinitionOfNoDeclarativeShapeIsRefusedByTheReHoming(): void
    {
        $shapelessDefinition = new ShapelessDefinition('logger');

        try {
            DecoratorRehoming::rehomed($shapelessDefinition, DecoratorLayers::base('logger'));
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'logger' definition shape cannot be re-homed -- only the four declarative shapes decorate.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownTargetIsRefused(): void
    {
        try {
            Decorators::apply($this->defaultSet(), DecoratorMap::parse('decorators', [
                'missing' => [$this->timingEntry()],
            ]));
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Cannot decorate the unknown service 'missing' (declare 'missing' under 'services' first).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testASquattedNamespaceIdentifierIsRefused(): void
    {
        $definitions = [
            new ValueDefinition(DecoratorLayers::base('logger'), 'squatted'),
            ...$this->defaultSet(),
        ];

        try {
            Decorators::apply($definitions, $this->prefixedDecoration());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service identifier '@decor:logger:base' occupies the decoration lane's own "
                . "'@decor:' namespace -- spell a host identifier instead.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testACollisionWithThisTargetsOwnSpellingIsRefused(): void
    {
        $definitions = [
            new InstanceDefinition('logger', FileLogger::class),
            new ValueDefinition(DecoratorLayers::layer('logger', 1), 'squatted'),
        ];

        $chain = DecoratorMap::parse('decorators', [
            'logger' => [
                ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
            ],
        ])->decorations()['logger'];

        try {
            DecoratorSet::decorated($definitions, 0, $definitions[0], $chain);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service identifier '@decor:logger:1' collides with the decoration lane's "
                . 'synthetic spelling for this target -- spell a host identifier instead.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnplannedTargetIsRefused(): void
    {
        try {
            DecoratorApplication::applied(
                [new UnplannedInstanceDefinition('logger', FileLogger::class)],
                'logger',
                DecoratorMap::parse('decorators', [
                    'logger' => [$this->timingEntry()],
                ])->decorations()['logger'],
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Cannot decorate 'logger' before the autowire compile pass has planned the definition set.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testADecoratorReferencingTheTargetItselfIsAWalkedCycle(): void
    {
        try {
            ServiceDefinitionGraph::validate(Decorators::apply(
                $this->defaultSet(),
                DecoratorMap::parse('decorators', [
                    'logger' => [
                        [
                            'class' => DualLogger::class,
                            'arguments' => [['service' => '@inner'], ['service' => 'logger']],
                        ],
                    ],
                ]),
            ), new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'logger' is part of a circular reference chain: logger -> logger.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testADecoratorReferencingAnotherDecoratedTargetResolvesItsWrapper(): void
    {
        $definitions = [
            new InstanceDefinition('logger', FileLogger::class),
            new InstanceDefinition('app.path', FileLogger::class),
        ];

        $applied = Decorators::apply(
            $definitions,
            DecoratorMap::parse('decorators', [
                'app.path' => [$this->timingEntry()],
                'logger' => [
                    [
                        'class' => DualLogger::class,
                        'arguments' => [['service' => '@inner'], ['service' => 'app.path']],
                    ],
                ],
            ]),
        );

        ServiceDefinitionGraph::validate($applied, new Container());

        $container = $this->installed($applied);
        $logger = $container->get('logger');
        self::assertInstanceOf(DualLogger::class, $logger);
        self::assertSame(
            'dual(file(app.log):hi|timing(file(app.log):hi))',
            $logger->line('hi'),
        );
        self::assertInstanceOf(
            PrefixedLogger::class,
            $logger->other(),
            "a reference to another decorated target resolves that target's OUTERMOST wrapper",
        );
    }

    public function testADoubledInnerReferenceIsRewrittenTwice(): void
    {
        $applied = Decorators::apply(
            $this->defaultSet(),
            DecoratorMap::parse('decorators', [
                'logger' => [
                    [
                        'factory' => ValueWrappers::class . '::pair',
                        'arguments' => [['service' => '@inner'], ['service' => '@inner']],
                    ],
                ],
            ]),
        );

        $wrapper = $applied[0];
        self::assertInstanceOf(FactoryDefinition::class, $wrapper);
        self::assertSame(
            [['service' => DecoratorLayers::base('logger')], ['service' => DecoratorLayers::base('logger')]],
            $wrapper->arguments,
        );
    }

    /**
     * One factory-shaped decorator entry: the timing wrapper over the
     * layer it wraps.
     *
     * @return array<string, mixed>
     */
    private function timingEntry(): array
    {
        return ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]];
    }

    /**
     * The default set: a tagged logger over a value, plus a sibling.
     *
     * @return list<ServiceDefinition>
     */
    private function defaultSet(): array
    {
        return [
            new InstanceDefinition(
                'logger',
                FileLogger::class,
                [['service' => 'app.path']],
                ServiceLifetime::SINGLETON,
                new ServiceTags(['app.logger']),
            ),
            new ValueDefinition('app.path', 'app.log'),
        ];
    }

    private function prefixedDecoration(): DecoratorMap
    {
        return DecoratorMap::parse('decorators', [
            'logger' => [
                ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
            ],
        ]);
    }

    /**
     * Installs a planned set through the compiler and answers the
     * container, the boot's own install loop.
     *
     * @param list<ServiceDefinition> $definitions
     */
    private function installed(array $definitions): Container
    {
        $definitionCompiler = new DefinitionCompiler();
        $container = new Container();
        foreach ($definitions as $definition) {
            $container->set($definition->id, $definitionCompiler->compile($definition), $definition->lifetime);
        }

        return $container;
    }
}
