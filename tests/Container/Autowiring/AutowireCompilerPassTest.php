<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireAttributes;
use Zef\Framework\Container\Autowiring\AutowireBirther;
use Zef\Framework\Container\Autowiring\AutowireBirths;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;
use Zef\Framework\Container\Autowiring\AutowireDeclaredArguments;
use Zef\Framework\Container\Autowiring\AutowireDeclaredStretch;
use Zef\Framework\Container\Autowiring\AutowireFactoryProduct;
use Zef\Framework\Container\Autowiring\AutowireImplementations;
use Zef\Framework\Container\Autowiring\AutowireParameterPlanner;
use Zef\Framework\Container\Autowiring\AutowireParameterSpec;
use Zef\Framework\Container\Autowiring\AutowireParameterTypes;
use Zef\Framework\Container\Autowiring\AutowirePlanning;
use Zef\Framework\Container\Autowiring\AutowirePositionalCheck;
use Zef\Framework\Container\Autowiring\AutowireReferenceRefusals;
use Zef\Framework\Container\Autowiring\AutowireReferences;
use Zef\Framework\Container\Autowiring\AutowireRefusals;
use Zef\Framework\Container\Autowiring\AutowireRequestPlanning;
use Zef\Framework\Container\Autowiring\AutowireResult;
use Zef\Framework\Container\Autowiring\AutowireReuse;
use Zef\Framework\Container\Autowiring\AutowireSpecFactory;
use Zef\Framework\Container\Autowiring\AutowireStaticReturn;
use Zef\Framework\Container\Autowiring\AutowireTargetBinding;
use Zef\Framework\Container\Autowiring\AutowireValueMaterialisations;
use Zef\Framework\Container\Autowiring\AutowireValueRefusals;
use Zef\Framework\Container\Autowiring\AutowireValueServices;
use Zef\Framework\Container\Autowiring\AutowireVariadicCollection;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorFitRefusals;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\NamedFit;
use Zef\Framework\Container\Autowiring\PositionalFit;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Framework\Container\Autowiring\SyntheticValueGuard;
use Zef\Framework\Container\Autowiring\Value;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ValueDefinition;
use Zef\Test\Support\Container\Autowiring\BirthChainAlpha;
use Zef\Test\Support\Container\Autowiring\BirthChainBeta;
use Zef\Test\Support\Container\Autowiring\BuiltinStaticFactory;
use Zef\Test\Support\Container\Autowiring\ChainRoot;
use Zef\Test\Support\Container\Autowiring\DependentHouse;
use Zef\Test\Support\Container\Autowiring\DoubleDepHouse;
use Zef\Test\Support\Container\Autowiring\EnumDefaultedHouse;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\GreeterFactory;
use Zef\Test\Support\Container\Autowiring\InjectedDefaultedHouse;
use Zef\Test\Support\Container\Autowiring\InjectedDepHouse;
use Zef\Test\Support\Container\Autowiring\InjectedHouse;
use Zef\Test\Support\Container\Autowiring\InstanceMethodFactory;
use Zef\Test\Support\Container\Autowiring\InterfaceHouse;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\LoggerVariadicHouse;
use Zef\Test\Support\Container\Autowiring\NamedWinsHouse;
use Zef\Test\Support\Container\Autowiring\NullableDefaultedHouse;
use Zef\Test\Support\Container\Autowiring\NullableHouse;
use Zef\Test\Support\Container\Autowiring\OptionalUnionHouse;
use Zef\Test\Support\Container\Autowiring\ParameterlessHouse;
use Zef\Test\Support\Container\Autowiring\SimpleDep;
use Zef\Test\Support\Container\Autowiring\SimpleDepVariadicHouse;
use Zef\Test\Support\Container\Autowiring\Status;
use Zef\Test\Support\Container\Autowiring\TargetedHouse;
use Zef\Test\Support\Container\Autowiring\TypedHouse;
use Zef\Test\Support\Container\Autowiring\TypedVariadicHouse;
use Zef\Test\Support\Container\Autowiring\UninstantiableHouse;
use Zef\Test\Support\Container\Autowiring\UnionHouse;
use Zef\Test\Support\Container\Autowiring\UnionStaticFactory;
use Zef\Test\Support\Container\Autowiring\UnionVariadicHouse;
use Zef\Test\Support\Container\Autowiring\VariadicHouse;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Container\Parameters\ClassTypedValueHouse;
use Zef\Test\Support\Container\Parameters\ConnectionHouse;
use Zef\Test\Support\Container\Parameters\DefaultedHouse;
use Zef\Test\Support\Container\Parameters\DoubledIntentHouse;
use Zef\Test\Support\Container\Parameters\DoubledValueHouse;
use Zef\Test\Support\Container\Parameters\PoolHouse;
use Zef\Test\Support\Container\Parameters\SharedFlagAudit;
use Zef\Test\Support\Container\Parameters\SharedFlagHouse;
use Zef\Test\Support\Container\Parameters\SpelledHouse;
use Zef\Test\Support\Container\Parameters\UnionValueHouse;
use Zef\Test\Support\Container\Parameters\VariadicValueHouse;

