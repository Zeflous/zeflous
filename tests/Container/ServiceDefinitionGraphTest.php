<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\ArgumentNames;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCycles;
use Zef\Framework\Container\DefinitionEdges;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionReferences;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\FactoryShadows;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ValueDefinition;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ServiceDefinitionGraph::class)]
#[CoversClass(DefinitionCycles::class)]
#[CoversClass(DefinitionEdges::class)]
#[CoversClass(FactoryShadows::class)]
#[CoversClass(DefinitionReferences::class)]
#[CoversClass(ArgumentNames::class)]
final class ServiceDefinitionGraphTest extends TestCase
{
    public function testAReferenceIntoAnotherDefinitionValidates(): void
    {
        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.greeter', Greeter::class),
            new InstanceDefinition('app.announcer', Announcer::class, [
                ['service' => 'app.greeter'],
                'ops',
            ]),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testAReferenceIntoARegisteredServiceValidates(): void
    {
        $container = new Container();
        $container->set('app.root', static fn (): Greeter => new Greeter('Hello'));

        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.leaf', Greeter::class, [['service' => 'app.root']]),
        ], $container);

        self::addToAssertionCount(1);
    }

    public function testACycleIsDetectedWhenARegisteredLeafPrecedesTheCycleEdge(): void
    {
        $container = new Container();
        $container->set('app.leaf', static fn (): Greeter => new Greeter('Hello'));

        try {
            ServiceDefinitionGraph::validate([
                // The registered leaf comes FIRST: an implementation that
                // stopped collecting the definition's remaining edges at
                // the leaf (break instead of continue) would never see the
                // app.a -> app.b edge and the cycle would slip through.
                new InstanceDefinition('app.a', Greeter::class, [
                    ['service' => 'app.leaf'],
                    ['service' => 'app.b'],
                ]),
                new AliasDefinition('app.b', 'app.a'),
            ], $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.a.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownTargetIsRejectedWithBothEndsNamed(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.leaf', Greeter::class, [['service' => 'app.missing']]),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.leaf' references the unknown service 'app.missing'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testADirectCycleIsRejectedWithTheChain(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.a', Greeter::class, [['service' => 'app.b']]),
                new InstanceDefinition('app.b', Greeter::class, [['service' => 'app.a']]),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.a.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIndirectCycleIsRejectedWithTheWalkedChain(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.a', Greeter::class, [['service' => 'app.b']]),
                new InstanceDefinition('app.b', Greeter::class, [['service' => 'app.c']]),
                new InstanceDefinition('app.c', Greeter::class, [['service' => 'app.a']]),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.a' is part of a circular reference chain: app.a -> app.b -> app.c -> app.a.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAliasCycleIsRejected(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new AliasDefinition('app.x', 'app.y'),
                new AliasDefinition('app.y', 'app.x'),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' is part of a circular reference chain: app.x -> app.y -> app.x.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testASharedDependencyIsNotACycle(): void
    {
        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.greeter', Greeter::class),
            new InstanceDefinition('app.a', Greeter::class, [['service' => 'app.greeter']]),
            new InstanceDefinition('app.b', Greeter::class, [['service' => 'app.greeter']]),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testAClassNamedFactoryTargetIsNotAContainerEdge(): void
    {
        ServiceDefinitionGraph::validate([
            new FactoryDefinition('app.label', ExternalFactory::class . '::make', []),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testAServiceNamedFactoryTargetIsAContainerEdge(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new FactoryDefinition('app.label', 'app.builder::build', []),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.label' references the unknown service 'app.builder'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAClassNamedFactoryTargetThePlannedSetAlsoCarriesIsADerivedEdge(): void
    {
        // The audit's own repro (#783, finding 6): the factory target
        // names an existing class AND an identifier the planned set
        // carries -- the runtime precedence resolves the service, so
        // the cycle it closes is the boot's to refuse, not the
        // resolution's to discover mid-flight.
        try {
            ServiceDefinitionGraph::validate([
                new FactoryDefinition('app.label', ExternalFactory::class . '::build', []),
                new InstanceDefinition(ExternalFactory::class, Greeter::class, [['service' => 'app.label']]),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.label' is part of a circular reference chain: app.label -> "
                . ExternalFactory::class . ' -> app.label.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheDerivedEdgeIsCollectedForAPlannedClassNamedTarget(): void
    {
        $edges = DefinitionEdges::collect([
            new FactoryDefinition('app.label', ExternalFactory::class . '::build', []),
            new InstanceDefinition(ExternalFactory::class, Greeter::class),
        ], new Container());

        self::assertSame([ExternalFactory::class], $edges['app.label']);
    }

    public function testTheDerivedEdgeCarriesTheCaptureDisciplineToo(): void
    {
        // A dependency the runtime will walk is a dependency all the
        // same: the class-named factory target, planned with a request
        // lifetime, is the capture the boot refuses before a single
        // resolution -- the derived edge rides every discipline the
        // declared references ride.
        try {
            ServiceDefinitionGraph::validate([
                new FactoryDefinition('app.label', ExternalFactory::class . '::build', []),
                new InstanceDefinition(
                    ExternalFactory::class,
                    Greeter::class,
                    [],
                    ServiceLifetime::REQUEST,
                ),
            ], new Container());
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.label' (singleton) depends on the request-scoped service '"
                . ExternalFactory::class . "': a request-scoped instance cannot be captured beyond its scope -- "
                . "give '" . ExternalFactory::class . "' a singleton or transient lifetime, "
                . "or make 'app.label' request-scoped.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAClosureRegisteredClassNamedTargetStaysALeafEdge(): void
    {
        // The leaf doctrine unchanged: the closure-registered shadow
        // takes the instance lane at call time, but the host's own
        // registration is a leaf the graph does not pretend to prove --
        // the derived edge exists only over the planned set, the edges
        // the boot itself owns.
        $container = new Container();
        $container->set(
            ExternalFactory::class,
            static fn (): ExternalFactory => new ExternalFactory('leaf'),
        );

        ServiceDefinitionGraph::validate([
            new FactoryDefinition('app.label', ExternalFactory::class . '::build', []),
        ], $container);

        self::addToAssertionCount(1);
    }

    public function testThePlannedShadowResolvesThroughTheRealBootAsTheInstanceLane(): void
    {
        // The boot-level pin: the planned definition shadowing its own
        // class name resolves as a service -- the factory's product
        // built from the instance, never the static call (the method
        // reads its own state, so a static spelling would be the
        // engine's own refusal, not an answer).
        $config = new Config([
            'services' => [
                ExternalFactory::class => [
                    'class' => ExternalFactory::class,
                    'arguments' => ['instance'],
                ],
                'app.label' => [
                    'factory' => ExternalFactory::class . '::build',
                    'arguments' => ['widget'],
                ],
            ],
        ]);

        $container = new ContainerBootstrapper()->bootstrap($config, new Container());

        self::assertSame('instance:widget', $container->get('app.label'));
    }

    public function testValueDefinitionsCarryNoEdges(): void
    {
        $references = DefinitionReferences::of(new ValueDefinition('app.debug', true));

        self::assertSame([], $references);
    }

    public function testAliasEdgesAreTheTargetAlone(): void
    {
        $references = DefinitionReferences::of(new AliasDefinition('app.alias', 'app.target'));

        self::assertSame(['app.target'], $references);
    }

    public function testInstanceEdgesAreTheArgumentReferencesAlone(): void
    {
        $references = DefinitionReferences::of(
            new InstanceDefinition('app.x', Greeter::class, [['service' => 'app.y'], 'plain']),
        );

        self::assertSame(['app.y'], $references);
    }

    public function testFactoryEdgesOnAServiceTargetIncludeTheTarget(): void
    {
        $references = DefinitionReferences::of(
            new FactoryDefinition('app.x', 'app.builder::build', [['service' => 'app.y']]),
        );

        self::assertSame(['app.builder', 'app.y'], $references);
    }

    public function testFactoryEdgesOnAClassTargetSkipTheTarget(): void
    {
        $references = DefinitionReferences::of(
            new FactoryDefinition('app.x', ExternalFactory::class . '::make', [['service' => 'app.y']]),
        );

        self::assertSame(['app.y'], $references);
    }

    public function testAnUnvalidatedNonStringServiceEntryCarriesNoEdge(): void
    {
        $references = DefinitionReferences::of(
            new InstanceDefinition('app.x', Greeter::class, [['service' => 7], ['service' => 'app.y']]),
        );

        self::assertSame(['app.y'], $references, 'only the well-formed reference becomes an edge');
    }

    public function testInstanceEdgesIncludeTheCallReferences(): void
    {
        $references = DefinitionReferences::of(
            new InstanceDefinition('app.x', Greeter::class, [['service' => 'app.y']], calls: [
                new MethodCall('setLogger', [['service' => 'app.logger']]),
                new MethodCall('warmUp'),
                new MethodCall('addTag', [['service' => 'app.y'], 'plain']),
            ]),
        );

        self::assertSame(['app.y', 'app.logger', 'app.y'], $references);
    }

    public function testFactoryEdgesIncludeTheCallReferences(): void
    {
        $references = DefinitionReferences::of(
            new FactoryDefinition('app.x', ExternalFactory::class . '::make', [], calls: [
                new MethodCall('setLogger', [['service' => 'app.logger']]),
            ]),
        );

        self::assertSame(['app.logger'], $references);
    }

    public function testACallReferenceIntoAnUnknownServiceIsRejected(): void
    {
        try {
            ServiceDefinitionGraph::validate(
                [
                    new InstanceDefinition('app.x', Greeter::class, ['Hello'], calls: [
                        new MethodCall('setLogger', [['service' => 'app.absent']]),
                    ]),
                ],
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' references the unknown service 'app.absent'.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testASingletonThatCapturesARequestScopedServiceThroughACallIsRefused(): void
    {
        try {
            ServiceDefinitionGraph::validate(
                [
                    new InstanceDefinition('app.leaf', Greeter::class, ['Hello'], ServiceLifetime::REQUEST),
                    new InstanceDefinition('app.holder', Announcer::class, ['ops'], calls: [
                        new MethodCall('addGreeter', [['service' => 'app.leaf']]),
                    ]),
                ],
                new Container(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.holder' (singleton) depends on the request-scoped service 'app.leaf': "
                . 'a request-scoped instance cannot be captured beyond its scope -- '
                . "give 'app.leaf' a singleton or transient lifetime, or make 'app.holder' request-scoped.",
                $definitionException->getMessage(),
            );
        }
    }
}
