<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DecoratorClassShape;
use Zef\Framework\Container\DecoratorEntries;
use Zef\Framework\Container\DecoratorEntry;
use Zef\Framework\Container\DecoratorEntryArguments;
use Zef\Framework\Container\DecoratorEntryKeys;
use Zef\Framework\Container\DecoratorEntryParser;
use Zef\Framework\Container\DecoratorEntryShapes;
use Zef\Framework\Container\DecoratorMap;
use Zef\Framework\Container\DecoratorTargets;
use Zef\Framework\Container\DefinitionException;
use Zef\Test\Support\Container\Decorators\FileLogger;
use Zef\Test\Support\Container\Decorators\LoggerDecorators;
use Zef\Test\Support\Container\Decorators\PrefixedLogger;

/**
 * The decoration knob's own grammar: the `decorators` configuration
 * entry parsed fail-closed -- the map shape, the target keys, the
 * chain lists, the entry mini-shapes, the reserved inner reference's
 * presence, and the lane's budget (ROADMAP area 7's declaration
 * surface).
 *
 * @internal
 */
#[CoversClass(DecoratorMap::class)]
#[CoversClass(DecoratorTargets::class)]
#[CoversClass(DecoratorEntries::class)]
#[CoversClass(DecoratorEntry::class)]
#[CoversClass(DecoratorEntryParser::class)]
#[CoversClass(DecoratorEntryShapes::class)]
#[CoversClass(DecoratorEntryKeys::class)]
#[CoversClass(DecoratorEntryArguments::class)]
#[CoversClass(DecoratorClassShape::class)]
final class DecoratorMapTest extends TestCase
{
    private const string TIMING_FACTORY = LoggerDecorators::class . '::timing';

    private const array TIMING_ENTRY = ['factory' => self::TIMING_FACTORY, 'arguments' => self::INNER];

    private const array INNER = [['service' => '@inner']];

    public function testAnAbsentKnobAnswersNoDecorations(): void
    {
        self::assertSame([], DecoratorMap::parse('decorators', null)->decorations());
    }

    public function testAValidKnobAnswersTheParsedChains(): void
    {
        $decoratorMap = DecoratorMap::parse('decorators', [
            'logger' => [
                ['class' => PrefixedLogger::class, 'arguments' => [['service' => '@inner'], 'trace']],
                ['factory' => LoggerDecorators::class . '::timing', 'arguments' => [['service' => '@inner']]],
            ],
        ]);

        $decorations = $decoratorMap->decorations();
        self::assertSame(['logger'], array_keys($decorations));
        self::assertCount(2, $decorations['logger']);

        $first = $decorations['logger'][0];
        self::assertSame(PrefixedLogger::class, $first->class);
        self::assertNull($first->factory);
        self::assertSame([['service' => '@inner'], 'trace'], $first->arguments);

        $second = $decorations['logger'][1];
        self::assertNull($second->class);
        self::assertSame(LoggerDecorators::class . '::timing', $second->factory);
        self::assertSame([['service' => '@inner']], $second->arguments);
    }

    public function testANonMapKnobIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', 'logger');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'decorators' configuration must be a map of service identifiers to decorator lists, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringTargetKeyIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', [7 => [['class' => PrefixedLogger::class]]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'A decorator target must be a non-empty service identifier, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAChainThatIsNotAPositionalListIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => ['first' => []]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The decorator list for 'logger' must be a positional list, array given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyChainIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => []]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The decorator list for 'logger' is empty -- declare at least one decorator, "
                . "or drop the 'logger' entry from the 'decorators' map.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryThatIsNotAMapIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => [PrefixedLogger::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Each decorator for 'logger' must be a map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryCarryingAnUnknownKeyIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', [
                'logger' => [['class' => PrefixedLogger::class, 'tags' => ['a.tag']]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A decorator for 'logger' carries the unknown key 'tags' "
                . '(known keys: class, factory, arguments).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryCarryingNoShapeKeyIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => [['arguments' => [['service' => '@inner']]]]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A decorator for 'logger' carries none of the shape keys "
                . "(exactly one of 'class', 'factory' is required).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryCarryingBothShapeKeysIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', [
                'logger' => [
                    [
                        'class' => PrefixedLogger::class,
                        'factory' => LoggerDecorators::class . '::timing',
                    ],
                ],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A decorator for 'logger' carries the shape keys 'class', 'factory' "
                . "(exactly one of 'class', 'factory' is allowed).",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryNamingAMissingClassIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => [['class' => 'No\Such\Decorator']]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The decorator for 'logger' class 'No\\Such\\Decorator' does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithANonStringClassIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => [['class' => 42]]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The decorator for 'logger' class must be a non-empty class name, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithAMalformedFactorySpellingIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', ['logger' => [['factory' => 'not-a-method']]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'decorators.logger' factory 'not-a-method' must carry exactly one '::' separator.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithNonListArgumentsIsRefusedUnderTheKnobLabel(): void
    {
        try {
            DecoratorMap::parse('decorators', [
                'logger' => [['class' => PrefixedLogger::class, 'arguments' => 'trace']],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'decorators.logger' arguments must be a list, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryWithAMultiKeyReferenceIsRefusedUnderTheKnobLabel(): void
    {
        try {
            DecoratorMap::parse('decorators', [
                'logger' => [
                    ['class' => PrefixedLogger::class, 'arguments' => [
                        ['service' => '@inner', 'extra' => 1],
                    ]],
                ],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "Service 'decorators.logger' argument reference must be the single key 'service' "
                . '(extra keys alongside it are rejected).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEntryThatNeverReferencesTheInnerIsRefused(): void
    {
        try {
            DecoratorMap::parse('decorators', [
                'logger' => [['class' => FileLogger::class, 'arguments' => ['other.log']]],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "A decorator for 'logger' never references '@inner' -- a decorator wraps the service "
                . "it decorates (add a ['service' => '@inner'] argument), or rebind the service instead.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheBudgetAdmitsAtMostOneHundredTwentyEightDecorators(): void
    {
        $chain = [];
        for ($i = 0; $i < 64; ++$i) {
            $chain[] = self::TIMING_ENTRY;
        }

        $at = DecoratorMap::parse('decorators', ['a' => $chain, 'b' => $chain]);
        self::assertSame(128, array_sum(array_map(count(...), $at->decorations())));

        $over = array_merge($chain, [self::TIMING_ENTRY]);

        try {
            DecoratorMap::parse('decorators', ['a' => $over, 'b' => $chain]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                'The decoration budget is exceeded (128 decorators across every target).',
                $definitionException->getMessage(),
            );
        }
    }
}
