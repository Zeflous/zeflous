<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\DateTimeType;
use Zef\Framework\Database\JsonType;
use Zef\Framework\Database\TypeMap;
use Zef\Framework\Database\TypeMapEncode;
use Zef\Framework\Database\TypeMappingException;

/**
 * Pins the type map's write-direction fold: the mapped columns
 * encoded to the driver-side vocabulary at query build, the null
 * value passing untouched (a nullable column's NULL is the
 * database's own answer), and every unmapped column travelling as
 * the host spelled it.
 *
 * @internal
 */
#[CoversClass(TypeMapEncode::class)]
final class TypeMapEncodeTest extends TestCase
{
    public function testTheMappedColumnsEncodeAlone(): void
    {
        $typeMap = new TypeMap(['payload' => new JsonType(), 'created_at' => new DateTimeType()]);

        self::assertSame(
            ['payload' => '{"a":1}', 'created_at' => '2026-11-05 03:14:15', 'note' => 'x'],
            TypeMapEncode::row(
                $typeMap,
                [
                    'payload' => ['a' => 1],
                    'created_at' => new DateTimeImmutable('2026-11-05 03:14:15'),
                    'note' => 'x',
                ],
            ),
        );
    }

    public function testTheNullValuePassesUntouched(): void
    {
        $typeMap = new TypeMap(['created_at' => new DateTimeType()]);

        self::assertSame(
            ['created_at' => null, 'note' => 'x'],
            TypeMapEncode::row($typeMap, ['created_at' => null, 'note' => 'x']),
            "a nullable column's NULL is the database's own answer, not an encoded anything",
        );
    }

    public function testTheUnmappedColumnNeverStopsTheWalk(): void
    {
        $typeMap = new TypeMap(['created_at' => new DateTimeType()]);

        self::assertSame(
            ['id' => 1, 'note' => null, 'created_at' => '2026-11-05 03:14:15'],
            TypeMapEncode::row(
                $typeMap,
                ['id' => 1, 'note' => null, 'created_at' => new DateTimeImmutable('2026-11-05 03:14:15')],
            ),
            'an unmapped column (and a null one) passes by, and the walk keeps walking',
        );
    }

    public function testTheWrongSideValueRefusesLoudly(): void
    {
        $typeMap = new TypeMap(['created_at' => new DateTimeType()]);

        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's write value must be DateTimeImmutable (got string)",
        );

        TypeMapEncode::row($typeMap, ['created_at' => '2026-11-05 03:14:15']);
    }
}
