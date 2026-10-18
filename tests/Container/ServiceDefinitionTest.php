<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\AliasShape;
use Zef\Framework\Container\ArgumentsForm;
use Zef\Framework\Container\AutowireKey;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\DefinitionKeys;
use Zef\Framework\Container\DefinitionReferences;
use Zef\Framework\Container\DefinitionShapes;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\FactoryShape;
use Zef\Framework\Container\InstanceArguments;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\InstanceShape;
use Zef\Framework\Container\NamedArguments;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceLifetime;
use Zef\Framework\Container\ServiceTagKey;
use Zef\Framework\Container\ServiceTags;
use Zef\Framework\Container\UnplannedInstanceDefinition;
use Zef\Framework\Container\ValueDefinition;
use Zef\Framework\Container\ValueShape;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;

/**
 * @internal
 */
#[CoversClass(ServiceDefinition::class)]
#[CoversClass(ServiceTagKey::class)]
#[CoversClass(ServiceTags::class)]
#[CoversClass(DefinitionKeys::class)]
#[CoversClass(DefinitionShapes::class)]
#[CoversClass(InstanceShape::class)]
#[CoversClass(InstanceArguments::class)]
#[CoversClass(ArgumentsForm::class)]
#[CoversClass(AutowireKey::class)]
#[CoversClass(NamedArguments::class)]
#[CoversClass(UnplannedInstanceDefinition::class)]
#[CoversClass(DefinitionReferences::class)]
#[CoversClass(FactoryShape::class)]
#[CoversClass(AliasShape::class)]
#[CoversClass(ValueShape::class)]
#[CoversClass(InstanceDefinition::class)]
#[CoversClass(FactoryDefinition::class)]
#[CoversClass(AliasDefinition::class)]
#[CoversClass(ValueDefinition::class)]
#[CoversClass(DefinitionException::class)]
final class ServiceDefinitionTest extends TestCase
{
    public function testAnInstanceDefinitionParsesWithItsClassAndArguments(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', [
            'class' => Greeter::class,
            'arguments' => ['Hello', 2],
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame('app.greeter', $definition->id);
        self::assertSame(Greeter::class, $definition->class);
        self::assertSame(['Hello', 2], $definition->arguments);
    }

    public function testAnInstanceDefinitionDefaultsToAnEmptyArgumentList(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', ['class' => Greeter::class]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame([], $definition->arguments);
    }

    public function testAFactoryDefinitionKeepsTheVerbatimFactoryString(): void
    {
        $definition = ServiceDefinition::fromArray('app.label', [
            'factory' => ExternalFactory::class . '::make',
            'arguments' => ['widget'],
        ]);

        self::assertInstanceOf(FactoryDefinition::class, $definition);
        self::assertSame(ExternalFactory::class . '::make', $definition->factory);
        self::assertSame(['widget'], $definition->arguments);
    }

    public function testAnAliasDefinitionCarriesItsTarget(): void
    {
        $definition = ServiceDefinition::fromArray(Greeter::class, ['alias' => 'app.greeter']);

        self::assertInstanceOf(AliasDefinition::class, $definition);
        self::assertSame('app.greeter', $definition->target);
    }

    public function testAValueDefinitionHoldsAnyRawValue(): void
    {
        $definition = ServiceDefinition::fromArray('app.debug', ['value' => true]);

        self::assertInstanceOf(ValueDefinition::class, $definition);
        self::assertTrue($definition->value);
    }

    public function testAValueDefinitionHoldsArraysAndNull(): void
    {
        $map = ServiceDefinition::fromArray('app.map', ['value' => ['a' => 1, 'b' => 2]]);
        $null = ServiceDefinition::fromArray('app.absent', ['value' => null]);

        self::assertInstanceOf(ValueDefinition::class, $map);
        self::assertInstanceOf(ValueDefinition::class, $null);
        self::assertSame(['a' => 1, 'b' => 2], $map->value);
        self::assertNull($null->value);
    }

    public function testTheBareClassShapeCarriesItsLifetime(): void
    {
        // The absent-arguments branch of the class shape carries the
        // lifetime exactly as the planning forms do -- no form is a
        // silent singleton.
        $definition = ServiceDefinition::fromArray('app.x', ['class' => Greeter::class, 'lifetime' => 'request']);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(ServiceLifetime::REQUEST, $definition->lifetime);
    }

