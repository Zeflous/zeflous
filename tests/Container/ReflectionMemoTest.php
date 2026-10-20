<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\LazyConstructions;
use Zef\Framework\Container\LazyDeferrals;
use Zef\Framework\Container\LazyIdentities;
use Zef\Framework\Container\ReflectionMemo;
use Zef\Test\Support\Container\Greeter;

/**
 * The reflection memo (ROADMAP area 11's reflection caching): the
 * per-resolution introspection the two resolution lanes paid --
 * the class-shape closure's `newInstanceArgs` and the lazy lane's
 * `newLazyProxy` -- answered through one carried reflection per
 * class, pinned here by the census the memo itself exposes.
 *
 * @internal
 */
#[CoversClass(ReflectionMemo::class)]
#[CoversClass(DefinitionCompiler::class)]
#[CoversClass(LazyDeferrals::class)]
#[CoversClass(LazyConstructions::class)]
#[CoversClass(LazyIdentities::class)]
final class ReflectionMemoTest extends TestCase
{
    public function testTheSameClassAnswersTheSameCarriedReflection(): void
    {
        $reflectionMemo = new ReflectionMemo();

        $first = $reflectionMemo->reflect(Greeter::class);
        $second = $reflectionMemo->reflect(Greeter::class);

        self::assertSame($first, $second);
        self::assertSame(1, $reflectionMemo->count());
    }

    public function testDistinctClassesAreCarriedDistinctly(): void
    {
        $reflectionMemo = new ReflectionMemo();

        $reflectionMemo->reflect(Greeter::class);
        $reflectionMemo->reflect(Container::class);
        $reflectionMemo->reflect(Greeter::class);

        self::assertSame(2, $reflectionMemo->count());
    }

    public function testAnUnknownClassPropagatesReflectionsOwnRefusal(): void
    {
        $reflectionMemo = new ReflectionMemo();

        try {
            $reflectionMemo->reflect('Zef\Test\Support\Container\DoesNotExistAnywhere');
            self::fail('Expected a ReflectionException to be thrown.');
        } catch (ReflectionException $reflectionException) {
            self::assertStringContainsString('DoesNotExistAnywhere', $reflectionException->getMessage());
        }

        self::assertSame(0, $reflectionMemo->count());
    }

    /**
     * The class-shape closure's own payoff: a transient resolved twice
     * constructs two instances through ONE carried reflection -- the
     * second `get()` pays the map lookup, not a fresh reflection.
     */
    public function testATransientClassShapeResolvesTwiceThroughOneReflection(): void
    {
        $reflectionMemo = new ReflectionMemo();
        $definitionCompiler = new DefinitionCompiler(reflectionMemo: $reflectionMemo);

        $factory = $definitionCompiler->compile(
            new InstanceDefinition('app.greeter', Greeter::class, ['Hello', 2]),
        );

        $container = new Container();

        $first = $factory($container);
        $second = $factory($container);

        self::assertInstanceOf(Greeter::class, $first);
        self::assertInstanceOf(Greeter::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(1, $reflectionMemo->count());
    }

    public function testTwoClassShapesCarryTheirTwoReflections(): void
    {
        $reflectionMemo = new ReflectionMemo();
        $definitionCompiler = new DefinitionCompiler(reflectionMemo: $reflectionMemo);

        $greeter = $definitionCompiler->compile(
            new InstanceDefinition('app.greeter', Greeter::class, ['Hello', 1]),
        );
        $containerShape = $definitionCompiler->compile(
            new InstanceDefinition('app.container', Container::class, []),
        );

        $greeter(new Container());
        self::assertSame(1, $reflectionMemo->count());

        $containerShape(new Container());
        self::assertSame(2, $reflectionMemo->count());
    }

    /**
     * The compile alone carries nothing: the reflection is a
     * *resolution* cost, never a compile cost -- the closure defers
     * it to the first (and, through the memo, the only) construction.
     */
    public function testCompileTimeAloneCarriesNoReflection(): void
    {
        $reflectionMemo = new ReflectionMemo();
        $definitionCompiler = new DefinitionCompiler(reflectionMemo: $reflectionMemo);

        $definitionCompiler->compile(
            new InstanceDefinition('app.greeter', Greeter::class, ['Hello', 1]),
        );
        $definitionCompiler->compile(
            new InstanceDefinition('app.container', Container::class, []),
        );

        self::assertSame(0, $reflectionMemo->count());
    }

    /**
     * The lazy lane's own payoff: a transient deferral answered twice
     * builds two distinct native proxies through ONE carried
     * reflection -- without the memo, every proxy construction made
     * its own `ReflectionClass` for the very same class.
     */
    public function testATransientDeferralAnswersTwiceThroughOneReflection(): void
    {
        $reflectionMemo = new ReflectionMemo();
        $lazyIdentities = new LazyIdentities();
        $lazyIdentities->mark('app.greeter', Greeter::class);

        $lazyDeferrals = new LazyDeferrals(
            lazyIdentities: $lazyIdentities,
            reflectionMemo: $reflectionMemo,
        );

        $guarded = static fn (string $id, Container $container): Greeter => new Greeter('Hello', 2);

        $container = new Container();

        $first = $lazyDeferrals->answering('app.greeter', $container, $guarded);
        $second = $lazyDeferrals->answering('app.greeter', $container, $guarded);

        self::assertInstanceOf(Greeter::class, $first);
        self::assertInstanceOf(Greeter::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(1, $reflectionMemo->count());
    }

    public function testTheUnmarkedIdentifierAnswersTheGuardedCallItself(): void
    {
        $reflectionMemo = new ReflectionMemo();
        $lazyDeferrals = new LazyDeferrals(reflectionMemo: $reflectionMemo);

        $guarded = static fn (string $id, Container $container): Greeter => new Greeter('Hello', 1);

        $answer = $lazyDeferrals->answering('app.greeter', new Container(), $guarded);

        self::assertInstanceOf(Greeter::class, $answer);
        self::assertSame(0, $reflectionMemo->count());
    }

    public function testTheProgrammaticSpellingRidesTheCallersOwnMemo(): void
    {
        $reflectionMemo = new ReflectionMemo();

        $proxy = LazyConstructions::of(
            $reflectionMemo,
            Greeter::class,
            static fn (): Greeter => new Greeter('Hello', 3),
        );

        self::assertInstanceOf(Greeter::class, $proxy);
        self::assertSame(1, $reflectionMemo->count());

        LazyConstructions::of(
            $reflectionMemo,
            Greeter::class,
            static fn (): Greeter => new Greeter('Hello', 4),
        );

        self::assertSame(1, $reflectionMemo->count());
    }
}
