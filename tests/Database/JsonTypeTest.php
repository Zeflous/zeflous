<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use JsonException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\JsonType;
use Zef\Framework\Database\TypeMappingException;

/**
 * Pins the JSON column's converter: the array with unicode keys
 * round-tripping equal both directions (under JSON_THROW_ON_ERROR, the
 * tree's own encode lineage), the exact compact spelling the boundary
 * stores, the scalar JSON literals, and the loud refusals -- an object
 * never accepted (the read direction answers arrays, never stdClass),
 * an unencodable value never silently skipped, a malformed cell the
 * named refusal, never a silent null.
 *
 * @internal
 */
#[CoversClass(JsonType::class)]
final class JsonTypeTest extends TestCase
{
    private JsonType $jsonType;

    #[Override]
    protected function setUp(): void
    {
        $this->jsonType = new JsonType();
    }

    public function testTheWriteDirectionAnswersTheCompactSpelling(): void
    {
        self::assertSame('{"a":1}', $this->jsonType->toDatabase(['a' => 1]));
        self::assertSame('[1,2,3]', $this->jsonType->toDatabase([1, 2, 3]));
    }

    public function testTheScalarLiteralsTravelAsTheirOwnJsonSpellings(): void
    {
        self::assertSame('true', $this->jsonType->toDatabase(true));
        self::assertSame('123', $this->jsonType->toDatabase(123));
        self::assertSame('"text"', $this->jsonType->toDatabase('text'));
        self::assertSame('null', $this->jsonType->toDatabase(null));
    }

    public function testTheArrayWithUnicodeKeysRoundTripsEqual(): void
    {
        $payload = ['kunci' => 'nilai', '日本語' => ['x' => 1], 'flag' => true, 'hole' => null];

        $stored = $this->jsonType->toDatabase($payload);

        self::assertSame($payload, $this->jsonType->toPhp($stored));
    }

    public function testTheNestedArrayRoundTripsEqual(): void
    {
        $payload = ['rows' => [['id' => 1, 'tags' => ['a', 'b']], ['id' => 2, 'tags' => []]], 'count' => 2];

        self::assertSame($payload, $this->jsonType->toPhp($this->jsonType->toDatabase($payload)));
    }

    public function testAnObjectRefusesWithTheTypeNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The JSON column's write value must be an array or a scalar (got stdClass)",
        );

        $this->jsonType->toDatabase((object) ['a' => 1]);
    }

    public function testAnUnencodableValueRefusesAsAnEncodingFailure(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The JSON column's write value cannot be encoded (Inf and NaN cannot be JSON encoded).",
        );

        $this->jsonType->toDatabase(\NAN);
    }

    public function testTheUnencodableValueRefusalCarriesTheEngineCause(): void
    {
        try {
            $this->jsonType->toDatabase(\NAN);
            self::fail('the unencodable value is the named refusal');
        } catch (TypeMappingException $typeMappingException) {
            self::assertSame(
                "The JSON column's write value cannot be encoded (Inf and NaN cannot be JSON encoded).",
                $typeMappingException->getMessage(),
            );
            self::assertSame(0, $typeMappingException->getCode(), 'no engine code is claimed');
            self::assertInstanceOf(JsonException::class, $typeMappingException->getPrevious());
        }
    }

    public function testANonStringCellRefusesWithTheAffinityTrapNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The JSON column's stored cell must be a string (got int) -- a bare scalar cell"
            . ' is the SQLite numeric-affinity trap, not an encoded value',
        );

        $this->jsonType->toPhp(123);
    }

    public function testAMalformedCellIsTheNamedRefusalNeverASilentNull(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The JSON column's stored cell '{oops' is malformed JSON -- the named refusal, never a silent null",
        );

        $this->jsonType->toPhp('{oops');
    }

    public function testTheEmptyStringIsAMalformedCellToo(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The JSON column's stored cell '' is malformed JSON");

        $this->jsonType->toPhp('');
    }

    public function testTheMalformedCellRefusalCarriesTheEngineCause(): void
    {
        try {
            $this->jsonType->toPhp('{oops');
            self::fail('the malformed cell is the named refusal');
        } catch (TypeMappingException $typeMappingException) {
            self::assertSame(0, $typeMappingException->getCode(), 'no engine code is claimed');
            self::assertInstanceOf(JsonException::class, $typeMappingException->getPrevious());
        }
    }
}
