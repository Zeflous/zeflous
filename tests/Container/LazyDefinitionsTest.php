<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\AliasShape;
use Zef\Framework\Container\Autowiring\AutowireRequestPlanning;
use Zef\Framework\Container\ClassSpellings;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\FactoryShape;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\InstanceShape;
use Zef\Framework\Container\LazyClasses;
use Zef\Framework\Container\LazyDeclarations;
use Zef\Framework\Container\LazyKey;
use Zef\Framework\Container\LazyRefusals;
use Zef\Framework\Container\LazyReturns;
use Zef\Framework\Container\LazyReturnTypes;
use Zef\Framework\Container\LazyTargets;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;
use Zef\Framework\Container\ValueDefinition;
use Zef\Framework\Container\ValueShape;
use Zef\Test\Support\Container\Lazy\EngineBlueprint;
use Zef\Test\Support\Container\Lazy\EngineContract;
use Zef\Test\Support\Container\Lazy\EngineFactory;
use Zef\Test\Support\Container\Lazy\EngineMode;
use Zef\Test\Support\Container\Lazy\HeavyEngine;
use Zef\Test\Support\Container\Lazy\LazyReturnShapes;
use Zef\Test\Support\Container\Lazy\ReadonlyEngine;

/**
 * The lazy lane's declaration grammar: the `lazy` key over the two
 * constructing shapes -- its derivation (the class shape's own class,
 * the factory shape's declared return class), and every spelling the
 * grammar refuses with the way out in the message (ROADMAP area 7's
 * proxy half).
 *
 * @internal
 */
