<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ApcuRateLimiter;
use Zef\Framework\Http\RateLimitDecision;
use Zef\Test\Support\Http\MutableClock;
use Zef\Test\Support\Http\RecordingApcuCounterStore;

/**
 * The APCu-backed fixed-window limiter's pure arithmetic, on its own
 * terms: the key derivation, the epoch claim's once-per-window
 * semantics, and the decision clamps -- against a recording store double
 * and a fixed clock (the real extension boundary is covered by the
 * process-isolated store test).
 *
 * @internal
 */
#[CoversClass(ApcuRateLimiter::class)]
#[CoversClass(RateLimitDecision::class)]
final class ApcuRateLimiterTest extends TestCase
{
    public function testThePrimitivesAreAddressedThroughTheDerivedKeys(): void
    {
        $recordingApcuCounterStore = new RecordingApcuCounterStore();
        $apcuRateLimiter = new ApcuRateLimiter($recordingApcuCounterStore, now: static fn (): int => 100);

        $apcuRateLimiter->check('203.0.113.9', 100, 60);

        self::assertSame(
            [['zef:ratelimit:reset:' . hash('sha256', '203.0.113.9'), 160, 60]],
            $recordingApcuCounterStore->claims,
            'the epoch is claimed at now+window with the window as TTL',
        );
        self::assertSame(
            [['zef:ratelimit:count:' . hash('sha256', '203.0.113.9'), 60]],
            $recordingApcuCounterStore->increments,
            'the counter is incremented with the window as its TTL',
        );
    }

    public function testTheDecisionFollowsThePrimitivesAnswers(): void
    {
        $recordingApcuCounterStore = new RecordingApcuCounterStore();
        $apcuRateLimiter = new ApcuRateLimiter($recordingApcuCounterStore, now: static fn (): int => 100);

        $decision = $apcuRateLimiter->check('203.0.113.9', 2, 60);

        self::assertTrue($decision->allowed);
        self::assertSame(2, $decision->limit);
        self::assertSame(1, $decision->remaining);
        self::assertSame(60, $decision->retryAfter);
    }

    public function testTheEpochIsClaimedOncePerWindow(): void
    {
        $recordingApcuCounterStore = new RecordingApcuCounterStore();
        $apcuRateLimiter = new ApcuRateLimiter($recordingApcuCounterStore, now: static fn (): int => 100);

        $first = $apcuRateLimiter->check('203.0.113.9', 5, 60);
        $second = $apcuRateLimiter->check('203.0.113.9', 5, 60);

        self::assertSame(60, $first->retryAfter);
        self::assertSame(60, $second->retryAfter, 'the second request reads the claimed epoch, not a fresh one');
        self::assertCount(
            2,
            $recordingApcuCounterStore->claims,
            'the claim primitive is still consulted every request',
        );
    }

    public function testTheBudgetDenialClampsRemainingAtZero(): void
    {
        $mutableClock = new MutableClock(100);
        $apcuRateLimiter = new ApcuRateLimiter(new RecordingApcuCounterStore(), now: $mutableClock->now());

        $apcuRateLimiter->check('203.0.113.9', 1, 60);

        $mutableClock->advance(1);
        $denied = $apcuRateLimiter->check('203.0.113.9', 1, 60);

        self::assertFalse($denied->allowed);
        self::assertSame(0, $denied->remaining);
        self::assertSame(59, $denied->retryAfter);
    }

    public function testTheRetryAfterClampsAtOneSecond(): void
    {
        $recordingApcuCounterStore = new RecordingApcuCounterStore();
        $recordingApcuCounterStore->claimedEpochs['zef:ratelimit:reset:' . hash('sha256', '203.0.113.9')] = 159;
        $apcuRateLimiter = new ApcuRateLimiter($recordingApcuCounterStore, now: static fn (): int => 159);

        $decision = $apcuRateLimiter->check('203.0.113.9', 5, 60);

        self::assertSame(1, $decision->retryAfter, 'a claimed epoch that has arrived still answers one');
    }
}
