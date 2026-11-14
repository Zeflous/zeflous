<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\AliasLifetimes;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * The alias-lifetime alignment stage on its own (#769's surgery): the
 * one walk that rewrites every ordinary alias to its target's sharing
 * policy -- declared targets transitively through alias chains, leaf
 * targets through the container's own registrations, and the two
 * verdicts that stay the graph validator's (unknown targets, cycles)
 * answered unchanged for the refusal one stage later.
 *
 * @internal
 */
#[CoversClass(AliasLifetimes::class)]
final class AliasLifetimesTest extends TestCase
{
    public function testATransientTargetAlignsItsAlias(): void
    {
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.transient'),
            new InstanceDefinition('app.transient', Greeter::class, [], ServiceLifetime::TRANSIENT),
        ], new Container());

        self::assertSame(ServiceLifetime::TRANSIENT, $aligned[0]->lifetime);
    }

    public function testARequestTargetAlignsItsAlias(): void
    {
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.scoped'),
            new InstanceDefinition('app.scoped', Greeter::class, [], ServiceLifetime::REQUEST),
        ], new Container());

        self::assertSame(ServiceLifetime::REQUEST, $aligned[0]->lifetime);
    }

    public function testASingletonTargetKeepsTheAliasOnItsOwnObject(): void
    {
        $aliasDefinition = new AliasDefinition('app.alias', 'app.shared');
        $instanceDefinition = new InstanceDefinition('app.shared', Greeter::class);

        $aligned = AliasLifetimes::align([$aliasDefinition, $instanceDefinition], new Container());

        self::assertSame(ServiceLifetime::SINGLETON, $aligned[0]->lifetime);
        self::assertSame(
            $aliasDefinition,
            $aligned[0],
            'a singleton ride rewrites nothing: the same object passes through',
        );
    }

    public function testTheAlignmentRidesAliasChainsWhateverTheDeclarationOrder(): void
    {
        // The chain a -> b -> c declared outermost-first: every link
        // answers the one non-alias policy at the chain's end.
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.a', 'app.b'),
            new AliasDefinition('app.b', 'app.c'),
            new InstanceDefinition('app.c', Greeter::class, [], ServiceLifetime::TRANSIENT),
        ], new Container());

        self::assertSame(ServiceLifetime::TRANSIENT, $aligned[0]->lifetime);
        self::assertSame(ServiceLifetime::TRANSIENT, $aligned[1]->lifetime);
    }

    public function testAChainDeclaredInnermostFirstRidesTheSamePolicy(): void
    {
        // The same chain with the target declared LAST: the alignment
        // is a walk over the whole set, not a fold over its order.
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.a', 'app.b'),
            new AliasDefinition('app.b', 'app.c'),
            new InstanceDefinition('app.c', Greeter::class, [], ServiceLifetime::REQUEST),
        ], new Container());

        self::assertSame(ServiceLifetime::REQUEST, $aligned[0]->lifetime);
        self::assertSame(ServiceLifetime::REQUEST, $aligned[1]->lifetime);
    }

    public function testTheContextualSyntheticAliasKeepsItsOwnContract(): void
    {
        // The contextual lane's synthetic alias carries the consumer's
        // lifetime by design -- the alignment's own skip, the prefix
        // the lane owns.
        $aliasDefinition = new AliasDefinition(
            '@contextual:app.holder|app.leaf',
            'app.transient',
            ServiceLifetime::REQUEST,
        );

        $aligned = AliasLifetimes::align([
            $aliasDefinition,
            new InstanceDefinition('app.transient', Greeter::class, [], ServiceLifetime::TRANSIENT),
        ], new Container());

        self::assertSame($aliasDefinition, $aligned[0], "the contextual synthetic is not this stage's subject");
        self::assertSame(ServiceLifetime::REQUEST, $aligned[0]->lifetime);
    }

    public function testAnAliasToAContextualSyntheticRidesIt(): void
    {
        // An ordinary alias whose target is a contextual synthetic rides
        // the synthetic's declared (consumer) policy directly -- the
        // walk honours the target it finds, it does not re-align it.
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', '@contextual:app.holder|app.leaf'),
            new AliasDefinition('@contextual:app.holder|app.leaf', 'app.transient', ServiceLifetime::REQUEST),
            new InstanceDefinition('app.transient', Greeter::class, [], ServiceLifetime::TRANSIENT),
        ], new Container());

        self::assertSame(ServiceLifetime::REQUEST, $aligned[0]->lifetime);
    }

    public function testAContainerCarriedLeafTargetAnswersItsOwnRegistrationPolicy(): void
    {
        $container = new Container();
        $container->set('app.leaf', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.leaf'),
        ], $container);

        self::assertSame(ServiceLifetime::TRANSIENT, $aligned[0]->lifetime);
    }

    public function testAFallbackCoveredLeafTargetAnswersTheCoveringPrefixPolicy(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'repo.',
            static fn (): stdClass => new stdClass(),
            ServiceLifetime::TRANSIENT,
        );

        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'repo.widget'),
        ], $container);

        self::assertSame(ServiceLifetime::TRANSIENT, $aligned[0]->lifetime);
    }

    public function testAnUnknownTargetIsLeftForTheGraphsOwnRefusal(): void
    {
        $aliasDefinition = new AliasDefinition('app.alias', 'app.ghost');

        $aligned = AliasLifetimes::align([$aliasDefinition], new Container());

        self::assertSame(
            $aliasDefinition,
            $aligned[0],
            "the alignment judges lifetimes only, never the set's own validity",
        );

        try {
            ServiceDefinitionGraph::validate($aligned, new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.alias' references the unknown service 'app.ghost'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAliasCycleIsLeftForTheGraphsOwnRefusal(): void
    {
        // The walk meets its own open id and answers the alias
        // unchanged -- the cycle refusal is the graph's verdict, one
        // stage later, and the alignment must terminate to let it land.
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.a', 'app.b'),
            new AliasDefinition('app.b', 'app.a'),
        ], new Container());

        self::assertCount(2, $aligned);
        self::assertSame(ServiceLifetime::SINGLETON, $aligned[0]->lifetime);
        self::assertSame(ServiceLifetime::SINGLETON, $aligned[1]->lifetime);

        try {
            ServiceDefinitionGraph::validate($aligned, new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.a.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAValueTargetRidesTheSingletonLane(): void
    {
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.value'),
            new ValueDefinition('app.value', 42),
        ], new Container());

        self::assertSame(ServiceLifetime::SINGLETON, $aligned[0]->lifetime);
    }

    public function testNonAliasDefinitionsPassThroughUntouched(): void
    {
        $instanceDefinition = new InstanceDefinition('app.thing', Greeter::class);
        $factoryDefinition = new FactoryDefinition('app.made', ExternalFactory::class . '::make');
        $valueDefinition = new ValueDefinition('app.value', 42);

        $aligned = AliasLifetimes::align([$instanceDefinition, $factoryDefinition, $valueDefinition], new Container());

        self::assertSame($instanceDefinition, $aligned[0]);
        self::assertSame($factoryDefinition, $aligned[1]);
        self::assertSame($valueDefinition, $aligned[2]);
    }

    public function testTheOrderAndCountOfTheSetIsPreserved(): void
    {
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.transient'),
            new InstanceDefinition('app.transient', Greeter::class, [], ServiceLifetime::TRANSIENT),
            new AliasDefinition('app.other', 'app.transient'),
        ], new Container());

        self::assertSame(
            ['app.alias', 'app.transient', 'app.other'],
            array_map(
                static fn (ServiceDefinition $serviceDefinition): string => $serviceDefinition->id,
                $aligned,
            ),
        );
    }

    public function testAFactoryShapedTargetAlignsItsAliasToo(): void
    {
        $aligned = AliasLifetimes::align([
            new AliasDefinition('app.alias', 'app.made'),
            new FactoryDefinition('app.made', ExternalFactory::class . '::make', [], ServiceLifetime::REQUEST),
        ], new Container());

        self::assertSame(ServiceLifetime::REQUEST, $aligned[0]->lifetime);
    }
}