#[CoversClass(ClassSpellings::class)]
#[CoversClass(LazyKey::class)]
#[CoversClass(LazyClasses::class)]
#[CoversClass(LazyDeclarations::class)]
#[CoversClass(LazyReturns::class)]
#[CoversClass(LazyReturnTypes::class)]
#[CoversClass(LazyTargets::class)]
#[CoversClass(LazyRefusals::class)]
#[CoversClass(InstanceShape::class)]
#[CoversClass(FactoryShape::class)]
#[CoversClass(AliasShape::class)]
#[CoversClass(ValueShape::class)]
#[CoversClass(InstanceDefinition::class)]
#[CoversClass(ServiceDefinition::class)]
#[CoversClass(UnplannedInstanceDefinition::class)]
#[CoversClass(AutowireRequestPlanning::class)]
final class LazyDefinitionsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        HeavyEngine::reset();
    }

    public function testTheClassShapeParsesTheLazyKeyBehindItsOwnClass(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'arguments' => [['service' => 'app.fuel']],
            'lazy' => true,
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(HeavyEngine::class, $definition->lazyClass);
    }

    public function testAnExplicitlyFalseLazyKeyIsTheEagerSpelling(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'lazy' => false,
        ]);

        self::assertNull($definition->lazyClass);
    }

    public function testTheAbsentLazyKeyIsTheEagerSpellingEveryDeclarationAlreadyHad(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
        ]);

        self::assertNull($definition->lazyClass);
    }

    public function testANonBooleanLazyKeyIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.engine', [
                'class' => HeavyEngine::class,
                'lazy' => 'yes',
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' lazy must be a boolean, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testARequestLifetimeCannotDefer(): void
    {
        try {
            ServiceDefinition::fromArray('app.engine', [
                'class' => HeavyEngine::class,
                'lifetime' => 'request',
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy with a request lifetime: a deferred materialisation "
                . 'cannot promise the scope it will run in (declare singleton or transient, '
                . 'or drop the lazy key).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testARequestLifetimeCannotDeferBehindAFactory(): void
    {
        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => EngineFactory::class . '::make',
                'arguments' => [['service' => 'app.fuel']],
                'lifetime' => 'request',
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy with a request lifetime: a deferred materialisation "
                . 'cannot promise the scope it will run in (declare singleton or transient, '
                . 'or drop the lazy key).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAbstractClassCannotCarryAProxy(): void
    {
        try {
            ServiceDefinition::fromArray('app.engine', [
                'class' => EngineBlueprint::class,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the class '" . EngineBlueprint::class . "': "
                . 'a native lazy proxy is an instance of the class itself, and an abstract class, '
                . 'an interface or an enum has none (declare a concrete class).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEnumCannotCarryAProxy(): void
    {
        try {
            ServiceDefinition::fromArray('app.mode', [
                'class' => EngineMode::class,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.mode' cannot be lazy behind the class '" . EngineMode::class . "': "
                . 'a native lazy proxy is an instance of the class itself, and an abstract class, '
                . 'an interface or an enum has none (declare a concrete class).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAReadonlyClassCarriesAProxyAsHonestlyAsAPlainOne(): void
    {
        $definition = ServiceDefinition::fromArray('app.readonly', [
            'class' => ReadonlyEngine::class,
            'arguments' => [5],
            'lazy' => true,
        ]);

        self::assertSame(ReadonlyEngine::class, $definition->lazyClass);
    }

    public function testTheNameKeyedFormCarriesTheRequestToThePlannedDeclaration(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'arguments' => ['fuel' => ['service' => 'app.fuel']],
            'lazy' => true,
        ]);

        self::assertSame(HeavyEngine::class, $definition->lazyClass);
    }

    public function testTheAutowireFormCarriesTheRequestToThePlannedDeclaration(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'autowire' => true,
            'lazy' => true,
        ]);

        self::assertSame(HeavyEngine::class, $definition->lazyClass);
    }

    public function testACallListRidesTheDeferredMaterialisationUnchanged(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'arguments' => [['service' => 'app.fuel']],
            'calls' => [['method' => 'thrust']],
            'lazy' => true,
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertNotSame([], $definition->calls);
        self::assertSame(HeavyEngine::class, $definition->lazyClass);
    }

    public function testTheFactoryShapeDerivesItsProxyClassFromTheDeclaredReturn(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'factory' => EngineFactory::class . '::make',
            'arguments' => [['service' => 'app.fuel']],
            'lazy' => true,
        ]);

        self::assertInstanceOf(FactoryDefinition::class, $definition);
        self::assertSame(HeavyEngine::class, $definition->lazyClass);
    }

    public function testTheAbsentReturnDeclarationRefuses(): void
    {
        $factory = LazyReturnShapes::class . '::none';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory declares "
                . 'no return type, so the declaration cannot name the class its proxy defers behind '
                . '(declare the product class as the return type).',
                $definitionException->getMessage(),
            );
        }
    }

    #[DataProvider('provideTheUnnameableReturnTypesRefuseCases')]
    public function testTheUnnameableReturnTypesRefuse(string $method, string $what): void
    {
        $factory = LazyReturnShapes::class . '::' . $method;

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory's return "
                . "type is {$what}, and a native lazy proxy needs exactly one declared class "
                . '(declare the product class as the return type).',
                $definitionException->getMessage(),
            );
        }
    }

    public static function provideTheUnnameableReturnTypesRefuseCases(): iterable
    {
        yield 'nullable return' => ['nullable', 'a nullable type'];

        yield 'union return' => ['union', 'a union or intersection type'];

        yield 'builtin return' => ['builtin', 'a builtin type'];
    }

    public function testTheSelfSpellingRefuses(): void
    {
        $factory = LazyReturnShapes::class . '::selfed';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory's return "
                . "type is spelled 'self', which reflection does not resolve to a class name "
                . '(declare the product class itself as the return type).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheStaticSpellingRefuses(): void
    {
        $factory = LazyReturnShapes::class . '::statted';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory's return "
                . "type is spelled 'static', which reflection does not resolve to a class name "
                . '(declare the product class itself as the return type).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownReturnClassRefuses(): void
    {
        $factory = LazyReturnShapes::class . '::ghosted';

        try {
            LazyReturns::classOf('app.engine', $factory);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the declared return "
                . "class 'Zef\\Test\\Support\\Container\\Lazy\\GhostEngine' does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAServiceIdentifierTargetCannotNameItsProduct(): void
    {
        $factory = 'app.factory_service::make';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory target "
                . "'app.factory_service' is not a class the runtime carries (a lazy factory names its product "
                . "at the parse, and the service-instance form's product class is not nameable there — "
                . 'spell the factory as Class::method).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUndeclaredFactoryMethodRefuses(): void
    {
        $factory = EngineFactory::class . '::nowhere';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the factory '{$factory}': the factory target "
                . "'" . EngineFactory::class . "' declares no method 'nowhere' (the lazy grammar reflects the factory "
                . 'at the parse — the eager spelling takes this refusal at the first resolution).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnInterfaceReturnClassCannotCarryAProxy(): void
    {
        $factory = LazyReturnShapes::class . '::interfaced';

        try {
            ServiceDefinition::fromArray('app.engine', [
                'factory' => $factory,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.engine' cannot be lazy behind the class '"
                . EngineContract::class . "': a native lazy proxy "
                . 'is an instance of the class itself, and an abstract class, an interface or an '
                . 'enum has none (declare a concrete class).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheAliasShapeRefusesTheLazyKey(): void
    {
        try {
            ServiceDefinition::fromArray('app.alias', [
                'alias' => 'app.engine',
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.alias' carries 'lazy' on a 'alias' definition "
                . '(an alias is transparent: it rides the laziness of the definition it points to).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheValueShapeRefusesTheLazyKey(): void
    {
        try {
            ServiceDefinition::fromArray('app.answer', [
                'value' => 42,
                'lazy' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.answer' carries 'lazy' on a 'value' definition "
                . '(a value is a constant: it is held, never built, and there is nothing to defer).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheShapesThatBuildNothingRefuseTheWithLazyClassRebuild(): void
    {
        // The base's own guard, reached the only way it can be: a
        // hand-built definition of a shape that builds nothing. The
        // grammar's own parsers refuse the `lazy` key earlier still,
        // so this refusal is the unreachable guard against hand-built
        // misuse it nevertheless keeps.
        $valueDefinition = new ValueDefinition('app.answer', 42);

        try {
            $valueDefinition->withLazyClass(HeavyEngine::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'app.answer' definition shape cannot be lazy "
                . "(the 'lazy' key belongs to class and factory definitions).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheWithRebuildsCarryTheLazyClass(): void
    {
        $definition = ServiceDefinition::fromArray('app.engine', [
            'class' => HeavyEngine::class,
            'tags' => ['engine.base', 'engine.core'],
            'lazy' => true,
        ]);
        self::assertInstanceOf(InstanceDefinition::class, $definition);

        $withCalls = $definition->withMethodCalls([]);
        self::assertSame(HeavyEngine::class, $withCalls->lazyClass);

        // The tag merge keeps both sides whole: every declaration label
        // survives the add, and every added label lands.
        $withTags = $withCalls->withAddedTags(['engine.tag', 'engine.extra']);
        self::assertSame(HeavyEngine::class, $withTags->lazyClass);
        self::assertSame(
            ['engine.base', 'engine.core', 'engine.tag', 'engine.extra'],
            $withTags->tags->all(),
        );

        $rewritten = $withTags->withRewrittenReferences('app.fuel', '@contextual:x|app.fuel');
        self::assertSame(HeavyEngine::class, $rewritten->lazyClass);

        $eager = $definition->withLazyClass(null);
        self::assertNull($eager->lazyClass);
        self::assertSame(HeavyEngine::class, $definition->lazyClass, 'the rebuild answers a new value object');
    }
}
