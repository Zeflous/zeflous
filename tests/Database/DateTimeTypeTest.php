<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zef\Framework\Database\DateTimeType;
use Zef\Framework\Database\TypeMappingException;

/**
 * Pins the datetime column's converter: the UTC-canonical boundary
 * posture (the Jakarta-zone instant in, the UTC wall spelling out; the
 * stored spelling in, the equal immutable instant back), the
 * whole-second canonical the driver's own clock answers, the
 * offset-carrying foreign spelling that still wins, and the loud
 * refusals both directions own -- the mutable never accepted, the
 * malformed cell never silently coerced.
 *
 * @internal
 */
#[CoversClass(DateTimeType::class)]
final class DateTimeTypeTest extends TestCase
{
    private DateTimeType $dateTimeType;

    #[Override]
    protected function setUp(): void
    {
        $this->dateTimeType = new DateTimeType();
    }

    public function testTheWriteDirectionAnswersTheUtcWallSpelling(): void
    {
        $jakarta = new DateTimeImmutable('2026-11-05 10:14:15', new DateTimeZone('Asia/Jakarta'));

        self::assertSame('2026-11-05 03:14:15', $this->dateTimeType->toDatabase($jakarta));
    }

    public function testTheReadDirectionAnchorsTheOffsetFreeSpellingAsUtc(): void
    {
        $read = $this->dateTimeType->toPhp('2026-11-05 03:14:15');

        self::assertInstanceOf(DateTimeImmutable::class, $read);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $read->getTimestamp(),
        );
        self::assertSame('UTC', $read->getTimezone()->getName());
    }

    public function testTheRoundTripAnswersTheEqualInstant(): void
    {
        $jakarta = new DateTimeImmutable('2026-11-05 10:14:15.000000', new DateTimeZone('Asia/Jakarta'));

        $read = $this->dateTimeType->toPhp($this->dateTimeType->toDatabase($jakarta));

        self::assertInstanceOf(DateTimeImmutable::class, $read);
        self::assertSame(
            $jakarta->getTimestamp(),
            $read->getTimestamp(),
            'the instant the host handed in is the instant the boundary hands back',
        );
        self::assertSame(0, $read->getOffset(), 'and the answer travels anchored in the canonical zone');
        self::assertSame('2026-11-05 03:14:15 +00:00', $read->format('Y-m-d H:i:s P'));
    }

    public function testTheOffsetCarryingSpellingStillWinsOverTheAnchor(): void
    {
        $read = $this->dateTimeType->toPhp('2026-11-05T10:14:15+07:00');

        self::assertInstanceOf(DateTimeImmutable::class, $read);
        self::assertSame(
            new DateTimeImmutable('2026-11-05 03:14:15 UTC')->getTimestamp(),
            $read->getTimestamp(),
        );
    }

    public function testTheCanonicalSpellingCarriesWholeSeconds(): void
    {
        $withMicroseconds = new DateTimeImmutable('2026-11-05 03:14:15.987654 UTC');

        self::assertSame(
            '2026-11-05 03:14:15',
            $this->dateTimeType->toDatabase($withMicroseconds),
            "the spelling the driver's own CURRENT_TIMESTAMP answers -- microseconds are the DATETIME(6) follow-up",
        );
    }

    public function testTheMutableNeverCrossesTheBoundary(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's write value must be DateTimeImmutable (got DateTime)",
        );

        // The mutable can only enter through the reflection seam: the
        // tree's own fixer rewrites every literal DateTime symbol into
        // the immutable -- the idiom enforced by the toolchain, refused
        // by the boundary, pinned here over the one mutable spelling.
        $mutable = new ReflectionClass('DateTime')->newInstance();

        $this->dateTimeType->toDatabase($mutable);
    }

    public function testAStringValueRefusesWithTheTypeNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's write value must be DateTimeImmutable (got string)",
        );

        $this->dateTimeType->toDatabase('2026-11-05 03:14:15');
    }

    public function testANullWriteValueRefusesWithTheTypeNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's write value must be DateTimeImmutable (got null)",
        );

        $this->dateTimeType->toDatabase(null);
    }

    public function testANonStringCellRefusesWithTheTypeNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains("The datetime column's stored cell must be a string (got int)");

        $this->dateTimeType->toPhp(1);
    }

    public function testAMalformedCellRefusesWithTheSpellingNamed(): void
    {
        $this->expectException(TypeMappingException::class);
        $this->expectExceptionMessageIsOrContains(
            "The datetime column's stored cell 'not a datetime' is not a spelling the immutable clock reads",
        );

        $this->dateTimeType->toPhp('not a datetime');
    }

    public function testTheMalformedCellRefusalCarriesTheEngineCause(): void
    {
        try {
            $this->dateTimeType->toPhp('not a datetime');
            self::fail('the malformed cell is the named refusal');
        } catch (TypeMappingException $typeMappingException) {
            self::assertSame(0, $typeMappingException->getCode(), 'no engine code is claimed');
            self::assertInstanceOf(DateMalformedStringException::class, $typeMappingException->getPrevious());
        }
    }
}
