<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\SessionEntries;

/**
 * The entries fold on its own terms: the write side of the session
 * port's in-memory bag, pinned at the unit seam the fold itself owns.
 * The adapter hands the clock reading in as a parameter ({@see
 * InMemorySessionAdapter} holds the injectable clock), so every
 * boundary here is a plain integer comparison -- no frozen clock
 * needed -- and the two clocks the fold keeps are pinned separately:
 * the idle timeout decides which entries a sweep takes (the timeout
 * fully elapsed is expired, the last instant before it is not -- the
 * same boundary the load path refuses at), while the cadence bounds
 * how often the walk runs at all (at most once per its sixty
 * seconds, riding a save that was already paying a write).
 *
 * The write-before-sweep order is load-bearing: the entry a save just
 * wrote always survives that same save's sweep (its own reading is
 * zero seconds old), which is what keeps the returning client's save
 * from being retired by the very walk it triggered -- the sweep
 * exists for the entry no load will ever revisit, the round-3
 * audit's R3-4, not for the writer itself.
 *
 * @internal
 */
#[CoversClass(SessionEntries::class)]
final class SessionEntriesTest extends TestCase
{
    public function testTheSavedRoundTripAnswersTheCell(): void
    {
        $sessionEntries = new SessionEntries();

        $sessionEntries->saved('AQ', ['user' => 7, 'cart' => ['coffee', 'filters']], 1000, 1800);

        self::assertSame(
            ['data' => ['user' => 7, 'cart' => ['coffee', 'filters']], 'writtenAt' => 1000],
            $sessionEntries->entry('AQ'),
        );
    }

    public function testAnUnknownIdentifierAnswersNull(): void
    {
        self::assertNull(new SessionEntries()->entry('AQ'));
    }

    public function testEverySaveRefreshesTheWrittenAtReading(): void
    {
        $sessionEntries = new SessionEntries();
        $sessionEntries->saved('AQ', ['user' => 7], 1000, 1800);

        $sessionEntries->saved('AQ', ['user' => 7, 'cart' => ['coffee']], 2000, 1800);

        self::assertSame(
            ['data' => ['user' => 7, 'cart' => ['coffee']], 'writtenAt' => 2000],
            $sessionEntries->entry('AQ'),
            'every save refreshes the written-at reading, so activity extends the session',
        );
    }

    public function testDestroyRetiresTheCellInPlace(): void
    {
        $sessionEntries = new SessionEntries();
        $sessionEntries->saved('AQ', ['user' => 7], 1000, 1800);

        $sessionEntries->destroy('AQ');

        self::assertNull($sessionEntries->entry('AQ'));
    }

    public function testDestroyingAnUnknownIdentifierIsTheNoOpItSpells(): void
    {
        new SessionEntries()->destroy('AQ');

        self::expectNotToPerformAssertions();
    }

    public function testTheSavePathSweepsTheEntryWhoseTimeoutFullyElapsed(): void
    {
        $sessionEntries = new SessionEntries();
        $sessionEntries->saved('abandoned', ['user' => 7], 1000, 1800);

        $sessionEntries->saved('returning', ['user' => 8], 2800, 1800);

        self::assertNull(
            $sessionEntries->entry('abandoned'),
            'the abandoned entry whose timeout fully elapsed (2800 - 1000 = 1800) was swept '
            . 'at the returning save; no load will ever revisit it',
        );
        self::assertSame(
            ['data' => ['user' => 8], 'writtenAt' => 2800],
            $sessionEntries->entry('returning'),
            "the entry the triggering save just wrote survives that same save's sweep",
        );
    }

    public function testTheSweepSparesTheEntryAtTheLastInstantBeforeItsTimeout(): void
    {
        $sessionEntries = new SessionEntries();
        $sessionEntries->saved('AQ', ['user' => 7], 1000, 1800);

        $sessionEntries->saved('later', ['user' => 8], 2799, 1800);

        self::assertSame(
            ['data' => ['user' => 7], 'writtenAt' => 1000],
            $sessionEntries->entry('AQ'),
            'the last instant before the timeout (2799 - 1000 = 1799) is not expired; '
            . 'the sweep takes it only when the timeout fully elapses',
        );
    }

    public function testTheSweepRunsAtMostOncePerItsCadence(): void
    {
        $sessionEntries = new SessionEntries();

        // The first save sweeps immediately (nothing to take yet) and
        // arms the cadence: the walk runs again no sooner than sixty
        // seconds later.
        $sessionEntries->saved('a', ['n' => 1], 1000, 10);

        $sessionEntries->saved('b', ['n' => 2], 1059, 10);

        self::assertSame(
            ['data' => ['n' => 1], 'writtenAt' => 1000],
            $sessionEntries->entry('a'),
            'fifty-nine seconds since the sweep: the cadence held, so the entry the walk '
            . "has not yet reached is still the bag's to answer even though its own "
            . 'timeout has fully elapsed',
        );

        $sessionEntries->saved('c', ['n' => 3], 1060, 10);

        self::assertNull(
            $sessionEntries->entry('a'),
            'sixty seconds since the sweep: the cadence elapsed at the third save, and '
            . 'the walk retired the entry whose timeout had fully elapsed',
        );
    }
}
