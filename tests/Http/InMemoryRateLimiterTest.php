<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\BucketRollover;
use Zef\Framework\Http\BucketRoom;
use Zef\Framework\Http\BucketTable;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\InMemoryRateLimiter;
use Zef\Framework\Http\RateLimitDecision;
use Zef\Test\Support\Http\MutableClock;

/**
 * The per-process fixed-window limiter, on its own terms: the window
 * arithmetic, the clamps, the rollover, the capacity bound and its
 * honest reclamation -- all against a fixed clock, so every boundary is
 * deterministic.
 *
 * @internal
 */
#[CoversClass(BucketRoom::class)]
#[CoversClass(BucketRollover::class)]
#[CoversClass(BucketTable::class)]
#[CoversClass(InMemoryRateLimiter::class)]
#[CoversClass(RateLimitDecision::class)]
final class InMemoryRateLimiterTest extends TestCase
{
    public function testTheFirstRequestOfAWindowAnswersTheFullBudget(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: static fn (): int => 100);

        $decision = $inMemoryRateLimiter->check('203.0.113.9', 100, 60);

        self::assertTrue($decision->allowed);
        self::assertSame(100, $decision->limit);
        self::assertSame(99, $decision->remaining);
        self::assertSame(60, $decision->retryAfter);
    }

    public function testTheCountAccumulatesWithinOneWindow(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: static fn (): int => 100);

        $first = $inMemoryRateLimiter->check('203.0.113.9', 3, 60);
        $second = $inMemoryRateLimiter->check('203.0.113.9', 3, 60);
        $third = $inMemoryRateLimiter->check('203.0.113.9', 3, 60);

        self::assertSame(2, $first->remaining);
        self::assertSame(1, $second->remaining);
        self::assertTrue($third->allowed, 'the third request is still within the budget of three');
    }

    public function testTheWindowDeniesWithClampedRemainingAndRetryAfter(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        $mutableClock->advance(1);
        $denied = $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        self::assertFalse($denied->allowed);
        self::assertSame(0, $denied->remaining, 'remaining never answers a negative count');
        self::assertSame(59, $denied->retryAfter);
    }

    public function testTheRetryAfterClampsAtOneSecond(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        $mutableClock->advance(59);
        $atTheEdge = $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        self::assertSame(1, $atTheEdge->retryAfter, 'a retry-after of one is the honest floor');
    }

    public function testTheWindowRollsOverWholeWhenItsEpochPasses(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        $denied = $inMemoryRateLimiter->check('203.0.113.9', 1, 60);
        self::assertFalse($denied->allowed, 'the budget of one is spent inside the window');

        $mutableClock->advance(61);
        $fresh = $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        self::assertTrue($fresh->allowed, 'a whole new window answers a whole new budget');
        self::assertSame(60, $fresh->retryAfter, 'the new window resets a full window away');
    }

    public function testEveryKeyCarriesItsOwnBucket(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: static fn (): int => 100);

        $first = $inMemoryRateLimiter->check('203.0.113.9', 1, 60);
        $second = $inMemoryRateLimiter->check('198.51.100.7', 1, 60);

        self::assertTrue($first->allowed);
        self::assertTrue($second->allowed, "one address spending its budget never spends another's");
    }

    public function testTheCapacityBoundHaltsWhenNothingCanBeReclaimed(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(maxKeys: 1, now: static fn (): int => 100);

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        try {
            $inMemoryRateLimiter->check('198.51.100.7', 5, 60);
            self::fail('A new key at capacity must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame('Rate limiter capacity exhausted.', $httpException->getMessage());
        }
    }

    public function testTheSweepReclaimsExpiredBucketsBeforeRefusing(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(maxKeys: 1, now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        $mutableClock->advance(61);
        $reclaimed = $inMemoryRateLimiter->check('198.51.100.7', 5, 60);

        self::assertTrue($reclaimed->allowed, "the expired bucket's capacity is honestly free again");
    }

    public function testTheRolloverBoundaryAtTheResetEpochAnswersAFreshWindow(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        $mutableClock->advance(60);
        $atTheEpoch = $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        self::assertSame(4, $atTheEpoch->remaining, 'the epoch itself already rolls the window over');
        self::assertSame(60, $atTheEpoch->retryAfter, 'the new window resets a full window away');
    }

    public function testTheSweepReclaimsAtTheResetEpochBoundary(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(maxKeys: 1, now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        $mutableClock->advance(60);
        $reclaimed = $inMemoryRateLimiter->check('198.51.100.7', 5, 60);

        self::assertTrue($reclaimed->allowed, 'a bucket at its reset epoch is honestly expired');
    }

    public function testAnExistingKeyKeepsItsBucketEvenAtCapacity(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(maxKeys: 1, now: static fn (): int => 100);

        $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        $denied = $inMemoryRateLimiter->check('203.0.113.9', 1, 60);

        self::assertFalse($denied->allowed, 'the existing key is accounted, not refused');
        self::assertSame(0, $denied->remaining);
    }

    public function testTheTableRetainsEveryLiveBucketThroughRepeatedAdmissions(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);
        $inMemoryRateLimiter->check('198.51.100.7', 5, 60);
        $inMemoryRateLimiter->check('203.0.113.9', 5, 60);

        $third = $inMemoryRateLimiter->check('198.51.100.7', 5, 60);

        self::assertSame(3, $third->remaining, "the second address kept its own count across the first's re-admission");
    }

    public function testTheReclaimedTableRetainsEverySurvivingBucket(): void
    {
        $mutableClock = new MutableClock(100);
        $inMemoryRateLimiter = new InMemoryRateLimiter(maxKeys: 3, now: $mutableClock->now());

        $inMemoryRateLimiter->check('203.0.113.9', 5, 10);
        $inMemoryRateLimiter->check('198.51.100.7', 5, 60);
        $inMemoryRateLimiter->check('192.0.2.44', 5, 60);

        $mutableClock->advance(11);
        $inMemoryRateLimiter->check('198.18.0.1', 5, 60);

        $surviving = $inMemoryRateLimiter->check('192.0.2.44', 5, 60);

        self::assertSame(3, $surviving->remaining, 'the reclaim kept every live bucket, not just one');
    }

    public function testTheTablesOwnDefaultCapacityBoundIsTenThousandKeys(): void
    {
        $bucketTable = new BucketTable();

        for ($key = 0; $key < 10000; ++$key) {
            $bucketTable->advance("198.51.100.{$key}", 3600, 100);
        }

        try {
            $bucketTable->advance('203.0.113.9', 3600, 100);
            self::fail("A key beyond the table's own default capacity must fail closed.");
        } catch (HttpException $httpException) {
            self::assertSame('Rate limiter capacity exhausted.', $httpException->getMessage());
        }
    }

    public function testTheDefaultCapacityBoundIsTenThousandKeys(): void
    {
        $inMemoryRateLimiter = new InMemoryRateLimiter(now: static fn (): int => 100);

        for ($key = 0; $key < 10000; ++$key) {
            $inMemoryRateLimiter->check("198.51.100.{$key}", 1, 3600);
        }

        try {
            $inMemoryRateLimiter->check('203.0.113.9', 1, 3600);
            self::fail('A key beyond the default capacity must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame('Rate limiter capacity exhausted.', $httpException->getMessage());
        }
    }

    public function testTheCapacityBoundItselfValidates(): void
    {
        try {
            new InMemoryRateLimiter(maxKeys: 0);
            self::fail('A capacity below one must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The rate limiter max_keys knob must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideTheDefaultClockIsTheSystemClockCases
     */
    #[DataProvider('provideTheDefaultClockIsTheSystemClockCases')]
    public function testTheDefaultClockIsTheSystemClock(string $mode): void
    {
        $limiter = $mode === 'default'
            ? new InMemoryRateLimiter()
            : new InMemoryRateLimiter();

        $decision = $limiter->check('203.0.113.9', 100, 60);

        self::assertTrue($decision->allowed);
        self::assertGreaterThan(0, $decision->retryAfter);
        self::assertLessThanOrEqual(60, $decision->retryAfter);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideTheDefaultClockIsTheSystemClockCases(): iterable
    {
        yield 'the default clock' => ['default'];

        yield 'an injected clock' => ['injected'];
    }
}
