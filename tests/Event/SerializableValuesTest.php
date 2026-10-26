<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\SerializableValues;

/**
 * Pins the JSON-round-trip walk: every kind the shape's vocabulary
 * admits when it can survive the spelling, and every offense the
 * walk names by kind and path -- the object, the resource, the
 * non-finite float, the string that is not UTF-8, the non-string
 * root key -- with the nested positions spelled into the path.
 *
 * @internal
 */
#[CoversClass(SerializableValues::class)]
final class SerializableValuesTest extends TestCase
{
    public function testACleanScalarMapWalksClean(): void
    {
        self::assertNull(SerializableValues::offense([
            'name' => 'Ada',
            'age' => 36,
            'ratio' => 0.5,
            'active' => true,
            'note' => null,
        ]));
    }

    public function testNestedListsAndMapsWalkClean(): void
    {
        self::assertNull(SerializableValues::offense([
            'tags' => ['alpha', 'beta'],
            'user' => ['name' => 'Ada', 'scopes' => ['read', 'write']],
            'empty' => [],
        ]));
    }

    public function testAFloatWithWholeValueWalksClean(): void
    {
        self::assertNull(SerializableValues::offense(['ratio' => 1.0]));
    }

    public function testAnObjectInsideAListIsNamedWithItsPosition(): void
    {
        $offense = SerializableValues::offense([
            'tags' => ['alpha', (object) ['name' => 'beta']],
        ]);

        self::assertSame('stdClass at tags[1]', $offense);
    }

    public function testAnObjectAtTheRootIsNamed(): void
    {
        $offense = SerializableValues::offense(['owner' => (object) []]);

        self::assertSame('stdClass at owner', $offense);
    }

    public function testAResourceIsNamedWithItsType(): void
    {
        $stream = fopen('php://memory', 'rb');
        self::assertIsResource($stream);

        try {
            $offense = SerializableValues::offense(['handle' => $stream]);

            self::assertSame('resource (stream) at handle', $offense);
        } finally {
            fclose($stream);
        }
    }

    public function testANestedObjectIsNamedWithItsNestedPath(): void
    {
        $offense = SerializableValues::offense([
            'user' => ['scopes' => ['read', (object) []]],
        ]);

        self::assertSame('stdClass at user[scopes][1]', $offense);
    }

    public function testNonFiniteFloatsAreNamed(): void
    {
        self::assertSame(
            'NAN at ratio',
            SerializableValues::offense(['ratio' => \NAN]),
        );
        self::assertSame(
            'INF at ratio',
            SerializableValues::offense(['ratio' => \INF]),
        );
        self::assertSame(
            '-INF at ratio',
            SerializableValues::offense(['ratio' => -\INF]),
        );
    }

    public function testAStringThatIsNotValidUtf8IsNamed(): void
    {
        $offense = SerializableValues::offense(['memo' => "\xB1\x31"]);

        self::assertSame('a string that is not valid UTF-8 at memo', $offense);
    }

    public function testAStringValueInsideAListThatIsNotValidUtf8IsNamed(): void
    {
        $offense = SerializableValues::offense(['tags' => ["\xB1\x31"]]);

        self::assertSame('a string that is not valid UTF-8 at tags[0]', $offense);
    }

    public function testANonStringRootKeyIsNamed(): void
    {
        $offense = SerializableValues::offense([0 => 'zero']);

        self::assertSame('a non-string key 0 at the payload root', $offense);
    }

    public function testNestedIntegerKeysAreListsAndWalkClean(): void
    {
        self::assertNull(SerializableValues::offense([
            'matrix' => [[1, 2], [3, 4]],
            'sparse' => ['name' => 'Ada', 0 => 'first'],
        ]));
    }

    public function testTheFirstOffenseWinsWhenSeveralOffend(): void
    {
        $offense = SerializableValues::offense([
            'alpha' => \NAN,
            'beta' => (object) [],
        ]);

        self::assertSame('NAN at alpha', $offense);
    }

    public function testTheEmptyMapWalksClean(): void
    {
        self::assertNull(SerializableValues::offense([]));
    }
}
