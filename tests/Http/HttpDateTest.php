<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpDate;

/**
 * @internal
 */
#[CoversClass(HttpDate::class)]
final class HttpDateTest extends TestCase
{
    private const string RFC_1123 = 'Mon, 01 Jan 2024 10:00:00 GMT';

    private const int RFC_1123_TIMESTAMP = 1704103200;

    public function testTheRfc1123FormParses(): void
    {
        $date = HttpDate::parse(self::RFC_1123);

        self::assertNotNull($date);
        self::assertSame(self::RFC_1123_TIMESTAMP, $date->getTimestamp());
    }

    /**
     * Engine-verified deviation from the reference: the abbreviation
     * specifier already accepts full day names, so the reference's `l, ...`
     * grammar variants were dead redundancy.
     */
    public function testFullDayNamesParseThroughTheAbbreviationSpecifier(): void
    {
        $date = HttpDate::parse('Monday, 01 Jan 2024 10:00:00 GMT');

        self::assertNotNull($date);
        self::assertSame(self::RFC_1123_TIMESTAMP, $date->getTimestamp());
    }

    public function testTheZoneLessFormIsReadInTheGmtFrame(): void
    {
        $date = HttpDate::parse('Mon, 01 Jan 2024 10:00:00');

        self::assertNotNull($date);
        self::assertSame(self::RFC_1123_TIMESTAMP, $date->getTimestamp());
    }

    public function testAnExplicitOffsetKeepsItsOwnFrame(): void
    {
        $date = HttpDate::parse('Mon, 01 Jan 2024 10:00:00 +05:00');

        self::assertNotNull($date);
        self::assertSame(self::RFC_1123_TIMESTAMP - 5 * 3600, $date->getTimestamp());
    }

    #[DataProvider('provideValuesNoGrammarShapeAcceptsAreRejectedCases')]
    public function testValuesNoGrammarShapeAcceptsAreRejected(string $value): void
    {
        self::assertNull(HttpDate::parse($value), "'{$value}' must not parse as an HTTP date");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValuesNoGrammarShapeAcceptsAreRejectedCases(): iterable
    {
        yield 'not a date' => ['not-a-date'];

        yield 'empty' => [''];

        yield 'day name only' => ['Mon'];

        yield 'trailing junk' => ['Mon, 01 Jan 2024 10:00:00 GMT junk'];

        yield 'rolled-over day' => ['Mon, 32 Jan 2024 10:00:00 GMT'];

        yield 'rolled-over second' => ['Mon, 01 Jan 2024 10:00:60 GMT'];

        yield 'dashed RFC 850 form' => ['Monday, 01-Jan-24 10:00:00 GMT'];
    }
}
