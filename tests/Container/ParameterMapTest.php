<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\ParameterInjectability;
use Zef\Framework\Container\ParameterKeys;
use Zef\Framework\Container\ParameterLiterals;
use Zef\Framework\Container\ParameterMap;
use Zef\Framework\Container\ParameterProviderAnswers;
use Zef\Framework\Container\ParameterProviderCheck;
use Zef\Framework\Container\ParameterProviderConstruction;
use Zef\Framework\Container\ParameterProviderInterface;
use Zef\Framework\Container\ParameterProviders;
use Zef\Framework\Container\ParameterRefusals;
use Zef\Framework\Container\ParameterValues;
use Zef\Test\Support\Container\Parameters\NeedyProvider;
use Zef\Test\Support\Container\Parameters\NotAProvider;
use Zef\Test\Support\Container\Parameters\NullAnswerProvider;
use Zef\Test\Support\Container\Parameters\OptionalProvider;
use Zef\Test\Support\Container\Parameters\TimeoutsProvider;
use Zef\Test\Support\Container\Parameters\UninjectableAnswerProvider;
use Zef\Test\Support\Container\Parameters\UninjectableElementProvider;
use Zef\Test\Support\Container\Parameters\VersionProvider;

/**
 * @internal
 */
#[CoversClass(ParameterMap::class)]
#[CoversClass(ParameterInjectability::class)]
#[CoversClass(ParameterProviderCheck::class)]
#[CoversClass(ParameterProviderConstruction::class)]
#[CoversClass(ParameterProviderAnswers::class)]
#[CoversClass(ParameterValues::class)]
#[CoversClass(ParameterLiterals::class)]
#[CoversClass(ParameterKeys::class)]
#[CoversClass(ParameterProviders::class)]
#[CoversClass(ParameterRefusals::class)]
final class ParameterMapTest extends TestCase
{
    public function testANullKnobIsTheValidEmptyParameterSet(): void
    {
        $values = ParameterMap::parse('parameters', null)->values();

        self::assertSame([], $values->toArray());
        self::assertFalse($values->has('db.host'));
    }

