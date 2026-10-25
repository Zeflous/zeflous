<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\InMemorySessionAdapter;
use Zef\Test\Support\Http\MutableClock;

/**
 * The session port's in-memory driver, on its own terms: the
 * save/load round-trip, the unknown identifier's null, the destroy
 * that answers as though the session never existed -- and the
 * sliding expiry under the frozen clock, boundary included: the
 * timeout fully elapsed is expired, the last instant before it is
 * not, and every save refreshes the idle clock so activity extends
 * a session and only write-idleness ends one. The save-path sweep
 * retires the abandoned entry no load will ever revisit, at most
 * once per its cadence (the round-3 audit's R3-4).
 *
 * @internal
 */
#[CoversClass(InMemorySessionAdapter::class)]
final class InMemorySessionAdapterTest extends TestCase
{
    public function testTheSaveLoadRoundTripAnswersTheBag(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);

        $inMemorySessionAdapter->save('AQ', ['user' => 7, 'cart' => ['coffee', 'filters']]);

        self::assertSame(
            ['user' => 7, 'cart' => ['coffee', 'filters']],
            $inMemorySessionAdapter->load('AQ'),
        );
    }

    public function testAnUnknownIdentifierAnswersNull(): void
    {
        self::assertNull(new InMemorySessionAdapter(1800)->load('AQ'));
    }

    public function testDestroyAnswersAsThoughTheSessionNeverExisted(): void
    {
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800);
        $inMemorySessionAdapter->save('AQ', ['user' => 7]);

        $inMemorySessionAdapter->destroy('AQ');

        self::assertNull($inMemorySessionAdapter->load('AQ'));
    }

    public function testDestroyingAnUnknownIdentifierIsTheNoOpItSpells(): void
    {
        new InMemorySessionAdapter(1800)->destroy('AQ');

        self::expectNotToPerformAssertions();
    }

    public function testTheIdleBoundaryIsTheLastInstantBeforeTheTimeout(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save('AQ', ['user' => 7]);

        $mutableClock->advance(1799);

        self::assertSame(
            ['user' => 7],
            $inMemorySessionAdapter->load('AQ'),
            'the last instant before the timeout is alive',
        );
    }

    public function testTheTimeoutFullyElapsedIsExpired(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save('AQ', ['user' => 7]);

        $mutableClock->advance(1800);

        self::assertNull($inMemorySessionAdapter->load('AQ'), 'the timeout fully elapsed answers null');
    }

    public function testAnExpiredEntryIsPurgedAtItsOwnLoad(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save('AQ', ['user' => 7]);

        $mutableClock->advance(1800);
        $inMemorySessionAdapter->load('AQ');

        $mutableClock->advance(-1);

        self::assertNull(
            $inMemorySessionAdapter->load('AQ'),
            'the entry was purged at the expired load, not resurrected by a clock the caller wound back',
        );
    }

    public function testEverySaveRefreshesTheIdleClock(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save('AQ', ['user' => 7]);

        $mutableClock->advance(1000);
        $inMemorySessionAdapter->save('AQ', ['user' => 7, 'cart' => ['coffee']]);

        $mutableClock->advance(1799);

        self::assertSame(
            ['user' => 7, 'cart' => ['coffee']],
            $inMemorySessionAdapter->load('AQ'),
            'the second save refreshed the idle clock, so activity extended the session',
        );
    }

    public function testTheSavePathSweepsTheEntriesNoLoadWillEverRevisit(): void
    {
        // The round-3 audit's R3-4: the client that minted, was issued,
        // and never returned leaves an entry no load will ever revisit
        // -- the save path sweeps it, so abandoned sessions cannot grow
        // the bag one entry per abandonment.
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(1800, $mutableClock->now());
        $inMemorySessionAdapter->save('abandoned', ['user' => 7]);

        $mutableClock->advance(1861);
        $inMemorySessionAdapter->save('returning', ['user' => 8]);

        $mutableClock->advance(-1860);

        self::assertNull(
            $inMemorySessionAdapter->load('abandoned'),
            'the sweep retired the abandoned entry at the returning save; '
            . 'the wound-back clock cannot resurrect what the sweep already took',
        );
        self::assertSame(
            ['user' => 8],
            $inMemorySessionAdapter->load('returning'),
            'the returning save survives its own sweep',
        );
    }

    public function testTheSweepRunsAtMostOncePerItsCadence(): void
    {
        $mutableClock = new MutableClock(1000);
        $inMemorySessionAdapter = new InMemorySessionAdapter(10, $mutableClock->now());

        // The first save sweeps and finds nothing expired.
        $inMemorySessionAdapter->save('a', ['n' => 1]);

        $mutableClock->advance(30);
        // 'a' is expired on paper, but only 30 seconds since the sweep.
        $inMemorySessionAdapter->save('b', ['n' => 2]);

        $mutableClock->advance(-25);

        self::assertSame(
            ['n' => 1],
            $inMemorySessionAdapter->load('a'),
            'the cadence held: the entry the sweep has not yet reached is still the load\'s to answer',
        );

        $mutableClock->advance(56);
        // 61 seconds since the sweep at 1000: the cadence elapses.
        $inMemorySessionAdapter->save('c', ['n' => 3]);

        self::assertNull(
            $inMemorySessionAdapter->load('a'),
            'the cadence elapsed at the third save, and the sweep retired the expired entry',
        );
    }
}