/**
 * @internal
 */
#[CoversClass(AutowireCompilerPass::class)]
#[CoversClass(AutowireParameterPlanner::class)]
#[CoversClass(AutowireResult::class)]
#[CoversClass(AutowireAttributes::class)]
#[CoversClass(AutowireValueServices::class)]
#[CoversClass(SyntheticValueGuard::class)]
#[CoversClass(AutowireValueMaterialisations::class)]
#[CoversClass(AutowireValueRefusals::class)]
#[CoversClass(Value::class)]
#[CoversClass(ParameterValues::class)]
#[CoversClass(AutowireBirther::class)]
#[CoversClass(AutowireBirths::class)]
#[CoversClass(AutowireFactoryProduct::class)]
#[CoversClass(AutowireImplementations::class)]
#[CoversClass(AutowireStaticReturn::class)]
#[CoversClass(AutowireTargetBinding::class)]
#[CoversClass(AutowireVariadicCollection::class)]
#[CoversClass(AutowireDeclaredArguments::class)]
#[CoversClass(AutowireDeclaredStretch::class)]
#[CoversClass(AutowireParameterTypes::class)]
#[CoversClass(AutowirePlanning::class)]
#[CoversClass(AutowirePositionalCheck::class)]
#[CoversClass(AutowireReferenceRefusals::class)]
#[CoversClass(AutowireReferences::class)]
#[CoversClass(AutowireRefusals::class)]
#[CoversClass(AutowireRequestPlanning::class)]
#[CoversClass(AutowireReuse::class)]
#[CoversClass(AutowireSpecFactory::class)]
#[CoversClass(ConstructorFitRefusals::class)]
#[CoversClass(NamedFit::class)]
#[CoversClass(PositionalFit::class)]
#[CoversClass(ConstructorSpecs::class)]
final class AutowireCompilerPassTest extends TestCase
{
    private AutowireCompilerPass $autowireCompilerPass;

    #[Override]
    protected function setUp(): void
    {
        $this->autowireCompilerPass = new AutowireCompilerPass();
    }

    public function testTheHybridFormSplicesNamedArgumentsDefaultsAndReferences(): void
    {
        $planned = $this->planned([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['salutation' => 'Hello']],
        ]);

