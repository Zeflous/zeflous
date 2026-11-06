<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\DateTimeType;
use Zef\Framework\Database\JsonType;
use Zef\Framework\Database\TypeMap;
use Zef\Framework\Database\TypeMapDecode;
use Zef\Framework\Database\TypeMappingException;

/**
 * Pins the type map's read-direction fold: the narrowed rows' mapped
 * columns decoded after the port's own narrowing, the null cell
 * passing untouched (a nullable column answers NULL before it answers
 * anything a converter could decode), every unmapped column travelling
 * as the port answered it, and the row-list walk preserving shape and
 * order.
 *
 * @internal
 */
#[CoversClass(TypeMapDecode::class)]
final class TypeMapDecodeTest extends TestCase
{
    public function testTheMappedColumnsDecodeAlone(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType(), 'created_at' => new DateTimeType()]);

        $decoded = TypeMapDecode::row(
            $typeMap,
            ['payload' => '{"a":1}', 'created_at' => '2026-11-05 03:14:15', 'note' => 'x'],
        );

        self::assertSame(['a' => 1], $decoded['payload']);
        $decodedCreatedAt = $decoded['created_at'];
        self::assertInstanceOf(DateTimeImmutable::class, $decodedCreatedAt);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $decodedCreatedAt->getTimestamp(),
        );
        self::assertSame('x', $decoded['note']);
    }

    public function testTheNullCellPassesUntouched(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType()]);

        self::assertSame(
            ['payload' => null, 'id' => 1],
            TypeMapDecode::row($typeMap, ['payload' => null, 'id' => 1]),
            'a nullable column answers NULL before it answers anything a converter could decode',
        );
    }

    public function testTheRowListWalkPreservesShapeAndOrder(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType()]);

        self::assertSame(
            [['payload' => ['first' => 1], 'id' => 1], ['payload' => ['second' => 2], 'id' => 2]],
            TypeMapDecode::rows(
                $typeMap,
                [['payload' => '{"first":1}', 'id' => 1], ['payload' => '{"second":2}', 'id' => 2]],
            ),
        );
    }

    public function testTheUnmappedColumnNeverStopsTheWalk(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType()]);

        self::assertSame(
            ['id' => 1, 'note' => null, 'payload' => ['a' => 1]],
            TypeMapDecode::row($typeMap, ['id' => 1, 'note' => null, 'payload' => '{"a":1}']),
            'an unmapped column (and a null one) passes by, and the walk keeps walking',
        );
    }

    public function testTheMalformedCellIsTheNamedRefusalOnTheFoldToo(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType()]);

        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The JSON column's stored cell '{oops' is malformed JSON");

        TypeMapDecode::row($typeMap, ['payload' => '{oops']);
    }
}
