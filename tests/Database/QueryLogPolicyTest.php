<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\QueryLogBounds;
use Zef\Framework\Database\QueryLogException;
use Zef\Framework\Database\QueryLogPolicy;

/**
 * Pins the query-log family's policy: the constructor defaults a
 * spelled block arms the family with (the arm ON within a spelled
 * block, the default bar at a tenth of the slow-request family's own
 * one-second weight), the corridor the threshold must hold at BOTH
 * construction paths, and the bar's own arithmetic -- the strictly-
 * exceeds verdict held in integer nanoseconds, so the exact boundary
 * (a span that MEETS the bar is not slow, a span one nanosecond over
 * it is) is a pin no sleep-based test could spell, and a broken
 * clock seam's negative span fabricates nothing.
 *
 * @internal
 */
#[CoversClass(QueryLogPolicy::class)]
#[CoversClass(QueryLogBounds::class)]
#[CoversClass(QueryLogException::class)]
final class QueryLogPolicyTest extends TestCase
{
    public function testAConstructedListingCarriesTheFamilyDefaults(): void
    {
        $queryLogPolicy = new QueryLogPolicy();

        self::assertTrue($queryLogPolicy->enabled, 'a spelled block arms the family with the arm ON');
        self::assertSame(100, $queryLogPolicy->thresholdMs, 'the default bar is a tenth of the request bar');
    }

    public function testBothCorridorEndsAreLegal(): void
    {
        $floor = new QueryLogPolicy(thresholdMs: 1);
        $ceiling = new QueryLogPolicy(thresholdMs: 3600000);

        self::assertSame(1, $floor->thresholdMs);
        self::assertSame(3600000, $ceiling->thresholdMs);
    }

    public function testAZeroBarHalts(): void
    {
        $this->assertOutOfCorridor(
            0,
            'The database.query_log threshold_ms knob must be between 1 and 3600000, 0 given.',
        );
    }

    public function testANegativeBarHalts(): void
    {
        $this->assertOutOfCorridor(
            -1,
            'The database.query_log threshold_ms knob must be between 1 and 3600000, -1 given.',
        );
    }

    public function testAnHourDeepBarAndBeyondHalts(): void
    {
        $this->assertOutOfCorridor(
            3600001,
            'The database.query_log threshold_ms knob must be between 1 and 3600000, 3600001 given.',
        );
    }

    public function testASpanThatExactlyMeetsTheBarIsNotSlow(): void
    {
        $queryLogPolicy = new QueryLogPolicy(thresholdMs: 100);

        self::assertFalse($queryLogPolicy->exceededBy(100 * 1000000));
    }

    public function testASpanOneNanosecondOverTheBarIsSlow(): void
    {
        $queryLogPolicy = new QueryLogPolicy(thresholdMs: 100);

        self::assertTrue($queryLogPolicy->exceededBy(100 * 1000000 + 1));
    }

    public function testTheVerdictHoldsAtTheCorridorFloor(): void
    {
        $queryLogPolicy = new QueryLogPolicy(thresholdMs: 1);

        self::assertFalse($queryLogPolicy->exceededBy(999999));
        self::assertTrue($queryLogPolicy->exceededBy(1000001));
    }

    public function testABrokenClockSeamsNegativeSpanFabricatesNothing(): void
    {
        $queryLogPolicy = new QueryLogPolicy(thresholdMs: 1);

        self::assertFalse(
            $queryLogPolicy->exceededBy(-1000000),
            'a negative span exceeds no positive bar, whatever the clock seam answered',
        );
    }

    private function assertOutOfCorridor(int $thresholdMs, string $expectedMessage): void
    {
        try {
            new QueryLogPolicy(thresholdMs: $thresholdMs);
        } catch (QueryLogException $queryLogException) {
            self::assertSame($expectedMessage, $queryLogException->getMessage());

            return;
        }

        self::fail('The out-of-corridor threshold must halt the construction.');
    }
}