    public function testANonMapKnobIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', 'nope');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' configuration must be a map of addresses to values, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerKnobIsRejectedWithItsType(): void
    {
        try {
            ParameterMap::parse('parameters', 7);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' configuration must be a map of addresses to values, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerKeyIsRejectedWithTheKeyNamed(): void
    {
        try {
            ParameterMap::parse('parameters', [5 => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' configuration keys must be dot-addresses, the integer key 5 given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testFlatScalarsAndNullFlattenVerbatim(): void
    {
        $values = ParameterMap::parse('parameters', [
            'app_name' => 'zef',
            'retries' => 3,
            'debug' => true,
            'ratio' => 0.5,
            'unset_thing' => null,
        ])->values();

        self::assertSame('zef', $values->value('app_name'));
        self::assertSame(3, $values->value('retries'));
        self::assertTrue($values->value('debug'));
        self::assertSame(0.5, $values->value('ratio'));
        self::assertNull($values->value('unset_thing'));
        self::assertTrue($values->has('unset_thing'));
        self::assertSame(
            ['app_name', 'retries', 'debug', 'ratio', 'unset_thing'],
            array_keys($values->toArray()),
        );
    }

    public function testNestedMapsJoinTheAddressWithDots(): void
    {
        $values = ParameterMap::parse('parameters', [
            'db' => ['host' => 'localhost', 'port' => 3306],
            'observability' => ['export' => ['timeout_ms' => 500]],
        ])->values();

        self::assertSame('localhost', $values->value('db.host'));
        self::assertSame(3306, $values->value('db.port'));
        self::assertSame(500, $values->value('observability.export.timeout_ms'));
        self::assertSame(
            ['db.host', 'db.port', 'observability.export.timeout_ms'],
            array_keys($values->toArray()),
        );
    }

    public function testAListIsTheParametersWholeValue(): void
    {
        $values = ParameterMap::parse('parameters', [
            'db' => ['replicas' => ['a.example', 'b.example']],
        ])->values();

        self::assertSame(['a.example', 'b.example'], $values->value('db.replicas'));
        self::assertFalse($values->has('db.replicas.0'));
        self::assertSame(['db.replicas'], array_keys($values->toArray()));
    }

    public function testAnEmptyArrayIsAValueNotAVanishingNode(): void
    {
        $values = ParameterMap::parse('parameters', [
            'empty_pool' => [],
        ])->values();

        self::assertTrue($values->has('empty_pool'));
        self::assertSame([], $values->value('empty_pool'));
    }

    public function testAListOfMapsTravelsWhole(): void
    {
        $values = ParameterMap::parse('parameters', [
            'clusters' => [
                ['name' => 'a', 'port' => 1],
                ['name' => 'b', 'port' => 2],
            ],
        ])->values();

        self::assertSame(
            [['name' => 'a', 'port' => 1], ['name' => 'b', 'port' => 2]],
            $values->value('clusters'),
        );
    }

    public function testAMixedNodeIsAValueNotAnAddressableMap(): void
    {
        $values = ParameterMap::parse('parameters', [
            'mixed' => [0 => 'a', 'host' => 'b'],
        ])->values();

        self::assertSame([0 => 'a', 'host' => 'b'], $values->value('mixed'));
    }

    public function testAnObjectLeafIsRejectedWithTheAddress(): void
    {
        try {
            ParameterMap::parse('parameters', ['db' => ['pool' => new stdClass()]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' value at 'db.pool' is not injectable, stdClass given "
                . '-- a parameter is a scalar, null or an array of such.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnObjectElementInsideAValueIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['pool' => ['a', new stdClass()]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' value at 'pool' is not injectable, stdClass given "
                . '-- a parameter is a scalar, null or an array of such.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnObjectElementDeepInsideAValueIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['pool' => ['edges' => ['inner' => [new stdClass()]]]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The 'parameters' value at 'pool.edges.inner' is not injectable, stdClass given",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyAddressIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['' => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' address '' is invalid: an address is 1 to 190 characters "
                . 'of dot-separated segments.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptySegmentIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['a..b' => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' address 'a..b' is invalid: the segment '' must match "
                . ParameterKeys::SEGMENT . ' (dot-separated; no colons -- the value-service '
                . 'idiom owns the colon).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testASegmentWithASpaceIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['a b' => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' address 'a b' is invalid: the segment 'a b' must match "
                . ParameterKeys::SEGMENT . ' (dot-separated; no colons -- the value-service '
                . 'idiom owns the colon).',
                $definitionException->getMessage(),
            );
        }
    }

    public function testASegmentWithAColonIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['db:port' => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The 'parameters' address 'db:port' is invalid: the segment 'db:port' must match",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAddressAtTheExactCapIsAccepted(): void
    {
        // 63 one-character segments, one 64-character segment: 190 characters exactly.
        $address = str_repeat('a.', 63) . str_repeat('b', 64);

        $values = ParameterMap::parse('parameters', [$address => 'x'])->values();

        self::assertSame('x', $values->value($address));
    }

    public function testAMultibyteAddressIsRefusedByTheSegmentGrammarNotTheCap(): void
    {
        // 100 characters -- 200 bytes: under the character cap, over the
        // byte cap, so the segment grammar is the check that refuses it.
        $address = str_repeat('é', 100);

        try {
            ParameterMap::parse('parameters', [$address => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "the segment '" . $address . "' must match",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAddressOverTheLengthCapIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', [str_repeat('a', 191) => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString('an address is 1 to 190 characters', $definitionException->getMessage());
        }
    }

    public function testAnOversizedSegmentIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', [str_repeat('a', 65) => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                'must match ' . ParameterKeys::SEGMENT,
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheWholeAlphabetOfASegmentIsAccepted(): void
    {
        $values = ParameterMap::parse('parameters', [
            'A-z_9-' => 'x',
            'UPPER.lower_dash' => 'y',
        ])->values();

        self::assertSame('x', $values->value('A-z_9-'));
        self::assertSame('y', $values->value('UPPER.lower_dash'));
    }

    public function testAValueBuiltDirectlyWithABadKeyIsRejected(): void
    {
        try {
            new ParameterValues(['a b' => 'x']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The 'parameters' address 'a b' is invalid",
                $definitionException->getMessage(),
            );
        }
    }

    public function testTheEmptyValuesConstructorIsTheNoParametersMap(): void
    {
        $values = ParameterValues::empty();

        self::assertSame([], $values->toArray());
        self::assertFalse($values->has('any.thing'));
    }

    public function testProvidersComputeValuesAtParse(): void
    {
        $values = ParameterMap::parse('parameters', [
            'providers' => [
                'app.version' => VersionProvider::class,
                'timeouts' => TimeoutsProvider::class,
            ],
            'db' => ['host' => 'localhost'],
        ])->values();

        self::assertSame('2.9.0-zeflous', $values->value('app.version'));
        self::assertSame(['connect' => 5, 'read' => 30], $values->value('timeouts'));
        self::assertSame('localhost', $values->value('db.host'));
    }

    public function testAProviderWithOptionalConstructorArgumentsIsAccepted(): void
    {
        $values = ParameterMap::parse('parameters', [
            'providers' => ['margin.plus_one' => OptionalProvider::class],
        ])->values();

        self::assertSame(5, $values->value('margin.plus_one'));
    }

    public function testAProviderAnsweringNullIsHonestData(): void
    {
        $values = ParameterMap::parse('parameters', [
            'providers' => ['nothing.here' => NullAnswerProvider::class],
        ])->values();

        self::assertTrue($values->has('nothing.here'));
        self::assertNull($values->value('nothing.here'));
    }

    public function testANonMapProvidersKeyIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => 'nope']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'providers' key under 'parameters' must be a map of addresses to "
                . 'parameter-provider class names, string given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerProvidersKeyIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => [VersionProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'providers' key under 'parameters' must map addresses to parameter-provider "
                . 'class names -- the key 0 names no address.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testANonStringProviderEntryIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['app.version' => 9]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The 'parameters' > 'providers' entry for 'app.version' must name a "
                . ParameterProviderInterface::class . ' class, int given.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMissingProviderClassIsRejected(): void
    {
        $missing = 'Zef\Test\Support\Container\Parameters\NoSuchProvider';

        try {
            ParameterMap::parse('parameters', ['providers' => ['app.version' => $missing]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter provider '{$missing}' (for the parameter 'app.version') does not exist.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAClassNotImplementingTheInterfaceIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['app.version' => NotAProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter provider '" . NotAProvider::class . "' (for the parameter 'app.version') must "
                . 'implement ' . ParameterProviderInterface::class . '.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAProviderDemandingConstructorArgumentsIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['app.needy' => NeedyProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter provider '" . NeedyProvider::class . "' (for the parameter 'app.needy') declares "
                . 'constructor parameters -- a provider constructs with none.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAProviderAnsweringAnObjectIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['app.thing' => UninjectableAnswerProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter provider '" . UninjectableAnswerProvider::class . "' (for the parameter "
                . "'app.thing') answered a non-injectable value, stdClass given -- a parameter is a "
                . 'scalar, null or an array of such.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAProviderAnsweringAnArrayWithAnObjectElementIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['app.things' => UninjectableElementProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter provider '" . UninjectableElementProvider::class . "' (for the parameter "
                . "'app.things') answered a non-injectable value, stdClass given -- a parameter is a "
                . 'scalar, null or an array of such.',
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnAddressSpelledByBothALiteralAndAProviderIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', [
                'providers' => ['db.host' => VersionProvider::class],
                'db' => ['host' => 'localhost'],
            ]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertSame(
                "The parameter 'db.host' is declared both as a literal value and through "
                . "'parameters' > 'providers' -- one address, one source.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testABadAddressInsideTheProvidersMapIsRejected(): void
    {
        try {
            ParameterMap::parse('parameters', ['providers' => ['a b' => VersionProvider::class]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "The 'parameters' > 'providers' address 'a b' is invalid",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyProvidersMapIsTheValidNoProvidersKnob(): void
    {
        $values = ParameterMap::parse('parameters', [
            'providers' => [],
            'db' => ['host' => 'localhost'],
        ])->values();

        self::assertSame(['db.host'], array_keys($values->toArray()));
    }
}
