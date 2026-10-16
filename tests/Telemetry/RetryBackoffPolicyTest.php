<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\RetryBackoffPolicy;

/**
 * The retry lane's bounded policy, on its own terms: the
 * constructor's invariant (the non-negative bounds, the cap never
 * below the initial delay), the two judgements the drain makes per
 * failed attempt (the retry count's exhaustion, the exponential
 * backoff clamped at the cap), and the non-negative index guards.
 *
 * @internal
 */
#[CoversClass(RetryBackoffPolicy::class)]
final class RetryBackoffPolicyTest extends TestCase
{
    public function testTheZeroRetryPolicyIsConstructible(): void
    {
        $retryBackoffPolicy = new RetryBackoffPolicy(0, 0, 0);

        self::assertFalse($retryBackoffPolicy->shouldRetry(0), 'the zero-retry policy never earns another attempt');
        self::assertSame(0, $retryBackoffPolicy->delayMs(0));
    }

    public function testAnegativeRetryCountIsRefused(): void
    {
        try {
            new RetryBackoffPolicy(-1, 100, 1000);
            self::fail('A negative retry count must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Invalid telemetry retry policy.', $invalidArgumentException->getMessage());
        }
    }

    public function testAnegativeInitialDelayIsRefused(): void
    {
        try {
            new RetryBackoffPolicy(2, -1, 1000);
            self::fail('A negative initial delay must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Invalid telemetry retry policy.', $invalidArgumentException->getMessage());
        }
    }

    public function testACapBelowTheInitialDelayIsRefused(): void
    {
        try {
            new RetryBackoffPolicy(2, 100, 99);
            self::fail('A cap below the initial delay must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Invalid telemetry retry policy.', $invalidArgumentException->getMessage());
        }
    }

    public function testTheCapAtTheInitialDelayIsTheNarrowestHonestPolicy(): void
    {
        $retryBackoffPolicy = new RetryBackoffPolicy(2, 100, 100);

        self::assertSame(100, $retryBackoffPolicy->delayMs(0));
        self::assertSame(100, $retryBackoffPolicy->delayMs(1));
        self::assertSame(100, $retryBackoffPolicy->delayMs(2), 'the doubling clamps at the cap');
    }

    public function testRetriesAreEarnedUntilTheCountIsSpent(): void
    {
        $retryBackoffPolicy = new RetryBackoffPolicy(2, 100, 1000);

        self::assertTrue($retryBackoffPolicy->shouldRetry(0), 'the first failure earns the first retry');
        self::assertTrue($retryBackoffPolicy->shouldRetry(1), 'the second failure earns the second retry');
        self::assertFalse($retryBackoffPolicy->shouldRetry(2), 'the third failure is the exhaustion');
        self::assertFalse($retryBackoffPolicy->shouldRetry(3));
    }

    public function testTheBackoffDoublesAndClamps(): void
    {
        $retryBackoffPolicy = new RetryBackoffPolicy(3, 100, 400);

        self::assertSame(100, $retryBackoffPolicy->delayMs(0));
        self::assertSame(200, $retryBackoffPolicy->delayMs(1));
        self::assertSame(400, $retryBackoffPolicy->delayMs(2), 'the third wait clamps at the cap');
        self::assertSame(400, $retryBackoffPolicy->delayMs(3));
        self::assertSame(400, $retryBackoffPolicy->delayMs(9), 'the cap holds for every later index');
    }

    public function testAnAnegativeRetryIndexIsRefusedByBothJudgements(): void
    {
        $retryBackoffPolicy = new RetryBackoffPolicy(1, 100, 1000);

        try {
            $retryBackoffPolicy->shouldRetry(-1);
            self::fail('shouldRetry must refuse a negative index.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Retry index must be non-negative.', $invalidArgumentException->getMessage());
        }

        try {
            $retryBackoffPolicy->delayMs(-1);
            self::fail('delayMs must refuse a negative index.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Retry index must be non-negative.', $invalidArgumentException->getMessage());
        }
    }
}