        self::assertSame(['Hello', 1], $this->firstInstance($planned)->arguments);
    }

    public function testTheNamedArgumentMayCarryAServiceReference(): void
    {
        $container = new Container();
        $container->set('app.salutation', static fn (): string => 'Hola');

        $planned = $this->planned([
            'app.greeter' => [
                'class' => Greeter::class,
                'arguments' => ['salutation' => ['service' => 'app.salutation']],
            ],
        ], $container);

        self::assertSame([['service' => 'app.salutation'], 1], $this->firstInstance($planned)->arguments);
    }

    public function testTheNameKeyedDeclarationOutranksTheAttributeOnTheSameParameter(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => NamedWinsHouse::class, 'arguments' => ['clock' => 'tokyo']],
        ]);

        self::assertSame(['tokyo', 'default'], $this->firstInstance($planned)->arguments);
    }

    public function testTheAutowireFormPlansTheWholeConstructor(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => DependentHouse::class, 'autowire' => true],
        ]);

        self::assertSame([['service' => SimpleDep::class], 'lbl'], $this->firstInstance($planned)->arguments);
        self::assertSame(SimpleDep::class, $this->firstInstance($planned, 1)->id);
        self::assertSame(['simple'], $this->firstInstance($planned, 1)->arguments);
    }

    public function testATypedParameterResolvesThroughTheTypeNamesOwnDeclaration(): void
    {
        $planned = $this->planned([
            Greeter::class => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.house' => ['class' => TypedHouse::class, 'autowire' => true],
        ]);

        self::assertSame([['service' => Greeter::class], 'default'], $this->firstInstance($planned, 1)->arguments);
    }

    public function testATypedParameterResolvesThroughAnAliasOfTheTypeName(): void
    {
        $planned = $this->planned([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            Greeter::class => ['alias' => 'app.greeter'],
            'app.house' => ['class' => TypedHouse::class, 'autowire' => true],
        ]);

        self::assertSame([['service' => Greeter::class], 'default'], $this->firstInstance($planned, 2)->arguments);
    }

    public function testATypedParameterResolvesThroughAContainerRegisteredService(): void
    {
        $container = new Container();
        $container->set(Greeter::class, static fn (): Greeter => new Greeter('Hello'));

        $planned = $this->planned([
            'app.house' => ['class' => TypedHouse::class, 'autowire' => true],
        ], $container);

        self::assertSame([['service' => Greeter::class], 'default'], $this->firstInstance($planned)->arguments);
    }

    public function testAnUnboundInterfaceIsTheHonestRefusalWithEveryWayOut(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => InterfaceHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$logger': no binding for '" . LoggerInterface::class . "'. "
                . 'Register the service, alias the type name to an implementation, bind the parameter '
                . 'with #[Inject] or #[Target], or give the parameter a default value.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testARequiredNullableInterfaceIsNotGivenAGuessedNull(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => NullableHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "no binding for '" . LoggerInterface::class . "'",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADefaultedNullableParameterReachesItsOwnDefaultWhenTheTypeIsUnbound(): void
    {
        // The refusal's way out said "or give the parameter a default
        // value" -- the defaulted nullable took it, and the plan must
        // keep the promise: the constructor's own null, not a refusal
        // that vetoes the very default it advised.
        $planned = $this->planned([
            'app.house' => ['class' => NullableDefaultedHouse::class, 'autowire' => true],
        ]);

        self::assertSame([null], $this->firstInstance($planned)->arguments);
    }

    public function testADefaultedNullableParameterStillResolvesABoundType(): void
    {
        // The default is the fallback, never the override: a binding
        // the declared set answers still wins the tier.
        $planned = $this->planned([
            'app.logger' => ['class' => FileLogger::class, 'arguments' => ['/tmp/app.log']],
            LoggerInterface::class => ['alias' => 'app.logger'],
            'app.house' => ['class' => NullableDefaultedHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => LoggerInterface::class]],
            $this->firstInstance($planned, 2)->arguments,
        );
    }

    public function testADefaultedParameterWithAnExplicitInjectKeepsTheRefusal(): void
    {
        // Explicit wins over auto: an #[Inject] that names nothing is a
        // declaration defect, and the constructor's default must not
        // mask it.
        try {
            $this->planned([
                'app.house' => ['class' => InjectedDefaultedHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "no binding for 'app.missing'",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnEnumCaseDefaultIsBakedIntoThePlan(): void
    {
        // An enum type names no birthable binding, so the constructor's
        // own case owns the plan -- verbatim, the singleton the AOT lane
        // then spells as its constant expression.
        $planned = $this->planned([
            'app.house' => ['class' => EnumDefaultedHouse::class, 'autowire' => true],
        ]);

        self::assertSame([Status::ACTIVE], $this->firstInstance($planned)->arguments);
    }

    public function testAnOptionalUnionParameterBakesItsOwnDefault(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => OptionalUnionHouse::class, 'autowire' => true],
        ]);

        self::assertSame([7], $this->firstInstance($planned)->arguments);
    }

    public function testARequiredUnionParameterIsRefusedWithItsReason(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => UnionHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$id': a union type names no single binding. "
                . 'Name the argument, bind it with #[Inject] or #[Target], or give it a default value.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testARequiredScalarWithoutADefaultIsRefusedWithTheWayOut(): void
    {
        try {
            $this->planned([
                'app.greeter' => ['class' => Greeter::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.greeter' parameter '\$salutation': the parameter requires a value "
                . 'the container cannot derive -- name the argument, bind it with #[Inject] or #[Target], '
                . 'or give it a default value.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnUnplannedVariadicOmitsToTheEmptyCollection(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => VariadicHouse::class, 'autowire' => true],
            'app.typed' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned)->arguments);
        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testAVariadicNamedArgumentSpreadsItsEntries(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => VariadicHouse::class, 'arguments' => ['notes' => ['a', 'b']]],
        ]);

        self::assertSame(['a', 'b'], $this->firstInstance($planned)->arguments);
    }

    public function testAVariadicNamedArgumentMustBeTheListToSpread(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => VariadicHouse::class, 'arguments' => ['notes' => 'a']],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$notes': a variadic parameter's "
                . 'argument must be the list of entries to spread, string given.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTheInjectAttributeResolvesThroughTheDeclaredSet(): void
    {
        $planned = $this->planned([
            'app.clock' => ['value' => 'tokyo'],
            'app.house' => ['class' => InjectedHouse::class, 'autowire' => true],
        ]);

        self::assertSame([['service' => 'app.clock']], $this->firstInstance($planned, 1)->arguments);
    }

    public function testTheInjectAttributeResolvesThroughAContainerRegisteredService(): void
    {
        $container = new Container();
        $container->set('app.clock', static fn (): string => 'utc');

        $planned = $this->planned([
            'app.house' => ['class' => InjectedHouse::class, 'autowire' => true],
        ], $container);

        self::assertSame([['service' => 'app.clock']], $this->firstInstance($planned)->arguments);
    }

    public function testTheInjectAttributeNamingNothingIsRefused(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => InjectedHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$clock': no binding for 'app.clock'.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTheInjectAttributeNamingAnInstantiableClassBirthsIt(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => InjectedDepHouse::class, 'autowire' => true],
        ]), new Container());

        $planned = $result->definitions[0];
        self::assertInstanceOf(InstanceDefinition::class, $planned);
        self::assertSame([['service' => SimpleDep::class]], $planned->arguments);
        self::assertSame([SimpleDep::class], $result->generated);
    }

    public function testTheTargetAttributeBindsAndBirthsTheConcreteClass(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => TargetedHouse::class, 'autowire' => true],
        ]), new Container());

        $house = $result->definitions[0];
        $birth = $result->definitions[1];
        self::assertInstanceOf(InstanceDefinition::class, $house);
        self::assertInstanceOf(InstanceDefinition::class, $birth);
        self::assertSame([['service' => FileLogger::class]], $house->arguments);
        self::assertSame([FileLogger::class], $result->generated);
        self::assertSame(['default.log'], $birth->arguments);
    }

    public function testTheTargetAttributeRefusesANonInstantiableBinding(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            targetClass: LoggerInterface::class,
        );
        $autowireReferences = new AutowireReferences(
            new ReflectionMetadataExtractor(),
            new AutowireBirths(),
            new AutowireValueServices(),
        );
        $autowireReferences->open([], new Container());

        try {
            $autowireReferences->resolve('app.house', $autowireParameterSpec);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$logger': #[Target("
                    . LoggerInterface::class . '::class)] is not an instantiable class.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testOneBirthServesEveryParameterNamingTheClass(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => DoubleDepHouse::class, 'autowire' => true],
        ]), new Container());

        $house = $result->definitions[0];
        self::assertInstanceOf(InstanceDefinition::class, $house);
        self::assertSame(
            [['service' => SimpleDep::class], ['service' => SimpleDep::class]],
            $house->arguments,
        );
        self::assertSame([SimpleDep::class], $result->generated);
        self::assertCount(2, $result->definitions);
    }

    public function testABirthChainThatClosesOntoItselfIsRefusedWithTheWalkedChain(): void
    {
        try {
            $this->planned([
                'app.root' => ['class' => ChainRoot::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                'The birth chain is circular: ' . BirthChainAlpha::class . ' -> '
                . BirthChainBeta::class . ' -> ' . BirthChainAlpha::class . '.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testThePositionalPlanStaysVerbatim(): void
    {
        $planned = $this->planned([
            'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Bonjour', 3]],
        ]);

        self::assertSame(['Bonjour', 3], $this->firstInstance($planned)->arguments);
    }

    public function testAPositionalPlanLongerThanTheConstructorIsRefused(): void
    {
        try {
            $this->planned([
                'app.greeter' => ['class' => Greeter::class, 'arguments' => ['Bonjour', 3, 'stray']],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot construct 'app.greeter': the definition declares 3 arguments but "
                . Greeter::class . '::__construct accepts only 2.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAPositionalPlanShorterThanTheRequiredPrefixIsRefused(): void
    {
        try {
            $this->planned([
                'app.greeter' => ['class' => Greeter::class],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot construct 'app.greeter': the declared arguments stop before the required "
                . "parameter '\$salutation' of " . Greeter::class . '::__construct -- declare the argument, '
                . "or plan the constructor (the name-keyed or 'autowire' forms).",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAPositionalPlanOverAParameterlessConstructorIsRefusedWhenNonEmpty(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => ParameterlessHouse::class, 'arguments' => ['stray']],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot construct 'app.house': the definition declares 1 arguments but "
                . ParameterlessHouse::class . '::__construct accepts only 0.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testANonInstantiableClassShapeIsRefusedAtBoot(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => UninstantiableHouse::class, 'arguments' => []],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot construct the service for '" . UninstantiableHouse::class . "': the class is not instantiable "
                . '(an interface, abstract class, enum, or private constructor).',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testANameKeyedArgumentNamingNoParameterIsRefused(): void
    {
        try {
            $this->planned([
                'app.greeter' => ['class' => Greeter::class, 'arguments' => ['named' => 1]],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.greeter': the argument 'named' names no parameter of "
                . 'the planned constructor (parameters: salutation, verbosity).',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTheReportAnswersTheGeneratedAndTheReused(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.dep' => ['class' => SimpleDep::class, 'arguments' => ['name' => 'one']],
            SimpleDep::class => ['alias' => 'app.dep'],
            'app.house' => ['class' => DependentHouse::class, 'autowire' => true],
        ]), new Container());

        $house = $result->definitions[2];
        self::assertInstanceOf(InstanceDefinition::class, $house);
        self::assertSame([['service' => SimpleDep::class], 'lbl'], $house->arguments);
        self::assertSame([], $result->generated);
        self::assertSame([SimpleDep::class], $result->reused);
    }

    public function testTheNonClassShapesPassThroughUntouched(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.factory' => ['factory' => ExternalFactory::class . '::make', 'arguments' => []],
            'app.alias' => ['alias' => 'app.factory'],
            'app.value' => ['value' => 42],
        ]), new Container());

        self::assertCount(3, $result->definitions);
        self::assertSame([], $result->generated);
        self::assertSame([], $result->reused);
    }

    public function testThePlannedEdgesFlowIntoTheBootGraphsCycleDiscipline(): void
    {
        $container = new Container();
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.first' => ['class' => TypedHouse::class, 'arguments' => ['greeter' => ['service' => 'app.second']]],
            'app.second' => ['class' => TypedHouse::class, 'arguments' => ['greeter' => ['service' => 'app.first']]],
        ]), $container);

        try {
            ServiceDefinitionGraph::validate($result->definitions, $container);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString('circular reference chain', $definitionException->getMessage());
        }
    }

    public function testOneRunPerProcessCallTheAccumulatorsReset(): void
    {
        $first = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => DependentHouse::class, 'autowire' => true],
        ]), new Container());

        self::assertSame([SimpleDep::class], $first->generated);

        $second = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.dep' => ['class' => SimpleDep::class, 'arguments' => ['name' => 'one']],
        ]), new Container());

        self::assertSame([], $second->generated);
        self::assertSame([], $second->reused);
        self::assertCount(1, $second->definitions);
    }

    public function testAVariadicNamedListEntryRidesTheReferenceGrammar(): void
    {
        try {
            $this->planned([
                'app.house' => [
                    'class' => VariadicHouse::class,
                    'arguments' => ['notes' => [['service' => 'app.first', 'extra' => 1]]],
                ],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.house' argument reference must be the single key 'service' "
                . '(extra keys alongside it are rejected).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testATypedVariadicGathersTheDeclaredClassImplementations(): void
    {
        $planned = $this->planned([
            'app.a' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.b' => ['class' => Greeter::class, 'arguments' => ['Hi']],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => 'app.a'], ['service' => 'app.b']],
            $this->firstInstance($planned, 2)->arguments,
        );
    }

    public function testTheCollectionSkipsNonMatchingDeclarationsWithoutStopping(): void
    {
        $planned = $this->planned([
            'app.debug' => ['value' => true],
            'app.file' => ['class' => FileLogger::class],
            'app.a' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => 'app.a']],
            $this->firstInstance($planned, 3)->arguments,
        );
    }

    public function testAnUnplannedImplementationFormStillJoinsTheCollection(): void
    {
        $planned = $this->planned([
            'app.file' => ['class' => FileLogger::class, 'autowire' => true],
            'app.house' => ['class' => LoggerVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => 'app.file']],
            $this->firstInstance($planned, 1)->arguments,
        );
    }

    public function testAStaticFactoryWithAUnionReturnTypeJoinsNoCollection(): void
    {
        $planned = $this->planned([
            'app.made' => ['factory' => UnionStaticFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testAnUnopenedCollectorRefusesToAnswer(): void
    {
        try {
            new AutowireImplementations()->ids(Greeter::class);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                'The implementation collector answers only inside an opened run '
                . '-- open(definitions, container) before collecting.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAStaticFactoryWithABuiltinReturnJoinsNoCollection(): void
    {
        $planned = $this->planned([
            'app.made' => ['factory' => BuiltinStaticFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testANonStaticFactoryMethodJoinsNoCollection(): void
    {
        $planned = $this->planned([
            'app.made' => ['factory' => InstanceMethodFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testAFactoryMethodTheClassDoesNotCarryJoinsNoCollection(): void
    {
        $planned = $this->planned([
            'app.made' => ['factory' => GreeterFactory::class . '::missing'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testATypedVariadicGathersTheDeclaredStaticFactoryImplementations(): void
    {
        $planned = $this->planned([
            'app.made' => ['factory' => GreeterFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => 'app.made']],
            $this->firstInstance($planned, 1)->arguments,
        );
    }

    public function testAnInterfaceTypedVariadicGathersTheImplementationsOfTheInterface(): void
    {
        $planned = $this->planned([
            'app.file' => ['class' => FileLogger::class],
            'app.house' => ['class' => LoggerVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame(
            [['service' => 'app.file']],
            $this->firstInstance($planned, 1)->arguments,
        );
    }

    public function testABirthNeverJoinsTheCollectionRegardlessOfPlanningOrder(): void
    {
        $birthAfter = $this->planned([
            'app.house' => ['class' => SimpleDepVariadicHouse::class, 'autowire' => true],
            'app.user' => ['class' => DependentHouse::class, 'autowire' => true],
        ]);

        $birthBefore = $this->planned([
            'app.user' => ['class' => DependentHouse::class, 'autowire' => true],
            'app.house' => ['class' => SimpleDepVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($birthAfter)->arguments);
        self::assertSame([], $this->firstInstance($birthBefore, 1)->arguments);
        self::assertSame(
            [['service' => SimpleDep::class], 'lbl'],
            $this->firstInstance($birthBefore)->arguments,
        );
    }

    public function testAFactoryTargetShadowedByADeclaredIdentifierJoinsNoCollection(): void
    {
        $planned = $this->planned([
            GreeterFactory::class => ['value' => 'shadowing the class name'],
            'app.made' => ['factory' => GreeterFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned, 2)->arguments);
    }

    public function testAFactoryTargetShadowedByAContainerIdentifierJoinsNoCollection(): void
    {
        $container = new Container();
        $container->set(GreeterFactory::class, static fn (): Greeter => new Greeter('container'));

        $planned = $this->planned([
            'app.made' => ['factory' => GreeterFactory::class . '::make'],
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ], $container);

        self::assertSame([], $this->firstInstance($planned, 1)->arguments);
    }

    public function testAShadowedTargetStillDeclaresItsOwnFactoryUnchanged(): void
    {
        $container = new Container();
        $container->set(GreeterFactory::class, static fn (): Greeter => new Greeter('container'));

        $planned = $this->planned([
            'app.made' => ['factory' => GreeterFactory::class . '::make'],
        ], $container);

        self::assertInstanceOf(FactoryDefinition::class, $planned[0]);
        self::assertSame(GreeterFactory::class . '::make', $planned[0]->factory);
    }

    public function testAUnionTypedVariadicIsRefusedAsTheAmbiguityItIs(): void
    {
        try {
            $this->planned([
                'app.house' => ['class' => UnionVariadicHouse::class, 'autowire' => true],
            ]);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Cannot autowire 'app.house' parameter '\$values': a union type names no single binding, "
                . 'and a variadic collection gathers the declared implementations of one type -- '
                . 'name the argument list to spread, or type the parameter with a single class or interface.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testASpelledVariadicStretchWinsOverTheCollection(): void
    {
        $planned = $this->planned([
            'app.a' => ['class' => Greeter::class, 'arguments' => ['Hello']],
            'app.b' => ['class' => Greeter::class, 'arguments' => ['Hi']],
            'app.house' => [
                'class' => TypedVariadicHouse::class,
                'arguments' => ['greeters' => [['service' => 'app.b']]],
            ],
        ]);

        self::assertSame(
            [['service' => 'app.b']],
            $this->firstInstance($planned, 2)->arguments,
        );
    }

    public function testATypedVariadicOverAnEmptyEcosystemAnswersTheEmptyCollection(): void
    {
        $planned = $this->planned([
            'app.house' => ['class' => TypedVariadicHouse::class, 'autowire' => true],
        ]);

        self::assertSame([], $this->firstInstance($planned)->arguments);
    }

    public function testAValueAddressPlansTheSyntheticServiceReference(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.connection' => ['class' => ConnectionHouse::class, 'autowire' => true],
        ]), new Container(), new ParameterValues([
            'db.host' => 'localhost',
            'db.port' => 3306,
        ]));

        $planned = $result->definitions[0];
        self::assertInstanceOf(InstanceDefinition::class, $planned);
        self::assertSame(
            [['service' => '@value:db.host'], ['service' => '@value:db.port']],
            $planned->arguments,
        );

        $hostValue = $result->definitions[1];
        $portValue = $result->definitions[2];
        self::assertInstanceOf(ValueDefinition::class, $hostValue);
        self::assertInstanceOf(ValueDefinition::class, $portValue);
        self::assertSame('@value:db.host', $hostValue->id);
        self::assertSame('localhost', $hostValue->value);
        self::assertSame('@value:db.port', $portValue->id);
        self::assertSame(3306, $portValue->value);
        self::assertSame(['@value:db.host', '@value:db.port'], $result->values);
        self::assertSame([], $result->generated);
    }

    public function testTwoConsumersOfOneAddressShareOneMaterialisation(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => SharedFlagHouse::class, 'autowire' => true],
            'app.audit' => ['class' => SharedFlagAudit::class, 'autowire' => true],
        ]), new Container(), new ParameterValues([
            'shared.flag' => true,
        ]));

        self::assertSame([['service' => '@value:shared.flag']], $this->firstInstance($result->definitions)->arguments);
        self::assertSame(
            [['service' => '@value:shared.flag']],
            $this->firstInstance($result->definitions, 1)->arguments,
        );
        self::assertCount(3, $result->definitions);
        self::assertSame(['@value:shared.flag'], $result->values);
    }

    public function testAnArrayParameterMaterialisesTheWholeValue(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.pool' => ['class' => PoolHouse::class, 'autowire' => true],
        ]), new Container(), new ParameterValues([
            'db.replicas' => ['a.example', 'b.example'],
        ]));

        $planned = $result->definitions[0];
        self::assertInstanceOf(InstanceDefinition::class, $planned);
        self::assertSame([['service' => '@value:db.replicas']], $planned->arguments);

        $value = $result->definitions[1];
        self::assertInstanceOf(ValueDefinition::class, $value);
        self::assertSame(['a.example', 'b.example'], $value->value);
    }

    public function testAMissingAddressRefusesEvenWhenTheConstructorCarriesADefault(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => DefaultedHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot plan 'app.house': the parameter '\$name' carries #[Value('missing.default')]",
                $autowiringException->getMessage(),
            );
            self::assertStringContainsString(
                "drop the attribute so the parameter's own default or explicit spelling applies",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testTheDefaultValueParameterAnswersWithoutValuesThreaded(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => ConnectionHouse::class, 'autowire' => true],
            ]), new Container());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the 'parameters' configuration has no value for 'db.host'",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADeclaredSpellingOfTheSyntheticNamespaceRefusesThePlan(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => ConnectionHouse::class, 'autowire' => true],
                '@value:db.host' => ['value' => 'shadowed'],
            ]), new Container(), new ParameterValues([
                'db.host' => 'localhost',
                'db.port' => 3306,
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the synthetic value service '@value:db.host' collides with a declared definition",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAnExplicitSpellingWinsOverTheValueAttribute(): void
    {
        $result = $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
            'app.house' => ['class' => SpelledHouse::class, 'arguments' => ['host' => 'spelled.example']],
        ]), new Container(), new ParameterValues([
            'db.host' => 'localhost',
        ]));

        $planned = $result->definitions[0];
        self::assertInstanceOf(InstanceDefinition::class, $planned);
        self::assertSame(['spelled.example'], $planned->arguments);
        self::assertSame([], $result->values);
    }

    public function testAValueAttributeCannotRideAClassTypedParameter(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => ClassTypedValueHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([
                'app.greeter' => 'x',
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$greeter' cannot carry #[Value] on a class-typed parameter",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAValueAttributeCannotRideAUnionParameter(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => UnionValueHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([
                'app.either' => 'x',
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$either' cannot carry #[Value] on a union or intersection parameter",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAValueAttributeCannotRideAVariadicParameter(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => VariadicValueHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([
                'app.tags' => 'x',
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$tags' cannot carry #[Value] on a variadic parameter",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAValueAttributeCannotCombineWithInject(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => DoubledIntentHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([
                'app.clock.value' => 'x',
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Parameter '\$clock' cannot combine #[Value] with #[Inject] or #[Target]",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testADoubledValueAttributeIsTheAmbiguityItIs(): void
    {
        try {
            $this->autowireCompilerPass->process(DefinitionMap::parse('services', [
                'app.house' => ['class' => DoubledValueHouse::class, 'autowire' => true],
            ]), new Container(), new ParameterValues([
                'db.host' => 'localhost',
            ]));
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertSame(
                "Parameter '\$host' carries more than one Value attribute.",
                $autowiringException->getMessage(),
            );
        }
    }

    public function testASecondRunDoesNotLeakTheFirstRunsMaterialisations(): void
    {
        $services = DefinitionMap::parse('services', [
            'app.house' => ['class' => SharedFlagHouse::class, 'autowire' => true],
        ]);

        $first = $this->autowireCompilerPass->process(
            $services,
            new Container(),
            new ParameterValues(['shared.flag' => true]),
        );
        self::assertSame(['@value:shared.flag'], $first->values);

        try {
            $this->autowireCompilerPass->process($services, new Container());
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "the 'parameters' configuration has no value for 'shared.flag'",
                $autowiringException->getMessage(),
            );
        }
    }

    /**
     * @param array<string, mixed> $services
     *
     * @return list<ServiceDefinition> the planned definitions, births appended
     */
    private function planned(array $services, ?Container $container = null): array
    {
        return $this->autowireCompilerPass->process(
            DefinitionMap::parse('services', $services),
            $container ?? new Container(),
        )->definitions;
    }

    /**
     * @param list<ServiceDefinition> $planned
     *
     * @return InstanceDefinition narrowed from the planned set
     */
    private function firstInstance(array $planned, int $index = 0): InstanceDefinition
    {
        $definition = $planned[$index];
        self::assertInstanceOf(InstanceDefinition::class, $definition);

        return $definition;
    }
}
