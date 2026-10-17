<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\CorrelationBudget;

/**
 * The attribute table's aggregate byte budget, covered as its own unit: the
 * rendered-width policy (a null renders four bytes, a bool four or five, an
 * integer twenty, a float twenty-four, a string its own byte width) and the
 * strictly-greater ceiling boundary the census spends against.
 *
 * @internal
 */
#[CoversClass(CorrelationBudget::class)]
final class CorrelationBudgetTest extends TestCase
{
    public function testTheCeilingBoundaryIsStrictlyGreater(): void
    {
        // 4 key bytes + 4 null bytes = 8; one more byte pair lands exactly on
        // the ceiling, which is still NOT exceeded; the byte after is.
        $correlationBudget = new CorrelationBudget(10);

        $correlationBudget->spend('abcd', null);

        self::assertFalse($correlationBudget->exceeded());

        $correlationBudget->spend('e', 'x');

        self::assertFalse($correlationBudget->exceeded());

        $correlationBudget->spend('f', 'x');

        self::assertTrue($correlationBudget->exceeded());
    }

    public function testANullRendersExactlyFourBytes(): void
    {
        $exactly = new CorrelationBudget(4);
        $exactly->spend('', null);

        self::assertFalse($exactly->exceeded());

        $below = new CorrelationBudget(3);
        $below->spend('', null);

        self::assertTrue($below->exceeded());
    }

    public function testABooleanRendersFourOrFiveBytes(): void
    {
        // Both boundary sides, so the widths are pinned exactly: a true
        // crosses a ceiling of three but not four, a false crosses four but
        // not five.
        $trueBelow = new CorrelationBudget(3);
        $trueBelow->spend('', true);

        self::assertTrue($trueBelow->exceeded());

        $trueExactly = new CorrelationBudget(4);
        $trueExactly->spend('', true);

        self::assertFalse($trueExactly->exceeded());

        $falseBelow = new CorrelationBudget(4);
        $falseBelow->spend('', false);

        self::assertTrue($falseBelow->exceeded());

        $falseExactly = new CorrelationBudget(5);
        $falseExactly->spend('', false);

        self::assertFalse($falseExactly->exceeded());
    }

    public function testAnIntegerRendersExactlyTwentyBytes(): void
    {
        $exactly = new CorrelationBudget(20);
        $exactly->spend('', 0);

        self::assertFalse($exactly->exceeded());

        $below = new CorrelationBudget(19);
        $below->spend('', 0);

        self::assertTrue($below->exceeded());
    }

    public function testAFloatRendersExactlyTwentyFourBytes(): void
    {
        $exactly = new CorrelationBudget(24);
        $exactly->spend('', 0.0);

        self::assertFalse($exactly->exceeded());

        $below = new CorrelationBudget(23);
        $below->spend('', 0.0);

        self::assertTrue($below->exceeded());
    }

    public function testAStringRendersItsOwnByteWidth(): void
    {
        // 'ä' is one character but two bytes on the wire the budget counts.
        $empty = new CorrelationBudget(0);
        $empty->spend('', '');

        self::assertFalse($empty->exceeded());

        $twoBytes = new CorrelationBudget(1);
        $twoBytes->spend('', 'ä');

        self::assertTrue($twoBytes->exceeded());

        $exactlyTwo = new CorrelationBudget(2);
        $exactlyTwo->spend('', 'ä');

        self::assertFalse($exactlyTwo->exceeded());
    }

    public function testTheKeyAndTheValueBothCount(): void
    {
        // 'key' renders three bytes and 'ab' two: five together cross a
        // ceiling that neither half crosses alone.
        $correlationBudget = new CorrelationBudget(4);

        $correlationBudget->spend('key', 'ab');

        self::assertTrue($correlationBudget->exceeded());
    }

    public function testSpendingAccumulatesAcrossEntries(): void
    {
        $correlationBudget = new CorrelationBudget(8);

        $correlationBudget->spend('a', null);
        $correlationBudget->spend('b', null);

        self::assertTrue($correlationBudget->exceeded());
    }
}
