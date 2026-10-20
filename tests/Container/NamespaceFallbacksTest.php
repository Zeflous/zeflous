<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\CircularDependencyException;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContainerAnswers;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FallbackConstructions;
use Zef\Framework\Container\InterceptorsMap;
use Zef\Framework\Container\ListenersMap;
use Zef\Framework\Container\NamespaceBuiltRefusals;
use Zef\Framework\Container\NamespaceCoverings;
use Zef\Framework\Container\NamespaceFallbackGrammar;
use Zef\Framework\Container\NamespaceFallbacks;
use Zef\Framework\Container\NotFoundException;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Test\Support\Container\Interception\InterceptionLog;
use Zef\Test\Support\Container\Interception\RecordingInterceptor;
use Zef\Test\Support\Container\Interception\RecordingResolvingListener;
use Zef\Test\Support\Container\Namespaces\FallbackFactories;
use Zef\Test\Support\Container\Namespaces\FreshProduct;
use Zef\Test\Support\Container\Namespaces\NamespacedService;

/**
 * The namespace fallback registry's behaviour: the longest-prefix
 * coverage, the sharing policies, the budget, and the guarded,
 * intercepted construction every fallback rides (ROADMAP area 9's
 * namespace half -- definitions on demand).
 *
 * @internal
 */
#[CoversClass(NamespaceFallbacks::class)]
#[CoversClass(NamespaceCoverings::class)]
#[CoversClass(NamespaceFallbackGrammar::class)]
#[CoversClass(FallbackConstructions::class)]
#[CoversClass(ContainerAnswers::class)]
#[CoversClass(NamespaceBuiltRefusals::class)]
final class NamespaceFallbacksTest extends TestCase
{
    public function testTheLongestCoveringPrefixWins(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('App\\', FallbackFactories::withLabel('outer'));
        $container->namespaceFallbacks()->fallback('App\Specific\\', FallbackFactories::withLabel('specific'));

        self::assertSame('specific', $this->labelled($container, 'App\Specific\Thing'));
        self::assertSame('outer', $this->labelled($container, 'App\Other\Thing'));
    }

    public function testTheLongestPrefixIsMeasuredInCharactersNotBytes(): void
    {
        // The nested multibyte pair whose byte and character lengths
        // disagree about which is longer: 'ééé.a.' is six characters
        // (ten bytes) and 'ééé.' is four characters (seven bytes), so a
        // byte-measured fold would let the SHALLOW prefix steal the
        // deep prefix's coverage when it registers after it.
        $container = new Container();
        $container->namespaceFallbacks()->fallback('ééé.a\\', FallbackFactories::withLabel('deep'));
        $container->namespaceFallbacks()->fallback('ééé\\', FallbackFactories::withLabel('shallow'));

        self::assertSame('deep', $this->labelled($container, 'ééé.a.thing'));
    }

    public function testBothSpellingsOfOneNamespaceAreCovered(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('App\Ctrl\\', FallbackFactories::withLabel('covered'));

        self::assertTrue($container->has('App\Ctrl\User'));
        self::assertTrue($container->has('App.Ctrl.User'));
        self::assertSame('covered', $this->labelled($container, 'App.Ctrl.User'));
    }

    public function testTheSubtreeRootItselfIsCovered(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('App\Ctrl\\', FallbackFactories::withLabel('root'));

        self::assertTrue($container->has('App\Ctrl'));
        self::assertSame('root', $this->labelled($container, 'App\Ctrl'));
    }

    public function testASingletonFallbackMemoisesPerIdentifier(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'App\\',
            static fn (Container $container, string $id): object => new FreshProduct(),
        );

        $first = $container->get('App\One');
        $second = $container->get('App\One');

