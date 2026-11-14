<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Container\ActiveScope;
use Zef\Framework\Container\AliasChains;
use Zef\Framework\Container\AliasLifetimes;
use Zef\Framework\Container\AliasTargets;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerAnswers;
use Zef\Framework\Container\ContainerCompilePipeline;
use Zef\Framework\Container\ContextualSynthetics;
use Zef\Framework\Container\DefinitionCycles;
use Zef\Framework\Container\DefinitionEdges;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryRegistrations;
use Zef\Framework\Container\LifetimeCaptures;
use Zef\Framework\Container\RequestInstances;
use Zef\Framework\Container\RequestScope;
use Zef\Framework\Container\RequestScopeException;
use Zef\Framework\Container\RequestScopeRefusals;
use Zef\Framework\Container\ResolutionFrames;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceResolutions;
use Zef\Framework\Container\ServiceStore;
use Zef\Framework\Container\SingletonMemo;
use Zef\Framework\ContainerBootstrapper;
use Zef\Test\Support\Container\Aliasing\SalutingGreeter;
use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\Greeter;

/**
 * The alias ride at the boot's own surface (#769's symptom, inverted):
 * what `get(alias)` answers through the target's sharing policy -- the
 * transient target fresh per call (the first-instance pin the bug
 * served), the request target one instance per scope shared with the
 * target id, the singleton target the same instance forever -- plus
 * the two honest verdicts the boot's graph can finally see (the
 * alias-to-request boot that used to be refused, and the singleton
 * capture *through* an alias edge that used to be invisible).
 *
 * @internal
 */
#[CoversClass(AliasLifetimes::class)]
#[CoversClass(AliasChains::class)]
#[CoversClass(AliasTargets::class)]
#[CoversClass(ContextualSynthetics::class)]
#[CoversClass(ContainerAnswers::class)]
#[CoversClass(FactoryRegistrations::class)]
#[CoversClass(SingletonMemo::class)]
#[CoversClass(ServiceStore::class)]
#[CoversClass(ContainerBootstrapper::class)]
#[CoversClass(ContainerCompilePipeline::class)]
#[CoversClass(Container::class)]
#[CoversClass(ServiceResolutions::class)]
#[CoversClass(RequestScope::class)]
#[CoversClass(RequestInstances::class)]
#[CoversClass(ActiveScope::class)]
#[CoversClass(RequestScopeRefusals::class)]
#[CoversClass(ResolutionFrames::class)]
#[CoversClass(ServiceDefinitionGraph::class)]
#[CoversClass(DefinitionEdges::class)]
#[CoversClass(DefinitionCycles::class)]
#[CoversClass(LifetimeCaptures::class)]
final class AliasRidesBootTest extends TestCase
{
    public function testATransientTargetAnswersAFreshInstancePerCallThroughTheAlias(): void
    {
        $container = $this->booted([
            'app.work' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'transient'],
            'app.work.alias' => ['alias' => 'app.work'],
        ]);

