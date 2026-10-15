<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BoundedReplayProtector;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ReplayDecision;

/**
 * The replay seen-table, on its own terms: the fresh sight's ACCEPT,
 * the re-sight's DUPLICATE, the window's exact edge (an id is still a
 * duplicate at the boundary instant, fresh again one millisecond
 * past it), the capacity bound's fail-closed UNAVAILABLE, the
 * malformed-id REJECTED, the sweep's selectivity, and the
 * constructor's own bounds.
 *
 * @internal
 */
#[CoversClass(BoundedReplayProtector::class)]
#[CoversClass(ReplayDecision::class)]
final class BoundedReplayProtectorTest extends TestCase
{
    private const int WINDOW = 300_000;

    public function testAnAbsentIdNeedsNoProtection(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(ReplayDecision::NOT_REQUIRED, $boundedReplayProtector->check(null, 1000));
    }

    public function testAFreshIdIsAccepted(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('replay-1', 1000));
    }

    public function testAnEmptyIdIsRejected(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(ReplayDecision::REJECTED, $boundedReplayProtector->check('', 1000));
    }

    public function testAnOverLongIdIsRejected(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(ReplayDecision::REJECTED, $boundedReplayProtector->check(str_repeat('r', 129), 1000));
    }

    public function testAnIdOfExactlyTheBoundIsAccepted(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check(str_repeat('r', 128), 1000));
    }

    public function testAReSightInsideTheWindowIsADuplicate(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        $boundedReplayProtector->check('replay-1', 1000);
        self::assertSame(ReplayDecision::DUPLICATE, $boundedReplayProtector->check('replay-1', 1000 + self::WINDOW));
    }

    public function testAReSightPastTheWindowIsFreshAgain(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        $boundedReplayProtector->check('replay-1', 1000);
        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('replay-1', 1000 + self::WINDOW + 1));
    }

    public function testADuplicateDoesNotRefreshTheWindow(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        $boundedReplayProtector->check('replay-1', 1000);
        $boundedReplayProtector->check('replay-1', 1000 + self::WINDOW);
        self::assertSame(
            ReplayDecision::ACCEPT,
            $boundedReplayProtector->check('replay-1', 1000 + self::WINDOW + 1),
            "the window is the arrival's own, never re-armed by the re-sight",
        );
    }

    public function testTheSweepExpiresOnlyTheStaleEntries(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        $boundedReplayProtector->check('stale', 1000);
        $boundedReplayProtector->check('fresh', 1000 + self::WINDOW / 2);
        $boundedReplayProtector->check('other', 1000 + self::WINDOW + 1);

        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('stale', 1000 + self::WINDOW + 1));
        self::assertSame(
            ReplayDecision::DUPLICATE,
            $boundedReplayProtector->check('fresh', 1000 + self::WINDOW + 1),
            'the half-window entry is still inside its own window',
        );
    }

    public function testTheSweepKeepsWalkingPastAFreshEntryToExpireTheStaleOneBehindIt(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector(1024, 100);

        $boundedReplayProtector->check('fresh-first', 5000);
        $boundedReplayProtector->check('stale-second', 1000);
        $boundedReplayProtector->check('probe', 5050);

        self::assertSame(
            ReplayDecision::ACCEPT,
            $boundedReplayProtector->check('stale-second', 5050),
            'the sweep must keep walking past the fresh entry to expire the stale one behind it',
        );
    }

    public function testAFullTableAnswersUnavailable(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector(2, self::WINDOW);

        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('first', 1000));
        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('second', 1000));
        self::assertSame(ReplayDecision::UNAVAILABLE, $boundedReplayProtector->check('third', 1000));
    }

    public function testAFullTableRecoversWhenTheWindowPasses(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector(1, self::WINDOW);

        $boundedReplayProtector->check('first', 1000);
        self::assertSame(ReplayDecision::UNAVAILABLE, $boundedReplayProtector->check('second', 2000));
        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('second', 1000 + self::WINDOW + 1));
    }

    public function testAZeroCapacityIsRefused(): void
    {
        try {
            new BoundedReplayProtector(0, self::WINDOW);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth replay capacity must be 1 or above.', $httpException->getMessage());
        }
    }

    public function testAZeroWindowIsRefused(): void
    {
        try {
            new BoundedReplayProtector(1, 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth replay window must be 1 or above.', $httpException->getMessage());
        }
    }

    public function testTheDefaultCapacityHoldsItsOwnBound(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        for ($entry = 1; $entry <= 1024; ++$entry) {
            self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check("replay-{$entry}", 1000));
        }

        self::assertSame(ReplayDecision::UNAVAILABLE, $boundedReplayProtector->check('replay-1025', 1000));
    }

    public function testTheDefaultWindowHoldsItsOwnSpan(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        $boundedReplayProtector->check('replay-1', 1000);
        self::assertSame(ReplayDecision::DUPLICATE, $boundedReplayProtector->check('replay-1', 1000 + 300_000));
    }

    public function testAWindowOfExactlyOneMillisecondIsLegal(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector(1, 1);

        self::assertSame(ReplayDecision::ACCEPT, $boundedReplayProtector->check('replay-1', 1000));
        self::assertSame(
            ReplayDecision::ACCEPT,
            $boundedReplayProtector->check('replay-1', 1002),
            'one millisecond past the one-millisecond window',
        );
    }

    public function testAMultiByteIdIsMeasuredInCharacters(): void
    {
        $boundedReplayProtector = new BoundedReplayProtector();

        self::assertSame(
            ReplayDecision::ACCEPT,
            $boundedReplayProtector->check(str_repeat('é', 65), 1000),
            '65 characters are 130 bytes, and the bound is 128 characters',
        );
    }

    /**
     * @dataProvider provideTheDecisionsOwnAdmissionPredicateCases
     */
    #[DataProvider('provideTheDecisionsOwnAdmissionPredicateCases')]
    public function testTheDecisionsOwnAdmissionPredicate(ReplayDecision $replayDecision, bool $allows): void
    {
        self::assertSame($allows, $replayDecision->allows());
    }

    /**
     * @return iterable<non-empty-string, array{ReplayDecision, bool}>
     */
    public static function provideTheDecisionsOwnAdmissionPredicateCases(): iterable
    {
        yield 'not required' => [ReplayDecision::NOT_REQUIRED, true];

        yield 'accept' => [ReplayDecision::ACCEPT, true];

        yield 'duplicate' => [ReplayDecision::DUPLICATE, false];

        yield 'stale' => [ReplayDecision::STALE, false];

        yield 'rejected' => [ReplayDecision::REJECTED, false];

        yield 'unavailable' => [ReplayDecision::UNAVAILABLE, false];
    }
}
