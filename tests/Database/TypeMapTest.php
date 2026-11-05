<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\DateTimeType;
use Zef\Framework\Database\JsonType;
use Zef\Framework\Database\TypeMap;
use Zef\Framework\Database\TypeMappingException;

/**
 * Pins the per-table type map as the VALUE: the loudly-validated
 * column-to-converter vocabulary, its public spelling readable in the
 * host's own column order, and the construction-time refusals that
 * name the offending key or entry.
 *
 * @internal
 */
#[CoversClass(TypeMap::class)]
final class TypeMapTest extends TestCase
{
    public function testTheVocabularyTravelsInTheHostsOwnColumnOrder(): void
    {
        $dateTimeType = new DateTimeType();
        $jsonType = new JsonType();

        $typeMap = new TypeMap(['created_at' => $dateTimeType, 'payload' => $jsonType]);

        self::assertSame(['created_at' => $dateTimeType, 'payload' => $jsonType], $typeMap->converters);
    }

    public function testAnEmptyColumnKeyRefuses(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The type map's column key must be a non-empty string");

        new TypeMap(['' => new JsonType()]);
    }

    public function testANonStringColumnKeyRefusesWithTheTypeNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The type map's column key must be a non-empty string (got int)");

        new TypeMap([0 => new JsonType()]);
    }

    public function testANonConverterEntryRefusesWithTheColumnNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The type map's 'payload' entry must be a ColumnType (got string)");

        new TypeMap(['payload' => 'json']);
    }
}
