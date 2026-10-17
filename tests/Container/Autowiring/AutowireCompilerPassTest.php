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
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ConstructorFit;
use Zef\Framework\Container\Autowiring\ConstructorSpecs;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Test\Support\Container\Autowiring\BirthChainAlpha;
use Zef\Test\Support\Container\Autowiring\BirthChainBeta;
use Zef\Test\Support\Container\Autowiring\ChainRoot;
use Zef\Test\Support\Container\Autowiring\DependentHouse;
use Zef\Test\Support\Container\Autowiring\DoubleDepHouse;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\InjectedDepHouse;
use Zef\Test\Support\Container\Autowiring\InjectedHouse;
use Zef\Test\Support\Container\Autowiring\InterfaceHouse;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\NamedWinsHouse;
use Zef\Test\Support\Container\Autowiring\NullableHouse;
use Zef\Test\Support\Container\Autowiring\OptionalUnionHouse;
use Zef\Test\Support\Container\Autowiring\ParameterlessHouse;
use Zef\Test\Support\Container\Autowiring\SimpleDep;
use Zef\Test\Support\Container\Autowiring\TargetedHouse;
use Zef\Test\Support\Container\Autowiring\TypedHouse;
use Zef\Test\Support\Container\Autowiring\TypedVariadicHouse;
use Zef\Test\Support\Container\Autowiring\UninstantiableHouse;
use Zef\Test\Support\Container\Autowiring\UnionHouse;
use Zef\Test\Support\Container\Autowiring\VariadicHouse;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(AutowireCompilerPass::class)]
#[CoversClass(AutowireParameterPlanner::class)]
#[CoversClass(AutowireResult::class)]
#[CoversClass(AutowireAttributes::class)]
#[CoversClass(AutowireBirther::class)]
#[CoversClass(AutowireBirths::class)]
#[CoversClass(AutowireDeclaredArguments::class)]
#[CoversClass(AutowireParameterTypes::class)]
#[CoversClass(AutowirePlanning::class)]
#[CoversClass(AutowirePositionalCheck::class)]
#[CoversClass(AutowireReferenceRefusals::class)]
#[CoversClass(AutowireReferences::class)]
#[CoversClass(AutowireRefusals::class)]
#[CoversClass(AutowireRequestPlanning::class)]
#[CoversClass(AutowireReuse::class)]
#[CoversClass(AutowireSpecFactory::class)]
#[CoversClass(ConstructorFit::class)]
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