        self::assertNotSame($container->get('app.work.alias'), $container->get('app.work.alias'));
        self::assertNotSame($container->get('app.work.alias'), $container->get('app.work'));
        self::assertSame(ServiceLifetime::TRANSIENT, $container->lifetime('app.work.alias'));
    }

    public function testARequestTargetSharesOneInstancePerScopeWithItsAlias(): void
    {
        $container = $this->booted([
            'app.leaf' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'request'],
            'app.leaf.alias' => ['alias' => 'app.leaf'],
        ]);

        $firstScope = $container->createRequestScope();

        self::assertSame($firstScope->get('app.leaf.alias'), $firstScope->get('app.leaf'));
        self::assertSame($firstScope->get('app.leaf.alias'), $firstScope->get('app.leaf.alias'));

        $secondScope = $container->createRequestScope();

        self::assertNotSame($firstScope->get('app.leaf.alias'), $secondScope->get('app.leaf.alias'));
        self::assertSame(ServiceLifetime::REQUEST, $container->lifetime('app.leaf.alias'));
    }

    public function testARequestTargetAliasRefusesOutsideAScopeLikeItsTarget(): void
    {
        $container = $this->booted([
            'app.leaf' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'request'],
            'app.leaf.alias' => ['alias' => 'app.leaf'],
        ]);

        try {
            $container->get('app.leaf.alias');
            self::fail('Expected a RequestScopeException to be thrown.');
        } catch (RequestScopeException $requestScopeException) {
            self::assertSame(
                "Service 'app.leaf.alias' is request-scoped and cannot be resolved outside a request scope "
                . '-- open one with the container and resolve through it.',
                $requestScopeException->getMessage(),
            );
        }
    }

    public function testASingletonTargetAnswersTheSameInstanceThroughTheAlias(): void
    {
        $container = $this->booted([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.greeter.alias' => ['alias' => 'app.greeter'],
        ]);

        self::assertSame($container->get('app.greeter.alias'), $container->get('app.greeter'));
        self::assertSame($container->get('app.greeter.alias'), $container->get('app.greeter.alias'));
        self::assertSame(ServiceLifetime::SINGLETON, $container->lifetime('app.greeter.alias'));
    }

    public function testAnAliasToARequestScopedTargetBoots(): void
    {
        // The issue's own evidence, inverted: the boot used to refuse
        // the alias-to-request shape as a mechanical singleton
        // capture; aligned to the request lane, the boot is honest
        // and the resolution is the scope's own.
        $container = $this->booted([
            'app.leaf' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'request'],
            'app.leaf.alias' => ['alias' => 'app.leaf'],
        ]);

        $scope = $container->createRequestScope();

        self::assertInstanceOf(Greeter::class, $scope->get('app.leaf.alias'));
    }

    public function testTheCaptureVerdictSeesThroughTheAliasEdge(): void
    {
        // A singleton capturing a request instance *through* an alias:
        // today the edge reads the alias's mechanical singleton and the
        // boot installs the silent capture; aligned, the verdict names
        // the alias exactly the way it names a direct reference.
        try {
            $this->booted([
                'app.leaf' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'request'],
                'app.leaf.alias' => ['alias' => 'app.leaf'],
                'app.holder' => [
                    'class' => Announcer::class,
                    'arguments' => [['service' => 'app.leaf.alias'], 'ops'],
                ],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.holder' (singleton) depends on the request-scoped service 'app.leaf.alias': "
                . 'a request-scoped instance cannot be captured beyond its scope -- '
                . "give 'app.leaf.alias' a singleton or transient lifetime, or make 'app.holder' request-scoped.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheDecoratedAliasChainCarriesTheTargetPolicyThroughout(): void
    {
        // The alias is itself decorated: the wrapper that takes over the
        // alias identifier and the re-homed base under it must both
        // carry the target's policy -- the alignment lands before the
        // decoration apply so every layer of the chain is consistent.
        $container = $this->booted([
            'app.work' => ['class' => Greeter::class, 'arguments' => ['Hi'], 'lifetime' => 'transient'],
            'app.work.alias' => ['alias' => 'app.work'],
        ], [
            'decorators' => [
                'app.work.alias' => [
                    ['class' => SalutingGreeter::class, 'arguments' => [['service' => '@inner']]],
                ],
            ],
        ]);

        self::assertInstanceOf(SalutingGreeter::class, $container->get('app.work.alias'));
        self::assertNotSame($container->get('app.work.alias'), $container->get('app.work.alias'));
        self::assertSame(ServiceLifetime::TRANSIENT, $container->lifetime('app.work.alias'));
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, mixed>                $extraKnobs
     */
    private function booted(array $services, array $extraKnobs = []): Container
    {
        return new ContainerBootstrapper()->bootstrap(
            new Config(['services' => $services, ...$extraKnobs]),
            new Container(),
        );
    }
}
