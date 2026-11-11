<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Database\PdoParamTypes;

/**
 * The positional value's PDO parameter type: the mapping matrix every
 * bound value rides -- bool the boolean binding, int the integer
 * binding, null the NULL binding, and the string/float families the
 * string binding they share -- the engine-side spelling a `false`
 * value keeps (its own zero, never the empty string the array-lane
 * binding would hand the driver).
 *
 * @internal
 */
#[CoversClass(PdoParamTypes::class)]
final class PdoParamTypesTest extends TestCase
{
    #[DataProvider('provideTheValueRidesItsOwnEngineSideTypeCases')]
    public function testTheValueRidesItsOwnEngineSideType(mixed $value, int $expectedType): void
    {
        self::assertSame($expectedType, PdoParamTypes::of($value));
    }

    /**
     * @return iterable<non-empty-string, array{mixed, int}>
     */
    public static function provideTheValueRidesItsOwnEngineSideTypeCases(): iterable
    {
        yield 'false answers the boolean binding' => [false, PDO::PARAM_BOOL];

        yield 'true answers the boolean binding' => [true, PDO::PARAM_BOOL];

        yield 'zero answers the integer binding' => [0, PDO::PARAM_INT];

        yield 'a negative int answers the integer binding' => [-1, PDO::PARAM_INT];

        yield 'null answers the NULL binding' => [null, PDO::PARAM_NULL];

        yield 'the empty string answers the string binding' => ['', PDO::PARAM_STR];

        yield 'a float answers the string binding' => [1.5, PDO::PARAM_STR];
    }
}
