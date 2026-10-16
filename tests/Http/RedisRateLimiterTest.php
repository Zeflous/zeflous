<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\RateLimitDecision;
use Zef\Framework\Http\RedisRateLimiter;
use Zef\Test\Support\Http\RecordingSharedRateLimitStore;

/**
 * The shared-store limiter's arithmetic, on its own terms: the store
 * contract it hands its clock to, and the decision clamps -- against a
 * recording store double and a fixed clock.
 *
 * @internal
 */
#[CoversClass(RateLimitDecision::class)]
#[CoversClass(RedisRateLimiter::class)]
final class RedisRateLimiterTest extends TestCase
{
    public function testTheStoreReceivesTheKeyWindowAndClock(): void
    {
        $recordingSharedRateLimitStore = new RecordingSharedRateLimitStore(['count' => 1, 'reset' => 160]);
        $redisRateLimiter = new RedisRateLimiter($recordingSharedRateLimitStore, now: static fn (): int => 100);

        $redisRateLimiter->check('203.0.113.9', 100, 60);

        self::assertSame(
            [['203.0.113.9', 60, 100]],
            $recordingSharedRateLimitStore->increments,
            'the store owns the atomic read-modify-write; the limiter passes key, window and clock',
        );
    }

    public function testTheDecisionFollowsTheBucketsAnswer(): void
    {
        $recordingSharedRateLimitStore = new RecordingSharedRateLimitStore(['count' => 2, 'reset' => 137]);
        $redisRateLimiter = new RedisRateLimiter($recordingSharedRateLimitStore, now: static fn (): int => 100);

        $decision = $redisRateLimiter->check('203.0.113.9', 3, 60);

        self::assertTrue($decision->allowed);
        self::assertSame(3, $decision->limit);
        self::assertSame(1, $decision->remaining);
        self::assertSame(37, $decision->retryAfter);
    }

    public function testAnOverBudgetBucketDeniesWithClampedRemaining(): void
    {
        $recordingSharedRateLimitStore = new RecordingSharedRateLimitStore(['count' => 5, 'reset' => 160]);
        $redisRateLimiter = new RedisRateLimiter($recordingSharedRateLimitStore, now: static fn (): int => 100);

        $decision = $redisRateLimiter->check('203.0.113.9', 3, 60);

        self::assertFalse($decision->allowed);
        self::assertSame(0, $decision->remaining);
    }

    public function testTheRetryAfterClampsAtOneSecond(): void
    {
        $recordingSharedRateLimitStore = new RecordingSharedRateLimitStore(['count' => 2, 'reset' => 100]);
        $redisRateLimiter = new RedisRateLimiter($recordingSharedRateLimitStore, now: static fn (): int => 100);

        $decision = $redisRateLimiter->check('203.0.113.9', 5, 60);

        self::assertSame(1, $decision->retryAfter, 'a reset epoch that has arrived still answers one');
    }
}