        self::assertSame($first, $second);
        self::assertNotSame($first, $container->get('App\Two'));
    }

    public function testATransientFallbackBuildsEveryResolution(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'App\\',
            static fn (Container $container, string $id): object => new FreshProduct(),
            ServiceLifetime::TRANSIENT,
        );

        self::assertNotSame($container->get('App\One'), $container->get('App\One'));
    }

    public function testARequestLifetimeFallbackIsRefused(): void
    {
        $container = new Container();

        try {
            $container->namespaceFallbacks()->fallback(
                'App\\',
                static fn (Container $container, string $id): object => new FreshProduct(),
                ServiceLifetime::REQUEST,
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The namespace fallback for 'App\\' cannot be request-scoped -- fallback "
                . 'identifiers are not scope members.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheRootOnlyPrefixIsRefused(): void
    {
        $container = new Container();

        try {
            $container->namespaceFallbacks()->fallback(
                '\\',
                static fn (Container $container, string $id): object => new FreshProduct(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The namespace fallback prefix '\\' governs no namespace -- a bare "
                . 'separator would rebind every miss, not a namespace of them.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheBudgetRefusesTheSixtyFifthPrefix(): void
    {
        $container = new Container();

        for ($i = 0; $i < 64; ++$i) {
            $container->namespaceFallbacks()->fallback(
                'p' . $i . '.',
                static fn (Container $container, string $id): object => new FreshProduct(),
            );
        }

        try {
            $container->namespaceFallbacks()->fallback(
                'p64.',
                static fn (Container $container, string $id): object => new FreshProduct(),
            );
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The namespace fallback registry declares more than 64 prefixes '
                . "-- the lane's hard bound.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testReRegisteringWithinTheBudgetReplacesWithoutASecondSlot(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('p0.', FallbackFactories::withLabel('first'));

        for ($i = 1; $i < 64; ++$i) {
            $container->namespaceFallbacks()->fallback(
                'p' . $i . '.',
                static fn (Container $container, string $id): object => new FreshProduct(),
            );
        }

        // The re-registration replaces p0's factory without consuming a
        // slot: the registry still holds exactly 64 prefixes.
        $container->namespaceFallbacks()->fallback('p0.', FallbackFactories::withLabel('replaced'));

        self::assertSame('replaced', $this->labelled($container, 'p0.thing'));
    }

    public function testAReplacedFactoryForgetsTheMemoisedAnswers(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('App\\', FallbackFactories::withLabel('first'));

        $before = $container->get('App\Thing');

        $container->namespaceFallbacks()->fallback('App\\', FallbackFactories::withLabel('second'));

        $after = $container->get('App\Thing');

        self::assertNotSame($before, $after);
        self::assertSame('second', $this->labelled($container, 'App\Thing'));
    }

    public function testTheUncoveredMissIsTheHonestNotFound(): void
    {
        $container = new Container();

        try {
            $container->get('nothing.here');
            self::fail('Expected a NotFoundException to be thrown.');
        } catch (NotFoundException $notFoundException) {
            self::assertSame(
                'Service "nothing.here" is not defined.',
                $notFoundException->getMessage(),
            );
        }

        self::assertFalse($container->has('nothing.here'));
    }

    public function testANullAnswerIsALegalInstance(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'App\\',
            static fn (Container $container, string $id): ?object => null,
        );

        self::assertTrue($container->has('App\Nullish'));
        self::assertNull($container->get('App\Nullish'));
        self::assertNull($container->get('App\Nullish'));
    }

    public function testAFactoryReEnteringItsOwnIdentifierIsTheCycleRefusal(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback(
            'App\\',
            static fn (Container $container, string $id): mixed => $container->get($id),
        );

        try {
            $container->get('App\Loop');
            self::fail('Expected a CircularDependencyException to be thrown.');
        } catch (CircularDependencyException $circularDependencyException) {
            self::assertSame(
                "Service 'App\\Loop' is part of a circular reference chain: App\\Loop -> App\\Loop.",
                $circularDependencyException->getMessage(),
            );
        }
    }

    public function testTheFallbackConstructionRidesTheArmedInterception(): void
    {
        $container = new Container();
        $container->set('test.log', static fn (): InterceptionLog => new InterceptionLog());
        $container->interceptions()->arm(
            InterceptorsMap::parse('interceptors', [
                ['class' => RecordingInterceptor::class, 'arguments' => ['fb-link', ['service' => 'test.log']]],
            ]),
            ListenersMap::parse('listeners', [
                'resolving' => [
                    ['class' => RecordingResolvingListener::class, 'arguments' => [['service' => 'test.log']]],
                ],
            ]),
            $container,
        );
        $container->namespaceFallbacks()->fallback('App\\', FallbackFactories::forIdentifier(...));

        self::assertSame('App\Intercepted', $this->labelled($container, 'App\Intercepted'));

        $log = $container->get('test.log');
        self::assertInstanceOf(InterceptionLog::class, $log);
        self::assertSame(
            [
                'resolving: App\Intercepted',
                'fb-link > App\Intercepted',
                'fb-link < App\Intercepted',
            ],
            $log->lines(),
        );
    }

    public function testARegisteredIdentifierNeverTouchesTheFallbacks(): void
    {
        $container = new Container();
        $container->namespaceFallbacks()->fallback('shared\\', FallbackFactories::withLabel('fallback'));
        $container->set(
            'shared.declared',
            static fn (): NamespacedService => new NamespacedService('declared'),
        );

        self::assertSame('declared', $this->labelled($container, 'shared.declared'));
        self::assertSame('fallback', $this->labelled($container, 'shared.other'));
    }

    /**
     * The identifier's resolved label, typed for the narrowing the
     * assertions read.
     */
    private function labelled(Container $container, string $id): string
    {
        $service = $container->get($id);
        self::assertInstanceOf(NamespacedService::class, $service);

        return $service->label;
    }
}