    public function testThePositionalPlanCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => Greeter::class,
            'arguments' => ['Hello'],
            'lifetime' => 'transient',
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(ServiceLifetime::TRANSIENT, $definition->lifetime);
    }

    public function testNameKeyedArgumentsCarryTheLifetime(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => Greeter::class,
            'arguments' => ['salutation' => 'Hello'],
            'lifetime' => 'request',
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(ServiceLifetime::REQUEST, $definition->lifetime);
    }

    public function testTheWholeConstructorRequestCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => Greeter::class,
            'autowire' => true,
            'lifetime' => 'transient',
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(ServiceLifetime::TRANSIENT, $definition->lifetime);
    }

    public function testTheFactoryShapeCarriesItsLifetime(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'factory' => ExternalFactory::class . '::make',
            'lifetime' => 'request',
        ]);

        self::assertInstanceOf(FactoryDefinition::class, $definition);
        self::assertSame(ServiceLifetime::REQUEST, $definition->lifetime);
    }

    public function testAnAbsentLifetimeDefaultsToSingleton(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', ['class' => Greeter::class]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(ServiceLifetime::SINGLETON, $definition->lifetime);
    }

    public function testAnEmptyIdentifierIsRejected(): void
    {
        try {
            // A non-map entry keeps the identifier guard's failure distinct
            // from every downstream message: without the guard the parse
            // would fall through to the entry-shape rejection instead.
            ServiceDefinition::fromArray('', 'Hello');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service definition requires a non-empty identifier.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyIdentifierIsRejectedByTheAliasConstructor(): void
    {
        try {
            new AliasDefinition('', 'app.other');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service definition requires a non-empty identifier.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyIdentifierIsRejectedByTheFactoryConstructor(): void
    {
        try {
            new FactoryDefinition('', ExternalFactory::class . '::make');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service definition requires a non-empty identifier.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownIntegerKeyIsRejectedAsAString(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => Greeter::class, 5 => 'stray']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the unknown key '5' "
                . '(known keys: class, factory, alias, value, arguments, autowire, lifetime, tags, calls).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonMapEntryIsRejectedWithTypeAndIdentifier(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', 'Hello');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' must be a definition map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownKeyIsRejectedWithTheKnownKeyList(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['clas' => Greeter::class]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the unknown key 'clas' "
                . '(known keys: class, factory, alias, value, arguments, autowire, lifetime, tags, calls).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithoutAShapeKeyIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['arguments' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries none of the shape keys (exactly one of "
                . "'class', 'factory', 'alias', 'value' is required).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithTwoShapeKeysIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => Greeter::class, 'value' => 1]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the shape keys 'class', 'value' (exactly one of "
                . "'class', 'factory', 'alias', 'value' is allowed).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringClassIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => 42]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' class must be a non-empty class name, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyClassIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => '']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' class must be a non-empty class name, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnknownClassIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => 'No\Such\Class']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' class 'No\\Such\\Class' does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringFactoryIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['factory' => null]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' factory must be a non-empty 'Target::method' string, null given.",
                $definitionException->getMessage(),
            );
        }
    }

    #[DataProvider('provideMalformedFactoryStringsAreRejectedCases')]
    public function testMalformedFactoryStringsAreRejected(string $factory, string $message): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['factory' => $factory]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame($message, $definitionException->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMalformedFactoryStringsAreRejectedCases(): iterable
    {
        yield 'two separators' => [
            'A::b::c',
            "Service 'app.x' factory 'A::b::c' must carry exactly one '::' separator.",
        ];

        yield 'no separator' => [
            'Ab',
            "Service 'app.x' factory 'Ab' must carry exactly one '::' separator.",
        ];

        yield 'empty target' => [
            '::make',
            "Service 'app.x' factory '::make' must name both a target and a method.",
        ];

        yield 'empty method' => [
            'Factory::',
            "Service 'app.x' factory 'Factory::' must name both a target and a method.",
        ];
    }

    public function testANonStringAliasIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['alias' => ['a']]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' alias must be a non-empty service identifier, array given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testArgumentsOnAnAliasAreRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['alias' => 'other', 'arguments' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries arguments on a 'alias' definition "
                . '(arguments belong to class and factory definitions only).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testArgumentsOnAValueAreRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['value' => 1, 'arguments' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries arguments on a 'value' definition "
                . '(arguments belong to class and factory definitions only).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testNonArrayArgumentsAreRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', ['class' => Greeter::class, 'arguments' => 'nope']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' arguments must be a list, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testStringKeyedArgumentsAreRejectedOnTheFactoryShape(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'factory' => 'Some\Factory::make',
                'arguments' => ['named' => 1],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'arguments must be a positional list (string keys are rejected)',
                $definitionException->getMessage(),
            );
        }
    }

    public function testMixedKeyArgumentsAreRejectedOnTheClassShape(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'arguments' => ['salutation' => 'Hello', 1 => 'stray'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'name-keyed arguments must carry non-empty parameter names, int given',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMultiKeyArgumentReferenceIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'arguments' => [['service' => 'app.y', 'extra' => 1]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' argument reference must be the single key 'service'",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringArgumentReferenceIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'arguments' => [['service' => 7]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'argument reference must name a non-empty service identifier, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyArgumentReferenceIsRejected(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'arguments' => [['service' => '']],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'argument reference must name a non-empty service identifier, string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testHandBuiltDefinitionsEnforceTheSameIdentifierInvariant(): void
    {
        try {
            new ValueDefinition('', 1);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString('non-empty identifier', $definitionException->getMessage());
        }
    }

    public function testHandBuiltAliasDefinitionsEnforceTheTargetInvariant(): void
    {
        try {
            new AliasDefinition('app.x', '');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame("Service 'app.x' alias target must not be empty.", $definitionException->getMessage());
        }
    }

    public function testPlainArrayArgumentsAreNotConstrained(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => stdClass::class,
            'arguments' => [['nested' => 'map'], ['service' => 'app.y']],
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame([['nested' => 'map'], ['service' => 'app.y']], $definition->arguments);
    }

    public function testTheAutowireFormParsesIntoTheUnplannedRequest(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => Greeter::class,
            'autowire' => true,
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(Greeter::class, $definition->class);
        self::assertNull($definition->namedArguments);
    }

    public function testNameKeyedArgumentsParseIntoTheUnplannedRequest(): void
    {
        $definition = ServiceDefinition::fromArray('app.x', [
            'class' => Greeter::class,
            'arguments' => ['salutation' => 'Hello', 'verbosity' => ['service' => 'app.y']],
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(
            ['salutation' => 'Hello', 'verbosity' => ['service' => 'app.y']],
            $definition->namedArguments,
        );
    }

    public function testAutowireAlongsideArgumentsIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'autowire' => true,
                'arguments' => ['salutation' => 'Hello'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries both 'autowire' and 'arguments' "
                . '(choose one planning form: the name-keyed arguments, or the whole-constructor request).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAValueOtherThanTrueUnderAutowireIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'class' => Greeter::class,
                'autowire' => false,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the 'autowire' key with a value other than true "
                . '(the key is the whole-constructor planning request; an absent key is the default).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAutowireOnTheFactoryShapeIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'factory' => 'Some\Factory::make',
                'autowire' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the 'autowire' key on a 'factory' definition "
                . '(the whole-constructor planning request belongs to the class shape only).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAutowireOnTheAliasShapeIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'alias' => 'app.y',
                'autowire' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the 'autowire' key on a 'alias' definition "
                . '(the whole-constructor planning request belongs to the class shape only).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAutowireOnTheValueShapeIsRefused(): void
    {
        try {
            ServiceDefinition::fromArray('app.x', [
                'value' => 42,
                'autowire' => true,
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries the 'autowire' key on a 'value' definition "
                . '(the whole-constructor planning request belongs to the class shape only).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnUnplannedDefinitionWithAnEmptyIdentifierIsRefused(): void
    {
        try {
            new UnplannedInstanceDefinition('', stdClass::class);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service definition requires a non-empty identifier.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheReferencesOfAnUnplannedPlanAreRefusedUntilThePassRuns(): void
    {
        try {
            DefinitionReferences::of(new UnplannedInstanceDefinition('app.x', stdClass::class, ['a' => 1]));
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.x' carries an unplanned constructor plan -- "
                . 'run the autowire compiler pass before graph validation.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheBareClassShapeCarriesItsDeclaredTags(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', [
            'class' => Greeter::class,
            'tags' => ['app.greeting', 'shared.label'],
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(['app.greeting', 'shared.label'], $definition->tags->all());
    }

    public function testThePositionalArgumentsFormCarriesItsDeclaredTags(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', [
            'class' => Greeter::class,
            'arguments' => ['Hello'],
            'tags' => ['app.greeting'],
        ]);

        self::assertInstanceOf(InstanceDefinition::class, $definition);
        self::assertSame(['app.greeting'], $definition->tags->all());
    }

    public function testTheNameKeyedFormCarriesItsDeclaredTags(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', [
            'class' => Greeter::class,
            'arguments' => ['salutation' => 'Hello'],
            'tags' => ['app.greeting'],
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(['app.greeting'], $definition->tags->all());
    }

    public function testTheAutowireFormCarriesItsDeclaredTags(): void
    {
        $definition = ServiceDefinition::fromArray('app.greeter', [
            'class' => Greeter::class,
            'autowire' => true,
            'tags' => ['app.greeting'],
        ]);

        self::assertInstanceOf(UnplannedInstanceDefinition::class, $definition);
        self::assertSame(['app.greeting'], $definition->tags->all());
    }

    public function testTheFactoryShapeCarriesItsDeclaredTags(): void
    {
        $definition = ServiceDefinition::fromArray('app.label', [
            'factory' => ExternalFactory::class . '::make',
            'tags' => ['app.labeling'],
        ]);

        self::assertInstanceOf(FactoryDefinition::class, $definition);
        self::assertSame(['app.labeling'], $definition->tags->all());
    }

    public function testEveryShapeDefaultsToNoTagsAtAll(): void
    {
        $class = ServiceDefinition::fromArray('app.greeter', ['class' => Greeter::class]);
        $factory = ServiceDefinition::fromArray('app.label', ['factory' => ExternalFactory::class . '::make']);

        self::assertSame([], $class->tags->all());
        self::assertSame([], $factory->tags->all());
        self::assertFalse($class->tags->carries('app.greeting'));
        self::assertFalse($factory->tags->carries('app.labeling'));
    }

    public function testTagMembershipAnswersForOneExactSpelling(): void
    {
        $serviceTags = new ServiceTags(['app.greeting', 'shared.label']);

        self::assertTrue($serviceTags->carries('app.greeting'));
        self::assertTrue($serviceTags->carries('shared.label'));
        self::assertFalse($serviceTags->carries('app'));
        self::assertFalse($serviceTags->carries('APP.GREETING'));
    }

    public function testATagListIsRefusedWhenItIsNotAList(): void
    {
        try {
            ServiceDefinition::fromArray('app.greeter', [
                'class' => Greeter::class,
                'tags' => 'app.greeting',
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.greeter' tags must be a list of tag strings, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testATagListIsRefusedWhenItIsMapKeyed(): void
    {
        try {
            ServiceDefinition::fromArray('app.greeter', [
                'class' => Greeter::class,
                'tags' => ['primary' => 'app.greeting'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.greeter' tags must be a list of tag strings, array given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testATagEntryIsRefusedWhenItIsNotAString(): void
    {
        try {
            ServiceDefinition::fromArray('app.greeter', [
                'class' => Greeter::class,
                'tags' => [7],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service tag must match [A-Za-z0-9._-]{1,128}, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testATagEntryIsRefusedWhenItsSpellingLeavesTheGrammar(): void
    {
        foreach (['', ' app.greeting', 'app.greeting ', 'app greeting', 'app/greeting', 'mäkki'] as $tag) {
            try {
                new ServiceTags([$tag]);
                self::fail("Expected a DefinitionException for the tag '{$tag}'.");
            } catch (DefinitionException $definitionException) {
                self::assertSame(
                    "A service tag must match [A-Za-z0-9._-]{1,128}, '{$tag}' given.",
                    $definitionException->getMessage(),
                );
            }
        }
    }

    public function testATagEntryIsRefusedAtTheLengthBoundary(): void
    {
        $within = str_repeat('a', 128);
        $beyond = str_repeat('a', 129);

        self::assertSame([$within], new ServiceTags([$within])->all());

        try {
            new ServiceTags([$beyond]);
            self::fail('Expected a DefinitionException for the over-long tag.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A service tag must match [A-Za-z0-9._-]{1,128}, ' . "'{$beyond}'" . ' given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testATagDeclaredTwiceIsTheAmbiguityItIs(): void
    {
        try {
            ServiceDefinition::fromArray('app.greeter', [
                'class' => Greeter::class,
                'tags' => ['app.greeting', 'shared.label', 'app.greeting'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The service tag 'app.greeting' is declared more than once (a tag is declared once per definition).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAliasRefusesTags(): void
    {
        try {
            ServiceDefinition::fromArray(Greeter::class, [
                'alias' => 'app.greeter',
                'tags' => ['app.greeting'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service '" . Greeter::class . "' carries 'tags' on a 'alias' definition "
                . '(an alias is transparent: the tag belongs on the definition it points to).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAValueRefusesTags(): void
    {
        try {
            ServiceDefinition::fromArray('app.debug', [
                'value' => true,
                'tags' => ['app.greeting'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'app.debug' carries 'tags' on a 'value' definition "
                . '(a value is a constant: it is not a service to group).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAbsentTagsKeyAnswersNoTagsAtAll(): void
    {
        self::assertNull(ServiceTagKey::fromKey('app.greeter', null));
        self::assertSame([], new ServiceTags()->all());
    }
}
