<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCycles;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\LifetimeCaptures;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(DefinitionCycles::class)]
#[CoversClass(LifetimeCaptures::class)]
#[CoversClass(ServiceDefinitionGraph::class)]
final class LifetimeCapturesTest extends TestCase
{
    public function testASingletonCapturingARequestScopedDependencyIsRefused(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::REQUEST),
                new InstanceDefinition(
                    'app.holder',
                    Announcer::class,
                    [['service' => 'app.leaf'], 'ops'],
                ),
            ], new Container());
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

    public function testTheVerdictWalksPastANonSingletonDependent(): void
    {
        // The walk must continue past a narrower captor: a transient
        // dependent that legally captures a request-scoped target is
        // skipped, and a singleton capturing the same target AFTER it
        // is still refused -- the verdict is not finished when it meets
        // the first dependent whose lifetime is not a singleton.
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::REQUEST),
                new InstanceDefinition(
                    'app.middle',
                    Announcer::class,
                    [['service' => 'app.leaf'], 'ops'],
                    ServiceLifetime::TRANSIENT,
                ),
                new InstanceDefinition(
                    'app.holder',
                    Announcer::class,
                    [['service' => 'app.leaf'], 'ops'],
                ),
            ], new Container());
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

    public function testARequestScopedCaptorOfARequestScopedDependencyValidates(): void
    {
        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::REQUEST),
            new InstanceDefinition(
                'app.holder',
                Announcer::class,
                [['service' => 'app.leaf'], 'ops'],
                ServiceLifetime::REQUEST,
            ),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testASingletonCapturingATransientDependencyValidates(): void
    {
        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::TRANSIENT),
            new InstanceDefinition(
                'app.holder',
                Announcer::class,
                [['service' => 'app.leaf'], 'ops'],
            ),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testATransientCaptorOfARequestScopedDependencyValidates(): void
    {
        ServiceDefinitionGraph::validate([
            new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::REQUEST),
            new InstanceDefinition(
                'app.holder',
                Announcer::class,
                [['service' => 'app.leaf'], 'ops'],
                ServiceLifetime::TRANSIENT,
            ),
        ], new Container());

        self::addToAssertionCount(1);
    }

    public function testTheCaptiveVerdictRidesTheFactoryShapeToo(): void
    {
        try {
            ServiceDefinitionGraph::validate([
                new InstanceDefinition('app.leaf', Greeter::class, [], ServiceLifetime::REQUEST),
                new FactoryDefinition(
                    'app.holder',
                    ExternalFactory::class . '::make',
                    [['service' => 'app.leaf']],
                ),
            ], new Container());
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
